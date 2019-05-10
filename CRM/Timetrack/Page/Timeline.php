<?php

class CRM_Timetrack_Page_Timeline extends CRM_Core_Page {
  public function run() {
    Civi::resources()
      ->addScriptFile('coop.symbiotic.timetrack', 'js/timeline.js')
      ->addScriptFile('coop.symbiotic.timetrack', 'dist/dhtmlxscheduler/codebase/dhtmlxscheduler.js')
      ->addScriptFile('coop.symbiotic.timetrack', 'dist/dhtmlxscheduler/codebase/ext/dhtmlxscheduler_timeline.js')
      ->addStyleFile('coop.symbiotic.timetrack', 'dist/dhtmlxscheduler/codebase/dhtmlxscheduler.css');

    // TODO: currently we only show a timeline for the current user.
    // Get info on the current user.
    $users = [];
    $session = CRM_Core_Session::singleton();

    $contact = civicrm_api3('Contact', 'getsingle', [
      'contact_id' => $session->get('userID'),
    ]);

    $users[] = [
      'key' => $session->get('userID'),
      'label' => $contact['display_name'],
    ];

    // Get info on cases (contracts) and possible tasks.
    // then reformat the array for the format required by timeline.
    $tasks = [];
    $tmp = CRM_Timetrack_Utils::getCaseActivityTypes();

    foreach ($tmp as $key => $val) {
      $tasks[] = [
        'key' => $key,
        'label' => $val,
      ];
    }

    CRM_Core_Resources::singleton()->addSetting([
      'timetrack' => [
        'users' => $users,
        'tasks' => $tasks,
      ]
    ]);

    parent::run();
  }

}
