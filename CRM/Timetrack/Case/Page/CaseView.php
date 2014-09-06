<?php

class CRM_Timetrack_Case_Page_CaseView {
  function pageRun(&$page) {

  }

  function caseSummary($case_id) {
    $summary = array();

    $result = civicrm_api3('CustomValue', 'get', array(
      'entity_id' => $case_id,
      'entity_table' => 'Case',
      'return.custom_2' => 1,
    ));

    if (! empty($result['values'][2]['latest'])) {
      $kcontract_nid = $result['values'][2]['latest'];
      $node = new StdClass();
      $node->nid = $kcontract_nid;

      if ($kcontract_nid) {
        $actions = array(
          CRM_Utils_System::href(ts('Add punch'), 'civicrm/timetrack/punch/', array('reset' => 1, 'cid' => $case_id, 'action' => 'create')),
          CRM_Utils_System::href(ts('View all punches'), 'node/' . $kcontract_nid . '/punches'),
          CRM_Utils_System::href(ts('View billing'), 'node/' . $kcontract_nid . '/kbpill'),
        );

        $summary['kproject'] = array(
          'label' => ts('Kproject:'),
          'value' => implode(', ', $actions),
        );

        $summary['ktasks'] = array(
          'label' => ts('Tasks:'),
          'value' => kproject_tasks_list($node),
        );
      }
    }
    else {
      $summary['kproject'] = array(
        'label' => ts('Kproject:'),
        'value' => '<strong>None found. Either you need to add the node_id of the kcontract in the custom field of this case, or you need to create a <a href="/node/add/kcontract">new kcontract</a>.</strong>',
      );
    }

    return $summary;
  }
}
