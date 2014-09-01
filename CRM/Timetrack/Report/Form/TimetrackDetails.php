<?php

class CRM_Timetrack_Report_Form_TimetrackDetails extends CRM_Report_Form {
  protected $_addressField = FALSE;
  protected $_emailField = FALSE;
  protected $_summary = NULL;

  protected $_customGroupExtends = array();
  protected $_customGroupGroupBy = FALSE;

  function __construct() {
    parent::__construct();

    $this->_groupFilter = FALSE;
    $this->_tagFilter = FALSE;

    parent::__construct();

    $all_projects = $this->getAllProjects();

    $this->_columns = array(
      'civicrm_case' => array(
        'dao' => 'CRM_Case_DAO_Case',
        'alias' => 'case',
        'fields' => array(
          'subject' => array(
            'title' => ts('Project'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
        ),
        'filters' => array(
          // TODO: case type, case status?
          'id' => array(
            'title' => ts('Project'),
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'type' => CRM_Utils_Type::T_INT,
            'options' => $all_projects,
          ),
        ),
      ),
      'task' => array(
        'dao' => 'CRM_Activity_DAO_Activity',
        'alias' => 'task',
        'fields' => array(
          'title' => array(
            'title' => ts('Task'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
        ),
        'filters' => array(
          // TODO: activity type?
        ),
      ),
      'punch' => array(
        'dao' => 'CRM_Timetrack_DAO_Punch',
        'alias' => 'punch',
        'fields' => array(
          'pid' => array(
            'title' => ts('#ID'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_INT,
          ),
          'uid' => array(
            'title' => ts('Worker'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'begin' => array(
            'title' => ts('Begin'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_TIME, // FIXME, currently a timestamp
          ),
          'duration' => array(
            'title' => ts('Duration (h)'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_FLOAT,
          ),
          'comment' => array(
            'title' => ts('Comment'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
        ),
        'filters' => array(
          'begin' => array(
            'title' => ts('Period'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
        ),
      ),
    );
  }

  function preProcess() {
    $this->assign('reportTitle', ts("Timetrack detailed report"));
    parent::preProcess();
  }

  /**
   * Generic select function.
   * Most reports who declare columns implicitely will call this and also define more columnHeaders.
   */
  function select() {
    $select = $this->_columnHeaders = array();

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) || CRM_Utils_Array::value($fieldName, $this->_params['fields'])) {
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
          }
        }
      }
    }

    parent::select();

    // FIXME: remove when field has been converted to mysql date.
    $this->_select = preg_replace('/punch_civireport.begin as punch_begin/', 'FROM_UNIXTIME(punch_civireport.begin) as punch_begin', $this->_select);
  }

  function from() {
    $this->_from = 'FROM kpunch as punch_civireport
              LEFT JOIN ktask ON (ktask.nid = punch_civireport.nid)
              LEFT JOIN node as task_civireport ON (task_civireport.nid = ktask.nid)
              LEFT JOIN kcontract ON (kcontract.nid = ktask.parent)
              LEFT JOIN civicrm_value_infos_base_contrats_1 as cval ON (cval.kproject_node_2 = ktask.parent)
              LEFT JOIN civicrm_case as case_civireport ON (case_civireport.id = cval.entity_id)';
  }

  /**
   * This is only to apply the date filters. It is the template code from CiviCRM reports.
   * Child reports are expected to apply their own filters on the query as well.
   */
  function where() {
    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          // [ML] added this, but there may be a better way already in core?
          $tableAlias = (isset($table['alias']) ? $table['alias'] : $tableName);

          $clause = NULL;
          if (CRM_Utils_Array::value('operatorType', $field) & CRM_Utils_Type::T_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($tableAlias . '.' . $field['name'], $relative, $from, $to, $field['type']);
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }
    if (empty($clauses)) {
      $this->_where = " WHERE ( 1 ) ";
    }
    else {
      $this->_where = " WHERE " . implode(' AND ', $clauses);
    }

    // FIXME: hacks because the where clause includes mysql date,
    // but our DB still uses unix timestamps.
    $this->_where = preg_replace('/(\d{8})/', 'UNIX_TIMESTAMP(\1)', $this->_where);
  }

  function beginPostProcess() {
    parent::beginPostProcess();
  }

  function setDefaultValues($freeze = TRUE) {
    parent::setDefaultValues($freeze);
    return $this->_defaults;
  }

  function postProcess() {
    $this->beginPostProcess();

    $rows = array();

    $this->select();
    $this->from();
    $this->where();

    $sql = $this->_select . $this->_from . $this->_where;

    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    $crmEditable = array('punch_begin', 'punch_duration', 'punch_comment');

    foreach ($rows as &$row) {
      // Keep the plain/orig values for the statistics().
      if (! empty($row['punch_duration'])) {
        $row['punch_duration_plain'] = $row['punch_duration'];
        $row['punch_duration'] = sprintf('%.2f', CRM_Timetrack_Utils::roundUpSeconds($row['punch_duration']));
      }

      // TODO: This should only be allowed in certain circumstances (admins, punch owner?)
      foreach ($crmEditable as $f) {
        $row[$f . '_orig'] = $row[$f];
        $row[$f] = "<div class='crm-entity' data-entity='Timetrackpunch' data-id='{$row['punch_pid']}'><div class='crm-editable' data-field='{$f}'>" . $row[$f] . '</div></div>';
      }

      // Make punch ID link to the punch edit form
      $row['punch_pid'] = CRM_Utils_System::href($row['punch_pid'], 'punch/' . $row['punch_pid'] . '/edit');
    }
  }

  function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $nb_weeks_worked = 0;
    $nb_days_worked = 0;

    // Days worked
    $sql = 'SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(punch_civireport.begin))) as cpt '
         . $this->_from
         . $this->_where;

    $dao = CRM_Core_DAO::executeQuery($sql);

    if ($dao->fetch()) {
      $nb_days_worked = $dao->cpt;
    }

    $nb_weeks_worked = round($nb_days_worked / 5, 2);

    // Total time (orig and rounded)
    $total_seconds_orig = 0;
    $total_hours_rounded = 0;

    foreach ($rows as $r) {
      $total_seconds_orig += $r['punch_duration_plain'];
      $total_hours_rounded += $r['punch_duration_orig'];
    }

    $statistics['counts']['totaltime'] = array(
      'title' => ts('Total Time'),
      'value' => ts('%1 hours', array(1 => sprintf('%.2f', $total_seconds_orig / 60 / 60))),
      'type' => CRM_Utils_Type::T_STRING,
    );

    $statistics['counts']['totalroundedtime'] = array(
      'title' => ts('Total rounded time'),
      'value' => ts('%1 hours', array(1 => sprintf('%.2f', $total_hours_rounded))),
      'type' => CRM_Utils_Type::T_STRING,
    );

    $statistics['counts']['weeksworked'] = array(
      'title' => ts('Weeks worked'),
      'value' => ts('Aprox %1 weeks', array(1 => $nb_weeks_worked)),
      'type' => CRM_Utils_Type::T_STRING,
    );

    $statistics['counts']['avgperweek'] = array(
      'title' => ts('Average per week worked'),
      'value' => ts('%1 hours', array(1 => sprintf('%.2f', $total_hours_rounded / $nb_weeks_worked))),
      'type' => CRM_Utils_Type::T_STRING,
    );

    $statistics['counts']['daysworked'] = array(
      'title' => ts('Days worked'),
      'value' => ts('%1 days', array(1 => $nb_days_worked)),
      'type' => CRM_Utils_Type::T_STRING,
    );

    $avg_per_day = ($nb_days_worked > 0 ? $total_seconds_orig / $nb_days_worked / 60 / 60 : 0);

    $statistics['counts']['avgperday'] = array(
      'title' => ts('Average per day worked'),
      'value' => ts('%1 hours', array(1 => sprintf('%.2f', $avg_per_day))),
      'type' => CRM_Utils_Type::T_STRING,
    );

    return $statistics;
  }

  function getAllProjects() {
    $projects = array();

    $sql = 'SELECT c.id, cont.display_name, c.subject
              FROM civicrm_case as c
              INNER JOIN civicrm_case_contact as cc ON (cc.case_id = c.id)
              INNER JOIN civicrm_contact as cont ON (cont.id = cc.contact_id)
              ORDER BY cont.display_name ASC, c.subject ASC'; 

    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $projects[$dao->id] = $dao->display_name . ': ' . $dao->subject;
    }

    return $projects;
  }
}
