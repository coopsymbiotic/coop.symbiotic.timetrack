<?php

use CRM_Timetrack_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Timetrack_Form_Punch extends CRM_Core_Form {
  public $_cid;
  public $_pid;

  /**
   * Returns the case ID associated with a punch. Useful when editing a punch.
   */
  public function getCaseIdFromPunchId($punch_id) {
    return CRM_Core_DAO::singleValueQuery('SELECT ktask.case_id
      FROM kpunch
      LEFT JOIN ktask ON (ktask.id = kpunch.ktask_id)
      WHERE kpunch.id = %1', [
      1 => [$punch_id, 'Positive'],
    ]);
  }

  public function preProcess() {
    $this->_cid = CRM_Utils_Request::retrieve('cid', 'Integer', $this, FALSE, NULL);
    $this->_pid = CRM_Utils_Request::retrieve('pid', 'Integer', $this, FALSE, NULL);

    if (!$this->_cid) {
      $this->_cid = $this->getCaseIdFromPunchId($this->_pid);
    }

    if (!$this->_cid) {
      CRM_Core_Error::fatal(ts('Could not find the case ID or the punch ID from the request arguments.'));
    }

    parent::preProcess();
  }

  public function setDefaultValues() {
    $defaults = [];

    if ($this->_pid) {
      $result = civicrm_api3('Timetrackpunch', 'getsingle', ['id' => $this->_pid]);
      $defaults = array_merge($defaults, $result);

      if (!empty($defaults['begin'])) {
        $defaults['begin'] = $defaults['begin'];
      }

      if (!empty($defaults['duration'])) {
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

  public function buildQuickForm() {
    // If new punch from Case, limit tasks to that case, otherwise show all tasks when editing.
    $limit_case = ($this->_pid ? 0 : $this->_cid);
    $tasks = CRM_Timetrack_Utils::getActivitiesForCase($limit_case);

    $case_title = CRM_Timetrack_Utils::getCaseSubject($this->_cid);

    if ($this->_pid) {
      CRM_Utils_System::setTitle(ts('Edit punch in %1', [1 => $case_title]));
    }
    else {
      CRM_Utils_System::setTitle(ts('New punch in %1', [1 => $case_title]));
    }

    $this->add('hidden', 'cid', $this->_cid);
    $this->add('hidden', 'pid', $this->_pid);

    $this->add('select', 'activity_id', ts('Activity'), $tasks, TRUE, ['class' => 'crm-select2 huge']);
    $this->addEntityRef('contact_id', ts('Contact'), ['api' => ['params' => ['uf_user' => 1]]]);

    $this->add('datepicker', 'begin', ts('Start'), [], TRUE);
    $this->add('number', 'duration', ts('Duration'), ['class' => 'four', 'placeholder' => E::ts('Hours'), 'step' => '0.25'], TRUE);
    $this->add('text', 'comment', ts('Comment'), ['class' => 'huge'], TRUE);

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => FALSE,
      ],
      [
        'type' => 'next',
        'subName' => 'new',
        'name' => ts('Save and New'),
        'isDefault' => TRUE,
      ],
    ]);

    // Export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    $buttonName = $this->controller->getButtonName();

    $params = [];
    $params['begin'] = $values['begin'];
    $params['duration'] = $values['duration'] * 60 * 60;
    $params['comment'] = $values['comment'];
    $params['contact_id'] = $values['contact_id'];

    if ($this->_pid) {
      $params['id'] = $this->_pid;
    }

    civicrm_api3('Timetrackpunch', 'create', $params);

    CRM_Core_Session::setStatus(ts('The punch has been saved.'), '', 'success');

    if ($buttonName == $this->getButtonName('next', 'new')) {
      CRM_Utils_System::redirect(CRM_Utils_System::url(
        'civicrm/timetrack/punch',
        'reset=1&action=add&cid=' . $this->_cid
      ));
    }
    else {
      CRM_Utils_System::redirect(CRM_Utils_System::url(
        'civicrm/timetrack/punch',
        'reset=1&pid=' . $this->_pid . '&cid=' . $this->_cid
      ));
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
