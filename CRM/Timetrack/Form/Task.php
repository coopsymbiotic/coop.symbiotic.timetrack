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

  public function preProcess() {
    $this->_caseid = CRM_Utils_Request::retrieve('cid', 'Integer', $this, FALSE, NULL);
    $this->_taskid = CRM_Utils_Request::retrieve('tid', 'Integer', $this, FALSE, NULL);

    if ($this->_taskid) {
      // Editing an existing task. Fetch the task data for setDefaultValues() later.
      $this->_taskdata = \Civi\Api4\Timetracktask::get(false)
        ->addWhere('id', '=', $this->_taskid)
        ->execute()
        ->first();

      if ($this->_taskdata['lead']) {
        $contact = civicrm_api3('Contact', 'getsingle', [
          'id' => $this->_taskdata['lead'],
          'return.display_name' => 1,
        ]);

        $this->_taskdata['leadautocomplete'] = $contact['display_name'];
      }

      $this->_caseid = $this->_taskdata['case_id'];
    }
    else {
      $this->_taskdata = ['case_id' => $this->_caseid];
    }

    if (!$this->_caseid) {
      CRM_Core_Error::fatal(ts('Could not find the case ID or the task ID from the request arguments.'));
    }

    parent::preProcess();
  }

  public function setDefaultValues() {
    return $this->_taskdata;
  }

  public function buildQuickForm() {
    if ($this->_taskid) {
      CRM_Utils_System::setTitle(ts('Edit task %1 for %2', [1 => $this->_taskdata['title'], 2 => $this->_taskdata['case_subject']]));
    }
    else {
      $case_title = CRM_Timetrack_Utils::getCaseSubject($this->_caseid);
      CRM_Utils_System::setTitle(ts('New task for %1', [1 => $case_title]));
    }

    $this->add('hidden', 'task_id', $this->_taskid);

    $this->addEntityRef('case_id', ts('Case'), [
      'entity' => 'case',
      'api' => ['params' => ['status_id.grouping' => "Opened", 'is_deleted' => 0]],
      'select' => ['minimumInputLength' => 0],
    ], TRUE);

    $this->add('text', 'title', ts('Title'), ['class' => 'huge'], TRUE);
    $this->add('select', 'state', ts('Status'), CRM_Timetrack_PseudoConstant::getTaskStatuses(), TRUE);
    $this->add('datepicker', 'begin', ts('Start'), [], FALSE, ['time' => FALSE]);
    $this->add('datepicker', 'end', ts('End'), [], FALSE, ['time' => FALSE]);
    $this->add('text', 'estimate', ts('Estimate'));

    $this->addEntityRef('lead', ts('Lead'), [
      'create' => FALSE,
      'api' => ['params' => ['is_deceased' => 0, 'contact_type' => 'Individual']],
    ]);

    $this->add('wysiwyg', 'description', ts('Description/notes'));

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => FALSE,
      ],
      [
        'type' => 'next',
        'name' => ts('Save and New'),
        'subName' => 'new',
        'isDefault' => TRUE,
      ],
    ]);

    // Export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $params = $this->exportValues();
    $buttonName = $this->controller->getButtonName();

    // $result = civicrm_api3('Timetracktask', 'create', $params);

    $result = civicrm_api4('Timetracktask', 'create', [
      'values' => $params,
    ]);

    CRM_Core_Session::setStatus(ts('The task "%1" (%2) has been saved.', [1 => $params['title'], 2 => $result['id']]), '', 'success');

    parent::postProcess();

    $session = CRM_Core_Session::singleton();
    if ($buttonName == $this->getButtonName('next', 'new')) {
      $session->replaceUserContext(
        CRM_Utils_System::url(
          'civicrm/timetrack/task',
          'reset=1&action=add&cid=' . $this->_caseid
        )
      );
    }
    else {
      $contact_id = CRM_Core_DAO::singleValueQuery('select contact_id from civicrm_case_contact where case_id = %1 limit 1', [
        1 => [$this->_caseid, 'Positive'],
      ]);
      CRM_Utils_System::redirect(CRM_Utils_System::url(
        'civicrm/contact/view/case',
        'reset=1&action=view&context=case&id=' . $this->_caseid . '&cid=' . $contact_id
      ));
    }
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
