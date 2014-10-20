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
          CRM_Utils_System::href(ts('View punches'), 'node/' . $kcontract_nid . '/punches'),
          CRM_Utils_System::href(ts('View punches (experimental)'), 'civicrm/contact/search/custom', array('csid' => 16, 'case_id' => $case_id, 'force' => 1, 'crmSID' => '6_d')),
          // CRM_Utils_System::href(ts('View billing'), 'node/' . $kcontract_nid . '/kbpill'),
        );

        $summary['kproject'] = array(
          'label' => ts('Kproject:'),
          'value' => implode(', ', $actions),
        );

        $summary['billing_status'] = array(
          'label' => ts('Billing status'),
          'value' => ts('%1 unbilled hour(s)', array(1 => CRM_Timetrack_Utils::roundUpSeconds($this->getUnbilledHours($case_id)))),
        );

        $summary['ktasks'] = array(
          'label' => '',
          'value' => '<div id="crm-timetrack-caseview-tasks" class="crm-accordion-wrapper"><div class="crm-accordion-header">Tasks</div><div class="crm-accordion-body">' . kproject_tasks_list($node) . '</div></div>',
        );

        $summary['kinvoices'] = array(
          'label' => '',
          'value' => $this->getListOfInvoice($case_id),
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

  function getListOfInvoice($case_id) {
    $smarty = CRM_Core_Smarty::singleton();

    $smarty->assign('timetrack_header_idcss', 'caseview-invoices');
    $smarty->assign('timetrack_header_title', ts('Invoices', array('domain' => 'ca.bidon.timetrack')));

    // FIXME ts() domain.
    $headers = array(
      'subject' => ts('Title'),
      'total' => ts('Total punches'),
      'invoiced' => ts('Invoiced'),
      'invoiced_pct' => ts('% invoiced'),
      'ledger_id' => ts('Ledger ID'),
      'state' => ts('Status'),
    );

    $smarty->assign('timetrack_headers', $headers);

    $rows = array();

    $result = civicrm_api3('Timetrackinvoice', 'get', array(
      'case_id' => $case_id,
      'option.limit' => 1000,
    ));

    foreach ($result['values'] as $invoice) {
      $included_hours = CRM_Timetrack_Utils::roundUpSeconds($invoice['total_included'], 1);

      $rows[] = array(
        'subject' => CRM_Utils_System::href($invoice['subject'], 'node/' . $invoice['invoice_id'] . '/edit'),
        'total' => $included_hours,
        'invoiced' => $invoice['hours_billed'], // already in hours
        'invoiced_pct' => ($included_hours > 0 ? round($invoice['hours_billed'] / $included_hours * 100, 2) : 0) . '%',
        'state' => $invoice['state'],
        'ledger_id' => $invoice['ledger_bill_id'],
      );
    }

    $smarty->assign('timetrack_rows', $rows);

    return $smarty->fetch('CRM/Timetrack/Page/Snippets/AccordionTable.tpl');
  }

  function getUnbilledHours($case_id) {
    // TODO: move to API ?
    $dao = CRM_Core_DAO::executeQuery('SELECT sum(duration) as total
      FROM kpunch
      LEFT JOIN ktask on (ktask.nid = kpunch.nid)
      LEFT JOIN civicrm_value_infos_base_contrats_1 as bc on (bc.kproject_node_2 = ktask.parent)
      WHERE bc.entity_id = %1
        AND order_reference is NULL', array(
      1 => array($case_id, 'Positive'),
    ));

    $dao->fetch();
    return $dao->total;
  }
}
