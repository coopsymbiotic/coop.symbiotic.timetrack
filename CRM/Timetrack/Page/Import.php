<?php
use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Page_Import extends CRM_Core_Page {

  /**
   * Run page
   */
  public function run() {
    $loader = new \Civi\Angular\AngularLoader();
    $loader->setModules(['timetrackImport']);
    $loader->setPageName('civicrm/timetrack/import');
    $loader->useApp([
      'defaultRoute' => '/import',
    ]);
    $loader->load();
    CRM_Utils_System::setTitle(E::ts('Timetrack: Import time-punches'));
    parent::run();
  }

}
