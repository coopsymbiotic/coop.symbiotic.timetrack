<?php

class CRM_Timetrack_Page_TimelineData extends CRM_Core_Page {
  public function run() {
    // https://docs.dhtmlx.com/dataprocessor__initialization.html#usingdataprocessorwithrestapi
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->handlePost();
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
      // Punch edited
      $this->handlePut();
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
      $this->handleDelete();
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $this->handleGet();
    }

    CRM_Utils_System::civiExit();
  }

  public function handleGet() {
    $from = CRM_Utils_Request::retrieve('from', 'String', $this, TRUE);
    $to = CRM_Utils_Request::retrieve('to', 'String', $this, TRUE);

    $session = CRM_Core_Session::singleton();

    $result = civicrm_api3('Timetrackpunch', 'get', [
      'contact_id' => $session->get('userID'),
      'filter.begin_low' => str_replace('-', '', $from) . '000001',
      'filter.begin_high' => str_replace('-', '', $to) . '235959',
    ]);

    $punches = [];

    foreach ($result['values'] as $key => $val) {
      // TODO To avoid showing end date earlier than begin,
      // but will need better way of displaying that.
      if ($val['duration'] == -1) {
        $val['duration'] = 5;
      }

      $end_date = date('Y-m-d H:i:s', strtotime($val['begin']) + $val['duration']);

      $punches[] = [
        'id' => $val['id'], // required for punch deletion
        'punch_id' => $val['id'],
        'ktask_id' => $val['ktask_id'],
        'text' => $val['comment'],
        'start_date' => $val['begin'],
        'end_date' => $end_date,
        'contact_id' => $val['contact_id'], // FIXME should be contact_id
      ];
    }

    echo json_encode($punches);
  }

  public function handleDelete() {
    $result = civicrm_api3('Timetrackpunch', 'delete', [
      'id' => CRM_Utils_Request::retrieveValue('id', 'Positive', TRUE),
    ]);

    echo json_encode(['action' => 'deleted']);
  }

  public function handlePost() {
    try {
      // Calculate the punch duration
      $start_date = CRM_Utils_Request::retrieveValue('start_date', 'String', NULL, TRUE);
      $end_date = CRM_Utils_Request::retrieveValue('end_date', 'String', NULL, TRUE);
      $duration = strtotime($end_date) - strtotime($start_date);

      $params = [
        'begin' => $start_date,
        'duration' => $duration,
        'ktask_id' => CRM_Utils_Request::retrieveValue('ktask_id', 'Positive', NULL, TRUE),
        'comment' => CRM_Utils_Request::retrieveValue('text', 'String', NULL, TRUE),
        'contact_id' => CRM_Utils_Request::retrieveValue('contact_id', 'Positive', NULL, TRUE),
        'skip_punched_in_check' => 1,
        'skip_open_case_check' => 1,
        'skip_overlap_check' => 1,
      ];

      if ($punch_id = CRM_Utils_Request::retrieveValue('punch_id', 'Positive')) {
        $params['punch_id'] = $punch_id;
      }

      $t = civicrm_api3('Timetrackpunch', 'create', $params);
      $result = [
        'tid' => $t['id'],
      ];
    }
    catch (Exception $e) {
      $result = [
        'action' => 'error',
        'error_message' => $e->getMessage(),
      ];
    }

    echo json_encode($result);
  }

  /**
   * Handle a punch update.
   * A bit redundant with 'POST', but we have to extract the put variables.
   * Also, the punch_id is mandatory.. but we're relying on the API for validation.
   */
  public function handlePut() {
    parse_str(file_get_contents("php://input"), $vars);

    try {
      // Calculate the punch duration
      $start_date = $vars['start_date'] ?? NULL;
      $end_date = $vars['end_date'] ?? NULL;
      $duration = strtotime($end_date) - strtotime($start_date);

      $params = [
        'begin' => $start_date,
        'duration' => $duration,
        'punch_id' => $vars['punch_id'] ?? NULL,
        'ktask_id' => $vars['ktask_id'] ?? NULL,
        'comment' => $vars['text'] ?? NULL,
        'contact_id' => $vars['contact_id'] ?? NULL,
        'skip_punched_in_check' => 1,
        'skip_open_case_check' => 1,
        'skip_overlap_check' => 1,
      ];

      $t = civicrm_api3('Timetrackpunch', 'create', $params);
      $result = [
        'tid' => $t['id'],
      ];
    }
    catch (Exception $e) {
      $result = [
        'action' => 'error',
        'error_message' => $e->getMessage(),
      ];
    }

    echo json_encode($result);
  }

}
