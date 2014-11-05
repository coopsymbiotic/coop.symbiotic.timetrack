<?php

/**
 * 
 */
class CRM_Timetrack_Form_Search_TimetrackPunches implements CRM_Contact_Form_Search_Interface {
  protected $_formValues;
  protected $_tableName;
  protected $_tables;
  protected $_whereTables;
  protected $_permissionWhereClause;

  function __construct(&$formValues) {
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
      ts('Worker') => 'uid',
      ts('Begin') => 'begin',
      ts('Duration') => 'duration',
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

  function get($name) {
    return $this->$name;
  }

  function set($name, $value) {
    $this->$name = $value;
  }

  function buildForm(&$form) {
    // Needs to be set in the $form, so that we don't loose it after filter/task submit.
    $this->case_id = CRM_Utils_Request::retrieve('case_id', 'Integer', $form, FALSE, NULL);

    $elements = array();

    // Get the case subject
    $result = civicrm_api3('Case', 'getsingle', array(
      'id' => $this->case_id,
    ));

    $this->setTitle(ts('List of punches for %1', array(1 => $result['subject'])));

    // Punch status
    $form->addElement('hidden', 'case_id', $this->case_id);

    $form->addDate('start_date', ts('Punch start date'), FALSE, array('formatType' => 'custom', 'id' => 'date_start'));
    $form->addDate('end_date', ts('Punch end date'), FALSE, array('formatType' => 'custom', 'id' => 'date_end'));
    $form->addElement('select', 'state', ts('Invoice status'), array_merge(array('' => ts('- select -')), CRM_Timetrack_PseudoConstant::getInvoiceStatuses()));

    array_push($elements, 'case_id');
    array_push($elements, 'start_date');
    array_push($elements, 'end_date');
    array_push($elements, 'state');

    $form->assign('elements', $elements);
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
    // XXX: kpunch.pid as contact_id is a hack because the tasks require it for the checkboxes.
    $select = "kpunch.pid, kpunch.pid as contact_id, kpunch.uid, from_unixtime(kpunch.begin) as begin, kpunch.duration,
               kpunch.duration as duration_rounded, kpunch.comment, kpunch.korder_id as invoice_id,
               korder.state as order_state,
               task_civireport.title as task,
               civicrm_case.subject as case_subject, civicrm_case.id as case_id";

    $this->_tables['kpunch'] = "kpunch";
    $this->_tables['ktask'] = "LEFT JOIN ktask kt ON (kt.nid = kpunch.nid)";
    $this->_tables['node'] = "LEFT JOIN node as task_civireport ON (task_civireport.nid = kt.nid)";
    $this->_tables['kcontract'] = "LEFT JOIN kcontract ON (kcontract.nid = kt.parent)";
    $this->_tables['korder'] = "LEFT JOIN korder ON (korder.koid = kpunch.korder_id)";
    $this->_tables['base_contract'] = "LEFT JOIN civicrm_value_infos_base_contrats_1 as cval ON (cval.kproject_node_2 = kt.parent)";
    $this->_tables['civicrm_case'] = "LEFT JOIN civicrm_case ON (civicrm_case.id = cval.entity_id)";

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
  function contactIDs($offset = 0, $rowcount = 0, $sort = NULL) {
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
    $row['duration'] = CRM_Timetrack_Utils::roundUpSeconds($row['duration'], 1);
    $row['duration_rounded'] = CRM_Timetrack_Utils::roundUpSeconds($row['duration_rounded']);

    if (! empty($row['case_subject'])) {
      $contact_id = CRM_Timetrack_Utils::getCaseContact($row['case_id']);

      $row['case_subject'] = CRM_Utils_System::href($row['case_subject'], 'civicrm/contact/view/case', array(
        'reset' => 1,
        'id' => $row['case_id'],
        'cid' => $contact_id,
        'action' => 'view',
        'context' => 'case',
      ));
    }
  }
}
