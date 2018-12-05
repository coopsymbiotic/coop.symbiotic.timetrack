<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Timetrack_Form_Punch extends CRM_Core_Form {
  public $_cid;
  public $_pid;

  /**
   * Returns the case ID associated with a punch. Useful when editing a punch.
   */
  function getCaseIdFromPunchId($punch_id) {
    $sql = 'SELECT ktask.case_id
              FROM kpunch
              LEFT JOIN ktask on (ktask.nid = kpunch.nid)
             WHERE kpunch.id = %1';

    $params = array(
      1 => array($punch_id, 'Positive'),
    );

    return CRM_Core_DAO::singleValueQuery($sql, $params);
  }

  function preProcess() {
    $this->_cid = CRM_Utils_Request::retrieve('cid', 'Integer', $this, FALSE, NULL);
    $this->_pid = CRM_Utils_Request::retrieve('pid', 'Integer', $this, FALSE, NULL);

    if (! $this->_cid) {
      $this->_cid = $this->getCaseIdFromPunchId($this->_pid);
    }

    if (! $this->_cid) {
      CRM_Core_Error::fatal(ts('Could not find the case ID or the punch ID from the request arguments.'));
    }

    parent::preProcess();
  }

  function setDefaultValues() {
    $defaults = array();

    if ($this->_pid) {
      $result = civicrm_api3('Timetrackpunch', 'getsingle', array('id' => $this->_pid));
      $defaults = array_merge($defaults, $result);

      // TODO: mysql timestamps vs date..
      if (! empty($defaults['begin']) && ! empty($defaults['duration'])) {
        $defaults['end'] = date('Y-m-d H:i:s', $defaults['begin'] + $defaults['duration']);
      }

      if (! empty($defaults['begin'])) {
        $defaults['begin'] = date('Y-m-d H:i:s', $defaults['begin']);
      }

      if (! empty($defaults['duration'])) {
        $defaults['duration'] = round($defaults['duration'] / 60 / 60, 2);
      }
    }
    else {
      // Default begin time to now.
      $defaults['begin'] = date('Y-m-d H:i:s');

      // Default to current user.
      $session = CRM_Core_Session::singleton();
      $defaults['contact_id'] = $session->get('userID');
    }

    return $defaults;
  }

  function buildQuickForm() {
    // If new punch from Case, limit tasks to that case, otherwise show all tasks when editing.
    $limit_case = ($this->_pid ? 0 : $this->_cid);
    $tasks = CRM_Timetrack_Utils::getActivitiesForCase($limit_case);

    // @todo: convert users field to EntityRef; requires https://github.com/civicrm/civicrm-core/pull/13230
    $users = CRM_Timetrack_Utils::getUsers();
    $case_title = CRM_Timetrack_Utils::getCaseSubject($this->_cid);

    if ($this->_pid) {
      CRM_Utils_System::setTitle(ts('Edit punch in %1', array(1 => $case_title)));
    }
    else {
      CRM_Utils_System::setTitle(ts('New punch in %1', array(1 => $case_title)));
    }

    $this->add('hidden', 'cid', $this->_cid);
    $this->add('hidden', 'pid', $this->_pid);

    $this->add('select', 'activity_id', ts('Activity'), $tasks, TRUE, ['class' => 'crm-select2']);
    $this->add('select', 'contact_id', ts('Contact'), $users, TRUE, ['class' => 'crm-select2']);

    $this->add('datepicker', 'begin', ts('Start'), [], TRUE);
    $this->add('datepicker', 'end', ts('End'));
    $this->add('text', 'duration', ts('Duration'), [], TRUE);
    $this->add('text', 'comment', ts('Comment'));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => FALSE,
      ),
      array(
        'type' => 'next',
        'name' => ts('Save and New'),
        'isDefault' => TRUE,
      ),
    ));

    // Export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();
    $buttonName = $this->controller->getButtonName();

    // TODO save values, convert mysql date to timestamps..
    // TODO move out to API Timetrackpunch.create

    $begin = strtotime($values['begin']);
    $duration = $values['duration'] * 60 * 60;

    if ($this->_pid) {
      $dao = CRM_Core_DAO::executeQuery('UPDATE kpunch SET begin = %1, duration = %2, comment = %3, ktask_id = %4, contact_id = %5 WHERE id = %6', array(
        1 => array($begin, 'Positive'), // FIXME date mysql
        2 => array($duration, 'Integer'),
        3 => array($values['comment'], 'String'),
        4 => array($values['activity_id'], 'Positive'),
        5 => array($values['contact_id'], 'Positive'),
        6 => array($this->_pid, 'Positive'),
      ));
      CRM_Core_Session::setStatus(ts('The punch has been updated.'), '', 'success');
    }
    else {
      $dao = CRM_Core_DAO::executeQuery('INSERT INTO kpunch (begin, duration, comment, ktask_id, contact_id) VALUES (%1, %2, %3, %4, %5)', array(
        1 => array($begin, 'Positive'), // FIXME date mysql
        2 => array($duration, 'Integer'),
        3 => array($values['comment'], 'String'),
        4 => array($values['activity_id'], 'Positive'),
        5 => array($values['contact_id'], 'Positive'),
      ));
      CRM_Core_Session::setStatus(ts('The punch has been saved.'), '', 'success');
    }

    if ($buttonName == $this->getButtonName('next')) {
      CRM_Core_Session::setStatus(ts('You can add another punch.'), '', 'info');
      $session = CRM_Core_Session::singleton();
      $session->replaceUserContext(
        CRM_Utils_System::url(
          'civicrm/timetrack/punch',
          'reset=1&action=add&cid=' . $this->_cid
        )
      );
    }
    else {
      // FIXME? This kind of redirects randomly..
      $session = CRM_Core_Session::singleton();
      CRM_Utils_System::redirect($session->popUserContext());
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
