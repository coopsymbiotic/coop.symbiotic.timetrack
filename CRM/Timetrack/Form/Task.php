<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Timetrack_Form_Task extends CRM_Core_Form {
  public $_caseid;
  public $_taskid;

  function preProcess() {
    $this->_caseid = CRM_Utils_Request::retrieve('cid', 'Integer', $this, FALSE, NULL);
    $this->_taskid = CRM_Utils_Request::retrieve('tid', 'Integer', $this, FALSE, NULL);

    if ($this->_taskid) {
      // Editing an existing task. Fetch the task data for setDefaultValues() later.
      $this->_taskdata = civicrm_api3('Timetracktask', 'getsingle', array(
        'task_id' => $this->_taskid,
      ));

      $this->_caseid = $this->_taskdata['case_id'];
    }

    if (! $this->_caseid) {
      CRM_Core_Error::fatal(ts('Could not find the case ID or the task ID from the request arguments.'));
    }

    parent::preProcess();
  }

  function setDefaultValues() {
    $defaults = array();

    if ($this->_taskdata) {
      $defaults = array_merge($defaults, $this->_taskdata);

      // TODO: mysql timestamps vs date..
      if (! empty($defaults['begin'])) {
        $defaults['begin'] = date('m/d/Y', strtotime($defaults['begin']));
      }

      if (! empty($defaults['end'])) {
        $defaults['end'] = date('m/d/Y', strtotime($defaults['end']));
      }
    }
    else {
      $defaults['case_id'] = $this->_caseid;
    }

    return $defaults;
  }

  function buildQuickForm() {
    $projects = CRM_Timetrack_Utils::getOpenCases();
    $users = CRM_Timetrack_Utils::getUsers();

    if ($this->_taskid) {
      CRM_Utils_System::setTitle(ts('Edit task %1 for %2', array(1 => $this->_taskdata['title'], 2 => $this->_taskdata['case_subject'])));
    }
    else {
      $case_title = CRM_Timetrack_Utils::getCaseSubject($this->_caseid);
      CRM_Utils_System::setTitle(ts('New task for %1', array(1 => $case_title)));
    }

    $this->add('hidden', 'cid', $this->_caseid);
    $this->add('hidden', 'tid', $this->_taskid);

    // TODO: should be an auto-complete / select2
    $this->add('select', 'case_id', ts('Case'), $projects, TRUE);

    $this->add('text', 'title', ts('Title'), NULL, TRUE);
    $this->add('text', 'state', ts('Status'), NULL, TRUE);
    $this->addDate('begin', ts('Start'));
    $this->addDate('end', ts('End'));
    $this->add('text', 'estimate', ts('Estimate'));
    $this->add('text', 'lead', ts('Lead'));

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

    CRM_Core_Session::setStatus(ts('This does not do anything yet.'), '', 'success');

return;

    // TODO save values, convert mysql date to timestamps..
    // TODO move out to API Timetrackpunch.create

    $begin = strtotime($values['begin']);
    $duration = $values['duration'] * 60 * 60;

    if ($this->_taskid) {
      $dao = CRM_Core_DAO::executeQuery('UPDATE kpunch SET begin = %1, duration = %2, comment = %3, nid = %4, uid = %5 WHERE id = %6', array(
        1 => array($begin, 'Positive'), // FIXME date mysql
        2 => array($duration, 'Integer'),
        3 => array($values['comment'], 'String'),
        4 => array($values['activity_id'], 'Positive'),
        5 => array($values['contact_id'], 'Positive'),
        6 => array($this->_taskid, 'Positive'),
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
          'reset=1&action=add&cid=' . $this->_caseid
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
