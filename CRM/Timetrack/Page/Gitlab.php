<?php
use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Page_Gitlab extends CRM_Core_Page {

  public function run() {
    $alias = $_SERVER['HTTP_X_GITLAB_TOKEN'];
    $supported_hooks = ['Issue Hook', 'Merge Request Hook', 'Confidential Issue Hook'];

    if (!in_array($_SERVER['HTTP_X_GITLAB_EVENT'], $supported_hooks)) {
      Civi::log()->warning('Timetrack/Gitlab: received an unsupported webhook event type', [
        'token' => $_SERVER['HTTP_X_GITLAB_TOKEN'],
        'type' => $_SERVER['HTTP_X_GITLAB_EVENT'],
      ]);

      self::done("Unsupported webhook event type");
    }

    $body = file_get_contents('php://input');
    $data = json_decode($body, TRUE);

    if (empty($data)) {
      Civi::log()->warning('Timetrack/Gitlab: Received an empty body (or json parse error)', [
        'token' => $_SERVER['HTTP_X_GITLAB_TOKEN'],
        'type' => $_SERVER['HTTP_X_GITLAB_EVENT'],
      ]);

      self::done("Empty body?");
    }

    // Civi::log()->warning('GET: ' . print_r($data, 1));

    if ($data['object_attributes']['action'] != 'update') {
      self::done();
    }

    // No time tracking information.
    if (empty($data['changes']) || empty($data['changes']['total_time_spent'])) {
      self::done();
    }

    $username = $data['user']['username'];
    $time_spent = $data['changes']['total_time_spent']['current'] - $data['changes']['total_time_spent']['previous'];
    $title = $data['object_attributes']['title'];
    $separator = ($data['event_type'] == 'merge_request' ? '!' : '#');
    $ref = $data['project']['path_with_namespace'] . $separator . $data['object_attributes']['iid'];

    $date_end = $date['object_attributes']['updated_at'];

    $t = new DateTime($date_end);
    $t->sub(new DateInterval('PT' . $time_spent . 'S'));
    $date_begin = $t->format('Y-m-d H:i:s');

    try {
      $contact_id = civicrm_api3('Contact', 'getsingle', [
        'id' => '@user:' . $username,
      ])['contact_id'];

      civicrm_api3('Timetrackpunch', 'create', [
        'contact_id' => $contact_id,
        'alias' => $alias,
        'begin' => $date_begin,
        'duration' => $time_spent,
        'comment' => $ref . ' ' . $title,
      ]);
    }
    catch (Exception $e) {
      self::done($e->getMessage());
    }

    self::done();
  }

  /**
   * Respond "OK" on the standard output.
   * @param string $output
   */
  private function done($output = 'OK') {
    echo $output;

    if ($output != 'OK') {
      CRM_Utils_System::setHttpHeader("Status", "500 Internal Server Error");
    }

    CRM_Utils_System::civiExit();
  }

}
