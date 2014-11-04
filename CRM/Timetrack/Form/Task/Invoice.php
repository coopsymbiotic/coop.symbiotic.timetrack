<?php

/**
 * This class provides the functionality to invoice punches.
 */
class CRM_Timetrack_Form_Task_Invoice extends CRM_Contact_Form_Task {
  protected $defaults;
  protected $punchIds;

  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  function preProcess() {
    // CRM_Contact_Form_Task_EmailCommon::preProcessFromAddress($this);

    parent::preProcess();
  }

  function setDefaultValues() {
    return $this->defaults;
  }

  /**
   * Build the form
   *
   * @access public
   *
   * @return void
   */
  public function buildQuickForm() {
    $this->defaults = array();
    $smarty = CRM_Core_Smarty::singleton();

    $case_id = $this->getCaseID();
    $client_id = CRM_Timetrack_Utils::getCaseContact($case_id);
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $client_id));

    CRM_Utils_System::setTitle(ts('New invoice for %1', array(1 => $contact['display_name'])));

    $this->addElement('text', 'client_name', ts('Client'));
    $this->defaults['client_name'] = $contact['display_name'];

    $this->addElement('text', 'invoice_period_start', ts('From'));
    $this->defaults['invoice_period_start'] = $this->getPeriodStart();

    $this->addElement('text', 'invoice_period_end', ts('To'));
    $this->defaults['invoice_period_end'] = $this->getPeriodEnd();

    $this->addDate('invoice_date', ts('Invoice date'), TRUE);
    $this->defaults['invoice_date'] = date('m/d/Y');

    $tasks = $this->getBillingPerTasks();

    foreach ($tasks as $key => $val) {
      $this->addElement('text', 'task_' . $key . '_label');
      $this->addElement('text', 'task_' . $key . '_hours')->freeze();
      $this->addElement('text', 'task_' . $key . '_hours_billed');
      $this->addElement('text', 'task_' . $key . '_rate');
      $this->addElement('text', 'task_' . $key . '_amount');

      $this->defaults['task_' . $key . '_label'] = $val['title'];
      $this->defaults['task_' . $key . '_hours'] = $this->getTotalHours($val['punches'], 'duration');
      $this->defaults['task_' . $key . '_hours_billed'] = $this->getTotalHours($val['punches'], 'duration_rounded');
      $this->defaults['task_' . $key . '_rate'] = 85; // FIXME

      // This gets recalculated in JS on page load / change.
      $this->defaults['task_' . $key . '_amount'] = $this->defaults['task_' . $key . '_hours_billed'] * $this->defaults['task_' . $key . '_rate'];
    }

    for ($key = 0; $key < 5; $key++) {
      $this->addElement('text', 'task_extra' . $key . '_label');
      $this->addElement('text', 'task_extra' . $key . '_hours_billed');
      $this->addElement('text', 'task_extra' . $key . '_rate');
      $this->addElement('text', 'task_extra' . $key . '_amount');

      $tasks['extra' . $key] = array(
        'title' => '',
        'punches' => array(),
      );
    }

    $this->addDefaultButtons(ts('Save'));

    $smarty->assign('invoice_tasks', $tasks);
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $params = $this->exportValues();

    dsm($params, 'params');
    dsm($this);

    $line_items = array();

    $tasks = $this->getBillingPerTasks();

dsm($tasks, 'tasks');

    foreach ($params as $key => $val) {
      if (substr($key, 0, 4) == 'task') {
        
      }
    }
  }

  /**
   * Assuming the punches are all linked to a same case, we find the client name
   * from a random punch.
   */
  function getCaseID() {
    $pid = $this->_contactIds[0];

    $sql = "SELECT civicrm_case.id as case_id
            FROM kpunch
            LEFT JOIN ktask kt ON (kt.nid = kpunch.nid)
            LEFT JOIN node as task_civireport ON (task_civireport.nid = kt.nid)
            LEFT JOIN kcontract ON (kcontract.nid = kt.parent)
            LEFT JOIN korder as invoice_civireport ON (invoice_civireport.nid = kpunch.order_reference)
            LEFT JOIN civicrm_value_infos_base_contrats_1 as cval ON (cval.kproject_node_2 = kt.parent)
            LEFT JOIN civicrm_case ON (civicrm_case.id = cval.entity_id)
            WHERE kpunch.pid = %1";

    return CRM_Core_DAO::singleValueQuery($sql, array(
      1 => array($pid, 'Positive'),
    ));
  }

  function getPeriodStart() {
    $ids = $this->getPunchIds();
    return CRM_Core_DAO::singleValueQuery("SELECT FROM_UNIXTIME(MIN(begin)) as begin FROM kpunch WHERE pid IN (" . implode(',', $ids) . ")");
  }

  function getPeriodEnd() {
    $ids = $this->getPunchIds();
    return CRM_Core_DAO::singleValueQuery("SELECT FROM_UNIXTIME(MAX(begin)) as begin FROM kpunch WHERE pid IN (" . implode(',', $ids) . ")");
  }

  function getBillingPerTasks() {
    $tasks = array();

    $ids = $this->getPunchIds();
    $dao = CRM_Core_DAO::executeQuery("SELECT n.nid, n.title, p.pid, p.begin, p.duration, p.comment FROM kpunch p LEFT JOIN node n ON (n.nid = p.nid) WHERE pid IN (" . implode(',', $ids) . ")");

    while ($dao->fetch()) {
      if (! isset($tasks[$dao->nid])) {
        $tasks[$dao->nid] = array(
          'title' => $dao->title,
          'punches' => array(),
        );
      }

      $tasks[$dao->nid]['punches'][] = array(
        'pid' => $dao->pid,
        'begin' => $dao->begin,
        'duration' => CRM_Timetrack_Utils::roundUpSeconds($dao->duration, 1),
        'duration_rounded' => CRM_Timetrack_Utils::roundUpSeconds($dao->duration),
        'comment' => $dao->comment,
      );
    }

    return $tasks;
  }

  function getPunchIds() {
    if (isset($this->punchIds)) {
      return $this->punchIds;
    }

    $this->punchIds = array();

    foreach ($this->_contactIds as $cid) {
      $this->punchIds[] = intval($cid);
    }

    return $this->punchIds;
  }

  function getTotalHours($punches, $field = 'duration') {
    $total = 0;

    foreach ($punches as $p) {
      $total += $p[$field];
    }

    return $total;
  }
}
