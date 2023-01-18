<?php

require_once 'timetrack.civix.php';
use CRM_Timetrack_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function timetrack_civicrm_config(&$config) {
  if (isset(Civi::$statics[__FUNCTION__])) { return; }
  Civi::$statics[__FUNCTION__] = 1;

  Civi::dispatcher()->addListener('dataexplorer.boot', ['\Civi\Timetrack\Events', 'fireDataExplorerBoot']);

  // Override authx's check of the token header
  Civi::dispatcher()->addListener('civi.invoke.auth', function($event) {
    if ((implode('/', $event->args) === 'civicrm/timetrack/mattermost')) {
      $event->stopPropagation();
    }
  }, 1000);

  _timetrack_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function timetrack_civicrm_xmlMenu(&$files) {
  _timetrack_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function timetrack_civicrm_install() {
  return _timetrack_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function timetrack_civicrm_uninstall() {
  return _timetrack_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function timetrack_civicrm_enable() {
  return _timetrack_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function timetrack_civicrm_disable() {
  return _timetrack_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
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
 * Implements hook_civicrm_managed().
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
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function timetrack_civicrm_entityTypes(&$entityTypes) {
  _timetrack_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_caseTypes().
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
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function timetrack_civicrm_angularModules(&$angularModules) {
  _timetrack_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function timetrack_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _timetrack_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_buildForm() in a completely overkill way.
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
    $foo = new $formName();

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
    $foo = new $formName();

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
    $foo = new $formName();

    if (method_exists($foo, 'postProcess')) {
      $foo->postProcess($form);
    }
  }
}

/**
 * Implements hook_civicrm_caseSummary().
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
  // FIXME: how to define our own object?
  if ($objectType == 'contact') {
    $tasks[100] = [
      'title' => ts('Invoice punches', ['domain' => 'coop.symbiotic.timetrack']),
      'class' => 'CRM_Timetrack_Form_Task_Invoice',
      'result' => TRUE,
    ];
    $tasks[101] = [
      'title' => ts('Export punches', ['domain' => 'coop.symbiotic.timetrack']),
      'class' => 'CRM_Timetrack_Form_Task_Export',
      'result' => FALSE,
    ];
  }
}

/**
 * Implements hook_civicrm_triggerInfo().
 */
function timetrack_civicrm_triggerInfo(&$info, $tableName) {
  $info[] = [
    'table' => ['korder'],
    'when' => 'BEFORE',
    'event' => ['INSERT'],
    'sql' => "\nSET NEW.created_date = CURRENT_TIMESTAMP;\n",
  ];
}

/**
 * Implements hook_civicrm_permission().
 */
function timetrack_civicrm_permission(&$permissions) {
  $permissions['create timetrack punch'] = array(
    E::ts('CiviCRM Timetrack: %1', [1 => E::ts('create Timetrack punch')]),
    E::ts('Create or Edit Timetrack punches'),
  );
  $permissions['generate timetrack invoice'] = array(
    E::ts('CiviCRM Timetrack: %1', [1 => E::ts('generate Timetrack invoice')]),
    E::ts('Generate a Timetrack invoice (ODT document)'),
  );
}

/**
 * Implements hook_civicrm_alterAPIPermissions().
 */
function timetrack_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  // FIXME: should be using:
  // 'CiviCase: access my cases and activities' ?
  // .. or some custom permission?

  $permissions['timetrackpunch'] = [
    'get' => [
      'access CiviCRM',
    ],
    'create' => [
      'create timetrack punch',
    ],
    'punchout' => [
      'create timetrack punch',
    ],
  ];

  $permissions['timetrackpunchlist'] = [
    'preview' => [
      'create timetrack punch',
    ],
    'import' => [
      'create timetrack punch',
    ],
  ];

  $permissions['timetracktask'] = [
    'get' => [
      'access CiviCRM',
    ],
    'create' => [
      'access CiviCRM',
    ],
  ];
}

/**
 * Implements hook_civicrm_coreResourceList().
 */
function timetrack_civicrm_coreResourceList(&$list, $region) {
  if ($region == 'html-header' && CRM_Core_Permission::check('create timetrack punch')) {
    $menu = CRM_Timetrack_Menu::getMenuItems();
    Civi::resources()
      ->addScriptFile('coop.symbiotic.timetrack', 'js/menu.js', 0, 'html-header')
      ->addVars('timetrack', ['menu' => $menu]);
  }
}
