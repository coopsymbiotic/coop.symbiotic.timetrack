<?php

class CRM_Timetrack_Case_Page_CaseView {
  /**
   * Implements hook_civicrm_caseSummary().
   */
  function caseSummary($case_id) {
    $summary = array();

    CRM_Core_Resources::singleton()->addStyleFile('ca.bidon.timetrack', 'css/crm-timetrack-case-page-caseview.css');

    $dao = CRM_Core_DAO::executeQuery('SELECT * FROM kcontract WHERE case_id = %1', array(
      1 => array($case_id, 'Positive'),
    ));

    if ($dao->fetch()) {
      $actions = array(
        array(
          'label' => ts('Add punch'),
          'url' => CRM_Utils_System::url('civicrm/timetrack/punch', array('reset' => 1, 'cid' => $case_id, 'action' => 'create')),
          'classes' => 'icon add-icon',
        ),
        array(
          'label' => ts('Add task'),
          'url' => CRM_Utils_System::url('civicrm/timetrack/task', array('reset' => 1, 'cid' => $case_id, 'action' => 'create')),
          'classes' => 'icon add-icon',
        ),
        array(
          'label' => ts('View/invoice punches'),
          'url' => CRM_Utils_System::url('civicrm/contact/search/custom', array('csid' => 16, 'case_id' => $case_id, 'force' => 1, 'crmSID' => '6_d')),
          'classes' => 'icon search-icon',
        ),
        array(
          'label' => ts('Invoice other items'),
          'url' => CRM_Utils_System::url('civicrm/timetrack/invoice', array('case_id' => $case_id, 'reset' => 1)),
          'classes' => 'icon add-icon',
        ),
      );

      $actions_html = '';

      foreach ($actions as $key => $action) {
        $actions_html .= "<a href='{$action['url']}' class='button'><span><div class='{$action['classes']}'></div>{$action['label']}</span></a>";
      }

      $summary['timetrack_actions'] = array(
        'label' => ts('Time tracking:'),
        'value' => '<div>' . $actions_html . '</div>',
      );

      $summary['timetrack_billing_status'] = array(
        'label' => ts('Billing status'),
        'value' => ts('%1 unbilled hour(s)', array(1 => CRM_Timetrack_Utils::roundUpSeconds($this->getUnbilledHours($case_id)))),
      );

      $summary['timetrack_tasks'] = array(
        'label' => '',
        'value' => $this->getListOfTasks($case_id),
      );

      $summary['timetrack_invoices'] = array(
        'label' => '',
        'value' => $this->getListOfInvoice($case_id),
      );
    }
    else {
      // TODO: we should probably have a way to enable/disable timetracking per case type.
      // so that if we don't find any info, it's perfectly normal to have an option to edit.
      $summary['timetrack_warning'] = array(
        'label' => ts('Timetrack:'),
        'value' => 'No timetracking information was found for this case.',
      );
    }

    return $summary;
  }

  function getListOfTasks($case_id) {
    $smarty = CRM_Core_Smarty::singleton();

    $smarty->assign('timetrack_header_idcss', 'caseview-tasks');
    $smarty->assign('timetrack_header_title', ts('Tasks', array('domain' => 'ca.bidon.timetrack')));

    $taskStatuses = CRM_Timetrack_PseudoConstant::getTaskStatuses();

    // FIXME ts() domain.
    $headers = array(
      'title' => ts('Task'),
      'estimate' => ts('Estimate'),
      'total_included' => ts('Total punches'),
      'percent_done' => ts('% done'),
      'state' => ts('Status'),
      'begin' => ts('Begin'),
      'end' => ts('end'),
    );

    $smarty->assign('timetrack_headers', $headers);

    $rows = array();

    $total = array(
      'title' => ts('Total'),
      'estimate' => 0,
      'total_included' => 0,
      'percent' => '',
      'state' => '',
      'begin' => '',
      'end' => '',
    );

    $result = civicrm_api3('Timetracktask', 'get', array(
      'case_id' => $case_id,
      'option.limit' => 1000,
    ));

    foreach ($result['values'] as $task) {
      $included_hours = CRM_Timetrack_Utils::roundUpSeconds($task['total_included'], 1);
      $percent_done = '';

      if ($task['estimate']) {
        $percent_done = round($included_hours / $task['estimate'] * 100) . '%';
      }

      $rows[] = array(
        'title' => CRM_Utils_System::href($task['title'], 'civicrm/timetrack/task', array('tid' => $task['task_id'])),
        'estimate' => $task['estimate'],
        'total_included' => $included_hours,
        'percent_done' => $percent_done,
        'state' => $taskStatuses[$task['state']],
        'begin' => substr($task['begin'], 0, 10), // TODO format date l10n
        'end' => substr($task['end'], 0, 10), // TODO format date l10n
      );

      $total['estimate'] += $task['estimate'];
      $total['total_included'] += $task['total_included'];
    }

    $total['total_included'] = CRM_Timetrack_Utils::roundUpSeconds($total['total_included'], 1);
    $total['percent_done'] = ($total['estimate'] ? round($total['total_included'] / $total['estimate'] * 100) : '');

    $rows[] = $total;

    $smarty->assign('timetrack_rows', $rows);
    return $smarty->fetch('CRM/Timetrack/Page/Snippets/AccordionTable.tpl');
  }

  function getListOfInvoice($case_id) {
    $smarty = CRM_Core_Smarty::singleton();

    $smarty->assign('timetrack_header_idcss', 'caseview-invoices');
    $smarty->assign('timetrack_header_title', ts('Invoices', array('domain' => 'ca.bidon.timetrack')));

    // FIXME ts() domain.
    $headers = array(
      'title' => ts('Title'),
      'total' => ts('Total punches'),
      'invoiced' => ts('Invoiced'),
      'invoiced_pct' => ts('% invoiced'),
      'ledger_id' => ts('Ledger ID'),
      'state' => ts('Status'),
      'generate' => ts('Generate'),
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
        'title' => CRM_Utils_System::href($invoice['title'], 'civicrm/timetrack/invoice', array('invoice_id' => $invoice['invoice_id'])),
        'total' => $included_hours,
        'invoiced' => $invoice['hours_billed'], // already in hours
        'invoiced_pct' => ($included_hours > 0 ? round($invoice['hours_billed'] / $included_hours * 100, 2) : 0) . '%',
        'state' => $invoice['state'],
        'ledger_id' => $invoice['ledger_bill_id'],
        'generate' => CRM_Utils_System::href(ts('Generate'), 'civicrm/timetrack/invoice/generate', array('invoice_id' => $invoice['invoice_id'])),
      );
    }

    $smarty->assign('timetrack_rows', $rows);

    return $smarty->fetch('CRM/Timetrack/Page/Snippets/AccordionTable.tpl');
  }

  function getUnbilledHours($case_id) {
    // TODO: move to API ?
    $dao = CRM_Core_DAO::executeQuery('SELECT sum(duration) as total
      FROM kpunch
      INNER JOIN ktask on (ktask.id = kpunch.ktask_id AND ktask.case_id = %1)
      WHERE korder_id is NULL', array(
      1 => array($case_id, 'Positive'),
    ));

    $dao->fetch();
    return $dao->total;
  }
}
