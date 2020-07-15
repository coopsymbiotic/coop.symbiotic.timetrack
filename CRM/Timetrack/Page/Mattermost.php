<?php

use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Page_Mattermost extends CRM_Core_Page {

  public function run() {
    // Validate the Slash command token
    $token = Civi::settings()->get('timetrack_mattermost_slash_token');

    if (!$token || $token != $_POST['token']) {
      self::done('ERROR: :fire: Mattermost slash command token is not valid or is not set in Timetrack.');
    }

    // Validate the username with the CMS
    $contact_id = null;

    try {
      $contact_id = civicrm_api3('Contact', 'getsingle', [
        'id' => '@user:' . $_POST['user_name'],
        'return' => ['id'],
      ])['id'];
    }
    catch (Exception $e) {
      self::done('ERROR: :fire: CMS user not found');
    }

    // Validate the input
    $input = $_POST['text'];
    $matches = [];

    if (empty($input)) {
      try {
        $result = civicrm_api3('Timetrackpunch', 'punchout', [
          'contact_id' => $contact_id,
        ]);

        $id = $result['id'];
        $t = $result['values'][$id];

        self::done('OK :checkered_flag: punched out of task ' . $t['case_subject'] . '/' . $t['ktask_title'] . ' (' . $t['ktask_id'] . ') Duration: ' . $t['duration_text']);
      }
      catch (Exception $e) {
        self::done('ERROR: :fire: ' . $e->getMessage());
      }
    }

    if (!preg_match('/(-s )?([0-9]+:[0-9]+\+?\d*[h|m]?)? ?([^ ]+) ?(.*)/', $input, $matches)) {
      self::done('ERROR: :fire: invalid syntax. Try: /punch (-s 10:00+30[h|m) project/task Punch comment here');
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

    try {
      $result = civicrm_api3('Timetrackpunch', 'create', $params);

      $id = $result['id'];
      $t = $result['values'][$id];
      $extra = $t['extra_comments'] ?? '';

      if ($t['duration'] > 0) {
        self::done('OK :checkered_flag: punch added for task ' . $t['case_subject'] . '/' . $t['ktask_title'] . ' (' . $t['ktask_id'] . ') ' . $extra . ' --- ' . $t['duration']);
      }
      else {
        self::done('OK :white_check_mark: punched in task ' . $t['case_subject'] . '/' . $t['ktask_title'] . ' (' . $t['ktask_id'] . ') ' . $extra . '\\n**Don\'t forget to punch out!**');
      }
    }
    catch (Exception $e) {
      self::done('ERROR :fire: ' . $e->getMessage());
    }
  }

  /**
   * Outputs the response with proper http headers and exits.
   *
   * @param string $output
   */
  private function done(String $output) {
    CRM_Utils_System::setHttpHeader("Content-Type", "application/json");
    echo '{"text": "Input: ' . $_POST['command'] . ' ' . $_POST['text'] . '\\nResponse: ' . $output . '"}';
    CRM_Utils_System::civiExit();
  }

}
