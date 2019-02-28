<?php

class CRM_Timetrack_Case_Form_Case {
  function buildForm(&$form) {
    $form->add('text', 'alias', ts('Alias'));
    $form->add('number', 'estimate', ts('Estimate'));

    CRM_Core_Region::instance('form-bottom')->add(array(
      'template' => 'CRM/Timetrack/Form/CaseDetails.tpl',
      'weight' => 100,
    ));
  }

  function validateForm(&$fields, &$files, &$form, &$errors) {
    $alias = CRM_Utils_Array::value('alias', $fields);

    if ($alias) {
      $case_id = CRM_Core_DAO::singleValueQuery('SELECT case_id FROM kcontract WHERE lower(alias) = lower(%1)', array(
        1 => array($alias, 'String'),
      ));

      if ($case_id) {
        $case = civicrm_api3('Case', 'getsingle', array(
          'case_id' => $case_id,
        ));

        $errors['alias'] = ts('The alias "%1" is already taken by %2 (ID %3).', array(1 => $alias, 2 => $case['subject'], 3 => $case_id));
      }
    }
  }

  /**
   * @param CRM_Core_Form $form
   */
  function postProcess(&$form) {
    $params = $form->exportValues();

    // Only run on create/edit.
    if ($form->_action & CRM_Core_Action::DELETE) {
      return;
    }

    $case_id = $form->getVar('_caseId');

    if ($case_id) {
      CRM_Core_DAO::executeQuery('INSERT INTO kcontract (alias, estimate, case_id) VALUES (%1, %2, %3)', array(
        1 => array($params['alias'], 'String'),
        2 => array(CRM_Utils_Array::value('estimate', $params) ?: 0, 'Float'),
        3 => array($case_id, 'Positive'),
      ));
    }
  }
}
