<?php

class CRM_Timetrack_Page_GenerateInvoice extends CRM_Core_Page {
  function run() {
    $invoice_id = CRM_Utils_Request::retrieve('invoice_id', 'Positive', $this, TRUE);

    // http://www.tinybutstrong.com/plugins/opentbs/demo/demo_merge.php
    include('tinybutstrong/tbs_class.php');
    include('tinybutstrong-opentbs/tbs_plugin_opentbs.php');

    $TBS = new clsTinyButStrong; // new instance of TBS
    $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN); // load the OpenTBS plugin

    $client = $this->getClient($invoice_id);
    $invoice = $this->getInvoice($invoice_id);

    $lineitems = $this->getLineItems($invoice_id);
    $subtotal = $this->getLineItemsTotal($lineitems);

    $vars = array(
      'ClientName' => $client['display_name'],
      'ClientId' => $client['contact_id'],
      'ClientAddress1' => $client['street_address'],
      'ClientAddress2' => $client['supplemental_address_1'],
      'ClientAddress3' => $client['supplemental_address_2'],
      'ClientCity' => $client['city'],
      'ClientStateProvince' => CRM_Core_PseudoConstant::stateProvince($client['state_province_id']),
      'ClientPostalCode' => $client['postal_code'],
      'ClientCountry' => CRM_Core_PseudoConstant::country($client['country_id']),
      'CaseId' => $invoice['case_id'],
      'LedgerId' => $invoice['ledger_bill_id'],
      'InvoiceDate' => substr($invoice['created_date'], 0, 10), // FIXME date format using civi prefs
      'InvoiceId' => $invoice['invoice_id'],
      'ProjectPeriod' => 'XXXXXXXXXXXXXXXX', // FIXME, needs to be saved in DB
      'SubTotal' => CRM_Utils_Money::format($subtotal),
    );

    $result_default = civicrm_api3('Setting', 'get', array(
      'return' => 'TimetrackInvoiceTemplateDefault',
    ));

    // Check if we have a template for the pref language of the client.
    $lang_code = strtoupper(substr($client['preferred_language'], 0, 2));
    $result_lang = civicrm_api3('Setting', 'get', array(
      'return' => 'TimetrackInvoiceTemplate' . $lang_code,
    ));

    $template = $result_default['values'][1]['TimetrackInvoiceTemplateDefault'];

    if ($result_lang['count'] > 0 && ! empty($result_lang['values'][1]['TimetrackInvoiceTemplate' . $lang_code])) {
      $template = $result_lang['values'][1]['TimetrackInvoiceTemplate' . $lang_code];
    }

    $TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8);
    $TBS->VarRef = &$vars;
    $TBS->MergeBlock('t', $lineitems);

    $compactdate = str_replace('-', '', substr($invoice['created_date'], 0, 10));
    $output_file_name = 'invoice_' . $invoice['ledger_bill_id'] . '_' . $compactdate . '_' . $client['contact_id'] . '_' . $invoice['case_id'] . '.odt';
    $TBS->Show(OPENTBS_DOWNLOAD, $output_file_name);

    CRM_Utils_System::civiExit();
  }

  function getClient($invoice_id) {
    $invoice_result = $this->getInvoice($invoice_id);

    $result = civicrm_api3('Contact', 'getsingle', array(
      'contact_id' => $invoice_result['contact_id'],
    ));

    return $result;
  }

  function getInvoice($invoice_id) {
    static $invoice_result = array();

    if (! empty($invoice_result[$invoice_id])) {
      return $invoice_result[$invoice_id];
    }

    $result = civicrm_api3('Timetrackinvoice', 'getsingle', array(
      'invoice_id' => $invoice_id,
    ));

    $invoice_result[$invoice_id] = $result;
    return $result;
  }

  function getLineItems($invoice_id) {
    $lineitems = array();

    $result = civicrm_api3('Timetrackinvoicelineitem', 'get', array(
      'invoice_id' => $invoice_id,
    ));

    foreach ($result['values'] as $i) {
      $lineitems[] = array(
        'title' => $i['title'],
        'qty' => $i['hours_billed'], // FIXME rename to qty in DB.
        'cost' => $i['cost'],
        'unit' => $i['unit'],
        'amount' => CRM_Utils_Money::format($i['cost'] * $i['hours_billed']), // FIXME rename
      );
    }

    return $lineitems;
  }

  function getLineItemsTotal($lineitems) {
    $total = 0;

    foreach ($lineitems as $i) {
      $total += $i['amount'];
    }

    return $total;
  }
}
