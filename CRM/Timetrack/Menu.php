<?php

use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Menu {

  public function getMenuItems() {
    $userID = CRM_Core_Session::getLoggedInContactID();
    $menu = [];

    $menu = [
      'label' => E::ts('Track'),
      'name' => 'timetrack_items',
      'icon' => 'crm-i fa-clock-o',
      'child' => [],
    ];

    // Check if punched-in
    $result = civicrm_api3('Timetrackpunch', 'get', [
      'duration' => -1,
      'contact_id' => $userID,
      'sequential' => 1,
    ]);

    if ($result['count'] > 0) {
      $punch = $result['values'][0];
      $begin_date = substr($punch['begin'], 0, 10);
      $today_date = date('Y-m-d');

      // Display "Y-m-d H:i" if punch did not start today
      // otherwise display just "H:i"
      if ($begin_date == $today_date) {
        $punch['begin'] = substr($punch['begin'], 11, 5);
      }
      else {
        $punch['begin'] = substr($punch['begin'], 0, 16);
      }

      $node = [
        'label' => E::ts('Punched-in: %1, since %2', [1 => $punch['case_subject'] . ' / ' . $punch['ktask_title'], 2 => $punch['begin']]),
        'url' => '#',
        'name' => 'timetrack_current_punch',
        // 'icon' => 'crm-i fa-fw',
      ];
      $menu['child'][] = $node;

      // Update the label on the main Track menu
      $menu['label'] = E::ts('Punched-in');
    }

    // Input to punch new time
    $node = [
      'label' => E::ts('Ex: 10:00+1h project/task comment...'),
      'url' => '#',
      'name' => 'timetrack_punch',
      // 'icon' => 'crm-i fa-fw',
    ];
    $menu['child'][] = $node;

    // Search
    $node = [
      'label' => E::ts('Search...'),
      'url' => '#',
      'name' => 'timetrack_search',
      'icon' => 'crm-i fa-fw',
    ];
    $menu['child'][] = $node;

    // Show today's punches
    $today = [
      'label' => 'Today...',
      'url' => '#',
      'name' => 'timetrack_today',
      'icon' => 'crm-i fa-fw',
      'child' => [],
    ];

    $punchs = civicrm_api3('Timetrackpunch', 'get', [
      'contact_id' => $userID,
      'begin_low' => date('Ymd') . '000000',
    ]);

    foreach ($punchs['values'] as $p) {
      $node = [
        // Time:Only 1.5h: [project]: [task]
        'label' => substr($p['begin'], 11, 5) . ' ' . CRM_Timetrack_Utils::roundUpSeconds($p['duration']) . 'h: ' . $p['case_subject'] . ': ' . $p['ktask_title'] . ' (' . $p['comment'] . ')',
        'url' => CRM_Utils_System::url('civicrm/timetrack/punch', 'reset=1&pid=' . $p['id']),
        'name' => 'today_' . $p['id'],
        'icon' => 'crm-i fa-fw',
      ];
      $today['child'][] = $node;
    }

    $menu['child'][] = $today;

    $node = [
      'label' => 'Timeline',
      'url' => '#',
      'name' => 'timetrack_timeline',
      'icon' => 'crm-i fa-fw',
      'url' => CRM_Utils_System::url('civicrm/timetrack/timeline', 'reset=1'),
    ];
    $menu['child'][] = $node;

    $node = [
      'label' => E::ts('Import'),
      'url' => '#',
      'name' => 'timetrack_import',
      'icon' => 'crm-i fa-fw',
      'url' => CRM_Utils_System::url('civicrm/timetrack/import'),
    ];
    $menu['child'][] = $node;

    return $menu;
  }

}
