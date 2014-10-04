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

  $sqlparams = array(
    1 => array('korder', 'String'),
  );

  $sql = 'SELECT n.nid as invoice_id, n.title as subject, c.id as case_id, c.subject as case_subject,
                 ko.state, ko.ledger_order_id, ko.ledger_bill_id, ko.hours_billed, ko.paid
            FROM node as n
           INNER JOIN korder as ko on (ko.nid = n.nid)
           INNER JOIN civicrm_value_infos_base_contrats_1 as cv on (cv.kproject_node_2 = ko.node_reference)
           INNER JOIN civicrm_case as c on (c.id = cv.entity_id)
           WHERE type = %1';

  if ($case_id = CRM_Utils_Array::value('case_id', $params)) {
    $sql .= ' AND c.id = %2';
    $sqlparams[2] = array($case_id, 'Positive');
  }

  if ($subject = CRM_Utils_Array::value('subject', $params)) {
    $subject = CRM_Utils_Type::escape($subject, 'String');
    $sql .= " AND (c.subject LIKE '{$subject}%' OR tn.title LIKE '{$subject}%')";
  }

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $invoice = array(
      'subject' => $dao->subject,
      'case_subject' => $dao->case_subject,
      'invoice_id' => $dao->invoice_id,
      'case_id' => $dao->case_id,
      'state' => $dao->state,
      'paid' => $dao->paid,
      'hours_billed' => $dao->hours_billed,
      'ledger_order_id' => $dao->ledger_order_id, // TODO: deprecate. not used.
      'ledger_bill_id' => $dao->ledger_bill_id,
    );

    // Calculate the time of included punches
    $dao2 = CRM_Core_DAO::executeQuery('SELECT sum(duration) as total FROM kpunch WHERE order_reference = %1', array(
      1 => array($dao->invoice_id, 'Positive'),
    ));

    if ($dao2->fetch()) {
      $invoice['total_included'] = $dao2->total;
    }

    $invoices[] = $invoice;
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
 * Adjust Metadata for Get action
 *
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_timetrackinvoice_get_spec(&$params) {
  $params['invoice_is_deleted']['api.default'] = 0;

  $params['title']['title'] = 'Invoice title';
  $params['case_id']['case'] = 'Invoice case/project/contract ID';
}
