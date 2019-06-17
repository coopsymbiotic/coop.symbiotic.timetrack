<?php

/**
 * This class provides the functionality to invoice punches.
 */
class CRM_Timetrack_Form_Task_Invoice extends CRM_Timetrack_Form_SearchTask {
  protected $defaults;
  protected $punchIds;

  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  public function preProcess() {
    parent::preProcess();
  }

  public function setDefaultValues() {
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
    $this->defaults = [];
    $smarty = CRM_Core_Smarty::singleton();

    Civi::resources()->addScriptFile('coop.symbiotic.timetrack', 'js/task-invoice.js');

    $case_id = $this->getCaseID();
    $client_id = CRM_Timetrack_Utils::getCaseContact($case_id);
    $contact = civicrm_api3('Contact', 'getsingle', ['id' => $client_id]);
    $period_start = $this->getPeriodStart();
    $period_end = $this->getPeriodEnd();

    CRM_Utils_System::setTitle(ts('New invoice for %1', [1 => $contact['display_name']]));

    $this->defaults['client_name'] = $contact['display_name'];
    $this->defaults['title'] = $contact['display_name'] . ' ' . substr($period_end, 0, 10);
    $this->defaults['invoice_period_start'] = $period_start;
    $this->defaults['invoice_period_end'] = $period_end;
    $this->defaults['created_date'] = date('m/d/Y');
    $this->defaults['ledger_order_id'] = '';
    $this->defaults['ledger_invoice_id'] = '';

    $tasks = $this->getBillingPerTasks();

    foreach ($tasks as $key => $val) {
      $this->defaults['task_' . $key . '_title'] = $val['title'];
      $this->defaults['task_' . $key . '_hours'] = $this->getTotalHours($val['punches'], 'duration');
      $this->defaults['task_' . $key . '_hours_billed'] = $this->getTotalHours($val['punches'], 'duration_rounded');
      $this->defaults['task_' . $key . '_unit'] = ts('hour'); // FIXME
      $this->defaults['task_' . $key . '_cost'] = CRM_Timetrack_Form_Invoice::DEFAULT_HOURLY_RATE; // FIXME

      // This gets recalculated in JS on page load / change.
      $this->defaults['task_' . $key . '_amount'] = $this->defaults['task_' . $key . '_hours_billed'] * $this->defaults['task_' . $key . '_cost'];
    }

    for ($key = 0; $key < CRM_Timetrack_Form_Invoice::EXTRA_LINES; $key++) {
      $tasks['extra' . $key] = [
        'title' => '',
        'punches' => [],
      ];
    }

    CRM_Timetrack_Form_InvoiceCommon::buildForm($this, $tasks);
    $this->addDefaultButtons(ts('Save'));

    $smarty->assign('invoice_tasks', $tasks);
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   */
  public function postProcess() {
    $case_id = $this->getCaseID();
    $params = $this->exportValues();

    $total_hours_billed = 0;

    $tasks = $this->getBillingPerTasks();

    // TODO: remove code duplication / use InvoiceCommon's postProcess.

    foreach ($tasks as $key => $val) {
      $total_hours_billed += $params['task_' . $key . '_hours_billed'];
    }

    for ($key = 0; $key < CRM_Timetrack_Form_Invoice::EXTRA_LINES; $key++) {
      $total_hours_billed += $params['task_extra' . $key . '_hours_billed'];
    }

    // NB: created_date can't be set manually becase it is a timestamp
    // and the DB layer explicitely ignores timestamps (there is a trigger
    // defined in timetrack.php).
    $result = civicrm_api3('Timetrackinvoice', 'create', [
      'case_id' => $case_id,
      'title' => $params['title'],
      'state' => 3, // FIXME, expose to UI, pseudoconstant, etc.
      'ledger_order_id' => $params['ledger_order_id'],
      'ledger_bill_id' => $params['ledger_bill_id'],
      'hours_billed' => $total_hours_billed,
    ]);

    $order_id = $result['id'];

    $params['created_date'] = date('Ymd', strtotime($params['created_date']));

    CRM_Core_DAO::executeQuery('UPDATE korder SET created_date = %1 WHERE id = %2', [
      1 => [$params['created_date'], 'Timestamp'],
      2 => [$order_id, 'Positive'],
    ]);

    // Known tasks, extracted from the punches being billed.
    foreach ($tasks as $key => $val) {
      $result = civicrm_api3('Timetrackinvoicelineitem', 'create', [
        'order_id' => $order_id,
        'title' => $params['task_' . $key . '_title'],
        'hours_billed' => $params['task_' . $key . '_hours_billed'],
        'cost' => $params['task_' . $key . '_cost'],
        'unit' => $params['task_' . $key . '_unit'],
      ]);

      $line_item_id = $result['id'];

      // Assign punches to line item / order.
      foreach ($val['punches'] as $pkey => $pval) {
        CRM_Core_DAO::executeQuery('UPDATE kpunch SET korder_id = %1, korder_line_id = %2 WHERE id = %3', [
          1 => [$order_id, 'Positive'],
          2 => [$line_item_id, 'Positive'],
          3 => [$pval['pid'], 'Positive'],
        ]);
      }
    }

    // Extra tasks, no punches assigned.
    for ($key = 0; $key < CRM_Timetrack_Form_Invoice::EXTRA_LINES; $key++) {
      // FIXME: not sure what to consider sufficient to charge an 'extra' line.
      // Assuming that if there is a 'cost' value, it's enough to charge.
      if ($params['task_extra' . $key . '_cost']) {
        $result = civicrm_api3('Timetrackinvoicelineitem', 'create', [
          'order_id' => $order_id,
          'title' => $params['task_extra' . $key . '_title'],
          'hours_billed' => $params['task_extra' . $key . '_hours_billed'],
          'cost' => $params['task_extra' . $key . '_cost'],
          'unit' => $params['task_extra' . $key . '_unit'],
        ]);
      }
    }

    CRM_Core_Session::setStatus(ts('The order #%1 has been saved.', [1 => $order_id]), '', 'success');

    // Redirect back to the case.
    $url = CRM_Timetrack_Utils::getCaseUrl($case_id);
    CRM_Utils_System::redirect($url);
  }

  /**
   * Assuming the punches are all linked to a same case, we find the client name
   * from a random punch.
   */
  public function getCaseID() {
    $pid = $this->_componentIds[0];

    $sql = "SELECT case_id
            FROM kpunch
            LEFT JOIN ktask kt ON (kt.id = kpunch.ktask_id)
            WHERE kpunch.id = %1";

    return CRM_Core_DAO::singleValueQuery($sql, [
      1 => [$pid, 'Positive'],
    ]);
  }

  public function getPeriodStart() {
    $ids = $this->getPunchIds();
    return CRM_Core_DAO::singleValueQuery("SELECT FROM_UNIXTIME(MIN(begin)) as begin FROM kpunch WHERE id IN (" . implode(',', $ids) . ")");
  }

  public function getPeriodEnd() {
    $ids = $this->getPunchIds();
    return CRM_Core_DAO::singleValueQuery("SELECT FROM_UNIXTIME(MAX(begin)) as begin FROM kpunch WHERE id IN (" . implode(',', $ids) . ")");
  }

  public function getBillingPerTasks() {
    $tasks = [];

    $ids = $this->getPunchIds();
    $dao = CRM_Core_DAO::executeQuery("SELECT p.id, p.ktask_id, ktask.title, p.begin, p.duration, p.comment FROM kpunch p LEFT JOIN ktask ON (ktask.id = p.ktask_id) WHERE p.id IN (" . implode(',', $ids) . ")");

    while ($dao->fetch()) {
      if (!isset($tasks[$dao->ktask_id])) {
        $tasks[$dao->ktask_id] = [
          'title' => $dao->title,
          'punches' => [],
        ];
      }

      $tasks[$dao->ktask_id]['punches'][] = [
        'pid' => $dao->id,
        'begin' => $dao->begin,
        'duration' => CRM_Timetrack_Utils::roundUpSeconds($dao->duration, 1),
        'duration_rounded' => CRM_Timetrack_Utils::roundUpSeconds($dao->duration),
        'comment' => $dao->comment,
      ];
    }

    return $tasks;
  }

  public function getPunchIds() {
    return $this->_componentIds;
  }

  public function getTotalHours($punches, $field = 'duration') {
    $total = 0;

    foreach ($punches as $p) {
      $total += $p[$field];
    }

    return $total;
  }

}
