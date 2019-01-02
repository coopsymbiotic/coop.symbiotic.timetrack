<?php

/**
 * 
 */
class CRM_Timetrack_Form_Search_TimetrackPunches extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  protected $_formValues;
  protected $_tableName;
  protected $_tables;
  protected $_whereTables;
  protected $_permissionWhereClause;

  function __construct(&$formValues) {
    parent::__construct($formValues);

    $this->_formValues = $formValues;
    $this->_tables = array();
    $this->_whereTables = array();

    /**
     * Define the columns for search result rows
     */
    $this->_columns = array(
      "#" => 'case_id',
      ts('Project') => 'case_subject',
      ts('Task') => 'task',
      ts('Punch') => 'pid',
      ts('Contact') => 'real_contact_id',
      ts('Begin') => 'begin',
      ts('Duration') => 'duration_hours',
      ts('Rounded') => 'duration_rounded',
      ts('Comment') => 'comment',
      ts('Billing') => 'invoice_id',
    );

    if (empty($this->_formValues['case_id'])) {
      $this->_formValues = array_merge($this->_formValues, $this->setDefaultValues());
    }

    // Needs to be set in form for the export tasks?
    if (! empty($formValues['case_id'])) {
      $this->case_id = $formValues['case_id'];
    }
    else {
      $this->case_id = CRM_Utils_Request::retrieve('case_id', 'Integer', $this, FALSE, NULL);
    }
  }

  public function get($name) {
    return isset($this->$name) ? $this->$name : NULL;
  }

  public function set($name, $value) {
    $this->$name = $value;
  }

  /**
   * Builds the list of tasks or actions that a searcher can perform on a result set.
   *
   * @param CRM_Core_Form_Search $form
   * @return array
   */
  public function buildTaskList(CRM_Core_Form_Search $form) {
    // [ML] If I understand correctly, this refers to the tasks we defined
    // in hook_civicrm_searchTasks() ?
    $tasks = array(
      100 => ts('Invoice punches', array('domain' => 'coop.symbiotic.timetrack')),
    );

    return $tasks;
  }

  /**
   * @param CRM_Core_Form $form
   */
  function buildForm(&$form) {
    // Needs to be set in the $form, so that we don't loose it after filter/task submit.
    $this->case_id = CRM_Utils_Request::retrieve('case_id', 'Integer', $form, FALSE, NULL);

    $elements = [];

    // @todo: convert users field to EntityRef; requires https://github.com/civicrm/civicrm-core/pull/13230
    $users = CRM_Timetrack_Utils::getUsers();

    $case_title = CRM_Timetrack_Utils::getCaseSubject($this->case_id);
    $this->setTitle(ts('List of punches for %1', [1 => $case_title]));

    // Punch filters
    // NB: ktask select must not be named 'task' or it will conflict with the 'task' select in the results.
    $form->addElement('hidden', 'case_id', $this->case_id);

    $form->add('datepicker', 'start_date', ts('Punch start date'), [], FALSE, ['time' => FALSE]);
    $form->add('datepicker', 'end_date', ts('Punch end date'), [], FALSE, ['time' => FALSE]);

    $tasks = CRM_Timetrack_Utils::getActivitiesForCase($this->case_id);
    $tasks[''] = ts('- select -');

    $form->add('select', 'ktask', ts('Task'), $tasks);
    $form->add('select', 'contact_id', ts('Contact'), $users, FALSE, ['class' => 'crm-select2']);
    $form->add('text', 'comment', ts('Comment'), FALSE);
    $form->add('select', 'state', ts('Invoice status'), array_merge(array('' => ts('- select -')), CRM_Timetrack_PseudoConstant::getInvoiceStatuses()));

    array_push($elements, 'case_id');
    array_push($elements, 'start_date');
    array_push($elements, 'end_date');
    array_push($elements, 'ktask');
    array_push($elements, 'contact_id');
    array_push($elements, 'comment');
    array_push($elements, 'state');

    $form->assign('elements', $elements);

    // FIXME: this disables ./Contact/Form/Search.php from doing: $this->addClass('crm-ajax-selection-form');
    // because the ajax selection doesn't work on non-contacts (always returns 0 items).
    $form->set('component_mode', 999);

    // Hide the action links, since they only work for contacts.
    Civi::resources()->addStyle('.crm-search-results tbody > tr > td:last-child { display: none; }');
  }

  function setDefaultValues() {
    $defaults = array();

    if (empty($this->_formValues['case_id'])) {
      $this->case_id = CRM_Utils_Request::retrieve('case_id', 'Integer', $this, FALSE, NULL);
      if ($this->case_id) {
        $defaults['case_id'] = $this->case_id;
      }
    }

    // New punches by default
    $defaults['state'] = 0;

    return $defaults;
  }

  function templateFile() {
    return 'CRM/Timetrack/Form/Search/TimetrackPunches.tpl';
  }

  /**
   * Implements all().
   * Defines the default select and sort clauses.
   */
  function all($offset = 0, $rowcount = 0, $sort = null, $includeContactIDs = FALSE, $onlyIDs = FALSE) {
    // XXX: kpunch.id as contact_id is a hack because the tasks require it for the checkboxes.
    $select = "kpunch.id as pid, kpunch.id as contact_id, kpunch.contact_id as real_contact_id, from_unixtime(kpunch.begin) as begin, kpunch.duration as duration_hours,
               kpunch.duration as duration_rounded, kpunch.comment, kpunch.korder_id as invoice_id,
               korder.state as order_state,
               kt.title as task,
               civicrm_case.subject as case_subject, civicrm_case.id as case_id";

    $this->_tables['kpunch'] = "kpunch";
    $this->_tables['ktask'] = "LEFT JOIN ktask kt ON (kt.id = kpunch.ktask_id)";
    $this->_tables['kcontract'] = "LEFT JOIN kcontract ON (kcontract.case_id = kt.case_id)";
    $this->_tables['korder'] = "LEFT JOIN korder ON (korder.id = kpunch.korder_id)";
    $this->_tables['civicrm_case'] = "LEFT JOIN civicrm_case ON (civicrm_case.id = kt.case_id)";

    $this->_whereTables = $this->_tables;

    $this->_permissionWhereClause = CRM_ACL_API::whereClause(
      CRM_Core_Permission::VIEW,
      $this->_tables,
      $this->_whereTables,
      NULL
    );

    $from = $this->from();
    $where = $this->where($includeContactIDs);
    $groupby = $this->groupby();
    $having = $this->having();

    $sql = "SELECT $select FROM $from WHERE $where";

    if (! $onlyIDs) {
      // Define ORDER BY for query in $sort, with default value
      if (! empty($sort)) {
        if (is_string($sort)) {
          $sql .= " ORDER BY $sort ";
        }
        else {
          $sql .= " ORDER BY " . trim($sort->orderBy());
        }
      }
      else {
        $sql .= " ORDER BY begin DESC";
      }
    }

    if ($rowcount > 0 && $offset >= 0) {
      $sql .= " LIMIT $offset, $rowcount ";
    }

    return $sql;
  }

  /**
   * Implements from().
   * Returns a list of tables to select from.
   */
  function from() {
    return implode(' ', $this->_tables);
  }

  /**
   * Implements where().
   */
  function where($includeContactIDs = false){
    $clauses = array();

    if (! empty($this->_formValues['start_date'])) {
      // Convert to unix timestamp (FIXME)
      $start = $this->_formValues['start_date'];
      $start = strtotime($start);

      $clauses[] = 'kpunch.begin >= ' . $start;
    }

    if (! empty($this->_formValues['end_date'])) {
      // Convert to unix timestamp (FIXME)
      $end = $this->_formValues['end_date'] . ' 23:59:59';
      $end = strtotime($end);

      $clauses[] = 'kpunch.begin <= ' . $end;
    }

    if (isset($this->_formValues['state']) && $this->_formValues['state'] !== '') {
      if ($this->_formValues['state'] == 0) {
        $clauses[] = 'korder.state is NULL';
      }
      else {
        $clauses[] = 'korder.state = ' . intval($this->_formValues['state']);
      }
    }

    if (! empty($this->_formValues['ktask'])) {
      $clauses[] = 'kpunch.ktask_id = ' . CRM_Utils_Type::escape($this->_formValues['ktask'], 'Positive');
    }

    if (! empty($this->_formValues['contact_id'])) {
      $clauses[] = 'kpunch.contact_id = ' . CRM_Utils_Type::escape($this->_formValues['contact_id'], 'Positive');
    }

    if (! empty($this->_formValues['case_id'])) {
      $clauses[] = 'civicrm_case.id = ' . intval($this->_formValues['case_id']);
    }

    $where = implode(' AND ', $clauses);

/* FIXME
    if(! empty($this->_permissionWhereClause)){
      if (empty($where)) {
        $where = "$this->_permissionWhereClause";
      }
      else {
        $where = "$where AND $this->_permissionWhereClause";
      }
    }
*/

    return $where;
  }

  /**
   * Implements groupby().
   */
  function groupby() {
    $groupby = '';
    return $groupby;
  }

  /**
   * Implements having().
   */
  function having() {
    $having = '';
    return $having;
  }

 /**
   * Implements counts().
   */
  function count() {
    $sql = $this->all();
    $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
    return $dao->N;
  }

  /**
   * Not sure if mandatory or not. Was in the base example I re-used.
   */
  function contactIDs($offset = 0, $rowcount = 0, $sort = NULL, $returnSQL = FALSE) {
    return $this->all($offset, $rowcount, $sort, FALSE, TRUE);
  }

  /**
   * Implements columns().
   */
  function &columns() {
    return $this->_columns;
  }

  /**
   * Sets the page title.
   * Called from buildForm().
   */
  function setTitle($title) {
    if ($title) {
      CRM_Utils_System::setTitle($title);
    }
    else {
      CRM_Utils_System::setTitle(ts('Search'));
    }
  }

  function summary() {
    return NULL;
  }

  /**
   * Implements alterRow().
   */
  function alterRow(&$row) {
    $row['duration_hours'] = CRM_Timetrack_Utils::roundUpSeconds($row['duration_hours'], 1);
    $row['duration_rounded'] = CRM_Timetrack_Utils::roundUpSeconds($row['duration_rounded']);

    // Keep a cache of tasks for each case_id
    // TODO: in 4.6, we won't need this, thanks to CRM-15759
    // which can lookup option values when necessary (i.e. if the user clicks a field).
    static $task_cache = array();
    $case_id = $row['case_id'];
    $pid = $row['pid'];

    if (! isset($task_cache[$case_id])) {
      $task_cache[$case_id] = array();

      $result = civicrm_api3('Timetracktask', 'get', array(
        'case_id' => $case_id,
        'option.limit' => 1000,
      ));

      foreach ($result['values'] as $key => $val) {
        $task_cache[$case_id][$key] = $val['title'];
      }
    }

    // Link the pid to the punch edit form
    $url = CRM_Utils_System::url('civicrm/timetrack/punch', array('reset' => 1, 'cid' => $case_id, 'pid' => $pid));
    $row['pid'] = "<a class='crm-popup' href='$url'>" . $row['pid'] . '</a>';

    // Allow user to edit punch duration, comment and task type.
    $row['duration_hours'] = "<div class='crm-entity' data-entity='Timetrackpunch' data-id='{$pid}'><div class='crm-editable' data-field='duration_hours'>" . $row['duration_hours'] . '</div></div>';
    $row['comment'] = "<div class='crm-entity' data-entity='Timetrackpunch' data-id='{$pid}'><div class='crm-editable' data-field='comment'>" . $row['comment'] . '</div></div>';

    $options = json_encode($task_cache[$case_id]);
    $row['task'] = "<div class='crm-entity' data-entity='Timetrackpunch' data-id='{$pid}'><div class='crm-editable' data-field='ktask_id' data-type='select' data-options='$options'>" . $row['task'] . '</div></div>';

    if (! empty($row['case_subject'])) {
      $contact_id = CRM_Timetrack_Utils::getCaseContact($case_id);

      $row['case_subject'] = CRM_Utils_System::href($row['case_subject'], 'civicrm/contact/view/case', array(
        'reset' => 1,
        'id' => $case_id,
        'cid' => $contact_id,
        'action' => 'view',
        'context' => 'case',
      ));
    }
  }
}
