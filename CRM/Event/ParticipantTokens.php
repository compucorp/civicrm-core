<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use Civi\Token\AbstractTokenSubscriber;
use Civi\Token\Event\TokenRegisterEvent;

/**
 * Class CRM_Event_ParticipantTokens
 *
 * Generate "participant.*" tokens.
 */
class CRM_Event_ParticipantTokens extends AbstractTokenSubscriber {

  /**
   * Class constructor.
   */
  public function __construct() {
    $tokens = $this->getAllTokens();
    parent::__construct('Participant', $tokens);
  }

  /**
   * Register the declared tokens.
   *
   * @param \Civi\Token\Event\TokenRegisterEvent $e
   *   The registration event. Add new tokens using register().
   */
  public function registerTokens(TokenRegisterEvent $e) {
    if (!$this->checkActive($e->getTokenProcessor())) {
      return;
    }
    foreach ($this->tokenNames as $name => $label) {
      $e->register([
        'entity' => $this->entity,
        'field' => $name,
        'label' => $label,
      ]);
    }
  }

    /**
   * Get all the tokens supported by this processor.
   *
   * @return array|string[]
   * @throws \API_Exception
   */
  protected function getAllTokens(): array {
    $participantFields = CRM_Event_BAO_Participant::exportableFields();
    $tokens = [];

    // Filter out unused fields from token list.
    array_walk($participantFields, function ($v, $k) use (&$tokens) {
      if (!in_array($k, [
        'contact_id',
        'display_name',
        'event_id',
        'event_title',
        'event_start_date',
        'event_end_date',
        'default_role_id',
        'participant_id',
        'participant_fee_level',
        'participant_fee_amount',
        'participant_fee_currency',
        'event_type',
        'participant_status',
        'participant_role',
        'participant_register_date',
        'participant_source',
        'participant_note',
        'id',
      ])) {
        return;
      }
      $tokens[$k] = ts($v['title']);
    });

    $tokens = array_merge(CRM_Utils_Token::getCustomFieldTokens('Participant'), $tokens);

    return $tokens;
  }

  /**
   * @inheritDoc
   */
  public function evaluateToken(\Civi\Token\TokenRow $row, $entity, $field, $prefetch = NULL) {
    $actionSearchResult = $row->context['actionSearchResult'];
    $participantID = $actionSearchResult->entity_id ?? NULL;
    if (empty($participantID)) {
      return;
    }

    if (array_key_exists($field, $this->getParticipantTokenValues($participantID))) {
      foreach ($this->getParticipantTokenValues($participantID)[$field] as $format => $value) {
        $row->format($format)->tokens($entity, $field, $value ?? '');
      }
    }
  }

  /**
   * Get the tokens available for the participant.
   *
   * Cache by participant id
   *
   * @param int|null $participantID
   *
   * @return array
   *
   * @throws \API_Exception|\CRM_Core_Exception
   *
   * @internal
   */
  private function getParticipantTokenValues($participantID): array {
    $cacheKey = __CLASS__ . 'participant_tokens' . $participantID . '_' . CRM_Core_I18n::getLocale();
    if (!Civi::cache()->has($cacheKey)) {
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participantID,
      ]);

      if (!empty($participant['is_error'])) {
        return [];
      }

      foreach ($this->getAllTokens() as $fieldName => $fieldLabel) {
        if($value = $participant[$fieldName]) {
          $dateFields = [
          "event_end_date",
          "event_start_date",
          "participant_register_date",
        ];
  
          if (in_array($fieldName, $dateFields)) {
            $tokens[$fieldName]['text/html'] = CRM_Utils_Date::customFormat($value);
            continue;
          }

          if (is_array($value)) {
            // eg. role_id for participant would be an array here.
            $tokens[$fieldName]['text/html'] = implode(',', $value);
            continue;
          }
          
          $tokens[$fieldName]['text/html'] = $value;
          
        }
      }

      Civi::cache()->set($cacheKey, $tokens);
    }

    return Civi::cache()->get($cacheKey);;
  }

}
