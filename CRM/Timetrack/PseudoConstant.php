<?php

class CRM_Timetrack_PseudoConstant extends CRM_Core_PseudoConstant {
  const INVOICE_DRAFT = 1;
  const INVOICE_SENT = 3;
  const INVOICE_PAID = 4;
  const INVOICE_LOST = 5;
  const INVOICE_LEGACY = 6;

  const TASK_NEW = 5; // kproject legacy
  const TASK_OPEN = 1;
  const TASK_STALLED = 2;
  const TASK_COMPLETED = 3;
  const TASK_CANCELLED = 4;

  /**
   * Returns a list of statuses for punch invoices.
   * TODO: should be option values.
   */
  static function getInvoiceStatuses() {
    return array(
      // 0 => ts('New'), // FIXME : this is weird! (kproject had 'new')
      self::INVOICE_DRAFT => ts('Draft', array('domain' => 'coop.symbiotic.timetrack')),
      // 2 => ts('Ordered', array('domain' => 'coop.symbiotic.timetrack')),
      self::INVOICE_SENT => ts('Sent / Pending payment', array('domain' => 'coop.symbiotic.timetrack')),
      self::INVOICE_PAID => ts('Paid', array('domain' => 'coop.symbiotic.timetrack')),
      self::INVOICE_LOST => ts('Lost', array('domain' => 'coop.symbiotic.timetrack')),
      self::INVOICE_LEGACY => ts('Legacy', array('domain' => 'coop.symbiotic.timetrack')),
    );
  }

  /**
   * Returns a list of statuses for tasks.
   * TODO: should be option values.
   */
  static function getTaskStatuses() {
    return array(
      self::TASK_NEW => ts('New'), // FIXME: kproject legacy..
      self::TASK_OPEN => ts('Opened / In progress'),
      self::TASK_STALLED => ts('Stalled'),
      self::TASK_COMPLETED => ts('Completed / Resolved'),
      self::TASK_CANCELLED => ts('Cancelled / Rejected'),
    );
  }
}
