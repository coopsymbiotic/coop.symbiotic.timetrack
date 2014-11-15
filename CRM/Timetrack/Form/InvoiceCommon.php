<?php

/**
 * @file
 *
 * Common buildForm and postProcess for:
 * - CRM_Timetrack_Form_Invoice : invoice for extra things, not related to punches.
 * - CRM_Timetrack_Form_Task_Invoice : invoice punches.
 */

class CRM_Timetrack_Form_InvoiceCommon {
  /**
   *
   */
  static function buildForm(&$form, $tasks) {
    $form->addElement('text', 'client_name', ts('Client'))->freeze();
    $form->addElement('text', 'title', ts('Invoice title'));
    $form->addElement('text', 'invoice_period_start', ts('From'));
    $form->addElement('text', 'invoice_period_end', ts('To'));
    $form->addDate('created_date', ts('Invoice date'), TRUE);
    $form->add('text', 'ledger_order_id', ts('Ledger order ID'), 'size="7"', FALSE);
    $form->add('text', 'ledger_bill_id', ts('Ledger invoice ID'), 'size="7"', TRUE);

    // NB: this should include the "extra" tasks.
    // i.e. the calling function should have added them to $tasks.
    foreach ($tasks as $key => $val) {
      $form->addElement('text', 'task_' . $key . '_title');
      $form->addElement('text', 'task_' . $key . '_hours')->freeze();
      $form->addElement('text', 'task_' . $key . '_hours_billed');
      $form->addElement('text', 'task_' . $key . '_unit');
      $form->addElement('text', 'task_' . $key . '_cost');
      $form->addElement('text', 'task_' . $key . '_amount');
    }

    $status = array_merge(array('' => ts('- select -')), CRM_Timetrack_PseudoConstant::getInvoiceStatuses());
    $form->add('select', 'state', ts('Status'), $status);
    $form->addDate('deposit_date', ts('Deposit date'));
    $form->add('text', 'deposit_reference', ts('Deposit reference'));
    $form->add('textarea', 'details_public', ts('Notes for the client'));
    $form->add('textarea', 'details_private', ts('Internal notes'));
  }

  /**
   *
   * Returns the order_id that was created/updated.
   */
  static function postProcess(&$form, $tasks) {
    $params = $form->exportValues();
    $total_hours_billed = 0;

    // If editing an existing invoice.
    $invoice_id = CRM_Utils_Array::value('invoiceid', $params);

    // This is mostly important for updating the line items.
    // because we don't know if the task ID is for a new lineitem, or update.
    $action = ($invoice_id ? 'update' : 'create');

    foreach ($tasks as $key => $val) {
      $total_hours_billed += $params['task_' . $key . '_hours_billed'];
    }

    for ($key = 0; $key < CRM_Timetrack_Form_Invoice::EXTRA_LINES; $key++) {
      $total_hours_billed += $params['task_extra' . $key . '_hours_billed'];
    }

    $params['deposit_date'] = date('Ymd', strtotime($params['deposit_date']));

    // NB: created_date can't be set manually becase it is a timestamp
    // and the DB layer explicitely ignores timestamps (there is a trigger
    // defined in timetrack.php).
    $result = civicrm_api3('Timetrackinvoice', 'create', array(
      'id' => $invoice_id,
      'case_id' => $case_id,
      'title' => $params['title'],
      'state' => 3, // FIXME, expose to UI, pseudoconstant, etc.
      'ledger_order_id' => $params['ledger_order_id'],
      'ledger_bill_id' => $params['ledger_bill_id'],
      'hours_billed' => $total_hours_billed,
      'deposit_date' => $params['deposit_date'],
      'deposit_reference' => $params['deposit_reference'],
      'details_public' => $params['details_public'],
      'details_private' => $params['details_private'],
    ));

    $order_id = $result['id'];

    $params['created_date'] = date('Ymd', strtotime($params['created_date']));

    CRM_Core_DAO::executeQuery('UPDATE korder SET created_date = %1 WHERE id = %2', array(
      1 => array($params['created_date'], 'Timestamp'),
      2 => array($order_id, 'Positive'),
    ));

    // Known tasks, extracted from the punches being billed.
    foreach ($tasks as $key => $val) {
      if ($params['task_' . $key . '_cost'] === '') {
        continue;
      }

      $result = civicrm_api3('Timetrackinvoicelineitem', 'create', array(
        'id' => ($action == 'create' ? NULL : $key),
        'order_id' => $order_id,
        'title' => $params['task_' . $key . '_title'],
        'hours_billed' => $params['task_' . $key . '_hours_billed'],
        'cost' => $params['task_' . $key . '_cost'],
        'unit' => $params['task_' . $key . '_unit'],
      ));

      $line_item_id = $result['id'];

      // Assign punches to line item / order.
      if (! empty($val['punches'])) {
        foreach ($val['punches'] as $pkey => $pval) {
          CRM_Core_DAO::executeQuery('UPDATE kpunch SET korder_id = %1, korder_line_id = %2 WHERE id = %3', array(
            1 => array($order_id, 'Positive'),
            2 => array($line_item_id, 'Positive'),
            3 => array($pval['pid'], 'Positive'),
          ));
        }
      }
    }

    // Extra tasks, no punches assigned.
/* FIXME: BEFORE REMOVING: test that Task can invoice 'extra' punches.
    for ($key = 0; $key < CRM_Timetrack_Form_Invoice::EXTRA_LINES; $key++) {
      // FIXME: not sure what to consider sufficient to charge an 'extra' line.
      // Assuming that if there is a 'cost' value, it's enough to charge.
      if ($params['task_extra' . $key . '_cost']) {
        $result = civicrm_api3('Timetrackinvoicelineitem', 'create', array(
          'order_id' => $order_id,
          'title' => $params['task_extra' . $key . '_title'],
          'hours_billed' => $params['task_extra' . $key . '_hours_billed'],
          'cost' => $params['task_extra' . $key . '_cost'],
          'unit' => $params['task_extra' . $key . '_unit'],
        ));
      }
    }
*/

    return $order_id;
  }
}
