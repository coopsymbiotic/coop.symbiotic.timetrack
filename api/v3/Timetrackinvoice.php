<?php

/**
 * Retrieve one or more timetrackinvoice, given a set of search params
 * Implements Timetrackinvoice.get
 *
 * @param  array  input parameters
 *
 * @return array API Result Array
 * (@getfields timetrackinvoices_get}
 * @static void
 * @access public
 */
function civicrm_api3_timetrackinvoice_get($params) {
  $options = array();
  $invoices = array();

  $sqlparams = array();

  // XXX: assuming cases only have 1 client contact.
  $sql = 'SELECT ko.id as invoice_id, ko.title, c.id as case_id, c.subject as case_subject,
                 ko.state, ko.ledger_order_id, ko.ledger_bill_id, ko.hours_billed, ko.paid, ko.created_date,
                 ccont.contact_id
            FROM korder as ko
           INNER JOIN civicrm_case as c on (c.id = ko.case_id)
           LEFT JOIN civicrm_case_contact as ccont on (ccont.case_id = c.id)
           WHERE 1=1 ';

  if ($invoice_id = CRM_Utils_Array::value('invoice_id', $params)) {
    $sql .= ' AND ko.id = %1';
    $sqlparams[1] = array($invoice_id, 'Positive');
  }
  elseif ($invoice_id = CRM_Utils_Array::value('id', $params)) {
    $sql .= ' AND ko.id = %1';
    $sqlparams[1] = array($invoice_id, 'Positive');
  }

  if ($case_id = CRM_Utils_Array::value('case_id', $params)) {
    $sql .= ' AND c.id = %2';
    $sqlparams[2] = array($case_id, 'Positive');
  }

  if ($title = CRM_Utils_Array::value('title', $params)) {
    $title = CRM_Utils_Type::escape($title, 'String');
    $sql .= " AND (c.subject LIKE '{$title}%' OR ko.title LIKE '{$title}%')";
  }

  // FIXME: should respect API options groupby
  $sql .= ' ORDER BY created_date DESC';

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $invoice = array(
      'id' => $dao->id,
      'invoice_id' => $dao->id,
      'created_date' => $dao->created_date,
      'title' => $dao->title,
      'case_subject' => $dao->case_subject,
      'invoice_id' => $dao->invoice_id,
      'case_id' => $dao->case_id,
      'contact_id' => $dao->contact_id,
      'state' => $dao->state,
      'paid' => $dao->paid,
      'hours_billed' => $dao->hours_billed,
      'ledger_order_id' => $dao->ledger_order_id, // TODO: deprecate. not used.
      'ledger_bill_id' => $dao->ledger_bill_id,
    );

    // Calculate the time of included punches
    $dao2 = CRM_Core_DAO::executeQuery('SELECT sum(duration) as total FROM kpunch WHERE korder_id = %1', array(
      1 => array($dao->invoice_id, 'Positive'),
    ));

    if ($dao2->fetch()) {
      $invoice['total_included'] = $dao2->total;
    }

    $invoices[$dao->id] = $invoice;
  }

  return civicrm_api3_create_success($invoices, $params, 'timetrackinvoice');
}

/**
 * Retrieve the count of invoices matching the params.
 * Implements Timetrackinvoice.getcount
 */
function civicrm_api3_timetrackinvoice_getcount($params) {
  $invoices = civicrm_api3_timetrackinvoice_get($params);
  return count($invoices['values']);
}

/**
 * Create a new invoice.
 */
function civicrm_api3_timetrackinvoice_create($params) {
  $invoice = new CRM_Timetrack_DAO_Invoice();

  $invoice->copyValues($params);
  $invoice->save();

  if (is_null($invoice)) {
    return civicrm_api3_create_error('Entity not created (Timetrackinvoice create)');
  }

  $values = array();
  _civicrm_api3_object_to_array($invoice, $values[$invoice->id]);
  return civicrm_api3_create_success($values, $params, NULL, 'create', $invoice);
}

/**
 * Adjust Metadata for Get action
 *
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_timetrackinvoice_get_spec(&$params) {
  $params['invoice_is_deleted']['api.default'] = 0;

  $params['title']['title'] = 'Invoice title';
  $params['case_id']['case'] = 'Invoice case/project/contract ID';
  $params['created_date']['created_date'] = 'Invoice creation date';
}
