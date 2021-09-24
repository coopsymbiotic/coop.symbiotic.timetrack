<?php

use CRM_Timetrack_ExtensionUtil as E;

class CRM_Dataexplorer_Explore_Generator_Punch extends CRM_Dataexplorer_Explore_Generator {
  use CRM_Dataexplorer_Explore_Generator_DateTrait;

  function config($options = []) {
    if ($this->_configDone) {
      return $this->_config;
    }

    $defaults = [
      'y_label' => E::ts('Punchs'),
      'y_series' => 'hours',
      'y_type' => 'number',
    ];

    $this->_options = array_merge($defaults, $options);

    // It helps to call this here as well, because some filters affect the groupby options.
    // FIXME: see if we can call it only once, i.e. remove from data(), but not very intensive, so not a big deal.
    $params = [];
    $this->whereClause($params);

    // We can only have 2 groupbys, otherwise would be too complicated
    switch (count($this->_groupBy)) {
      case 0:
        $this->_config['axis_x'] = array(
          'label' => 'Total',
          'type' => 'number',
        );
        $this->_config['axis_y'][] = array(
          'label' => $this->_options['y_label'],
          'type' => $this->_options['y_type'],
          'series' => $this->_options['y_series'],
        );

        $this->_select[] = '"Total" as x';
        break;

      case 1:
      case 2:
        // Find all the labels for this type of group by
        if (in_array('period-year', $this->_groupBy)) {
          $this->configGroupByPeriodYear('p.begin');
        }
        if (in_array('period-month', $this->_groupBy)) {
          $this->configGroupByPeriodMonth('p.begin');
        }
        if (in_array('period-week', $this->_groupBy)) {
          $this->configGroupByPeriodWeek('p.begin');
        }
        if (in_array('period-day', $this->_groupBy)) {
          $this->configGroupByPeriodDay('p.begin');
        }
        if (in_array('timetrack-contact', $this->_groupBy)) {
          $this->configGroupByPunchContact();
        }
        if (in_array('timetrack-task', $this->_groupBy)) {
          $this->configGroupByPunchTask();
        }
        if (in_array('timetrack-case', $this->_groupBy)) {
          $this->configGroupByPunchCase();
        }

        break;

      default:
        CRM_Core_Error::fatal('Cannot groupby on ' . count($this->_groupBy) . ' elements. Max 2 allowed.');
    }

    // This happens if we groupby 'period' (month), but nothing else.
    if (empty($this->_config['axis_y'])) {
      $this->_config['axis_y'][] = array(
        'label' => $this->_options['y_label'],
        'type' => $this->_options['y_type'],
        'series' => $this->_options['y_series'],
        'id' => 1,
      );
    }

    $this->_configDone = TRUE;
    return $this->_config;
  }

  function data() {
    $data = [];
    $params = [];
    $this->_from[] = "kpunch as p ";

    // This makes it easier to check specific exceptions later on.
    $this->config();
                      
    if (in_array('period-year', $this->_groupBy)) {
      $this->queryAlterPeriod('year', 'p.begin');
    }
    if (in_array('period-month', $this->_groupBy)) {
      $this->queryAlterPeriod('month', 'p.begin');
    }
    if (in_array('period-week', $this->_groupBy)) {
      $this->queryAlterPeriod('week', 'p.begin');
    }
    if (in_array('period-day', $this->_groupBy)) {
      $this->queryAlterPeriod('day', 'p.begin');
    }
    if (in_array('timetrack-contact', $this->_groupBy)) {
      $this->queryAlterPunchContact();
    }
    if (in_array('timetrack-task', $this->_groupBy)) {
      $this->queryAlterPunchTask();
    }
    if (in_array('timetrack-case', $this->_groupBy)) {
      $this->queryAlterPunchCase();
    }

    $where = $this->whereClause($params);
    $this->runQuery($where, $params, $data, NULL);

    // FIXME: if we don't have any results, and we are querying two
    // types of data, the 2nd column of results (CSV) might get bumped into
    // the first column. This really isn't ideal, should fix the CSV merger.
    if (empty($data)) {
      $tlabel = $this->_config['axis_x']['label'];
      $data[$tlabel][$ylabel] = 0;
    }

    return $data;
  }

  function whereClause(&$params) {
    $where_clauses = array();
    $where_extra = '';

    $this->whereClauseCommon($params);

    foreach ($this->_filters as $filter) {
      // foo[0] will have 'period-start' and foo[1] will have 2014-09-01
      $foo = explode(':', $filter);

      // bar[0] will have 'period' and bar[1] will have 'start'
      // bar[0] will have 'diocese' and bar[1] will have '1'
      $bar = explode('-', $foo[0]);

      if ($bar[0] == 'period') {
        // Transform to MySQL date: remove the dashes in the date (2014-09-01 -> 20140901).
        $foo[1] = str_replace('-', '', $foo[1]);

        if ($bar[1] == 'start' && ! empty($foo[1])) {
          $params[1] = array($foo[1], 'Timestamp');
          $where_clauses[] = 'p.begin >= %1';
        }
        elseif ($bar[1] == 'end' && ! empty($foo[1])) {
          $params[2] = array($foo[1] . '235959', 'Timestamp');
          $where_clauses[] = 'p.begin <= %2';
        }
      }
      elseif ($bar[0] == 'relative_date') {
        $dates = CRM_Utils_Date::getFromTo($bar[1], NULL, NULL);

        $params[1] = array($dates[0], 'Timestamp');
        $where_clauses[] = 'p.begin >= %1';

        $params[2] = array($dates[1], 'Timestamp');
        $where_clauses[] = 'p.begin <= %2';
      }
      elseif ($bar[0] == 'punchinvoiced') {
        if ($bar[1] == 1) {
          $where_clauses[] = 'korder_id IS NOT NULL';
        }
        elseif ($bar[1] == 2) {
          $where_clauses[] = 'korder_id IS NULL';
        }
      }
      elseif ($bar[0] == 'punchcase') {
        // @todo This is pure lazyness
        if (empty(Civi::$statics[__CLASS__]['ktask_join'])) {
          $this->_from[] = 'LEFT JOIN civicrm_timetracktask as kt ON (kt.id = p.ktask_id)';
          Civi::$statics[__CLASS__]['ktask_join'] = 1;
        }
        $where_clauses[] = 'kt.case_id = ' . $bar[1];
      }
    }

    if (! empty($this->_config['filters']['punchinvoiced'])) {

    }

    if (! empty($this->_config['filters']['campaigns'])) {
      $where_clauses[] = 'campaign IN (' . implode(',', $this->_config['filters']['campaigns']) . ')';
    }

    if (! empty($this->_config['filters']['provinces'])) {
      $where_clauses[] = 'province_id IN (' . implode(',', $this->_config['filters']['provinces']) . ')';
    }

    if (! empty($this->_config['filters']['contact'])) {
      $where_clauses[] = 'gender_id IN (' . implode(',', $this->_config['filters']['sexe']) . ')';
    }

    $where = implode(' AND ', $where_clauses);
    $where = trim($where);

    return $where;
  }

  /**
   * Group by contact_id (Menu: Afficher -> Autres -> Contact).
   */
  function configGroupByPunchContact() {
    $contacts = [];

    $params = [];
    $where = $this->whereClause($params);

    if ($where) {
      $where = ' WHERE ' . $where;
    }

    $dao = CRM_Core_DAO::executeQuery("SELECT distinct p.contact_id, c.display_name FROM kpunch p LEFT JOIN civicrm_contact c ON (c.id = p.contact_id) $where", $params);

    while ($dao->fetch()) {
      $contacts[$dao->contact_id] = $dao->display_name;
    }

    // FIXME: Odd assumption? or clarify the code comment?
    if (empty($this->_config['axis_x'])) {
      // If X is empty, it's a bar chart.
      $this->_config['axis_x'] = [
        'label' => 'Contact',
        'type' => 'number',
      ];

      $this->_config['axis_y'][] = [
        'label' => $this->_options['y_label'],
        'type' => $this->_options['y_type'],
        'series' => $this->_options['y_series'],
        'id' => 1,
      ];

      $this->_config['x_translate'] = $contacts;
      $this->_select[] = 'contact_id as x';
      return;
    }

    foreach ($contacts as $key => $val) {
      if (empty($this->_config['filters']['contact']) || in_array($key, $this->_config['filters']['contact'])) {
        $this->_config['axis_y'][] = [
          'label' => $val,
          'type' => $this->_options['y_type'],
          'series' => $this->_options['y_series'],
          'id' => $key,
        ];
      }
    }

    $this->_config['y_translate'] = $contacts;
    $this->_select[] = 'contact_id as yy';
  }

  function queryAlterPunchContact() {
    $this->_group[] = 'contact_id';
  }

  /**
   * Group by case_id (Menu: Afficher -> Autres -> Case).
   */
  function configGroupByPunchCase() {
    $cases = [];

    $params = [];
    $where = $this->whereClause($params);

    if ($where) {
      $where = ' WHERE ' . $where;
    }

    $dao = CRM_Core_DAO::executeQuery("SELECT distinct c.id, c.subject FROM kpunch p LEFT JOIN civicrm_timetracktask as ktask ON (p.ktask_id = ktask.id) LEFT JOIN civicrm_case c ON (c.id = ktask.case_id) $where", $params);

    while ($dao->fetch()) {
      $cases[$dao->id] = $dao->subject;
    }

    if (empty($this->_config['axis_x'])) {
      // If X is empty, it's a bar chart.
      $this->_config['axis_x'] = array(
        'label' => 'Case',
        'type' => 'number',
      );

      $this->_config['axis_y'][] = array(
        'label' => $this->_options['y_label'],
        'type' => $this->_options['y_type'],
        'series' => $this->_options['y_series'],
        'id' => 1,
      );

      $this->_config['x_translate'] = $cases;
      $this->_select[] = 'case_id as x';
      return;
    }

    foreach ($cases as $key => $val) {
      if (empty($this->_config['filters']['case']) || in_array($key, $this->_config['filters']['case'])) {
        $this->_config['axis_y'][] = [
          'label' => $val,
          'type' => $this->_options['y_type'],
          'series' => $this->_options['y_series'],
          'id' => $key,
        ];
      }
    }

    $this->_config['y_translate'] = $cases;
    $this->_select[] = 'case_id as yy';
  }

  /**
   * Group by task_id (Menu: Afficher -> Autres -> Task).
   */
  function configGroupByPunchTask() {
    $tasks = [];

    $params = [];
    $where = $this->whereClause($params);

    if (empty($where)) {
      return;
    }

    $dao = CRM_Core_DAO::executeQuery("SELECT distinct ktask.id, ktask.title FROM kpunch p LEFT JOIN civicrm_timetracktask as ktask ON (p.ktask_id = ktask.id) WHERE $where", $params);

    while ($dao->fetch()) {
      $tasks[$dao->id] = $dao->title;
    }

    if (empty($this->_config['axis_x'])) {
      // If X is empty, it's a bar chart.
      $this->_config['axis_x'] = array(
        'label' => 'Task',
        'type' => 'number',
      );

      $this->_config['axis_y'][] = array(
        'label' => $this->_options['y_label'],
        'type' => $this->_options['y_type'],
        'series' => $this->_options['y_series'],
        'id' => 1,
      );

      $this->_config['x_translate'] = $tasks;
      $this->_select[] = 'ktask.id as x';
      return;
    }

    foreach ($tasks as $key => $val) {
      if (empty($this->_config['filters']['task']) || in_array($key, $this->_config['filters']['task'])) {
        $this->_config['axis_y'][] = array(
          'label' => $val,
          'type' => $this->_options['y_type'],
          'series' => $this->_options['y_series'],
          'id' => $key,
        );
      }
    }

    $this->_select[] = 'ktask.id as yy';
  }

  function queryAlterPunchCase() {
    $this->_group[] = 'case_id';
    $this->_from[] = 'LEFT JOIN civicrm_timetracktask as ktask ON (p.ktask_id = ktask.id) LEFT JOIN civicrm_case c ON (c.id = ktask.case_id)';
  }

  function queryAlterPunchTask() {
    $this->_group[] = 'ktask.id';
    $this->_from[] = 'LEFT JOIN civicrm_timetracktask as ktask ON (p.ktask_id = ktask.id) LEFT JOIN civicrm_case c ON (c.id = ktask.case_id)';
  }
}
