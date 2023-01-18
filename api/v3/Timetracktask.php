<?php

/**
 * Retrieve one or more timetracktasks, given a set of search params
 * Implements Timetracktask.get
 *
 * @param array $params
 *
 * Special parameters:
 * skip_open_case_check : if true, will return all tasks, including those of
 *  closed cases. Similar to Timetrackpunch.get().
 *
 * @return array API Result Array
 * (@getfields timetracktasks_get}
 */
function civicrm_api3_timetracktask_get($params) {
  $options = [];
  $tasks = [];

  // _civicrm_api3_contact_get_supportanomalies($params, $options);
  // $contacts = _civicrm_api3_get_using_query_object('contact', $params, $options);

  $sqlparams = [];

  $sql = 'SELECT kt.*, c.subject as case_subject
            FROM civicrm_timetracktask as kt
           INNER JOIN civicrm_case as c on (c.id = kt.case_id)
           INNER JOIN kcontract as kc on (c.id = kc.case_id)
           WHERE 1=1 ';

  if ($task_id = CRM_Utils_Array::value('task_id', $params)) {
    $sql .= ' AND kt.id = %2';
    $sqlparams[2] = [$task_id, 'Positive'];
  }
  elseif ($task_id = CRM_Utils_Array::value('id', $params)) {
    $sql .= ' AND kt.id = %2';
    $sqlparams[2] = [$task_id, 'Positive'];
  }

  if ($case_id = CRM_Utils_Array::value('case_id', $params)) {
    $sql .= ' AND c.id = %2';
    $sqlparams[2] = [$case_id, 'Positive'];
  }

  if ($alias = CRM_Utils_Array::value('alias', $params)) {
    $parts = explode('/', $alias);

    if (count($parts) == 1) {
      $sql .= " AND kc.alias = %3";
      $sqlparams[3] = [$parts[0], 'String'];
    }
    elseif (count($parts) == 2) {
      $title = CRM_Utils_Type::escape($parts[1], 'String');
      $sql .= " AND kc.alias = %3 AND kt.title LIKE '{$title}%'";
      $sqlparams[3] = [$parts[0], 'String'];
    }
    else {
      return civicrm_api3_create_error('Alias had an invalid syntax. Expected foo/bar, where foo is the client alias, and bar is a word part of the task title.');
    }
  }

  if ($subject = CRM_Utils_Array::value('subject', $params)) {
    $subject = CRM_Utils_Type::escape($subject, 'String');
    $sql .= " AND (c.subject LIKE '{$subject}%' OR kt.title LIKE '{$subject}%')";
  }

  // By default, show only tasks for open cases.
  if (empty($params['skip_open_case_check'])) {
    $caseStatuses = CRM_Timetrack_Utils::getCaseOpenStatuses();

    if (count($caseStatuses)) {
      $sql .= ' AND c.status_id IN (' . implode(',', array_values($caseStatuses)) . ')';
    }
  }

  // FIXME: Am I overly paranoid? How do we validate the sort to avoid sql injections?
  if (!empty($params['sort'])) {
    if (preg_match('/^[_\s,\.0-9A-Za-z]+$/', $params['sort'])) {
      $sql .= ' ORDER BY ' . $params['sort'];
    }
    else {
      return civicrm_api3_create_error('Suspicious sort option: ' . $params['sort']);
    }
  }
  else {
    $sql .= ' ORDER BY kt.title ASC';
  }

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $t = [
      'id' => $dao->id,
      'task_id' => $dao->id,
      'case_id' => $dao->case_id,
      'title' => $dao->title,
      'case_subject' => $dao->case_subject,
      'estimate' => $dao->estimate,
      'total_included' => 0,
      'state' => $dao->state,
      'begin' => $dao->begin ?? NULL,
      'end' => $dao->end ?? NULL,
      'lead' => $dao->lead,
      'description' => $dao->description,
    ];

    // Calculate the time of included punches
    $dao2 = CRM_Core_DAO::executeQuery('SELECT sum(duration) as total FROM kpunch WHERE ktask_id = %1', [
      1 => [$dao->id, 'Positive'],
    ]);

    if ($dao2->fetch()) {
      $t['total_included'] = $dao2->total;
    }

    $tasks[$dao->id] = $t;
  }

  return civicrm_api3_create_success($tasks, $params);
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
 * List of tasks for the menubar timetrack search.
 *
 * @param array $params
 * @return array API Result Array
 */
function civicrm_api3_timetracktask_getquick($params) {
  $options = [];
  $tasks = [];

  $sqlparams = [];

  $sql = 'SELECT kt.*, c.subject as case_subject
            FROM civicrm_timetracktask as kt
           INNER JOIN civicrm_case as c on (c.id = kt.case_id)
           INNER JOIN kcontract as kc on (c.id = kc.case_id)
           WHERE 1=1 ';

  // Ex:
  // aliasA
  // aliasA/
  // aliasA/task1
  // My Case
  // My Task

  $search = $params['search'] ?? '';
  $search_parts = explode('/', $search);

  if (count($search_parts) == 2) {
    $sql .= ' AND kc.alias = %3 AND kt.title LIKE %4';
    $sqlparams[3] = [$search_parts[0], 'String'];
    if (!empty($search_parts[1])) {
      $sqlparams[4] = [$search_parts[1] . '%', 'String'];
    }
  }
  else {
    $sql .= ' AND c.subject LIKE %3 OR kt.title LIKE %4';
    $sqlparams[3] = ['%' . $search . '%', 'String'];
    $sqlparams[4] = ['%' . $search . '%', 'String'];
  }

  // Show only tasks for open cases.
  $caseStatuses = CRM_Timetrack_Utils::getCaseOpenStatuses();

  if (count($caseStatuses)) {
    $sql .= ' AND c.status_id IN (' . implode(',', array_values($caseStatuses)) . ')';
  }

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $client_id = CRM_Timetrack_Utils::getCaseContact($dao->case_id);

    $t = [
      'id' => $dao->id,
      'task_id' => $dao->id,
      'case_id' => $dao->case_id,
      'contact_id' => $client_id,
      'title' => $dao->title,
      'case_subject' => $dao->case_subject,
      'state' => $dao->state,
      'begin' => $dao->begin ?? NULL,
      'end' => $dao->end ?? NULL,
      'lead' => $dao->lead,
    ];

    $tasks[] = $t;
  }

  return civicrm_api3_create_success($tasks, $params);
}

/**
 * Create a new task.
 */
function civicrm_api3_timetracktask_create($params) {
  $task = new CRM_Timetrack_DAO_Task();

  if (!empty($params['task_id']) && empty($params['id'])) {
    $params['id'] = $params['task_id'];
  }

  $task->copyValues($params);
  $task->save();

  if (is_null($task)) {
    return civicrm_api3_create_error('Entity not created (Timetracktask create)');
  }

  $values = [];
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
