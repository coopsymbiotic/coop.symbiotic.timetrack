<?php

/**
 * @file
 *
 * Common buildForm and postProcess for:
 * - CRM_Timetrack_Form_Invoice : invoice for extra things, not related to punches.
 * - CRM_Timetrack_Form_Task_Invoice : invoice punches.
 */

trait CRM_Timetrack_Form_InvoiceCommonTrait {

  /**
   * @param CRM_Core_Form $form
   * @param array $tasks
   * @param array $options
   */
  public static function buildFormCommon(&$form, $tasks, $options = []) {
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
  public static function postProcessCommon(&$form, $case_id, $tasks) {
    $total_hours_billed = 0;
    $params = $form->exportValues();

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
    $total_amount = 0;

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
      $total_amount += $params['task_' . $key . '_hours_billed'] * $params['task_' . $key . '_cost'];

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

    // Extra tasks, no punches assigned.
    for ($key = 0; $key < CRM_Timetrack_Form_Invoice::EXTRA_LINES; $key++) {
      // FIXME: not sure what to consider sufficient to charge an 'extra' line.
      // Assuming that if there is a 'cost' value, it's enough to charge.
      if ($params['task_extra' . $key . '_cost']) {
        $result = civicrm_api3('Timetrackinvoicelineitem', 'create', [
          'order_id' => $order_id,
          'title' => $params['task_extra' . $key . '_title'],
          'hours_billed' => $params['task_extra' . $key . '_hours_billed'],
          'cost' => $params['task_extra' . $key . '_cost'] ?? 0,
          'unit' => $params['task_extra' . $key . '_unit'],
        ]);
      }
    }

    // BETA: Create a contribution and LineItems
    // Check if the contribution already exists. @todo Support updates (sync changes).
/*
    $exists = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('source', '=', 'timetrack=' . $order_id)
      ->execute()
      ->first();

    if (!empty($exists['id'])) {
      return $order_id;
    }

    $contact_id = CRM_Timetrack_Utils::getCaseContact($case_id);

    // @todo
    // - price_field_id / price_field_value_id : currently set semi-random. Why won't they display on ContributionView?
    // - when generating the invoice PDF, it displays the PriceFieldValue label, not the line item label. How does lineitemeditor do it?
    // - update the invoice_id using QuickBooks? sync?
    // - taxes? from QB? or taxcalculator?
    $contribution = \Civi\Api4\Contribution::create(FALSE)
      ->addValue('contact_id', $contact_id)
      ->addValue('financial_type_id', 7) // @todo Consultation
      ->addValue('receive_date', date('Y-m-d H:i:s', strtotime($params['created_date'])))
      ->addValue('total_amount', $total_amount)
      ->addValue('source', 'timetrack=' . $order_id)
      ->addValue('contribution_status_id', 2) // Pending
      ->addValue('skipLineItem', 1) // surprisingly, it works
      ->execute()
      ->first();

    foreach ($tasks as $key => $val) {
      if ($params['task_' . $key . '_cost'] === '') {
        continue;
      }

      civicrm_api3('LineItem', 'create', [
        // 'id' => ($action == 'create' ? NULL : $key),
        'contribution_id' => $contribution['id'],
        'entity_table' => 'civicrm_contribution',
        'entity_id' => $contribution['id'],
        'price_field_id' => 4, // @todo hmm (random "other amount" field; create new PriceField?)
        'price_field_value_id' => 6, // @todo
        'label' => $params['task_' . $key . '_title'],
        'qty' => $params['task_' . $key . '_hours_billed'],
        'unit_price' => $params['task_' . $key . '_cost'],
        'line_total' => $params['task_' . $key . '_hours_billed'] * $params['task_' . $key . '_cost'],
        'financial_type_id' => 7, // @todo Consultation
      ]);
    }

    // Extra tasks, no punches assigned.
    for ($key = 0; $key < CRM_Timetrack_Form_Invoice::EXTRA_LINES; $key++) {
      // FIXME: not sure what to consider sufficient to charge an 'extra' line.
      // Assuming that if there is a 'cost' value, it's enough to charge.
      if (!empty($params['task_extra_' . $key . '_cost'])) {
        civicrm_api3('LineItem', 'create', [
          // 'id' => ($action == 'create' ? NULL : $key), // @todo (if we want to sync changes)
          'contribution_id' => $contribution['id'],
          'entity_table' => 'civicrm_contribution',
          'entity_id' => $contribution['id'],
          'price_field_id' => 1, // @todo hmm
          'price_field_value_id' => 1, // @todo
          'label' => $params['task_extra_' . $key . '_title'],
          'qty' => $params['task_extra_' . $key . '_hours_billed'],
          'unit_price' => $params['task_extra_' . $key . '_cost'],
          'line_total' => $params['task_extra_' . $key . '_hours_billed'] * $params['task_extra_' . $key . '_cost'],
          'financial_type_id' => 7, // @todo Consultation
        ]);
      }
    }
*/

    return $order_id;
  }

}
