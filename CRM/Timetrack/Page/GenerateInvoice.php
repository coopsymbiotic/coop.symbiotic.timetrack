<?php

class CRM_Timetrack_Page_GenerateInvoice extends CRM_Core_Page {

  public function run() {
    $invoice_id = CRM_Utils_Request::retrieve('invoice_id', 'Positive', $this, TRUE);

    // http://www.tinybutstrong.com/plugins/opentbs/demo/demo_merge.php
    include 'tinybutstrong/tbs_class.php';
    include 'tinybutstrong-opentbs/tbs_plugin_opentbs.php';

    $TBS = new clsTinyButStrong(); // new instance of TBS
    $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN); // load the OpenTBS plugin

    $client = $this->getClient($invoice_id);
    $invoice = $this->getInvoice($invoice_id);

    $lineitems = $this->getLineItems($invoice_id, $client['preferred_language']);
    $subtotal = $this->getLineItemsTotal($lineitems);

    // Check if we have a template for the pref language of the client.
    // We setLocale to display '$123' not 'CA$123' (depending on if the UI is in fr_CA or en_US)
    CRM_Core_I18n::singleton()->setLocale($client['preferred_language']);
    $lang_code = strtoupper(substr($client['preferred_language'], 0, 2));
    $template = Civi::settings()->get('TimetrackInvoiceTemplate' . $lang_code);

    if (empty($template)) {
      $template = Civi::settings()->get('TimetrackInvoiceTemplateDefault');
    }

    // Reformat numbers according to the template's language
    // @todo Ideally we would pass the currency - at symbiotic we have a custom field for this on the case
    // but it needs to be added as a setting on this extension
    $subtotal = Civi::format()->money($subtotal, NULL, $client['preferred_language']);

    $vars = [
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
      'SubTotal' => $subtotal //CRM_Utils_Money::format($subtotal),
    ];

    $TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8);
    $TBS->VarRef = &$vars;
    $TBS->MergeBlock('t', $lineitems);

    $compactdate = str_replace('-', '', substr($invoice['created_date'], 0, 10));
    $prefix = Civi::settings()->get('timetrack_invoice_filename_prefix');
    $alias = $this->getCaseAlias($invoice['case_id']);

    $output_file_name = $prefix . '_' . $invoice['ledger_bill_id'] . '_' . $compactdate . '_' . $client['contact_id'] . '_' . $invoice['case_id'] . ($alias ? '_' . $alias : '') . '.odt';
    $TBS->Show(OPENTBS_DOWNLOAD, $output_file_name);

    CRM_Utils_System::civiExit();
  }

  public function getClient($invoice_id) {
    $invoice_result = $this->getInvoice($invoice_id);

    $result = civicrm_api3('Contact', 'getsingle', [
      'contact_id' => $invoice_result['contact_id'],
    ]);

    return $result;
  }

  public function getInvoice($invoice_id) {
    static $invoice_result = [];

    if (!empty($invoice_result[$invoice_id])) {
      return $invoice_result[$invoice_id];
    }

    $result = civicrm_api3('Timetrackinvoice', 'getsingle', [
      'invoice_id' => $invoice_id,
    ]);

    $invoice_result[$invoice_id] = $result;
    return $result;
  }

  public function getCaseAlias($case_id) {
    $alias = CRM_Core_DAO::singleValueQuery('SELECT alias, estimate FROM kcontract WHERE case_id = %1', [
      1 => [$case_id, 'Positive'],
    ]);

    return $alias;
  }

  public function getLineItems($invoice_id, $locale) {
    $lineitems = [];

    $result = civicrm_api3('Timetrackinvoicelineitem', 'get', [
      'invoice_id' => $invoice_id,
    ]);

    foreach ($result['values'] as $i) {
      $amount = $i['cost'] * $i['hours_billed'];

      $lineitems[] = [
        'title' => $i['title'],
        'qty' => $i['hours_billed'], // FIXME rename to qty in DB.
        'cost' => $i['cost'],
        'unit' => $i['unit'],
        'amount_raw' => $amount,
        'amount' => Civi::format()->money($amount, NULL, $locale),
      ];
    }

    return $lineitems;
  }

  public function getLineItemsTotal($lineitems) {
    $total = 0;

    foreach ($lineitems as $i) {
      $total += $i['amount_raw'];
    }

    return $total;
  }

}
