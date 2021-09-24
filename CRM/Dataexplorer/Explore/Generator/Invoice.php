<?php

class CRM_Dataexplorer_Explore_Generator_Invoice extends CRM_Dataexplorer_Explore_Generator {
  protected $_options;

  function __construct() {
    parent::__construct();
  }

  function config($options = array()) {
    if ($this->_configDone) {
      return $this->_config;
    }

    $defaults = array(
      'y_label' => 'Invoiced',
      'y_series' => 'hours',
      'y_type' => 'number',
    );

    $this->_options = array_merge($defaults, $options);

    // It helps to call this here as well, because some filters affect the groupby options.
    // FIXME: see if we can call it only once, i.e. remove from data(), but not very intensive, so not a big deal.
    $params = array();
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
          'id' => 91, // FIXME: for CSV merge, ugly.
        );

        $this->_select[] = '"Total" as x';
        break;

      case 1:
      case 2:
        // Find all the labels for this type of group by
        if (in_array('period-year', $this->_groupBy)) {
          $this->configGroupByPeriodYear();
        }
        if (in_array('period-month', $this->_groupBy)) {
          $this->configGroupByPeriodMonth();
        }
        if (in_array('period-week', $this->_groupBy)) {
          $this->configGroupByPeriodWeek();
        }
        if (in_array('period-day', $this->_groupBy)) {
          $this->configGroupByPeriodDay();
        }
        if (in_array('other-contact', $this->_groupBy)) {
          $this->configGroupByOtherContact();
        }
        if (in_array('other-task', $this->_groupBy)) {
          $this->configGroupByOtherTask();
        }
        if (in_array('other-case', $this->_groupBy)) {
          $this->configGroupByOtherCase();
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
    $data = array();
    $params = array();

    // This makes it easier to check specific exceptions later on.
    $this->config();

    $this->_from[] = "korder as ko ";
                      
    if (in_array('period-year', $this->_groupBy)) {
      $this->queryAlterPeriod('year');
    }
    if (in_array('period-month', $this->_groupBy)) {
      $this->queryAlterPeriod('month');
    }
    if (in_array('period-week', $this->_groupBy)) {
      $this->queryAlterPeriod('week');
    }
    if (in_array('period-day', $this->_groupBy)) {
      $this->queryAlterPeriod('day');
    }
    if (in_array('other-campaign', $this->_groupBy)) {
      $this->queryAlterOtherCampaign();
    }
    if (in_array('other-contact', $this->_groupBy)) {
      $this->queryAlterOtherContact();
    }
    if (in_array('other-task', $this->_groupBy)) {
      $this->queryAlterOtherTask();
    }
    if (in_array('other-case', $this->_groupBy)) {
      $this->queryAlterOtherCase();
    }

    $where = $this->whereClause($params);
    $has_data = FALSE;

    $sql = 'SELECT ' . implode(', ', $this->_select) . ' '
         . ' FROM ' . implode(' ', $this->_from)
         . (!empty($where) ? ' WHERE ' . $where : '')
         . (!empty($this->_group) ? ' GROUP BY ' . implode(', ', $this->_group) : '');

    $dao = CRM_Core_DAO::executeQuery($sql, $params);

    while ($dao->fetch()) {
      if ($dao->x && $dao->y) {
        $has_data = TRUE;
        $x = $dao->x;

        if (isset($this->_config['x_translate']) && isset($this->_config['x_translate'][$x])) {
          $x = $this->_config['x_translate'][$x];
        }

        if (!empty($dao->yy)) {
          $data[$x][$dao->yy] = $dao->y;
        }
        else {
          $ylabel = $this->_options['y_label'];
          $data[$x][$ylabel] = $dao->y;
        }
      }
    }

    // FIXME: if we don't have any results, and we are querying two
    // types of data, the 2nd column of results (CSV) might get bumped into
    // the first column. This really isn't ideal, should fix the CSV merger.
    if (! $has_data) {
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
          $where_clauses[] = 'ko.created_date >= %1';
        }
        elseif ($bar[1] == 'end' && ! empty($foo[1])) {
          $params[2] = array($foo[1] . '235959', 'Timestamp');
          $where_clauses[] = 'ko.created_date <= %2';
        }
      }
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

  function configGroupByPeriodYear() {
    // Assume that if we are grouping by year, it's always a line chart.
    // that's why we check for the period groupby first.
    $this->_config['axis_x'] = [
      'label' => ts('Year'),
      'type' => 'date',
    ];

    $this->_select[] = "DATE_FORMAT(ko.created_date, '%Y') as x";
  }

  function configGroupByPeriodMonth() {
    // Assume that if we are grouping by month, it's always a line chart.
    // that's why we check for the period groupby first.
    $this->_config['axis_x'] = array(
      'label' => ts('Month'),
      'type' => 'date',
    );

    $this->_select[] = "DATE_FORMAT(ko.created_date, '%Y-%m') as x";
  }

  function configGroupByPeriodWeek() {
    // Assume that if we are grouping by week, it's always a line chart.
    // that's why we check for the period groupby first.
    $this->_config['axis_x'] = array(
      'label' => ts('Week'),
      'type' => 'date',
    );

    $this->_select[] = "YEARWEEK(ko.created_date) as x";
  }

  function configGroupByPeriodDay() {
    // Assume that if we are grouping by month, it's always a line chart.
    // that's why we check for the period groupby first.
    $this->_config['axis_x'] = array(
      'label' => ts('Day'),
      'type' => 'date',
    );

    $this->_select[] = "DATE_FORMAT(ko.created_date, '%Y-%m-%d') as x";
  }

  /**
   * @param String $type = { day, month, year }
   */
  function queryAlterPeriod($type) {
    // NB: date itself has already been put in the select[] by config().
    switch ($type) {
      case 'year':
        $this->_group[] = "DATE_FORMAT(ko.created_date, '%Y')";
        break;
      case 'month':
        $this->_group[] = "DATE_FORMAT(ko.created_date, '%Y-%m')";
        break;
      case 'week':
        $this->_group[] = "YEARWEEK(ko.created_date)";
        break;
      case 'day':
        $this->_group[] = "DATE_FORMAT(ko.created_date, '%Y-%m-%d')";
        break;
      default:
        CRM_Core_Error::fatal('Unknown type of period');
    }
  }

  /**
   * Group by contact_id (Menu: Afficher -> Autres -> Contact).
   */
  function configGroupByOtherContact() {
    $contacts = [];

    $params = [];
    $where = $this->whereClause($params);

    if (empty($where)) {
      return;
    }

    $dao = CRM_Core_DAO::executeQuery("SELECT distinct p.contact_id, c.display_name FROM kpunch p LEFT JOIN civicrm_contact c ON (c.id = p.contact_id) WHERE $where", $params);

    while ($dao->fetch()) {
      $contacts[$dao->contact_id] = $dao->display_name;
    }

    if (empty($this->_config['axis_x'])) {
      // If X is empty, it's a bar chart.
      $this->_config['axis_x'] = array(
        'label' => 'Supprimé',
        'type' => 'number',
      );

      $this->_config['axis_y'][] = array(
        'label' => $this->_options['y_label'],
        'type' => $this->_options['y_type'],
        'series' => $this->_options['y_series'],
        'id' => 1,
      );

      $this->_config['x_translate'] = $contacts;
      $this->_select[] = 'contact_id as x';
      return;
    }

    foreach ($contacts as $key => $val) {
      if (empty($this->_config['filters']['contact']) || in_array($key, $this->_config['filters']['contact'])) {
        $this->_config['axis_y'][] = array(
          'label' => $val,
          'type' => $this->_options['y_type'],
          'series' => $this->_options['y_series'],
          'id' => $key,
        );
      }
    }

    $this->_select[] = 'contact_id as yy';
  }

  function queryAlterOtherContact() {
    $this->_group[] = 'contact_id';
  }

  /**
   * Group by case_id (Menu: Afficher -> Autres -> Case).
   */
  function configGroupByOtherCase() {
    $cases = [];

    $params = [];
    $where = $this->whereClause($params);

    if (empty($where)) {
      return;
    }

    $dao = CRM_Core_DAO::executeQuery("SELECT distinct c.id, c.subject FROM kpunch p LEFT JOIN civicrm_timetracktask as ktask ON (p.ktask_id = ktask.id) LEFT JOIN civicrm_case c ON (c.id = ktask.case_id) WHERE $where", $params);

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

      $this->_config['x_translate'] = $contacts;
      $this->_select[] = 'case_id as x';
      return;
    }

    foreach ($cases as $key => $val) {
      if (empty($this->_config['filters']['case']) || in_array($key, $this->_config['filters']['case'])) {
        $this->_config['axis_y'][] = array(
          'label' => $val,
          'type' => $this->_options['y_type'],
          'series' => $this->_options['y_series'],
          'id' => $key,
        );
      }
    }

    $this->_select[] = 'case_id as yy';
  }

  /**
   * Group by task_id (Menu: Afficher -> Autres -> Task).
   */
  function configGroupByOtherTask() {
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

  function queryAlterOtherCase() {
    $this->_group[] = 'case_id';
    $this->_from[] = 'LEFT JOIN civicrm_timetracktask as ktask ON (p.ktask_id = ktask.id) LEFT JOIN civicrm_case c ON (c.id = ktask.case_id)';
  }

  function queryAlterOtherTask() {
    $this->_group[] = 'ktask.id';
    $this->_from[] = 'LEFT JOIN civicrm_timetracktask as ktask ON (p.ktask_id = ktask.id) LEFT JOIN civicrm_case c ON (c.id = ktask.case_id)';
  }
}
