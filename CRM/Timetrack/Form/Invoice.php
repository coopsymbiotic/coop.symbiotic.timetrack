<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Timetrack_Form_Invoice extends CRM_Core_Form {
  public $_caseid;
  public $_invoiceid;
  public $_action;

  protected $_invoicedata;
  protected $_tasksdata;

  const EXTRA_LINES = 5;
  const DEFAULT_HOURLY_RATE = 85;

  public function preProcess() {
    $this->_caseid = CRM_Utils_Request::retrieve('case_id', 'Integer', $this, FALSE, NULL);
    $this->_invoiceid = CRM_Utils_Request::retrieve('invoice_id', 'Integer', $this, FALSE, NULL);
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, NULL);

    if ($this->_invoiceid) {
      // Editing an existing invoice. Fetch the invoice data for setDefaultValues() later.
      $this->_invoicedata = civicrm_api3('Timetrackinvoice', 'getsingle', [
        'invoice_id' => $this->_invoiceid,
      ]);

      $result = civicrm_api3('Timetrackinvoicelineitem', 'get', [
        'invoice_id' => $this->_invoiceid
      ]);
      $this->_tasksdata = $result['values'];

      foreach ($this->_tasksdata as $key => &$val) {
        $val['hours'] = $this->getTotalHours($val['order_line_id']);
      }

      $this->_caseid = $this->_invoicedata['case_id'];
    }
    else {
      // New invoice. Since we don't have any punches (the use should have used
      // the custom search), we assume it's an invoice with only extra lines.
      $this->_tasksdata = [];

      for ($key = 0; $key < self::EXTRA_LINES; $key++) {
        $this->_tasksdata['extra' . $key] = [
          'title' => '',
          'punches' => [],
        ];
      }
    }

    if (!$this->_caseid) {
      CRM_Core_Error::fatal(ts('Could not find the case ID or the invoice ID from the request arguments.'));
    }

    $url = CRM_Timetrack_Utils::getCaseUrl($this->_caseid);
    $case_subject = CRM_Timetrack_Utils::getCaseSubject($this->_caseid);

    CRM_Utils_System::appendBreadCrumb([['title' => ts('CiviCase Dashboard'), 'url' => '/civicrm/case?reset=1']]);
    CRM_Utils_System::appendBreadCrumb([['title' => $case_subject, 'url' => $url]]);

    parent::preProcess();
  }

  public function setDefaultValues() {
    $defaults = [];

    if ($this->_invoicedata) {
      $defaults = array_merge($defaults, $this->_invoicedata);

      if ($this->_action == 'clone') {
        unset($defaults['ledger_order_id']);
        unset($defaults['ledger_bill_id']);
        unset($defaults['created_date']);
        unset($defaults['state']);
      }

      if (empty($defaults['created_date'])) {
        $defaults['created_date'] = date('Y-m-d');
      }

      foreach ($this->_tasksdata as $key => $val) {
        $defaults['task_' . $key . '_title'] = $val['title'];
        $defaults['task_' . $key . '_hours'] = $val['hours'];
        $defaults['task_' . $key . '_hours_billed'] = $val['hours_billed'];
        $defaults['task_' . $key . '_unit'] = ts('hour'); // FIXME
        $defaults['task_' . $key . '_cost'] = (isset($val['cost']) ? $val['cost'] : self::DEFAULT_HOURLY_RATE); // FIXME
        $defaults['task_' . $key . '_amount'] = $defaults['task_' . $key . '_hours_billed'] * $defaults['task_' . $key . '_cost'];
      }
    }

    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign('invoice_tasks', $this->_tasksdata);

    $contact_id = CRM_Timetrack_Utils::getCaseContact($this->_caseid);
    $contactdata = civicrm_api3('Contact', 'getsingle', ['contact_id' => $contact_id, 'return.display_name' => 1]);
    $defaults['client_name'] = $contactdata['display_name'];

    return $defaults;
  }

  public function buildQuickForm() {
    if ($this->_invoiceid) {
      if ($this->_action == 'clone') {
        CRM_Utils_System::setTitle(ts('New invoice for project %2 based on %1', [
          1 => $this->_invoicedata['title'],
          2 => $this->_invoicedata['case_subject']
        ]));
      }
      else {
        CRM_Utils_System::setTitle(ts('Edit invoice %1 for project %2', [
          1 => $this->_invoicedata['title'],
          2 => $this->_invoicedata['case_subject']
        ]));
      }
    }
    else {
      $case_title = CRM_Timetrack_Utils::getCaseSubject($this->_caseid);
      CRM_Utils_System::setTitle(ts('New invoice for %1', [1 => $case_title]));
    }

    $this->add('hidden', 'caseid', $this->_caseid);

    if ($this->_action != 'clone') {
      $this->add('hidden', 'invoiceid', $this->_invoiceid);
    }

    CRM_Timetrack_Form_InvoiceCommon::buildForm($this, $this->_tasksdata, [
      'invoice_other_only' => TRUE,
    ]);

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ],
    ]);

    // Export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $params = $this->exportValues();
    $caseid = CRM_Utils_Array::value('caseid', $params);

    $order_id = CRM_Timetrack_Form_InvoiceCommon::postProcess($this, $caseid, $this->_tasksdata);
    CRM_Core_Session::setStatus(ts('The invoice #%1 has been saved.', [1 => $order_id]), '', 'success');

    // Redirect back to the case.
    $url = CRM_Timetrack_Utils::getCaseUrl($caseid);
    CRM_Utils_System::redirect($url);

    parent::postProcess();
  }

  /**
   * Returns the total hours of punches included in a line item.
   */
  public function getTotalHours($invoice_line_item) {
    return CRM_Core_DAO::singleValueQuery('SELECT round(sum(duration) / 60 / 60, 2) as hours FROM kpunch WHERE korder_line_id = %1', [
      1 => [$invoice_line_item, 'Positive'],
    ]);
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  public function getTemplateFileName() {
    return 'CRM/Timetrack/Form/Task/Invoice.tpl';
  }

}
