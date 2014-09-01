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

  // _civicrm_api3_contact_get_supportanomalies($params, $options);
  // $contacts = _civicrm_api3_get_using_query_object('contact', $params, $options);

/* TODO
  $sqlparams = array(
    1 => array('ktask', 'String'),
  );

  $sql = 'SELECT tn.nid as task_id, tn.title as subject, c.id as case_id, c.subject as case_subject
            FROM node as tn
           INNER JOIN ktask as kt on (kt.nid = tn.nid)
           INNER JOIN civicrm_value_infos_base_contrats_1 as cv on (cv.kproject_node_2 = kt.parent)
           INNER JOIN civicrm_case as c on (c.id = cv.entity_id)
           WHERE type = %1';

  if ($case_id = CRM_Utils_Array::value('case_id', $params)) {
    $sql .= ' AND c.id = %2';
    $sqlparams[2] = array($case_id, 'Positive');
  }

  if ($subject = CRM_Utils_Array::value('subject', $params)) {
    $subject = CRM_Utils_Type::escape($subject, 'String');
    $sql .= " AND (c.subject LIKE '{$subject}%' OR tn.title LIKE '{$subject}%')";
  }

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $tasks[] = array(
      'subject' => $dao->subject,
      'case_subject' => $dao->case_subject,
      'task_id' => $dao->task_id,
      'case_id' => $dao->case_id,
    );
  }
*/

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
