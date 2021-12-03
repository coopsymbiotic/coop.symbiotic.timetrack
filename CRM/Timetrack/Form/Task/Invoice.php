<?php

use CRM_Timetrack_ExtensionUtil as E;

/**
 * This class provides the functionality to invoice punches.
 */
class CRM_Timetrack_Form_Task_Invoice extends CRM_Timetrack_Form_SearchTask {
  use CRM_Timetrack_Form_InvoiceCommonTrait;

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
    $invoice_date = date('Y-m-d');

    // Get the default unit in the language of the contact, since that will be in the invoice
    // @todo This should be an Option Group instead
    $i18n = CRM_Core_I18n::singleton();
    $orig_locale = $i18n->getLocale();
    $i18n->setLocale($contact['preferred_language']);
    $default_unit = E::ts('hour');
    $i18n->setLocale($orig_locale);

    CRM_Utils_System::setTitle(ts('New invoice for %1', [1 => $contact['display_name']]));

    // Default the 'from' to the default organization
    $domain_id = CRM_Core_Config::domainID();
    $this->defaults['invoice_from_id'] = civicrm_api3('Domain', 'getsingle', ['id' => $domain_id])['contact_id'];

    // Find the last day of the previous month
    // If we are in the beginning of the month, and there are no punches in this month,
    // assume that we are invoicing for last month's work.
    if (date('d') <= 7) {
      $d = new DateTime('-1 month');
      $last_month = $d->format('Y-m-t');

      if ($last_month >= $period_end) {
        $invoice_date = $last_month;
      }
    }

    $this->defaults['client_name'] = $contact['display_name'];
    $this->defaults['title'] = $contact['display_name'] . ' ' . substr($period_end, 0, 10);
    $this->defaults['invoice_period_start'] = $period_start;
    $this->defaults['invoice_period_end'] = $period_end;
    $this->defaults['created_date'] = $invoice_date;
    $this->defaults['ledger_order_id'] = '';
    $this->defaults['ledger_invoice_id'] = '';

    // The rates depend on:
    // - the global default
    // - the per-case default
    // - (not implemented yet) per-task rate
    $default_hourly_rate = Civi::settings()->get('timetrack_hourly_rate_default');

    if ($cfid = Civi::settings()->get('timetrack_hourly_rate_cfid')) {
      try {
        $default_hourly_rate = civicrm_api3('Case', 'getsingle', [
          'id' => $case_id,
          'return' => 'custom_' . $cfid,
        ])['custom_' . $cfid];
      }
      catch (Exception $e) {
        Civi::log()->warning('Timetrack: failed to get the hourly rate from case (' . $case_id . ')');
      }
    }

    $tasks = $this->getBillingPerTasks();

    foreach ($tasks as $key => $val) {
      $this->defaults['task_' . $key . '_title'] = $val['title'];
      $this->defaults['task_' . $key . '_hours'] = $this->getTotalHours($val['punches'], 'duration');
      $this->defaults['task_' . $key . '_hours_billed'] = $this->getTotalHours($val['punches'], 'duration_rounded');
      $this->defaults['task_' . $key . '_unit'] = $default_unit;
      $this->defaults['task_' . $key . '_cost'] = $default_hourly_rate;

      // This gets recalculated in JS on page load / change.
      $this->defaults['task_' . $key . '_amount'] = $this->defaults['task_' . $key . '_hours_billed'] * $this->defaults['task_' . $key . '_cost'];
    }

    for ($key = 0; $key < CRM_Timetrack_Form_Invoice::EXTRA_LINES; $key++) {
      $tasks['extra' . $key] = [
        'title' => '',
        'punches' => [],
      ];
    }

    CRM_Timetrack_Form_InvoiceCommonTrait::buildFormCommon($this, $tasks);
    $this->addDefaultButtons(ts('Save'));

    $smarty->assign('invoice_tasks', $tasks);
  }

  /**
   * Process the form after the input has been submitted and validated
   */
  public function postProcess() {
    $case_id = $this->getCaseID();

    $tasks = $this->getBillingPerTasks();
    $order_id = CRM_Timetrack_Form_InvoiceCommonTrait::postProcessCommon($this, $case_id, $tasks);
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
            LEFT JOIN civicrm_timetracktask as kt ON (kt.id = kpunch.ktask_id)
            WHERE kpunch.id = %1";

    return CRM_Core_DAO::singleValueQuery($sql, [
      1 => [$pid, 'Positive'],
    ]);
  }

  public function getPeriodStart() {
    $ids = $this->getPunchIds();
    return CRM_Core_DAO::singleValueQuery("SELECT MIN(begin) as begin FROM kpunch WHERE id IN (" . implode(',', $ids) . ")");
  }

  public function getPeriodEnd() {
    $ids = $this->getPunchIds();
    return CRM_Core_DAO::singleValueQuery("SELECT MAX(begin) as begin FROM kpunch WHERE id IN (" . implode(',', $ids) . ")");
  }

  public function getBillingPerTasks() {
    $tasks = [];

    $ids = $this->getPunchIds();
    $dao = CRM_Core_DAO::executeQuery("SELECT p.id, p.ktask_id, ktask.title, p.begin, p.duration, p.comment FROM kpunch p LEFT JOIN civicrm_timetracktask ktask ON (ktask.id = p.ktask_id) WHERE p.id IN (" . implode(',', $ids) . ")");

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
