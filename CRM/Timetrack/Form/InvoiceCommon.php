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
   * @param CRM_Core_Form $form
   * @param array $tasks
   * @param array $options
   */
  public static function buildForm(&$form, $tasks, $options = []) {
    $form->addEntityRef('invoice_from_id', ts('Invoice from'), [
      'create' => FALSE,
      'api' => ['extra' => ['email']],
    ], TRUE);

    $form->addElement('text', 'client_name', ts('Client'))->freeze();
    $form->addElement('text', 'title', ts('Invoice title'));
    $form->addElement('text', 'invoice_period_start', ts('From'));
    $form->addElement('text', 'invoice_period_end', ts('To'));
    $form->add('datepicker', 'created_date', ts('Invoice date'), [], TRUE, ['time' => FALSE]);
    $form->add('text', 'ledger_order_id', ts('Ledger order ID'), 'size="7"', FALSE);
    $form->add('text', 'ledger_bill_id', ts('Ledger invoice ID'), 'size="7"', TRUE);

    // NB: this should already include the "extra" tasks.
    // i.e. the calling function should have added them to $tasks.
    foreach ($tasks as $key => $val) {
      $form->add('text', 'task_' . $key . '_title', ts('Task %1 title', [1 => $key]), 'size="35"');

      if (empty($options['invoice_other_only'])) {
        $form->addElement('text', 'task_' . $key . '_hours')->freeze();
      }

      $form->add('text', 'task_' . $key . '_hours_billed', ts('Task %1 hours billed', [1 => $key]), 'size="6" style="text-align: right"');
      $form->add('text', 'task_' . $key . '_unit', ts('Task %1 unit', [1 => $key]), 'size="9"');
      $form->add('text', 'task_' . $key . '_cost', ts('Task %1 cost per unit', [1 => $key]), 'size="6" style="text-align: right"');
      $form->add('text', 'task_' . $key . '_amount', ts('Task %1 line total', [1 => $key]), 'size="9" style="text-align: right"');
    }

    $status = CRM_Timetrack_PseudoConstant::getInvoiceStatuses();
    $form->add('select', 'state', ts('Status'), $status);
    $form->add('datepicker', 'deposit_date', ts('Deposit date'), [], FALSE, ['time' => FALSE]);
    $form->add('text', 'deposit_reference', ts('Deposit reference'));
    $form->add('textarea', 'details_public', ts('Notes for the client'));
    $form->add('textarea', 'details_private', ts('Internal notes'));

    $form->assign('timetrack_invoice_options', $options);
  }

  /**
   *
   * Returns the order_id that was created/updated.
   */
  public static function postProcess(&$form, $case_id, $tasks) {
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
      if (isset($params['task_extra' . $key . '_hours_billed'])) {
        $total_hours_billed += $params['task_extra' . $key . '_hours_billed'];
      }
    }

    // NB: created_date can't be set manually becase it is a timestamp
    // and the DB layer explicitely ignores timestamps (there is a trigger
    // defined in timetrack.php).
    $apiparams = [
      'title' => $params['title'],
      'state' => $params['state'],
      'invoice_from_id' => $params['invoice_from_id'],
      'ledger_order_id' => $params['ledger_order_id'],
      'ledger_bill_id' => $params['ledger_bill_id'],
      'hours_billed' => $total_hours_billed,
    ];

    if ($case_id) {
      $apiparams['case_id'] = $case_id;
    }

    if ($invoice_id) {
      $apiparams['id'] = $invoice_id;
    }

    if ($params['deposit_date']) {
      $params['deposit_date'] = date('Ymd', strtotime($params['deposit_date']));
      $apiparams['deposit_date'] = $params['deposit_date'];
    }

    if ($params['deposit_reference']) {
      $apiparams['deposit_reference'] = $params['deposit_reference'];
    }

    if ($params['details_public']) {
      $apiparams['details_public'] = $params['details_public'];
    }

    if ($params['details_private']) {
      $apiparams['details_private'] = $params['details_private'];
    }

    $result = civicrm_api3('Timetrackinvoice', 'create', $apiparams);

    $order_id = $result['id'];

    $params['created_date'] = date('Ymd', strtotime($params['created_date']));

    CRM_Core_DAO::executeQuery('UPDATE korder SET created_date = %1 WHERE id = %2', [
      1 => [$params['created_date'], 'Timestamp'],
      2 => [$order_id, 'Positive'],
    ]);

    // Known tasks, extracted from the punches being billed.
    foreach ($tasks as $key => $val) {
      if ($params['task_' . $key . '_cost'] === '') {
        continue;
      }

      $result = civicrm_api3('Timetrackinvoicelineitem', 'create', [
        'id' => ($action == 'create' ? NULL : $key),
        'order_id' => $order_id,
        'title' => $params['task_' . $key . '_title'],
        'hours_billed' => $params['task_' . $key . '_hours_billed'],
        'cost' => $params['task_' . $key . '_cost'],
        'unit' => $params['task_' . $key . '_unit'],
      ]);

      $line_item_id = $result['id'];

      // Assign punches to line item / order.
      if (!empty($val['punches'])) {
        foreach ($val['punches'] as $pkey => $pval) {
          CRM_Core_DAO::executeQuery('UPDATE kpunch SET korder_id = %1, korder_line_id = %2 WHERE id = %3', [
            1 => [$order_id, 'Positive'],
            2 => [$line_item_id, 'Positive'],
            3 => [$pval['pid'], 'Positive'],
          ]);
        }
      }
    }

    return $order_id;
  }

}
