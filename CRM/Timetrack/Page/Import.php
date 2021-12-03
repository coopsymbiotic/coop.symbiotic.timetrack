<?php
use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Page_Import extends CRM_Core_Page {

  /**
   * Run page
   */
  public function run() {
    $loader = Civi::service('angularjs.loader');
    $loader->setPageName('civicrm/timetrack/import');
    $loader->addModules(['crmApp', 'timetrackImport']);
    $loader->useApp([
      'defaultRoute' => '/import',
    ]);
    $loader->load();
    parent::run();
  }

}
