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
   * Returns a list of activities for a case.
   * Mostly an API wrapper / transition mechanism.
   *
   * @param Int $case_id
   * @returns Array List of activities, keyed by activity ID.
   */
  static function getActivitiesForCase($case_id) {
    // TODO: for when we convert ktasks->activities
    // TODO: filter out reserved activities (open, change status, etc).
/*
    $result = civicrm_api3('Case', 'getsingle', array(
      'id' => $case_id,
    ));

    return $result['activities'];
*/

    $tasks = array('' => ts('- select -'));

    $sql = 'SELECT ktask_node.title, ktask_node.nid
              FROM civicrm_value_infos_base_contrats_1 as bc
              LEFT JOIN ktask as kt ON (kt.parent = bc.kproject_node_2)
              LEFT JOIN node as ktask_node ON (ktask_node.nid = kt.nid)
             WHERE bc.entity_id = %1';

    $params = array(
      1 => array($case_id, 'Positive'),
    );

    $dao = CRM_Core_DAO::executeQuery($sql, $params);

    while ($dao->fetch()) {
      $tasks[$dao->nid] = $dao->title;
    }

    return $tasks;
  }

  /**
   * Returns the case subject (contract name).
   */
  function getCaseSubject($case_id) {
    $result = civicrm_api3('Case', 'getsingle', array(
      'id' => $case_id,
      'return.subject' => 1,
    ));

    return $result['subject'];
  }

  function getOpenCases() {
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

    $sql = 'SELECT uf.uf_id, c.display_name
              FROM civicrm_contact c
             INNER JOIN civicrm_uf_match uf ON (uf.contact_id = c.id)';

    $params = array();

    $dao = CRM_Core_DAO::executeQuery($sql, $params);

    while ($dao->fetch()) {
      $users[$dao->uf_id] = $dao->display_name;
    }

    return $users;
  }
}
