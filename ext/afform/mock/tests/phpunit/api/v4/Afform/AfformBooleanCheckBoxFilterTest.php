<?php
namespace api\v4\Afform;

use Civi\Api4\Afform;
use Civi\Api4\Contact;
use Civi\Api4\Relationship;
use Civi\Api4\SavedSearch;
use Civi\Api4\SearchDisplay;

/**
 * Test case for the Boolean CheckBox filter fix in afField.component.js.
 *
 * Background: when a user unchecks a single Boolean CheckBox filter (e.g.
 * "is_active") in an Afform-embedded SearchKit display after having checked it,
 * the value transitions true -> false in $scope.getSetValue. Without the fix,
 * `false` is written to the form's fieldData and getAfformFilters() forwards it
 * to SearchDisplay::run as an active filter — returning only inactive records
 * instead of clearing the filter. The fix translates that user-uncheck to
 * `null` so the filter is dropped entirely, while preserving:
 *   - programmatic `false` from URL params / afform_default (no user action)
 *   - save-form Boolean values that must persist `false` to the DB
 *   - operator-keyed fields (search_operator), which use a different path
 *
 * These tests cover the regression-prone paths reachable from PHP:
 *
 *   #1 SearchDisplay::run with is_active=TRUE  -> only active records returned
 *   #2 SearchDisplay::run with no is_active filter -> all records returned
 *   #4 Save-form Boolean TRUE -> FALSE update -> FALSE persists in DB
 *
 * Tests #1 and #2 document the expected end-state of the fix at the API layer
 * (already covered indirectly by SearchRunTest; duplicated here for clarity at
 * the Afform layer). Test #4 is the regression guard for the !afForm path —
 * the JS fix must NOT coerce false -> null on save forms.
 *
 * #3 (URL param `?is_active=0` retaining is_active=false filter) cannot be
 * exercised from PHP without JS test infrastructure. There is currently no
 * karma/jasmine setup in ext/afform or ext/search_kit. Proposed as a separate
 * ticket: introduce JS unit tests for $scope.getSetValue so URL-param /
 * afform_default paths are regression-tested directly.
 *
 * @group headless
 */
class AfformBooleanCheckBoxFilterTest extends AfformUsageTestCase {

  /**
   * Build a SavedSearch + SearchDisplay over RelationshipCache with an
   * is_active filter, used by tests #1 and #2.
   */
  private function setupActiveFilterSearchDisplay(): array {
    $search = SavedSearch::create(FALSE)
      ->setValues([
        'name' => 'TestActiveFilterSearch',
        'label' => 'TestActiveFilterSearch',
        'api_entity' => 'Relationship',
        'api_params' => [
          'version' => 4,
          'select' => ['id', 'is_active'],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'having' => [],
        ],
      ])
      ->execute()->first();

    $display = SearchDisplay::create(FALSE)
      ->setValues([
        'name' => 'TestActiveFilterDisplay',
        'label' => 'TestActiveFilterDisplay',
        'saved_search_id.name' => 'TestActiveFilterSearch',
        'type' => 'table',
        'settings' => [
          'limit' => 50,
          'columns' => [
            ['key' => 'id', 'label' => 'ID', 'type' => 'field'],
            ['key' => 'is_active', 'label' => 'Active?', 'type' => 'field'],
          ],
        ],
        'acl_bypass' => FALSE,
      ])
      ->execute()->first();

    // Two active and two inactive relationships, distinguishable by a unique
    // description we can filter on later. RelationshipCache doubles each row.
    $description = uniqid('afform_bool_filter_test_');
    $contactA = Contact::create(FALSE)
      ->addValue('first_name', 'A')->addValue('last_name', $description)
      ->execute()->first();
    $contactB = Contact::create(FALSE)
      ->addValue('first_name', 'B')->addValue('last_name', $description)
      ->execute()->first();
    $contactC = Contact::create(FALSE)
      ->addValue('first_name', 'C')->addValue('last_name', $description)
      ->execute()->first();

    Relationship::create(FALSE)
      ->addValue('contact_id_a', $contactA['id'])
      ->addValue('contact_id_b', $contactB['id'])
      ->addValue('relationship_type_id:name', 'Employee of')
      ->addValue('description', $description)
      ->addValue('is_active', TRUE)
      ->execute();
    Relationship::create(FALSE)
      ->addValue('contact_id_a', $contactA['id'])
      ->addValue('contact_id_b', $contactC['id'])
      ->addValue('relationship_type_id:name', 'Employee of')
      ->addValue('description', $description)
      ->addValue('is_active', FALSE)
      ->execute();

    return [$search, $display, $description];
  }

  /**
   * Test #1: Boolean filter `is_active = TRUE` returns only active records.
   * Documents the end-state of the fix at the API layer.
   */
  public function testSearchDisplayActiveTrueFiltersToActiveOnly(): void {
    [, $display, $description] = $this->setupActiveFilterSearchDisplay();

    $result = civicrm_api4('SearchDisplay', 'run', [
      'savedSearch' => 'TestActiveFilterSearch',
      'display' => $display['name'],
      'filters' => [
        'description' => $description,
        'is_active' => TRUE,
      ],
    ]);

    $this->assertCount(1, $result, 'Active=TRUE filter should match only the active relationship.');
    $this->assertSame(TRUE, $result[0]['data']['is_active']);
  }

  /**
   * Test #2: With no `is_active` filter (the fixed JS state after user
   * unchecks), all matching records are returned.
   * Documents the end-state of the fix at the API layer.
   */
  public function testSearchDisplayNoActiveFilterReturnsAll(): void {
    [, $display, $description] = $this->setupActiveFilterSearchDisplay();

    $result = civicrm_api4('SearchDisplay', 'run', [
      'savedSearch' => 'TestActiveFilterSearch',
      'display' => $display['name'],
      'filters' => [
        'description' => $description,
        // is_active intentionally omitted — what JS sends when the user
        // unchecks the box (post-fix: null is filtered out by getFilterValues).
      ],
    ]);

    $this->assertCount(2, $result, 'No is_active filter should return both active and inactive relationships.');
  }

  /**
   * Test #4 (regression guard for the !afForm path).
   *
   * The fix translates user-uncheck (true -> false) on a Boolean CheckBox to
   * `null`, but ONLY when the field is in a search-filter context (no af-form
   * ancestor). On a save form, `false` must continue to persist to the
   * database — otherwise we silently fail to write the user's choice.
   *
   * This test creates a contact with do_not_email=TRUE, then submits an Afform
   * update toggling it to FALSE, and verifies the DB now stores FALSE. If the
   * fix's !afForm guard were ever removed/inverted, the update would silently
   * skip the write and the contact would remain TRUE — which this test catches.
   */
  public function testSaveFormBooleanToggleTrueToFalsePersistsToDb(): void {
    $layout = <<<EOHTML
<af-form ctrl="afform">
  <af-entity type="Individual" name="Individual1" label="Individual 1" actions="{create: true, update: true}" security="RBAC" />
  <fieldset af-fieldset="Individual1" class="af-container" af-title="Individual 1">
    <af-field name="id" defn="{input_type: 'Hidden'}" />
    <af-field name="do_not_email" />
  </fieldset>
</af-form>
EOHTML;
    $this->useValues([
      'layout' => $layout,
      'permission' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
    ]);

    // Start with do_not_email = TRUE in the database.
    $contact = Contact::create(FALSE)
      ->addValue('first_name', 'BoolToggleTest')
      ->addValue('last_name', uniqid())
      ->addValue('do_not_email', TRUE)
      ->execute()->first();

    $this->assertSame(TRUE, (bool) $contact['do_not_email'],
      'Setup: contact should start with do_not_email=TRUE.');

    // Submit the form with do_not_email = FALSE — simulating the user toggling
    // the checkbox off in a save form context. The fix must NOT coerce this
    // to null; FALSE must be written to the DB.
    Afform::submit()
      ->setName($this->formName)
      ->setValues([
        'Individual1' => [
          [
            'fields' => [
              'id' => $contact['id'],
              'do_not_email' => FALSE,
            ],
          ],
        ],
      ])
      ->execute();

    $updated = Contact::get(FALSE)
      ->addSelect('do_not_email')
      ->addWhere('id', '=', $contact['id'])
      ->execute()->first();

    $this->assertSame(FALSE, (bool) $updated['do_not_email'],
      'Save-form Boolean toggle TRUE -> FALSE must persist FALSE to the DB. '
      . 'If this fails, the !afForm guard in $scope.getSetValue has regressed '
      . 'and is silently coercing the user-set FALSE to null on save forms.');
  }

}
