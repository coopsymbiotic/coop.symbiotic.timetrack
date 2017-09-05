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
    $ids = array();
    $values = $form->controller->exportValues($form->get('searchFormName'));
    $form->_task = $values['task'];

    if ($values['radio_ts'] == 'ts_sel') {
      foreach ($values as $name => $value) {
        if (substr($name, 0, CRM_Core_Form::CB_PREFIX_LEN) == CRM_Core_Form::CB_PREFIX) {
          $ids[] = substr($name, CRM_Core_Form::CB_PREFIX_LEN);
        }
      }
    }
    elseif ($value['radio_ts'] == 'ts_all') {
      // FIXME: this duplicates codes from CRM_Timetrack_Form_Search_TimetrackPunches
      // it might not really be necessary, if the 'contact' hack works?
      // but might be better to keep it (and refactor), so that we can avoid that hack.
      $clauses = array();

      if (! empty($values['start_date'])) {
        // Convert to unix timestamp (FIXME)
        $start = $values['start_date'];
        $start = strtotime($start);

        $clauses[] = 'kpunch.begin >= ' . $start;
      }

      if (! empty($values['end_date'])) {
        // Convert to unix timestamp (FIXME)
        $end = $values['end_date'] . ' 23:59:59';
        $end = strtotime($end);

        $clauses[] = 'kpunch.begin <= ' . $end;
      }

      if (isset($values['state']) && $values['state'] !== '') {
        if ($values['state'] == 0) {
          $clauses[] = 'korder.state is NULL';
        }
        else {
          $clauses[] = 'korder.state = ' . intval($values['state']);
        }
      }

      if (! empty($values['ktask'])) {
        $clauses[] = 'kpunch.ktask_id = ' . CRM_Utils_Type::escape($values['ktask'], 'Positive');
      }

      if (! empty($values['case_id'])) {
        $clauses[] = 'civicrm_case.id = ' . intval($values['case_id']);
      }

      $where = implode(' AND ', $clauses);

      // XXX: kpunch.id as contact_id is a hack because the tasks require it for the checkboxes.
      $select = "kpunch.id as pid, kpunch.id as contact_id, kpunch.contact_id, from_unixtime(kpunch.begin) as begin, kpunch.duration,
                 kpunch.duration as duration_rounded, kpunch.comment, kpunch.korder_id as invoice_id,
                 korder.state as order_state,
                 kt.title as task,
                 civicrm_case.subject as case_subject, civicrm_case.id as case_id";

      $from = "kpunch
        LEFT JOIN ktask kt ON (kt.id = kpunch.ktask_id)
        LEFT JOIN kcontract ON (kcontract.case_id = kt.case_id)
        LEFT JOIN korder ON (korder.id = kpunch.korder_id)
        LEFT JOIN civicrm_case ON (civicrm_case.id = kt.case_id)";

      $sql = "SELECT $select FROM $from WHERE $where";

      $dao = CRM_Core_DAO::executeQuery($sql);

      while ($dao->fetch()) {
        $ids[] = $dao->pid;
      }
    }

    if (!empty($ids)) {
#      $form->_componentClause = ' civicrm_activity.id IN ( ' . implode(',', $ids) . ' ) ';
#      $form->assign('totalSelectedActivities', count($ids));
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
    $this->addButtons(array(
      array(
        'type' => $nextType,
        'name' => $title,
        'isDefault' => TRUE,
      ),
      array(
        'type' => $backType,
        'name' => ts('Cancel'),
      ),
    ));
  }

}
