<?php

/**
 * Retrieve one or more timetrackpunches, given a set of search params
 * Implements Timetrackpunch.get
 *
 * @param  array  input parameters
 *
 * @return array API Result Array
 * (@getfields timetrackpunch_get}
 * @static void
 * @access public
 */
function civicrm_api3_timetrackpunch_get($params) {
  $options = array();
  $punches = array();

  $sqlparams = array();

  $sql = 'SELECT kpunch.*
            FROM kpunch
           WHERE 1=1 ';

  if (! empty($params['id'])) {
    $sqlparams[1] = array($params['id'], 'Positive');
    $sql .= ' AND kpunch.id = %1';
  }

  if (! empty($params['uid'])) {
    $sqlparams[2] = array($params['uid'], 'Positive');
    $sql .= ' AND kpunch.uid = %2';
  }

  // Used to find an open punch.
  if (! empty($params['duration'])) {
    $sqlparams[3] = array($params['duration'], 'Integer');
    $sql .= ' AND kpunch.duration = %3';
  }

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $p = array(
      'id' => $dao->id,
      'ktask_id' => $dao->ktask_id,
      'activity_id' => $dao->ktask_id, // FIXME is this used?
      'contact_id' => $dao->uid,
      'uid' => $dao->uid,
      'case_id' => $dao->case_id,
      'begin' => $dao->begin,
      'duration' => $dao->duration,
      'comment' => $dao->comment,
      'korder_id' => $dao->korder_id,
      'korder_line_id' => $dao->korder_line_id,
    );

    $punches[$dao->id] = $p;
  }

  return civicrm_api3_create_success($punches, $params, 'timetrackpunch');
}

/**
 * Retrieve the count of punches matching the params.
 * Implements Timetrackpunch.getcount
 */
function civicrm_api3_timetrackpunch_getcount($params) {
  $tasks = civicrm_api3_timetrackpunch_get($params);
  return count($tasks['values']);
}

/**
 * Adjust Metadata for Get action
 *
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_timetrackpunch_get_spec(&$params) {
  $params['task_is_deleted']['api.default'] = 0;

  $params['id']['title'] = 'Punch ID';
  $params['uid']['title'] = 'User ID of the punch author';
  $params['ktask_id']['title'] = 'Task ID';
  $params['begin']['title'] = 'Punch begin';
  $params['duration']['title'] = 'Punch duration';
  $params['comment']['title'] = 'Punch comment';
  $params['korder_id']['title'] = 'Invoice ID';
  $params['korder_line_id']['title'] = 'Invoice line ID';
  $params['billable_intern']['title'] = 'Is punch billable internally?';
  $params['billable_client']['title'] = 'Is punch billable to the client?';
}

/**
 * Create a new punch.
 */
function civicrm_api3_timetrackpunch_create($params) {
  static $caseStatuses = NULL;

  $extra_comments = array();
  $punch = new CRM_Timetrack_DAO_Punch();

  // uid params is mandatory
  if (empty($params['uid'])) {
    return civicrm_api3_create_error('uid is mandatory (Timetrackpunch create)');
  }

  // Validate the task/case
  $task = NULL;

  if (! empty($params['ktask_id'])) {
    // Fetch the task by ID.
    $task = civicrm_api3('Timetracktask', 'getsingle', array(
      'id' => $params['ktask_id'],
    ));
  }
  elseif (! empty($params['alias'])) {
    // Fetch the task by alias.
    $result = civicrm_api3('Timetracktask', 'get', array(
      'alias' => $params['alias'],
    ));

    if ($result['count'] > 1) {
      $choices = array();
      foreach ($result['values'] as $v) {
        $choices[$v['id']] = $v['title'];
      }

      return civicrm_api3_create_error(ts('Did you mean one of: %1', array(1 => implode('; ', $choices))));
    }
    elseif ($result['count'] == 1) {
      $task = array_shift($result['values']);
      $params['ktask_id'] = $task['id'];
    }
    else {
      return civicrm_api3_create_error(ts('No tasks found for alias: %1', array(1 => $params['alias'])));
    }
  }

  if (empty($params['ktask_id'])) {
    return civicrm_api3_create_error('ktask_id is mandatory (Timetrackpunch create)');
  }

  if (! isset($caseStatuses)) {
    $result = civicrm_api3('OptionValue', 'get', array(
      'option_group_name' => 'case_status',
      'grouping' => 'Opened',
      'is_active' => 1,
    ));

    foreach ($result['values'] as $v) {
      $caseStatuses[] = $v['value'];
    }
  }

  $result = civicrm_api3('Case', 'getsingle', array(
    'id' => $task['case_id'],
  ));

  if (! in_array($result['status_id'], $caseStatuses)) {
    return civicrm_api3_create_error(ts('Cannot punch in this case (%1), it is not open.', array(1 => $result['title'])));
  }

  // Alias punch_id to 'id'.
  if (! empty($params['punch_id']) && empty($params['id'])) {
    $params['id'] = $params['punch_id'];
  }

  // Handle begin time, if specified.
  if (! empty($params['begin'])) {
    $begin = timetrack_convert_punch_start_to_timestamp($params['begin']);

    if ($begin === FALSE) {
      return civicrm_api3_create_error(ts('Begin time format error (%1). Choose from 00:00, 0m, 0min, 0h, 0hour.', array(1 => $params['begin'])));
    }

    if ($begin > time()) {
      return civicrm_api3_create_error(ts('Error: Cannot start punch later than now.'));
    }

    // Check to see if the user is already punched in.
    $result = civicrm_api3('Timetrackpunch', 'get', array(
      'duration' => -1,
      'uid' => $params['uid'],
    ));

    if ($result['count'] > 0) {
      $previous_punch = $result['values'][0];

      if ($start < $previous_punch['begin']) {
        return civicrm_api3_create_error(ts('Error: Trying to set end of previous punch before it began (%1).', array(1 => date('Y-m-d h:i:s', $previous_punch['begin']))));
      }
      else {
        // Adjust the end of the previous punch.
        $previous_punch['duration'] = $start - $previous_punch['begin'] - 1;
        $result = civicrm_api('Timetrackpunch', 'create', $previous_punch);

        // FIXME: t() / language
        $extra_comments[] = t('punched out of !task (!comment), worked !duration hours.', array(
          '!task' => $result['case_subject'] . ' / ' . $result['ktask_title'],
          '!duration' => CRM_Timetrack_Utils::roundUpSeconds($previous_punch['duration'], 1),
          '!comment' => $previous_punch['comment'],
        ), array('langcode' => $lang));
      }
    }

    // Check for overlapping punches.
    $test_punch = array(
      'id' => $task['id'],
      'uid' => $params['uid'],
      'begin' => $begin,
      'duration' => time() - $begin,
    );

    // FIXME: needs more testing.
    if (! $valid = timetrack_punch_validate($test_punch)) {
      return civicrm_api3_create_error(ts('Invalid punch: %1', array(1 => $valid)));
    }

    $params['begin'] = $begin;
  }
  else {
    // TODO: should be mysql datetime
    $params['begin'] = time();
  }

  // Check if need to un-punch (semi-redundant with previous check, if begin was provided).
  $result = civicrm_api3('Timetrackpunch', 'get', array(
    'duration' => -1,
    'uid' => $params['uid'],
  ));

  if ($result['count'] > 0) {
    $punchoutres = civicrm_api3('Timetrackpunch', 'punchout', array(
      'uid' => $params['uid'],
    ));

    $punchout_punch = array_shift($punchoutres['values']);

    // FIXME: ts() / language

    $extra_comments[] = t('and punched out of !task (!comment), worked !duration hours.', array(
      '!task' => $punchout_punch['case_subject'] . ' / ' . $punchout_punch['ktask_title'],
      '!duration' => CRM_Timetrack_Utils::roundUpSeconds($punchout_punch['duration'], 1),
      '!comment' => $punchout_punch['comment'],
    ), array('langcode' => $lang));
  }

  // No duration means that we are punching in (not punched out yet).
  if (empty($params['duration'])) {
    $params['duration'] = -1;
  }

  // May be added by the API, and will cause SQL errors.
  unset($params['version']);
  unset($params['debug']);

  $punch->copyValues($params);
  $punch->save();

  if (is_null($punch)) {
    return civicrm_api3_create_error('Entity not created (Timetrackpunch create) ' . implode(';', $extra_comments));
  }

  $values = array();
  _civicrm_api3_object_to_array($punch, $values[$punch->id]);

  // Fetch task title
  $task = civicrm_api3('Timetracktask', 'getsingle', array('id' => $params['ktask_id']));
  $values[$punch->id]['ktask_title'] = $task['title'];
  $values[$punch->id]['case_subject'] = $task['case_subject'];
  $values[$punch->id]['extra_comments'] = implode(';', $extra_comments);

  return civicrm_api3_create_success($values, $params, NULL, 'create', $punch);
}

/**
 * Punch out
 */
function civicrm_api3_timetrackpunch_punchout($params) {
  if (empty($params['uid'])) {
    return civicrm_api3_create_error('You must specify the user to punch out.');
  }

  $result = civicrm_api3('Timetrackpunch', 'getsingle', array(
    'uid' => $params['uid'],
    'duration' => -1,
  ));

  $punch = new CRM_Timetrack_DAO_Punch();
  $punch->copyValues($result);

  $punch->duration = time() - $punch->begin;

  if (! empty($params['comment'])) {
    $punch->comment = $params['comment'];
  }

  $punch->save();

  $values = array();
  _civicrm_api3_object_to_array($punch, $values[$punch->id]);

  // Fetch task title
  $task = civicrm_api3('Timetracktask', 'getsingle', array('id' => $punch->ktask_id));
  $values[$punch->id]['ktask_title'] = $task['title'];
  $values[$punch->id]['case_subject'] = $task['case_subject'];

  return civicrm_api3_create_success($values, $params, NULL, 'punchout', $punch);
}

/**
 * Implements Timetrackpunch.setvalue
 * Used mainly in the reports, to correct/update a specific punch.
 *
 * @param  array  input parameters
 */
function civicrm_api3_timetrackpunch_setvalue($params) {
  $entity = 'CRM_Timetrack_DAO_Punch';
  $id = $params['id'];

  $field = $params['field'];
  $field = str_replace('punch_', '', $field);

  $value = $params['value'];

  // TODO FIXME: check_permissions

  $result = FALSE;

  $object = new CRM_Timetrack_DAO_Punch();
  $object->id = $id;

  if ($object->find(TRUE)) {
    // FIXME: should have used CRM_Core_DAO::setFieldValue(), but it assumes that the table
    // has a primary field 'id'.

    if ($field == 'comment') {
      CRM_Core_DAO::executeQuery('UPDATE kpunch SET comment = %1 WHERE id = %2', array(
        1 => array($value, 'String'),
        2 => array($id, 'Positive')
      ));

      $result = TRUE;
    }
    elseif ($field == 'begin') {
      $value = strtotime($value);

      CRM_Core_DAO::executeQuery('UPDATE kpunch SET begin = %1 WHERE id = %2', array(
        1 => array($value, 'Positive'), // FIXME convert to string when field is fixed
        2 => array($id, 'Positive')
      ));

      $result = TRUE;
    }
    elseif ($field == 'duration') {
      // assume we are setting as hours
      $value = intval($value * 60 * 60);

      CRM_Core_DAO::executeQuery('UPDATE kpunch SET duration = %1 WHERE id = %2', array(
        1 => array($value, 'Positive'),
        2 => array($id, 'Positive'),
      ));

      $result = TRUE;
    }
  }

  $object->free();

  if ($result) {
    CRM_Utils_Hook::post('edit', $entity, $id, $entity);
    return civicrm_api3_create_success($entity);
  }

  return civicrm_api3_create_error("error assigning $field=$value for $entity (id=$id)");
}

/**
 * Converts a time string of specific format to a timestamp.
 * TODO: move to BAO
 *
 * Copied from kpirc.module.
 *
 * Acceptable time formats:
 * 00[m|min|h|hour] - Time minus specified length
 * 00[h|:]00 - Exact time today
 *
 * @return
 *   A timestamp or FALSE if the string is not formatted correctly
 */
function timetrack_convert_punch_start_to_timestamp($time_to_convert = NULL) {
  if ($time_to_convert === NULL) {
    return time();
  }

  if (preg_match("/^[0-9][0-9]?[h:][0-9]{2}\z/i", $time_to_convert)) {
    // absolute time (00:00)

    $time_to_convert = str_replace(array('h', 'H'), ':', $time_to_convert);

    // Converts time (00:00) to timestamp of the same time today,
    // or FALSE if format is incorrect.
    return strtotime($time_to_convert);
  }
  elseif (preg_match("/^[0-9]*(m|min|minute)s?\z/i", $time_to_convert)) {
    // relative time in minutes (000m)
    $minutes = (int) $time_to_convert;

    return strtotime(date('Y-m-d H:i:s') . '-' . $minutes . ' minutes');
  }
  elseif (preg_match("/^[0-9]*(h|hour)s?\z/i", $time_to_convert)) {
    // relative time in hours (000h)
    $hours = (int) $time_to_convert;

    return strtotime(date('Y-m-d H:i:s') . '-' . $hours . ' hours');
  }
  else {
    // format error
    return FALSE;
  }
}

/**
 * Validates that the punch can be safely added to the database.
 *
 * TODO: move to BAO. Use CiviCRM DB API.
 * Copied from kpirc.module.
 *
 * @param Array $punch
 *   An array containing the punch properties as they are to be written.
 *
 * @return
 *   TRUE if the entry is valid, an error message otherwise.
 */
function timetrack_punch_validate($punch) {
  $query = 'SELECT * FROM {kpunch} k ' .
           'WHERE k.ktask_id = :ktaskid AND k.uid = :uid ' .
           'AND ( ' .
           '(:begin >= k.begin AND :begin <= (k.begin + k.duration)) ' .
           'OR (:end >= k.begin AND :end <= (k.begin + k.duration)) ' .
           'OR ((k.begin >= :begin) AND (k.begin <= :end))' .
           ') ORDER BY k.id DESC';

  $args = array(
    ':ktaskid' => $punch['ktask_id'],
    ':uid' => $punch['uid'],
    ':begin' => $punch['begin'],
    ':end'   => $punch['begin'] + $punch['duration'],
  );

  $result = db_query($query, $args);

  if ($record = $result->fetchObject()) {
    $node = node_load($record->nid);

    return t('Error: Overlapping with punch @pid (@comment) on @taskname id=@taskid',
      array('@pid' => $record->id, '@comment' => $record->comment, '@taskname' => $record->title, '@taskid' => $record->ktask_id));
  }
  else {
    return TRUE;
  }
}
