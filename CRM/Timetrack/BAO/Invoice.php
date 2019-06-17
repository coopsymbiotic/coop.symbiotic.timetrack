<?php

class CRM_Timetrack_BAO_Invoice {
  /**
   * This function is called by the CiviCRM API when doing a
   * Timetrackinvoice.getoptions
   */
  public static function buildOptions($fieldName, $context, $params) {
    if ($fieldName == 'state') {
      return CRM_Timetrack_PseudoConstant::getInvoiceStatuses();
    }

    return NULL;
  }

}
