<?php

class CRM_Timetrack_Utils {
  static function roundUpSeconds($seconds, $roundToMinutes = 15) {
    // 1- we round the seconds to the closest 15 mins
    // 2- we convert the seconds to hours, so 3600 seconds = 1h.
    // 3- round to max 2 decimals, in case we're rounding to the minute.
    return round(ceil($seconds / ($roundToMinutes * 60)) * ($roundToMinutes * 60) / 3600, 2);
  }

  /**
   * Returns the main contact (client) of a case.
   */
  static function getCaseContact($case_id) {
    static $case_contact_cache = array();

    if (isset($case_contact_cache[$case_id])) {
      return $case_contact_cache[$case_id];
    }

    $dao = CRM_Core_DAO::executeQuery('SELECT contact_id FROM civicrm_case_contact WHERE case_id = %1', array(
      1 => array($case_id, 'Positive'),
    ));

    if ($dao->fetch()) {
      $case_contact_cache[$case_id] = $dao->contact_id;
      return $dao->contact_id;
    }

    $case_contact_cache[$case_id] = NULL;
    return NULL;
  }

  /**
   * For a given case_id, return the URL of the case (involves looking up the main contact_id of the case).
   */
  static function getCaseUrl($case_id) {
    $contact_id = self::getCaseContact($case_id);

    if (! $contact_id) {
      CRM_Core_Error::fatal(ts('Could not find a contact for the case ID %1', array(1 => $case_id)));
    }

    return CRM_Utils_System::url('civicrm/contact/view/case', 'reset=1&id=' . $case_id . '&cid=' . $contact_id . '&action=view');
  }

  /**
   * Returns an array of all case+activities.
   * TODO: we should add an option to restrict using contract relations.
   */
  static function getCaseActivityTypes($add_select = TRUE) {
    return self::getActivitiesForCase(0, $add_select);
  }

  /**
   * Returns a list of activities for a case.
   * Mostly an API wrapper / transition mechanism.
   *
   * @param Int $case_id
   * @returns Array List of activities, keyed by activity ID.
   */
  static function getActivitiesForCase($case_id, $add_select = TRUE) {
    $tasks = array();

    if ($add_select) {
      $tasks = array('' => ts('- select -'));
    }

    $params = array(
      'option.limit' => 0,
      'sort' => 'case_subject ASC, title ASC',
    );

    if ($case_id) {
      $params['case_id'] = $case_id;
    }

    $result = civicrm_api3('Timetracktask', 'get', $params);

    foreach ($result['values'] as $key => $val) {
      $tasks[$key] = $val['case_subject'] . ' > ' . $val['title'];
    }

    return $tasks;
  }

  /**
   * Returns the case subject (contract name).
   */
  static function getCaseSubject($case_id) {
    $result = civicrm_api3('Case', 'getsingle', array(
      'id' => $case_id,
      'return.subject' => 1,
    ));

    return $result['subject'];
  }

  static function getOpenCases() {
    $cases = array();

    $dao = CRM_Core_DAO::executeQuery(
      'SELECT c.id, c.subject
         FROM civicrm_case c
         INNER JOIN civicrm_option_group og ON (og.name = "case_status")
         INNER JOIN civicrm_option_value ov ON (ov.option_group_id = og.id AND ov.grouping = "Opened" AND c.status_id = ov.value)
         WHERE c.is_deleted = 0'
    );

    while ($dao->fetch()) {
      $cases[$dao->id] = $dao->subject;
    }

    asort($cases);
    return $cases;
  }

  /**
   * Returns a list of contacts who have CMS access.
   * Should probably return contacts from a certain subtype/group/permission..?
   */
  static function getUsers() {
    $users = array('' => ts('- select -'));

    $sql = 'SELECT c.id, c.display_name
              FROM civicrm_uf_match uf
        INNER JOIN civicrm_contact c ON (uf.contact_id = c.id)';

    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $users[$dao->id] = $dao->display_name;
    }

    return $users;
  }

  /**
   * Returns case status IDs that equal to 'open'.
   */
  static function getCaseOpenStatuses() {
    static $cache = array();

    if (! empty($cache)) {
      return $cache;
    }

    $result = civicrm_api3('OptionValue', 'get', array(
      'option_group_name' => 'case_status',
      'grouping' => 'Opened',
      'is_active' => 1,
    ));

    foreach ($result['values'] as $v) {
      $cache[] = $v['value'];
    }

    return $cache;
  }
}
