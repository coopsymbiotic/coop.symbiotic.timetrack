<?php

/**
 * Class for timetrack task actions.
 *
 * Not to be confused with CRM_Timetrack_Form_Task, which is to edit a case task.
 */
class CRM_Timetrack_Form_SearchTask extends CRM_Core_Form {

  /**
   * The task being performed.
   *
   * @var int
   */
  protected $_task;

  /**
   * The additional clause that we restrict the search with.
   *
   * @var string
   */
  protected $_componentClause = NULL;

  /**
   * The array that holds all the component ids.
   *
   * @var array
   */
  protected $_componentIds;

  /**
   * The array that holds all the contact ids.
   *
   * @var array
   */
  public $_contactIds;

  /**
   * Build all the data structures needed to build the form.
   */
  public function preProcess() {
    self::preProcessCommon($this);
  }

  /**
   * Common pre-process function.
   *
   * @param CRM_Core_Form $form
   * @param bool $useTable
   */
  public static function preProcessCommon(&$form, $useTable = FALSE) {
    $ids = [];
    $values = $form->controller->exportValues($form->get('searchFormName'));
    $form->_task = $values['task'];

    if ($values['radio_ts'] == 'ts_sel') {
      foreach ($values as $name => $value) {
        if (substr($name, 0, CRM_Core_Form::CB_PREFIX_LEN) == CRM_Core_Form::CB_PREFIX) {
          $ids[] = substr($name, CRM_Core_Form::CB_PREFIX_LEN);
        }
      }
    }
    elseif ($values['radio_ts'] == 'ts_all') {
      // FIXME: this duplicates codes from CRM_Timetrack_Form_Search_TimetrackPunches
      // it might not really be necessary, if the 'contact' hack works?
      // but might be better to keep it (and refactor), so that we can avoid that hack.
      $clauses = [];
      $sql_params = [];

      if (!empty($values['start_date'])) {
        $start = CRM_Utils_Date::isoToMysql($values['start_date']);
        $clauses[] = 'kpunch.begin >= %1';
        $sql_params[1] = [$start, 'Timestamp'];
      }

      if (!empty($values['end_date'])) {
        $end = CRM_Utils_Date::isoToMysql($values['end_date'] . ' 23:59:59');
        $clauses[] = 'kpunch.begin <= %2';
        $sql_params[2] = [$end, 'Timestamp'];
      }

      if (isset($values['state']) && $values['state'] !== '') {
        if ($values['state'] == 0) {
          $clauses[] = 'korder.state is NULL';
        }
        else {
          $clauses[] = 'korder.state = %3';
          $sql_params[3] = [$values['state'], 'Positive'];
        }
      }

      if (!empty($values['ktask'])) {
        $clauses[] = 'kpunch.ktask_id = %4';
        $sql_params[4] = [$values['ktask'], 'Positive'];
      }

      if (!empty($values['case_id'])) {
        $clauses[] = 'civicrm_case.id = %5';
        $sql_params[5] = [$values['case_id'], 'Positive'];
      }

      $where = implode(' AND ', $clauses);

      // XXX: kpunch.id as contact_id is a hack because the tasks require it for the checkboxes.
      $select = "kpunch.id as pid, kpunch.id as contact_id, kpunch.contact_id, kpunch.begin, kpunch.duration,
                 kpunch.duration as duration_rounded, kpunch.comment, kpunch.korder_id as invoice_id,
                 korder.state as order_state,
                 kt.title as task,
                 civicrm_case.subject as case_subject, civicrm_case.id as case_id";

      $from = "kpunch
        LEFT JOIN civicrm_timetracktask as kt ON (kt.id = kpunch.ktask_id)
        LEFT JOIN kcontract ON (kcontract.case_id = kt.case_id)
        LEFT JOIN korder ON (korder.id = kpunch.korder_id)
        LEFT JOIN civicrm_case ON (civicrm_case.id = kt.case_id)";

      $sql = "SELECT $select FROM $from WHERE $where";
      $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);

      while ($dao->fetch()) {
        $ids[] = $dao->pid;
      }
    }

    $form->_componentIds = $ids;

    // Set the context for redirection for any task actions.
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $form);
    $urlParams = 'force=1';
    if (CRM_Utils_Rule::qfKey($qfKey)) {
      $urlParams .= "&qfKey=$qfKey";
    }

    $session = CRM_Core_Session::singleton();
    $searchFormName = strtolower($form->get('searchFormName'));
    if ($searchFormName == 'search') {
      $session->replaceUserContext(CRM_Utils_System::url('civicrm/activity/search', $urlParams));
    }
    else {
      $session->replaceUserContext(CRM_Utils_System::url("civicrm/contact/search/$searchFormName",
        $urlParams
      ));
    }
  }

  /**
   * Simple shell that derived classes can call to add buttons to
   * the form with a customized title for the main Submit
   *
   * @param string $title
   *   Title of the main button.
   * @param string $nextType
   *   Button type for the form after processing.
   * @param string $backType
   * @param bool $submitOnce
   */
  public function addDefaultButtons($title, $nextType = 'next', $backType = 'back', $submitOnce = FALSE) {
    $this->addButtons([
      [
        'type' => $nextType,
        'name' => $title,
        'isDefault' => TRUE,
      ],
      [
        'type' => $backType,
        'name' => ts('Cancel'),
      ],
    ]);
  }

}
