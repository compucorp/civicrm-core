<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2019                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 * Class CRM_Event_ActionMapping
 *
 * This defines the scheduled-reminder functionality for CiviCase entities.
 */
class CRM_Case_ActionMapping extends \Civi\ActionSchedule\Mapping {

  /**
   * The value for civicrm_action_schedule.mapping_id which identifies the
   * "Case" mapping.
   */
  const CASE_MAPPING_ID = 99;

  /**
   * Register Case-related action mappings.
   *
   * @param \Civi\ActionSchedule\Event\MappingRegisterEvent $registrations
   */
  public static function onRegisterActionMappings(\Civi\ActionSchedule\Event\MappingRegisterEvent $registrations) {
    $registrations->register(CRM_Case_ActionMapping::create([
      'id' => CRM_Case_ActionMapping::CASE_MAPPING_ID,
      'entity' => 'civicrm_case',
      'entity_label' => ts('Case'),
      'entity_value' => 'case_type',
      'entity_value_label' => ts('Case Type'),
      'entity_status' => 'case_status',
      'entity_status_label' => ts('Case Status'),
      'entity_date_start' => 'start_date',
      'entity_date_end' => 'end_date',
    ]));
  }

  /**
   * @inheritdoc
   */
  public function getRecipientListing($type) {
    if ($type == 'case_roles') {
      $result = civicrm_api3('RelationshipType', 'get', [
        'sequential' => 1,
        'options' => ['limit' => 0, 'sort' => "label_b_a"],
      ])['values'];
      $cRoles = [];
      foreach ($result as $each) {
        $cRoles[$each['id']] = $each['label_b_a'];
      }
      return $cRoles;
    }
    return [];
  }

  /**
   * @inheritdoc
   */
  public function getRecipientTypes() {
    return ['case_roles' => 'Case Roles'];
  }

  /**
   * @inheritdoc
   */
  public function getDateFields() {
    return [
      'start_date' => ts('Case Start Date'),
      'end_date' => ts('Case End Date'),
      'case_status_change_date' => ts('On Case Status Change'),
    ];
  }

  /**
   * @inheritdoc
   */
  public function createQuery($schedule, $phase, $defaultParams) {
    $selectedValues = (array) \CRM_Utils_Array::explodePadded($schedule->entity_value);
    $selectedStatuses = (array) \CRM_Utils_Array::explodePadded($schedule->entity_status);

    $query = \CRM_Utils_SQL_Select::from("{$this->entity} c")->param($defaultParams);
    $query->join('r', "INNER JOIN civicrm_relationship r ON c.id = r.case_id");
    $query->join('cs', "INNER JOIN civicrm_contact ct ON r.contact_id_b = ct.id");
    $query->join('ce', "INNER JOIN civicrm_email ce ON ce.contact_id = ct.id");

    if (!empty($selectedValues)) {
      $query->where("c.case_type_id IN (#caseTypeId)")
        ->param('caseTypeId', $selectedValues);
    }

    if (!empty($selectedStatuses)) {
      $query->where("c.status_id IN (#selectedStatuses)")
        ->param('selectedStatuses', $selectedStatuses);
    }

    if ($schedule->recipient_listing && $schedule->limit_to) {
      switch ($schedule->recipient) {
        case 'case_roles':
          $caseRoles = (array) \CRM_Utils_Array::explodePadded($schedule->recipient_listing);
          $query->where("r.relationship_type_id IN (#caseRoles)")
            ->param('caseRoles', $caseRoles);
          break;
      }
    }

    // $schedule->start_action_date is user-supplied data. validate.
    if (!array_key_exists($schedule->start_action_date, $this->getDateFields())) {
      throw new CRM_Core_Exception("Invalid date field");
    }

    if ($schedule->start_action_date == 'case_status_change_date') {
      // For this case, we use activity of type 'Change Case Status' and check if the case status has been changed
      // from an indifferent status to the one configured in scheduled reminder.
      $query->join('cac', "INNER JOIN civicrm_case_activity cac ON cac.case_id = c.id");
      $query->where("cac.id = (SELECT MAX(cac2.id) FROM civicrm_case_activity cac2 WHERE cac2.case_id = c.id)");

      $query->join('act', "INNER JOIN civicrm_activity act ON act.id = cac.activity_id");
      $query->join('opt', "INNER JOIN civicrm_option_value opt ON opt.value = act.activity_type_id");
      $query->join('opg', "INNER JOIN civicrm_option_group opg ON opg.id = opt.option_group_id");
      $query->where("opt.name = 'Change Case Status'");
      $query->where("opg.name = 'activity_type'");

      // If start condition is 'after' we check for completed activity
      // and if 'before' we check for scheduled activity.
      if ($schedule->start_action_condition == 'after') {
        $activityStatus = 'Completed';
      }
      else {
        $activityStatus = 'Scheduled';
      }

      // Subquery to check for activity status.
      $subQuery = \CRM_Utils_SQL_Select::from("civicrm_option_value opt2")->select('opt2.value');
      $subQuery->join('opg2', 'INNER JOIN civicrm_option_group opg2 ON opg2.id = opt2.option_group_id');
      $subQuery->where("opt2.name = @activityStatus")->param('activityStatus', $activityStatus);
      $subQuery->where("opg2.name = 'activity_status'");
      $query->where("act.status_id = (" . $subQuery->toSQL() . ")");

      $query['casDateField'] = 'act.activity_date_time';
    }
    else {
      $query['casDateField'] = 'c.' . $schedule->start_action_date;
    }

    $query['casAddlCheckFrom'] = 'civicrm_case c';
    $query['casContactIdField'] = 'ct.id';
    $query['casEntityIdField'] = 'r.case_id';
    $query['casContactTableAlias'] = 'ct';

    // Relationship is active.
    $today = date('Ymd');
    $query->where("r.is_active = 1 AND ( r.end_date is NULL OR r.end_date >= {$today} ) )");

    // Case is not deleted.
    $query->where("c.is_deleted = 0");

    return $query;
  }
}
