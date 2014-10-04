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

  protected $case_id;

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

    // set also setDefaultValues();
    $this->case_id = CRM_Utils_Request::retrieve('case_id', 'Integer', $this, FALSE, NULL);
  }

  function get($name) {
    return $this->$name;
  }

  function set($name, $value) {
    $this->$name = $value;
  }

  function buildForm(&$form) {
    $elements = array();

    // Get the case subject
    $result = civicrm_api3('Case', 'getsingle', array(
      'id' => $this->case_id,
    ));

    $this->setTitle(ts('List of punches for %1', array(1 => $result['subject'])));

    // Punch status
    $form->addElement('checkbox', 'case_status_id[1]', NULL, 'Réception');
    $form->addElement('checkbox', 'case_status_id[2]', NULL, 'Assigné');
    array_push($elements, 'case_status_id');

    $form->assign('elements', $elements);
  }

  function setDefaultValues() {
    $defaults = array();

    // see also in constructor
    $this->case_id = CRM_Utils_Request::retrieve('case_id', 'Integer', $this, FALSE, NULL);
    $defaults['case_id'] = $this->case_id;

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
    $select = "kpunch.pid, kpunch.uid, from_unixtime(kpunch.begin) as begin, kpunch.duration,
               kpunch.duration as duration_rounded, kpunch.comment, kpunch.order_reference as invoice_id,
               task_civireport.title as task,
               civicrm_case.subject as case_subject, civicrm_case.id as case_id";

    $this->_tables['kpunch'] = "kpunch";
    $this->_tables['ktask'] = "LEFT JOIN ktask kt ON (kt.nid = kpunch.nid)";
    $this->_tables['node'] = "LEFT JOIN node as task_civireport ON (task_civireport.nid = kt.nid)";
    $this->_tables['kcontract'] = "LEFT JOIN kcontract ON (kcontract.nid = kt.parent)";
    $this->_tables['korder'] = "LEFT JOIN korder as invoice_civireport ON (invoice_civireport.nid = kpunch.order_reference)";
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

/*
    if (empty($this->_formValues['case_status_id'])) {
      $clauses[] = "civicase.status_id IN (4, 5)";
    }
    else {
      $case_status_ids = array_keys($this->_formValues['case_status_id']);
      $status_clause = array();

      foreach ($case_status_ids as $id) {
        $status_clause[] = 'civicase.status_id = ' . intval($id);
      }

      $clauses[] = '(' . implode(' OR ', $status_clause) . ')';
    }
*/

    if ($this->case_id) {
      $clauses[] = 'civicrm_case.id = ' . intval($this->case_id);
    }

    $where = implode(' AND ', $clauses);
/*
    if(!empty($this->_permissionWhereClause)){
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
  }
}
