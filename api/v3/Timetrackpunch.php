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

  if (! empty($params['contact_id'])) {
    $sqlparams[2] = array($params['contact_id'], 'Positive');
    $sql .= ' AND kpunch.contact_id = %2';
  }
  elseif (! empty($params['contact_id'])) {
    $sqlparams[2] = array($params['contact_id'], 'Positive');
    $sql .= ' AND kpunch.contact_id = %2'; // FIXME ix when we fix kpunch.user_id
  }
  elseif (! empty($params['contact_ids'])) {
    $t = array();

    // This is used mainly by the timeline JS viewer, but we support drush as well.
    if (! is_array($params['contact_ids'])) {
      $params['contact_ids'] = explode(',', $params['contact_ids']);
    }

    foreach ($params['contact_ids'] as $i) {
      $i = trim($i);

      if (CRM_Utils_Type::validate($i, 'Positive')) {
        $t[] = $i;
      }
    }

    if (! empty($t)) {
      $sql .= ' AND kpunch.contact_id IN (' . implode($t, ',') . ')';
    }
  }

  // Used to find an open punch.
  if (! empty($params['duration'])) {
    $sqlparams[3] = array($params['duration'], 'Integer');
    $sql .= ' AND kpunch.duration = %3';
  }

  // FIXME: Using the DAO would be much simpler!
  if (! empty($params['filter.begin_low'])) {
    $sqlparams[4] = array($params['filter.begin_low'], 'Timestamp');
    $sql .= ' AND kpunch.begin >= UNIX_TIMESTAMP(%4)';
  }

  if (! empty($params['filter.begin_high'])) {
    $sqlparams[5] = array($params['filter.begin_high'], 'Timestamp');
    $sql .= ' AND kpunch.begin <= UNIX_TIMESTAMP(%5)';
  }

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $p = array(
      'id' => $dao->id,
      'ktask_id' => $dao->ktask_id,
      'activity_id' => $dao->ktask_id, // FIXME is this used?
      'contact_id' => $dao->contact_id,
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
  $params['contact_id']['title'] = 'Contact ID of the punch author';
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
 *
 * Special parameters:
 * skip_open_case_check : if true, will avoid checking if a case is open,
 *  so that we can punch/edit in a closed case. Useful for when updating
 *  data from a report, where the case might have been closed by then.
 *
 * skip_punched_in_check : do not check if the user is currently punched in,
 *  so that we can punch/edit other punches, while being punched in.
 */
function civicrm_api3_timetrackpunch_create($params) {
  static $caseStatuses = NULL;

  $extra_comments = array();
  $punch = new CRM_Timetrack_DAO_Punch();

  // contact_id param is mandatory
  if (empty($params['contact_id'])) {
    return civicrm_api3_create_error('contact_id is mandatory (Timetrackpunch create)');
  }

  // Validate the task/case
  $task = NULL;

  if (! empty($params['alias'])) {
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

  if (empty($params['skip_open_case_check'])) {
    if (! isset($caseStatuses)) {
      $caseStatuses = CRM_Timetrack_Utils::getCaseOpenStatuses();
    }

    $result = civicrm_api3('Case', 'getsingle', array(
      'id' => $task['case_id'],
    ));

    if (! in_array($result['status_id'], $caseStatuses)) {
      return civicrm_api3_create_error(ts('Cannot punch in this case (%1), it is not open.', array(1 => $result['title'])));
    }
  }

  // Alias punch_id to 'id'.
  if (! empty($params['punch_id']) && empty($params['id'])) {
    $params['id'] = $params['punch_id'];
  }

  // Handle begin time, if specified.
  if (! empty($params['begin'])) {
    $begin = timetrack_convert_punch_start_to_timestamp($params['begin']);

    if ($begin === FALSE) {
      return civicrm_api3_create_error(ts('Begin time format error (%1). Choose from 00:00, YYYY-MM-DD 00:00, 0m, 0min, 0h, 0hour.', array(1 => $params['begin'])));
    }

    if ($begin > time()) {
      return civicrm_api3_create_error(ts('Error: Cannot start punch later than now.'));
    }

    // Check to see if the user is already punched in.
    if (empty($params['skip_punched_in_check'])) {
      $result = civicrm_api3('Timetrackpunch', 'get', array(
        'duration' => -1,
        'contact_id' => $params['contact_id'],
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

          $extra_comments[] = ts('punched out of %1 (%2), worked %3 hours.', array(
            1 => $result['case_subject'] . ' / ' . $result['ktask_title'],
            2 => CRM_Timetrack_Utils::roundUpSeconds($previous_punch['duration'], 1),
            3 => $previous_punch['comment'],
          ));
        }
      }
    }

    // Check for overlapping punches.
    if (empty($params['skip_overlap_check'])) {
      $test_punch = array(
        'id' => $task['id'],
        'contact_id' => $params['contact_id'],
        'begin' => $begin,
        'duration' => time() - $begin,
      );

      // FIXME: needs more testing.
      if (! $valid = timetrack_punch_validate($test_punch)) {
        return civicrm_api3_create_error(ts('Invalid punch: %1', array(1 => $valid)));
      }
    }

    $params['begin'] = $begin;
  }
  else {
    // TODO: should be mysql datetime
    $params['begin'] = time();
  }

  // Check if need to un-punch (semi-redundant with previous check, if begin was provided).
  if (empty($params['skip_punched_in_check'])) {
    $result = civicrm_api3('Timetrackpunch', 'get', array(
      'duration' => -1,
      'contact_id' => $params['contact_id'],
    ));

    if ($result['count'] > 0) {
      $punchoutres = civicrm_api3('Timetrackpunch', 'punchout', array(
        'contact_id' => $params['contact_id'],
      ));

      $punchout_punch = array_shift($punchoutres['values']);

      // FIXME: ts() / language

      $extra_comments[] = ts('and punched out of %1 (%2), worked %3 hours.', array(
        1 => $punchout_punch['case_subject'] . ' / ' . $punchout_punch['ktask_title'],
        2 => CRM_Timetrack_Utils::roundUpSeconds($punchout_punch['duration'], 1),
        3 => $punchout_punch['comment'],
      ));
    }
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

  return civicrm_api3_create_success($values, $params);
}

/**
 * Punch out
 */
function civicrm_api3_timetrackpunch_punchout($params) {
  if (empty($params['contact_id'])) {
    return civicrm_api3_create_error('You must specify the user to punch out.');
  }

  $result = civicrm_api3('Timetrackpunch', 'getsingle', array(
    'contact_id' => $params['contact_id'],
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
  $values[$punch->id]['duration_text'] = CRM_Timetrack_Utils::roundUpSeconds($punch->duration, 1);

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
    if ($field == 'comment') {
      // TODO: this could probably just use the general $dao as in the 'else' below.
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
    elseif ($field == 'ktask_id') {
      $object->$field = $value;
      $result = $object->save();
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

  if ((strlen($time_to_convert) == 16 || strlen($time_to_convert) == 19) && CRM_Utils_Rule::dateTime($time_to_convert)) {
    // Date time: YYYY-MM-DD 00:00:00 or YYYY-MM-DD 00:00
    return strtotime($time_to_convert);
  }
  elseif (preg_match("/^[0-9][0-9]?[h:][0-9]{2}\z/i", $time_to_convert)) {
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
    ':contact_id' => $punch['contact_id'],
    ':begin' => $punch['begin'],
    ':end'   => $punch['begin'] + $punch['duration'],
  );

  $result = db_query($query, $args);

  if ($record = $result->fetchObject()) {
    $node = node_load($record->nid);

    return ts('Error: Overlapping with punch %1 (%2) on %3 id=%4',
      array(1 => $record->id, 2 => $record->comment, 3 => $record->title, 4 => $record->ktask_id));
  }
  else {
    return TRUE;
  }
}

/**
 * Deletes an existing Email
 *
 * @param array $params
 *
 * @example EmailDelete.php Standard Delete Example
 *
 * @return boolean
 *   | error  true if successfull, error otherwise
 * {@getfields email_delete}
 */
function civicrm_api3_timetrackpunch_delete($params) {
  CRM_Utils_Type::validate($params['id'], 'Positive');

  $dao = new CRM_Timetrack_DAO_Punch();
  $dao->id = $params['id'];

  $dao->delete();

  return TRUE;
}
