<?php

class CRM_Timetrack_Case_Form_Case {
  function buildForm(&$form) {
    $form->add('text', 'alias', ts('Alias'));
    $form->add('text', 'estimate', ts('Estimate'));

    // FIXME: in 4.5, we can use 'form-bottom' instead of page-body.
    CRM_Core_Region::instance('page-body')->add(array(
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

  function postProcess(&$form) {
    $params = $form->exportValues();

    // FIXME: assuming two cases are not created at the same time..   
    $case_id = CRM_Core_DAO::singleValueQuery("SELECT max(id) as id FROM civicrm_case");

    if ($case_id) {
      CRM_Core_DAO::executeQuery('INSERT INTO kcontract (alias, estimate, case_id) VALUES (%1, %2, %3)', array(
        1 => array($params['alias'], 'String'),
        2 => array($params['estimate'], 'Float'),
        3 => array($case_id, 'Positive'),
      ));
    }
  }
}
