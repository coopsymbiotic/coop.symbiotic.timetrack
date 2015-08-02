<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Timetrack_Form_Invoice extends CRM_Core_Form {
  public $_caseid;
  public $_invoiceid;

  protected $_invoicedata;
  protected $_tasksdata;

  const EXTRA_LINES = 5;
  const DEFAULT_HOURLY_RATE = 85;

  function preProcess() {
    $this->_caseid = CRM_Utils_Request::retrieve('case_id', 'Integer', $this, FALSE, NULL);
    $this->_invoiceid = CRM_Utils_Request::retrieve('invoice_id', 'Integer', $this, FALSE, NULL);

    if ($this->_invoiceid) {
      // Editing an existing invoice. Fetch the invoice data for setDefaultValues() later.
      $this->_invoicedata = civicrm_api3('Timetrackinvoice', 'getsingle', array(
        'invoice_id' => $this->_invoiceid,
      ));

      $result = civicrm_api3('Timetrackinvoicelineitem', 'get', array(
        'invoice_id' => $this->_invoiceid
      ));
      $this->_tasksdata = $result['values'];

      foreach ($this->_tasksdata as $key => &$val) {
        $val['hours'] = $this->getTotalHours($val['order_line_id']);
      }

      $this->_caseid = $this->_invoicedata['case_id'];
    }
    else {
      // New invoice. Since we don't have any punches (the use should have used
      // the custom search), we assume it's an invoice with only extra lines.
      $this->_tasksdata = array();

      for ($key = 0; $key < self::EXTRA_LINES; $key++) {
        $this->_tasksdata['extra' . $key] = array(
          'title' => '',
          'punches' => array(),
        );
      }
    }

    if (! $this->_caseid) {
      CRM_Core_Error::fatal(ts('Could not find the case ID or the invoice ID from the request arguments.'));
    }

    parent::preProcess();
  }

  function setDefaultValues() {
    $defaults = array();

    if ($this->_invoicedata) {
      $defaults = array_merge($defaults, $this->_invoicedata);

      // FIXME: I don't understan jcalendar widgets and it's date formats..
      if (! empty($defaults['created_date'])) {
        $defaults['created_date'] = date('m/d/Y', strtotime($defaults['created_date']));
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
    $contactdata = civicrm_api3('Contact', 'getsingle', array('contact_id' => $contact_id, 'return.display_name' => 1));
    $defaults['client_name'] = $contactdata['display_name'];

    return $defaults;
  }

  function buildQuickForm() {
    if ($this->_invoiceid) {
      CRM_Utils_System::setTitle(ts('Edit invoice %1 for project %2', array(
        1 => $this->_invoicedata['title'],
        2 => $this->_invoicedata['case_subject']
      )));
    }
    else {
      $case_title = CRM_Timetrack_Utils::getCaseSubject($this->_caseid);
      CRM_Utils_System::setTitle(ts('New invoice for %1', array(1 => $case_title)));
    }

    $this->add('hidden', 'caseid', $this->_caseid);
    $this->add('hidden', 'invoiceid', $this->_invoiceid);

    CRM_Timetrack_Form_InvoiceCommon::buildForm($this, $this->_tasksdata);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ),
    ));

    // Export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $params = $this->exportValues();
    $caseid = CRM_Utils_Array::value('caseid', $params);

    $order_id = CRM_Timetrack_Form_InvoiceCommon::postProcess($this, $caseid, $this->_tasksdata);
    CRM_Core_Session::setStatus(ts('The invoice #%1 has been saved.', array(1 => $order_id)), '', 'success');

    // Find the main contact ID of the case, to redirect back to the case.
    $contacts = CRM_Case_BAO_Case::getContactNames($caseid);

    if (count($contacts)) {
      $c = array_shift($contacts);
      $url = CRM_Utils_System::url('civicrm/contact/view/case', 'reset=1&id=' . $caseid . '&cid=' . $c['contact_id'] . '&action=view');
      CRM_Utils_System::redirect($url);
    }

    parent::postProcess();
  }

  /**
   * Returns the total hours of punches included in a line item.
   */
  function getTotalHours($invoice_line_item) {
    return CRM_Core_DAO::singleValueQuery('SELECT round(sum(duration) / 60 / 60, 2) as hours FROM kpunch WHERE korder_line_id = %1', array(
      1 => array($invoice_line_item, 'Positive'),
    ));
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  function getTemplateFileName() {
    return 'CRM/Timetrack/Form/Task/Invoice.tpl';
  }
}
