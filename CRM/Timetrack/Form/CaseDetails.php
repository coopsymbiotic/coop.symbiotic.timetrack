<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Timetrack_Form_CaseDetails extends CRM_Core_Form {
  public $_cid;

  function preProcess() {
    $this->_cid = CRM_Utils_Request::retrieve('cid', 'Integer', $this, FALSE, NULL);

    if (! $this->_cid) {
      CRM_Core_Error::fatal(ts('Could not find the case ID from the request arguments.'));
    }

    parent::preProcess();
  }

  function setDefaultValues() {
    $defaults = array();

    $dao = CRM_Core_DAO::executeQuery('SELECT alias, estimate FROM kcontract WHERE case_id = %1', array(
      1 => array($this->_cid, 'Positive'),
    ));

    if ($dao->fetch()) {
      $defaults['alias'] = $dao->alias;
      $defaults['estimate'] = $dao->estimate;
    }

    return $defaults;
  }

  function buildQuickForm() {
    $case_title = CRM_Timetrack_Utils::getCaseSubject($this->_cid);
    CRM_Utils_System::setTitle(ts('Edit case details for %1', array(1 => $case_title)));

    $this->add('hidden', 'case_id', $this->_cid);
    $this->add('text', 'alias', ts('Alias'));
    $this->add('text', 'estimate', ts('Estimate'));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => FALSE,
      ),
    ));

    // Export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();
    $buttonName = $this->controller->getButtonName();

    $alias = strtotime($values['begin']);
    $estimate = $values['duration'] * 60 * 60;

    $exists = CRM_Core_DAO::singleValueQuery('SELECT case_id FROM kcontract WHERE case_id = %1', array(1 => array($values['case_id'], 'Positive')));

    if ($exists) {
      $dao = CRM_Core_DAO::executeQuery('UPDATE kcontract SET alias = %1, estimate = %2 WHERE case_id = %3', array(
        1 => array($values['alias'], 'String'),
        2 => array($values['estimate'], 'Integer'),
        3 => array($values['case_id'], 'Positive'),
      ));

      CRM_Core_Session::setStatus(ts('The case details have been updated.'), '', 'success');
    }
    else {
      $dao = CRM_Core_DAO::executeQuery('INSERT INTO kcontract (alias, estimate, case_id) VALUES (%1, %2, %3)', array(
        1 => array($values['alias'], 'String'),
        2 => array($values['estimate'], 'Integer'),
        3 => array($values['case_id'], 'Positive'),
      ));
      CRM_Core_Session::setStatus(ts('The case details have been set.'), '', 'success');
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
