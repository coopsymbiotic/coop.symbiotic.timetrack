<?php

use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Utils {
  public static function roundUpSeconds($seconds, $roundToMinutes = 15) {
    // 1- we round the seconds to the closest 15 mins
    // 2- we convert the seconds to hours, so 3600 seconds = 1h.
    // 3- round to max 2 decimals, in case we're rounding to the minute.
    if (!$seconds) {
      $seconds = 0;
    }

    return round(ceil($seconds / ($roundToMinutes * 60)) * ($roundToMinutes * 60) / 3600, 2);
  }

  /**
   * Returns the main contact (client) of a case.
   */
  public static function getCaseContact($case_id) {
    static $case_contact_cache = [];

    if (isset($case_contact_cache[$case_id])) {
      return $case_contact_cache[$case_id];
    }

    $dao = CRM_Core_DAO::executeQuery('SELECT contact_id FROM civicrm_case_contact WHERE case_id = %1', [
      1 => [$case_id, 'Positive'],
    ]);

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
  public static function getCaseUrl($case_id) {
    $contact_id = self::getCaseContact($case_id);

    if (!$contact_id) {
      CRM_Core_Error::fatal(ts('Could not find a contact for the case ID %1', [1 => $case_id]));
    }

    return CRM_Utils_System::url('civicrm/contact/view/case', 'reset=1&id=' . $case_id . '&cid=' . $contact_id . '&action=view');
  }

  /**
   * Returns an array of all case+activities.
   * TODO: we should add an option to restrict using contract relations.
   */
  public static function getCaseActivityTypes($add_select = TRUE) {
    return self::getActivitiesForCase(0, $add_select);
  }

  /**
   * Returns a list of activities for a case.
   * Mostly an API wrapper / transition mechanism.
   *
   * @param int $case_id
   * @return array
   *   List of activities, keyed by activity ID.
   */
  public static function getActivitiesForCase($case_id, $add_select = TRUE) {
    $tasks = [];

    if ($add_select) {
      $tasks = ['' => ts('- select -')];
    }

    $params = [
      'option.limit' => 0,
      'sort' => 'case_subject ASC, title ASC',
    ];

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
   *
   * @return string
   */
  public static function getCaseSubject($case_id) {
    $result = civicrm_api3('Case', 'getsingle', [
      'id' => $case_id,
      'return.subject' => 1,
    ]);

    return $result['subject'];
  }

  /**
   * Returns a list of contacts who have CMS access.
   * Should probably return contacts from a certain subtype/group/permission..?
   *
   * @return array
   */
  public static function getUsers() {
    $users = ['' => ts('- select -')];

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
  public static function getCaseOpenStatuses() {
    static $cache = [];

    if (!empty($cache)) {
      return $cache;
    }

    $result = civicrm_api3('OptionValue', 'get', [
      'option_group_name' => 'case_status',
      'grouping' => 'Opened',
      'is_active' => 1,
    ]);

    foreach ($result['values'] as $v) {
      $cache[] = $v['value'];
    }

    return $cache;
  }

  /**
   * Parse a string such as "10:00+1h foo/task my comment" and create a punch..
   */
  public static function parseAndPunch($input, $contact_id) {

    if (!preg_match('/(-s )?([0-9]+:[0-9]+\+?\d*[h|m]?)? ?([^ ]+) ?(.*)/', $input, $matches)) {
      throw new Exception('invalid syntax. Try: /punch (-s 10:00+30[h|m) project/task Punch comment here');
    }

    $task = trim($matches[3]);
    $comment = trim($matches[4]);

    $params = [
      'contact_id' => $contact_id,
      'alias' => $task,
      'comment' => $comment,
    ];

    if (!empty($matches[2])) {
      $time_parts = explode('+', $matches[2]);

      if (count($time_parts) == 1) {
        $params['begin'] = $matches[2];
      }
      else {
        $len = mb_strlen($time_parts[1]);
        $unit = mb_substr($time_parts[1], -1, 1);
        $duration = mb_substr($time_parts[1], 0, $len - 1);

        $params['begin'] = $time_parts[0];

        if ($unit == 'h') {
          $params['duration'] = $duration * 60 * 60;
        }
        else {
          // we assume anything else is minutes.
          $params['duration'] = $duration * 60;
        }
      }
    }

    $result = civicrm_api3('Timetrackpunch', 'create', $params);

    $id = $result['id'];
    $t = $result['values'][$id];
    $extra = $t['extra_comments'] ?? '';

    if ($t['duration'] > 0) {
      $rounded = sprintf('%.2f', CRM_Timetrack_Utils::roundUpSeconds($t['duration'], 1));
      return ':checkered_flag: ' . E::ts('%1h punch added for task %2/%3 (%4)', [
        1 => $rounded,
        2 => $t['case_subject'],
        3 => $t['ktask_title'],
        4 => $t['ktask_id'],
      ]) . ' ' . $extra;
    }
    else {
      return ':white_check_mark: ' . 'punched in task ' . $t['case_subject'] . '/' . $t['ktask_title'] . ' (' . $t['ktask_id'] . ') ' . $extra . '\\n**Don\'t forget to punch out!**';
    }
  }

  public static function angularSettingsFactory(): array {
    return [
      'timetrack_help_url' => Civi::settings()->get('timetrack_help_url'),
    ];
  }

}
