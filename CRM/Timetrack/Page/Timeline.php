<?php

class CRM_Timetrack_Page_Timeline extends CRM_Core_Page {
  function run() {
    CRM_Core_Resources::singleton()
      ->addScriptFile('ca.bidon.timetrack', 'js/timeline.js')
      ->addScriptFile('ca.bidon.timetrack', 'dist/dhtmlxscheduler/codebase/dhtmlxscheduler.js')
      ->addScriptFile('ca.bidon.timetrack', 'dist/dhtmlxscheduler/codebase/ext/dhtmlxscheduler_timeline.js')
      ->addStyleFile('ca.bidon.timetrack', 'dist/dhtmlxscheduler/codebase/dhtmlxscheduler.css');

    // TODO: currently we only show a timeline for the current user.
    // Get info on the current user.
    $users = array();
    $session = CRM_Core_Session::singleton();

    $contact = civicrm_api3('Contact', 'getsingle', array(
      'contact_id' => $session->get('userID'),
    ));

    $users[] = array(
      'key' => $session->get('ufID'), // FIXME should be userID (contact_id)
      'label' => $contact['display_name'],
    );

    // Get info on cases (contracts)
    $tasks = array();
    $result = civicrm_api3('Timetracktask', 'get', array(
      'option.limit' => 1000,
      'sort' => 'case_subject ASC, title ASC',
    ));

    foreach ($result['values'] as $key => $val) {
      $tasks[] = array(
        'key' => $val['id'],
        'label' => $val['case_subject'] . ' > ' . $val['title'],
      );
    }

    CRM_Core_Resources::singleton()->addSetting(array(
      'timetrack' => array(
        'users' => $users,
        'tasks' => $tasks,
      )
    ));

    parent::run();
  }
}
