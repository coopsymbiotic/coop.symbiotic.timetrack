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

  $sqlparams = array(
    1 => array($params['id'], 'Positive'),
  );

  $sql = 'SELECT *
            FROM kpunch
           WHERE pid = %1';

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    // TODO: add missing fields, parent IDs?
    $punches[] = array(
      'id' => $dao->pid,
      'activity_id' => $dao->nid,
      'contact_id' => $dao->uid,
      'begin' => $dao->begin,
      'duration' => $dao->duration,
      'comment' => $dao->comment,
      'order_reference' => $dao->order_reference,
    );
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

  $params['comment']['title'] = 'Punch comment';
  $params['pid']['title'] = 'Punch ID';
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
  $object->pid = $id;
  $object->id = $id;

  if ($object->find(TRUE)) {
    // FIXME: should have used CRM_Core_DAO::setFieldValue(), but it assumes that the table
    // has a primary field 'id'.

    if ($field == 'comment') {
      CRM_Core_DAO::executeQuery('UPDATE kpunch SET comment = %1 WHERE pid = %2', array(
        1 => array($value, 'String'),
        2 => array($id, 'Positive')
      ));

      $result = TRUE;
    }
    elseif ($field == 'begin') {
      $value = strtotime($value);

      CRM_Core_DAO::executeQuery('UPDATE kpunch SET begin = %1 WHERE pid = %2', array(
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
