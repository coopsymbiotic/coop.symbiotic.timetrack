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
        self::done('ERROR :fire: ' . $e->getMessage());
      }
    }

    try {
      $message = CRM_Timetrack_Utils::parseAndPunch($input, $contact_id);
      self::done('OK ' . $message);
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
    $command = substr($_POST['command'], 1);
    echo '{"response_type": "in_channel", "text": "' . $command . ' ' . $_POST['text'] . '\\n' . $output . '"}';
    CRM_Utils_System::civiExit();
  }

}
