<?php

/**
 * Retrieve one or more timetracktasks, given a set of search params
 * Implements Timetracktask.get
 *
 * @param  array  input parameters
 *
 * @return array API Result Array
 * (@getfields timetracktasks_get}
 * @static void
 * @access public
 */
function civicrm_api3_timetracktask_get($params) {
  $options = array();
  $tasks = array();

  // _civicrm_api3_contact_get_supportanomalies($params, $options);
  // $contacts = _civicrm_api3_get_using_query_object('contact', $params, $options);

  $sqlparams = array();

  $sql = 'SELECT kt.*, c.subject as case_subject
            FROM ktask as kt
           INNER JOIN civicrm_case as c on (c.id = kt.case_id)
           WHERE 1=1 ';

  if ($task_id = CRM_Utils_Array::value('task_id', $params)) {
    $sql .= ' AND kt.id = %2';
    $sqlparams[2] = array($task_id, 'Positive');
  }
  elseif ($task_id = CRM_Utils_Array::value('id', $params)) {
    $sql .= ' AND kt.id = %2';
    $sqlparams[2] = array($task_id, 'Positive');
  }

  if ($case_id = CRM_Utils_Array::value('case_id', $params)) {
    $sql .= ' AND c.id = %2';
    $sqlparams[2] = array($case_id, 'Positive');
  }

  if ($subject = CRM_Utils_Array::value('subject', $params)) {
    $subject = CRM_Utils_Type::escape($subject, 'String');
    $sql .= " AND (c.subject LIKE '{$subject}%' OR tn.title LIKE '{$subject}%')";
  }

  // TODO: should be more flexible
  $sql .= ' ORDER BY kt.title ASC';

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $t = array(
      'id' => $dao->id,
      'task_id' => $dao->id,
      'case_id' => $dao->case_id,
      'title' => $dao->title,
      'case_subject' => $dao->case_subject,
      'estimate' => $dao->estimate,
      'total_included' => 0,
      'state' => $dao->state,
      'begin' => ($dao->begin ? date('Y-m-d', $dao->begin) : NULL), // TODO: convert to mysql datetime
      'end' => ($dao->end ? date('Y-m-d', $dao->end) : NULL), // TODO: convert.
      'lead' => $dao->lead,
    );

    // Calculate the time of included punches
    $dao2 = CRM_Core_DAO::executeQuery('SELECT sum(duration) as total FROM kpunch WHERE ktask_id = %1', array(
      1 => array($dao->id, 'Positive'),
    ));

    if ($dao2->fetch()) {
      $t['total_included'] = $dao2->total;
    }

    $tasks[$dao->id] = $t;
  }

  return civicrm_api3_create_success($tasks, $params, 'timetracktask');
}

/**
 * Retrieve the count of tasks matching the params.
 * Implements Timetracktask.getcount
 */
function civicrm_api3_timetracktask_getcount($params) {
  $tasks = civicrm_api3_timetracktask_get($params);
  return count($tasks['values']);
}

/**
 * Create a new task.
 */
function civicrm_api3_timetracktask_create($params) {
  $task = new CRM_Timetrack_DAO_Task();

  $task->copyValues($params);
  $task->save();

  if (is_null($task)) {
    return civicrm_api3_create_error('Entity not created (Timetracktask create)');
  }

  $values = array();
  _civicrm_api3_object_to_array($task, $values[$task->id]);
  return civicrm_api3_create_success($values, $params, NULL, 'create', $task);
}

/**
 * Adjust Metadata for Get action
 *
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_timetracktask_get_spec(&$params) {
  $params['task_is_deleted']['api.default'] = 0;

  $params['title']['title'] = 'Task title';
  $params['case_id']['case'] = 'Task case/project/contract ID';
}
