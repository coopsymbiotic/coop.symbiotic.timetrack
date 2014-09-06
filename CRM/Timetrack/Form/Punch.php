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
    $sql = 'SELECT bc.entity_id
              FROM kpunch
              LEFT JOIN ktask on (ktask.nid = kpunch.nid)
              LEFT JOIN civicrm_value_infos_base_contrats_1 as bc on (bc.kproject_node_2 = ktask.parent)
             WHERE pid = %1';

    $params = array(
      1 => array($punch_id, 'Positive'),
    );

    $dao = CRM_Core_DAO::executeQuery($sql, $params);

    if ($dao->fetch()) {
      return $dao->entity_id;
    }

    return NULL;
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

    return $defaults;
  }

  function buildQuickForm() {
    $projects = CRM_Timetrack_Utils::getOpenCases();
    $tasks = CRM_Timetrack_Utils::getActivitiesForCase($this->_cid);
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

    // TODO: the activity should probably be an auto-complete including the Case+Activity
    // because currently this does not allow us to change the Case associated with a punch.
    $this->add('select', 'case_id', ts('Case'), $projects);

    $this->add('select', 'activity_id', ts('Activity'), $tasks);
    $this->add('select', 'contact_id', ts('Contact'), $users);

    // TODO: using textfield for now, since we're using timestamps in the DB.
    $this->add('text', 'begin', ts('Start'));
    $this->add('text', 'end', ts('End'));
    $this->add('text', 'duration', ts('Duration'));
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
      $dao = CRM_Core_DAO::executeQuery('UPDATE kpunch SET begin = %1, duration = %2, comment = %3, nid = %4, uid = %5 WHERE pid = %6', array(
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
      $dao = CRM_Core_DAO::executeQuery('INSERT INTO kpunch (begin, duration, comment, nid, uid) VALUES (%1, %2, %3, %4, %5)', array(
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
