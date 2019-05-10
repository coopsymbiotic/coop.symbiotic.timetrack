<?php

/**
 * Retrieve one or more timetrackinvoicelineitem, given a set of search params
 * Implements Timetrackinvoicelineitem.get
 *
 * @param array $params
 *
 * @return array API Result Array
 * (@getfields timetrackinvoicelineitem_get}
 */
function civicrm_api3_timetrackinvoicelineitem_get($params) {
  $options = [];
  $lineitems = [];

  $sqlparams = [];

  $sql = 'SELECT *
            FROM korder_line
           WHERE 1=1';

  // TODO: we should deprecate references to "order", and use only "invoice".
  // except that the 'create' API kind of depends on it, until we rename the DAO..
  if ($order_id = CRM_Utils_Array::value('order_id', $params)) {
    $sql .= ' AND order_id = %1';
    $sqlparams[1] = [$order_id, 'Positive'];
  }

  if ($invoice_id = CRM_Utils_Array::value('invoice_id', $params)) {
    $sql .= ' AND order_id = %1';
    $sqlparams[1] = [$invoice_id, 'Positive'];
  }

  if ($order_line_id = CRM_Utils_Array::value('order_line_id', $params)) {
    $sql .= ' AND id = %2';
    $sqlparams[2] = [$order_line_id, 'Positive'];
  }

  if ($invoice_line_id = CRM_Utils_Array::value('invoice_line_id', $params)) {
    $sql .= ' AND id = %2';
    $sqlparams[2] = [$invoice_line_id, 'Positive'];
  }

  $dao = CRM_Core_DAO::executeQuery($sql, $sqlparams);

  while ($dao->fetch()) {
    $line = [
      'order_line_id' => $dao->id,
      'order_id' => $dao->order_id,
      'title' => $dao->title,
      'invoice_id' => (isset($dao->invoice_id) ? $dao->invoice_id : NULL), // not used?
      'hours_billed' => $dao->hours_billed,
      'unit' => $dao->unit,
      'cost' => $dao->cost,
    ];

    // Calculate the time of included punches
    $dao2 = CRM_Core_DAO::executeQuery('SELECT sum(duration) as total FROM kpunch WHERE korder_line_id = %1', [
      1 => [$dao->id, 'Positive'],
    ]);

    if ($dao2->fetch()) {
      $line['total_included'] = $dao2->total;
    }

    $lineitems[$dao->id] = $line;
  }

  return civicrm_api3_create_success($lineitems, $params, 'timetrackinvoicelineitem');
}

/**
 * Retrieve the count of invoicelineitems matching the params.
 * Implements Timetrackinvoicelineitem.getcount
 */
function civicrm_api3_timetrackinvoicelineitem_getcount($params) {
  $items = civicrm_api3_timetrackinvoicelineitem_get($params);
  return count($items['values']);
}

/**
 * Create a new invoice line item.
 */
function civicrm_api3_timetrackinvoicelineitem_create($params) {
  $item = new CRM_Timetrack_DAO_InvoiceLineitem();

  $item->copyValues($params);
  $item->save();

  if (is_null($item)) {
    return civicrm_api3_create_error('Entity not created (Timetrackinvoicelineitem create)');
  }

  $values = [];
  _civicrm_api3_object_to_array($item, $values[$item->id]);
  return civicrm_api3_create_success($values, $params, NULL, 'create', $item);
}

/**
 * Adjust Metadata for Get action
 * FIXME: not sure if this is OK.
 *
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_timetrackinvoicelineitem_get_spec(&$params) {
  $params['title']['title'] = 'Invoice Line item title';
}
