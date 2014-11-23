<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Timetrack_Form_Task extends CRM_Core_Form {
  protected $_caseid;
  protected $_taskid;
  protected $_taskdata;

  function preProcess() {
    $this->_caseid = CRM_Utils_Request::retrieve('cid', 'Integer', $this, FALSE, NULL);
    $this->_taskid = CRM_Utils_Request::retrieve('tid', 'Integer', $this, FALSE, NULL);

    if ($this->_taskid) {
      // Editing an existing task. Fetch the task data for setDefaultValues() later.
      $this->_taskdata = civicrm_api3('Timetracktask', 'getsingle', array(
        'task_id' => $this->_taskid,
      ));

      if ($this->_taskdata['lead']) {
        $contact = civicrm_api3('Contact', 'getsingle', array(
          'id' => $this->_taskdata['lead'],
          'return.display_name' => 1,
        ));

        $this->_taskdata['leadautocomplete'] = $contact['display_name'];
      }

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

    $this->add('hidden', 'task_id', $this->_taskid);

    // TODO: should be an auto-complete / select2
    $this->add('select', 'case_id', ts('Case'), $projects, TRUE);

    $this->add('text', 'title', ts('Title'), NULL, TRUE);
    $this->add('select', 'state', ts('Status'), CRM_Timetrack_PseudoConstant::getTaskStatuses(), TRUE);
    $this->addDate('begin', ts('Start'));
    $this->addDate('end', ts('End'));
    $this->add('text', 'estimate', ts('Estimate'));
    $this->add('text', 'leadautocomplete', ts('Lead'));
    $this->add('hidden', 'lead');

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
    $params = $this->exportValues();
    $buttonName = $this->controller->getButtonName();

    // TODO: fix mysql/unix timestamps
    if (! empty($params['begin'])) {
      $params['begin'] = strtotime($params['begin']);
    }
    if (! empty($params['end'])) {
      $params['end'] = strtotime($params['end']);
    }

    $result = civicrm_api3('Timetracktask', 'create', $params);
    CRM_Core_Session::setStatus(ts('The task #%1 has been saved.', array(1 => $result['id'])), '', 'success');

    parent::postProcess();

    if ($buttonName == $this->getButtonName('next')) {
      CRM_Core_Session::setStatus(ts('You can create another task.'), '', 'info');
      $session = CRM_Core_Session::singleton();
      $session->replaceUserContext(
        CRM_Utils_System::url(
          'civicrm/timetrack/task',
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
