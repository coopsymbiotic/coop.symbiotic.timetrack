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

    $lineitems = $this->getLineItems($invoice_id);
    $subtotal = $this->getLineItemsTotal($lineitems);

    // Check if we have a template for the pref language of the client.
    $lang_code = strtoupper(substr($client['preferred_language'], 0, 2));
    $template = Civi::settings()->get('TimetrackInvoiceTemplate' . $lang_code);

    if (empty($template)) {
      $template = Civi::settings()->get('TimetrackInvoiceTemplateDefault');
    } else {
      // Reformat numbers according to the template's language
      $subtotal = $this->formatNumber($subtotal, $lang_code);
      $lineitems = $this->formatNumbers($lineitems, $lang_code);
    }

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

  public function getLineItems($invoice_id) {
    $lineitems = [];

    $result = civicrm_api3('Timetrackinvoicelineitem', 'get', [
      'invoice_id' => $invoice_id,
    ]);

    foreach ($result['values'] as $i) {
      $lineitems[] = [
        'title' => $i['title'],
        'qty' => $i['hours_billed'], // FIXME rename to qty in DB.
        'cost' => $i['cost'],
        'unit' => $i['unit'],
        'amount' => $i['cost'] * $i['hours_billed'], // template will take care of formatting...
        //'amount' => CRM_Utils_Money::format($i['cost'] * $i['hours_billed']), // FIXME rename
      ];
    }

    return $lineitems;
  }

  public function getLineItemsTotal($lineitems) {
    $total = 0;

    foreach ($lineitems as $i) {
      $total += $i['amount'];
    }

    return $total;
  }

  public function formatNumber($number, $lang_code='EN') {

    $format = CRM_Utils_Array::value($lang_code, [
      'FR' => [2, ',', ''],
    ]);

    if ($format) {
      list($decimals, $decimals_sep, $thousands_sep) = $format;
      $number = number_format($number, $decimals, $decimals_sep, $thousands_sep);
    }

    return $number;
  }

  public function formatNumbers($array, $lang_code='EN') {

    $formatted = $array;
    foreach ($array as $key => $item) {
      if (is_array($item)) {
        $formatted[$key] = $this->formatNumbers($item, $lang_code);
      }
      elseif (is_numeric($item)) {
        $formatted[$key] = $this->formatNumber($item, $lang_code);
      }
    }

    return $formatted;
  }

}
