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
  $punch = new CRM_Timetrack_DAO_Punch();

  if (! empty($params['punch_id']) && empty($params['id'])) {
    $params['id'] = $params['punch_id'];
  }

  if (empty($params['begin'])) {
    // TODO: should be mysql datetime
    $params['begin'] = time();
  }
  else {
    // TODO: allow other values? like -5m?
    // NB: right now, this is a timestamp.
    // $params['begin'] = $params['begin'];
  }

  // No duration means that we are punching in (not punched out yet).
  if (empty($params['duration'])) {
    $params['duration'] = -1;
  }

  $punch->copyValues($params);
  $punch->save();

  if (is_null($punch)) {
    return civicrm_api3_create_error('Entity not created (Timetrackpunch create)');
  }

  $values = array();
  _civicrm_api3_object_to_array($punch, $values[$punch->id]);
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
  }

  $object->free();

  if ($result) {
    CRM_Utils_Hook::post('edit', $entity, $id, $entity);
    return civicrm_api3_create_success($entity);
  }

  return civicrm_api3_create_error("error assigning $field=$value for $entity (id=$id)");
}
