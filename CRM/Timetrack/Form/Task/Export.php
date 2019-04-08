<?php

use CRM_Timetrack_ExtensionUtil as E;

/**
 * This class provides the functionality to export punches.
 */
class CRM_Timetrack_Form_Task_Export extends CRM_Timetrack_Form_SearchTask {
  protected $defaults;
  protected $punchIds;

  /**
   * Build the form
   *
   * @access public
   *
   * @return void
   */
  public function buildQuickForm() {
    $this->addDefaultButtons(ts('Export'));
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $case_id = $this->getCaseID();
    $case_subject = CRM_Timetrack_Utils::getCaseSubject($case_id);

    $headers = [
      'id' => E::ts('ID'),
      'case_title' => E::ts('Project'),
      'task' => E::ts('Task'),
      'contact_id' => 'CID',
      'start_date' => 'Date',
      'duration' => 'Duration',
      'comment' => 'comment',
    ];

    $rows = [];

    $dao = CRM_Core_DAO::executeQuery('SELECT p.*, from_unixtime(p.begin) as start_date,
        t.title as task, s.subject as case_title
      FROM kpunch p
      LEFT JOIN ktask t ON (p.ktask_id = t.id)
      LEFT JOIN civicrm_case s ON (s.id = t.case_id)
      WHERE p.id IN (' . implode(',', $this->getPunchIds()) . ')');

    while ($dao->fetch()) {
      $dao->duration = sprintf('%.2f', CRM_Timetrack_Utils::roundUpSeconds($dao->duration));

      $rows[] = (array) $dao;
    }

    CRM_CiviExportExcel_Utils_SearchExport::export2excel2007($headers, $headers, $rows, [
      'file_prefix' => $case_subject,
    ]);
  }

  /**
   * Assuming the punches are all linked to a same case, we find the client name
   * from a random punch.
   */
  function getCaseID() {
    $pid = $this->_componentIds[0];

    $sql = "SELECT case_id
            FROM kpunch
            LEFT JOIN ktask kt ON (kt.id = kpunch.ktask_id)
            WHERE kpunch.id = %1";

    return CRM_Core_DAO::singleValueQuery($sql, array(
      1 => array($pid, 'Positive'),
    ));
  }

  function getPunchIds() {
    return $this->_componentIds;
  }
}
