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
 * Adjust Metadata for Get action
 *
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_timetracktask_get_spec(&$params) {
  $params['task_is_deleted']['api.default'] = 0;

  $params['title']['title'] = 'Task title';
  $params['case_id']['case'] = 'Task case/project/contract ID';
}
