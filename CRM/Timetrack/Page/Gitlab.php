<?php
use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Page_Gitlab extends CRM_Core_Page {

  public function run() {
    $alias = $_SERVER['HTTP_X_GITLAB_TOKEN'];

    if ($_SERVER['HTTP_X_GITLAB_EVENT'] != 'Issue Hook') {
      Civi::log()->warning('Timetrack/Gitlab: received a webhook event other than an Issue Hook', [
        'token' => $_SERVER['HTTP_X_GITLAB_TOKEN'],
        'type' => $_SERVER['HTTP_X_GITLAB_EVENT'],
      ]);

      self::done("We only listen to Issue hooks");
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
    $ref = $data['project']['path_with_namespace'] . '#' . $data['object_attributes']['iid'];

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
   * Repond "OK" on the standard output.
   */
  private function done($output = 'OK') {
    echo $output;
    CRM_Utils_System::civiExit();
  }

}
