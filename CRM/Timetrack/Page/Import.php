<?php
use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Page_Import extends CRM_Core_Page {


  public function run() {
    $loader = new \Civi\Angular\AngularLoader();
    $loader->setModules(array('timetrackImport'));
    $loader->setPageName('civicrm/timetrack/import');
    $loader->useApp(array(
      'defaultRoute' => '/import',
    ));
    $loader->load();
    CRM_Utils_System::setTitle('CiviCRM');
    parent::run();
  }

}
