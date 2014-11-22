<?php

require_once 'timetrack.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function timetrack_civicrm_config(&$config) {
  _timetrack_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function timetrack_civicrm_xmlMenu(&$files) {
  _timetrack_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function timetrack_civicrm_install() {
  return _timetrack_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function timetrack_civicrm_uninstall() {
  return _timetrack_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function timetrack_civicrm_enable() {
  return _timetrack_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function timetrack_civicrm_disable() {
  return _timetrack_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function timetrack_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _timetrack_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function timetrack_civicrm_managed(&$entities) {
  return _timetrack_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function timetrack_civicrm_caseTypes(&$caseTypes) {
  _timetrack_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function timetrack_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _timetrack_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_buildForm() is a completely overkill way.
 * Searches for an override class named after the initial $formName
 * and calls its buildForm().
 *
 * Ex: for a $formName "CRM_Case_Form_CaseView", it will:
 * - try to find * CRM/Timetrack/Case/Form/CaseView.php,
 * - require_once the file, instanciate an object, and
 * - call its buildForm() function.
 *
 * Why so overkill? My buildForm() implementations tend to become
 * really big and numerous, and even if I split up into multiple
 * functions, it still makes a really long php file.
 */
function timetrack_civicrm_buildForm($formName, &$form) {
  $formName = str_replace('CRM_', 'CRM_Timetrack_', $formName);
  $parts = explode('_', $formName);
  $filename = dirname(__FILE__) . '/' . implode('/', $parts) . '.php';

  if (file_exists($filename)) {
    require_once $filename;
    $foo = new $formName;

    if (method_exists($foo, 'buildForm')) {
      $foo->buildForm($form);
    }
  }
}

/**
 * Implements hook_civicrm_validateForm().
 *
 */
function timetrack_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  $formName = str_replace('CRM_', 'CRM_Timetrack_', $formName);
  $parts = explode('_', $formName);
  $filename = dirname(__FILE__) . '/' . implode('/', $parts) . '.php';

  if (file_exists($filename)) {
    require_once $filename;
    $foo = new $formName;

    if (method_exists($foo, 'validateForm')) {
      $foo->validateForm($fields, $files, $form, $errors);
    }
  }
}

/**
 * Implements hook_civicrm_postProcess().
 *
 * "This hook is invoked when a CiviCRM form is submitted. If the module has injected
 * any form elements, this hook should save the values in the database.
 * This hook is not called when using the API, only when using the regular
 * forms. If you want to have an action that is triggered no matter if it's a
 * form or an API, use the pre and post hooks instead."
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postProcess
 */
function timetrack_civicrm_postProcess($formName, &$form) {
  $formName = str_replace('CRM_', 'CRM_Timetrack_', $formName);
  $parts = explode('_', $formName);
  $filename = dirname(__FILE__) . '/' . implode('/', $parts) . '.php';

  if (file_exists($filename)) {
    require_once $filename;
    $foo = new $formName;

    if (method_exists($foo, 'postProcess')) {
      $foo->postProcess($form);
    }
  }
}

/**
 * Implements hook_civicrm_pageRun() is a completely overkill way.
 * Searches for an override class named after the initial $formName
 * and calls its buildForm().
 *
 * Ex: for a $formName "CRM_Case_Form_CaseView", it will:
 * - try to find * CRM/Timetrack/Case/Page/CaseView.php,
 * - require_once the file, instanciate an object, and
 * - call its pageRun() function.
 *
 * See @timetrack_civicrm_buildForm() for more background info.
 */
function timetrack_civicrm_pageRun(&$page) {
  $pageName = get_class($page);
  $pageName = str_replace('CRM_', 'CRM_Timetrack_', $pageName);
  $parts = explode('_', $pageName);
  $filename = dirname(__FILE__) . '/' . implode('/', $parts) . '.php';

  if (file_exists($filename)) {
    require_once $filename;
    $foo = new $pageName;

    if (method_exists($foo, 'pageRun')) {
      $foo->pageRun($form);
    }
  }
}

/**
 * Implements hook_civicrm_caseSummary();
 */
function timetrack_civicrm_caseSummary($case_id) {
  require_once dirname(__FILE__) . '/CRM/Timetrack/Case/Page/CaseView.php';
  $foo = new CRM_Timetrack_Case_Page_CaseView();
  return $foo->caseSummary($case_id);
}

/**
 * Implements hook_civicrm_searchTasks().
 */
function timetrack_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contact') {
    if (arg(3) == 'custom' && CRM_Utils_Array::value('csid', $_REQUEST)) {
      $dao = CRM_Core_DAO::executeQuery(
        "SELECT v.name
           FROM civicrm_option_group g
           LEFT JOIN civicrm_option_value v on (v.option_group_id = g.id)
          WHERE g.name = 'custom_search' and v.value = %1", array(
        1 => array(CRM_Utils_Array::value('csid', $_REQUEST), 'Positive')
      ));

      if ($dao->fetch()) {
        if ($dao->name == 'CRM_Timetrack_Form_Search_TimetrackPunches') {
          foreach ($tasks as $key => $val) {
            // For some weird reason, item 15 (print contacts) must not be removed
            // or we will run into a weird PHP error (!).
            if ($key != 15) {
              unset($tasks[$key]);
            }
          }
        }
      }
    }

    $tasks[100] = array(
      'title' => ts('Invoice punches', array('domain' => 'ca.bidon.timetrack')),
      'class' => 'CRM_Timetrack_Form_Task_Invoice',
      'result' => TRUE,
    );
  }
}

/**
 * Implements hook_civicrm_triggerInfo().
 */
function timetrack_civicrm_triggerInfo(&$info, $tableName) {
  $info[] = array(
    'table' => array('korder'),
    'when' => 'BEFORE',
    'event' => array('INSERT'),
    'sql' => "\nSET NEW.created_date = CURRENT_TIMESTAMP;\n",
  );
}
