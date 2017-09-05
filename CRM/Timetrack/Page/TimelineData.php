<?php

class CRM_Timetrack_Page_TimelineData extends CRM_Core_Page {
  function run() {
    if (! empty($_POST)) {
      $this->handlePost();
    }
    else {
      $this->handleGet();
    }

    CRM_Utils_System::civiExit();
  }

  function handleGet() {
    $from = CRM_Utils_Request::retrieve('from', 'String', $this, TRUE);
    $to = CRM_Utils_Request::retrieve('to', 'String', $this, TRUE);

    $session = CRM_Core_Session::singleton();

    $result = civicrm_api3('Timetrackpunch', 'get', array(
      'contact_id' => $session->get('userID'),
      'filter.begin_low' => str_replace('-', '', $from) . '000001',
      'filter.begin_high' => str_replace('-', '', $to) . '235959',
    ));

    $punches = array();

    foreach ($result['values'] as $key => $val) {
      // TODO To avoid showing end date earlier than begin,
      // but will need better way of displaying that.
      if ($val['duration'] == -1) {
        $val['duration'] = 5;
      }

      $punches[] = array(
        'id' => $val['id'], // required for punch deletion
        'punch_id' => $val['id'],
        'ktask_id' => $val['ktask_id'],
        'text' => $val['comment'],
        'start_date' => date('Y-m-d H:i:s', $val['begin']), // FIXME begin should be mysql date
        'end_date' => date('Y-m-d H:i:s', $val['begin'] + $val['duration']), // FIXME begin should be mysql date
        'contact_id' => $val['contact_id'], // FIXME should be contact_id
      );
    }

    echo json_encode($punches);
  }

  function handlePost() {
    $action = CRM_Utils_Request::retrieve('!nativeeditor_status', 'String', $this, FALSE, NULL, 'POST');

    if ($action == 'deleted') {
      $result = civicrm_api3('Timetrackpunch', 'delete', array(
        'id' => CRM_Utils_Request::retrieve('punch_id', 'Positive', $this, TRUE, NULL, 'POST'),
      ));
    }
    else {
      // Calculate the punch duration
      $start_date = CRM_Utils_Request::retrieve('start_date', 'String', $this, TRUE, NULL, 'POST');
      $end_date = CRM_Utils_Request::retrieve('end_date', 'String', $this, TRUE, NULL, 'POST');
      $duration = strtotime($end_date) - strtotime($start_date);

      $params = array(
        'ktask_id' => CRM_Utils_Request::retrieve('ktask_id', 'Positive', $this, TRUE, NULL, 'POST'),
        'comment' => CRM_Utils_Request::retrieve('text', 'String', $this, TRUE, NULL, 'POST'),
        'begin' => CRM_Utils_Request::retrieve('start_date', 'String', $this, TRUE, NULL, 'POST'),
        'contact_id' => CRM_Utils_Request::retrieve('contact_id', 'Positive', $this, TRUE, NULL, 'POST'),
        'duration' => $duration,
        'skip_punched_in_check' => 1,
        'skip_open_case_check' => 1,
        'skip_overlap_check' => 1,
      );

      if ($action == 'updated') {
        if ($punch_id = CRM_Utils_Request::retrieve('punch_id', 'Positive', $this, TRUE, NULL, 'POST')) {
          $params['punch_id'] = $punch_id;
        }
      }

      $result = civicrm_api3('Timetrackpunch', 'create', $params);
    }

    echo json_encode($result);
  }
}
