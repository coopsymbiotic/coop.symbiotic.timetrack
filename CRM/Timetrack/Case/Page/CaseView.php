<?php

use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Case_Page_CaseView {

  /**
   * Custom Search ID for the punch search.
   */
  protected $csid;

  /**
   * Implements hook_civicrm_caseSummary().
   */
  public function caseSummary($case_id) {
    $summary = [];

    Civi::resources()->addStyleFile('coop.symbiotic.timetrack', 'css/crm-timetrack-case-page-caseview.css');
    $csid = $this->getPunchesCSID();

    $dao = CRM_Core_DAO::executeQuery('SELECT * FROM kcontract WHERE case_id = %1', [
      1 => [$case_id, 'Positive'],
    ]);

    if ($dao->fetch()) {
      $actions = [
        [
          'label' => E::ts('Add punch'),
          'url' => CRM_Utils_System::url('civicrm/timetrack/punch', ['reset' => 1, 'cid' => $case_id, 'action' => 'create']),
          'classes' => 'icon ui-icon-plus',
        ],
        [
          'label' => E::ts('Add task'),
          'url' => CRM_Utils_System::url('civicrm/timetrack/task', ['reset' => 1, 'cid' => $case_id, 'action' => 'create']),
          'classes' => 'icon ui-icon-circle-plus',
        ],
      ];

      // These actions should not open in a popup, otherwise actions buttons are broken.
      $actionsreg = [
        [
          'label' => E::ts('View/invoice punches'),
          'url' => CRM_Utils_System::url('civicrm/contact/search/custom', ['csid' => $csid, 'case_id' => $case_id, 'force' => 1, 'crmSID' => '6_d']),
          'classes' => 'icon ui-icon-search',
        ],
        [
          'label' => E::ts('Invoice other items'),
          'url' => CRM_Utils_System::url('civicrm/timetrack/invoice', ['case_id' => $case_id, 'reset' => 1]),
          'classes' => 'icon ui-icon-circle-plus',
        ],
      ];

      $actions_html = '';

      foreach ($actions as $key => $action) {
        $actions_html .= "<a href='{$action['url']}' class='button'><span><div class='{$action['classes']}'></div>{$action['label']}</span></a>";
      }

      foreach ($actionsreg as $key => $action) {
        $actions_html .= "<a href='{$action['url']}' class='button no-popup'><span><div class='{$action['classes']}'></div>{$action['label']}</span></a>";
      }

      $summary['timetrack_actions'] = [
        'label' => E::ts('Time tracking:'),
        'value' => '<div>' . $actions_html . '</div>',
      ];

      $summary['timetrack_billing_status'] = [
        'label' => E::ts('Billing status:'),
        'value' => E::ts('%1 unbilled hour(s)', [1 => CRM_Timetrack_Utils::roundUpSeconds($this->getUnbilledHours($case_id))]),
      ];

      $summary['timetrack_irc_alias'] = [
        'label' => E::ts('Task alias:'),
        'value' => ($dao->alias ? $dao->alias : ts('n/a')),
      ];
    }
    else {
      // TODO: we should probably have a way to enable/disable timetracking per case type.
      // so that if we don't find any info, it's perfectly normal to have an option to edit.
      $summary['timetrack_warning'] = [
        'label' => E::ts('Timetrack:'),
        'value' => 'No timetracking information was found for this case.',
      ];
    }

    $url = CRM_Utils_System::url('civicrm/timetrack/case', ['reset' => 1, 'cid' => $case_id]);

    $summary['timetrack_edit'] = [
      'label' => '',
      'value' => "<div><a href='{$url}' class='crm-popup'><span><div class='icon ui-icon-pencil'></div>" . ts('Edit') . "</span></a></div>",
    ];

    $summary['timetrack_tasks'] = [
      'label' => '',
      'value' => $this->getListOfTasks($case_id),
    ];

    $summary['timetrack_invoices'] = [
      'label' => '',
      'value' => $this->getListOfInvoice($case_id),
    ];

    $summary['timetrack_invoice_task_overview'] = [
      'label' => '',
      'value' => $this->getInvoiceTaskOverview($case_id),
    ];

    return $summary;
  }

  public function getListOfTasks($case_id) {
    $smarty = CRM_Core_Smarty::singleton();

    $smarty->assign('timetrack_header_idcss', 'caseview-tasks');
    $smarty->assign('timetrack_header_title', ts('Tasks', ['domain' => 'coop.symbiotic.timetrack']));

    $csid = $this->getPunchesCSID();
    $taskStatuses = CRM_Timetrack_PseudoConstant::getTaskStatuses();

    $headers = [
      'title' => E::ts('Task'),
      'estimate' => E::ts('Estimate'),
      'total_included' => E::ts('Punches'),
      'percent_done' => E::ts('% done'),
      'state' => E::ts('Status'),
      'begin' => E::ts('Begin'),
      'end' => E::ts('End'),
    ];

    $smarty->assign('timetrack_headers', $headers);

    $rows = [];

    $total = [
      'title' => E::ts('Total'),
      'estimate' => 0,
      'total_included' => 0,
      'percent' => '',
      'state' => '',
      'begin' => '',
      'end' => '',
    ];

    $result = civicrm_api3('Timetracktask', 'get', [
      'case_id' => $case_id,
      'skip_open_case_check' => 1,
      'option.limit' => 0,
    ]);

    foreach ($result['values'] as $task) {
      $included_hours = CRM_Timetrack_Utils::roundUpSeconds($task['total_included'], 1);
      $percent_done = '';

      if ($task['estimate']) {
        $percent_done = round($included_hours / $task['estimate'] * 100) . '%';
      }

      $view_punches_url = CRM_Utils_System::url('civicrm/contact/search/custom', ['csid' => $csid, 'case_id' => $case_id, 'ktask' => $task['task_id'], 'force' => 1, 'crmSID' => '6_d']);
      $view_task_url = CRM_Utils_System::url('civicrm/timetrack/task', ['tid' => $task['task_id']]);

      $rows[] = [
        'title' => '<a class="crm-popup" href="' . $view_task_url . '">' . htmlspecialchars($task['title']) . '</a>',
        'description' => $task['description'],
        'estimate' => $task['estimate'],
        'total_included' => '<a class="crm-popup" href="' . $view_punches_url . '">' . $included_hours . '</a>',
        'percent_done' => $percent_done,
        'state' => $taskStatuses[$task['state']],
        'begin' => substr($task['begin'], 0, 10), // TODO format date l10n
        'end' => substr($task['end'], 0, 10), // TODO format date l10n
      ];

      $total['estimate'] += $task['estimate'] ?: 0;
      $total['total_included'] += $task['total_included'] ?: 0;
    }

    $total['total_included'] = CRM_Timetrack_Utils::roundUpSeconds($total['total_included'], 1);
    $total['percent_done'] = ($total['estimate'] ? round($total['total_included'] / $total['estimate'] * 100) : '');

    $rows[] = $total;

    $smarty->assign('timetrack_rows', $rows);
    return $smarty->fetch('CRM/Timetrack/Page/Snippets/AccordionTable.tpl');
  }

  public function getListOfInvoice($case_id) {
    $smarty = CRM_Core_Smarty::singleton();

    $csid = $this->getPunchesCSID();
    $smarty->assign('timetrack_header_idcss', 'caseview-invoices');
    $smarty->assign('timetrack_header_title', ts('Invoices', ['domain' => 'coop.symbiotic.timetrack']));

    // FIXME ts() domain.
    $headers = [
      'ledger_id' => ts('Ledger ID', ['domain' => 'coop.symbiotic.timetrack']),
      'created_date' => ts('Invoice date', ['domain' => 'coop.symbiotic.timetrack']),
      'total' => ts('Total punches', ['domain' => 'coop.symbiotic.timetrack']),
      'invoiced' => ts('Invoiced', ['domain' => 'coop.symbiotic.timetrack']),
      'invoiced_pct' => ts('% invoiced', ['domain' => 'coop.symbiotic.timetrack']),
      'state' => ts('Status', ['domain' => 'coop.symbiotic.timetrack']),
      'deposit_date' => ts('Deposit', ['domain' => 'coop.symbiotic.timetrack']),
      'deposit_reference' => ts('Reference', ['domain' => 'coop.symbiotic.timetrack']),
      'generate' => ts('Actions', ['domain' => 'coop.symbiotic.timetrack']),
    ];

    $smarty->assign('timetrack_headers', $headers);

    $rows = [];

    $result = civicrm_api3('Timetrackinvoice', 'get', [
      'case_id' => $case_id,
      'option.limit' => 0,
    ]);

    $invoice_status_options = civicrm_api3('Timetrackinvoice', 'getoptions', [
      'field' => 'state',
      'option.limit' => 0,
    ]);

    foreach ($result['values'] as $invoice) {
      $included_hours = CRM_Timetrack_Utils::roundUpSeconds($invoice['total_included'], 1);

      $rows[] = [
        'ledger_id' => CRM_Utils_System::href($invoice['ledger_bill_id'], 'civicrm/timetrack/invoice', ['invoice_id' => $invoice['invoice_id']]),
        'created_date' => substr($invoice['created_date'], 0, 10),
        'total' => $included_hours,
        'invoiced' => $invoice['hours_billed'], // already in hours
        'invoiced_pct' => ($included_hours > 0 ? round($invoice['hours_billed'] / $included_hours * 100, 2) : 0) . '%',
        'state' => "<div class='crm-entity' data-entity='Timetrackinvoice' data-id='{$invoice['id']}'>"
          . "<div class='crm-editable' data-type='select' data-field='state'>" . $invoice_status_options['values'][$invoice['state']] . '</div>'
          . '</div>',
        'deposit_date' => "<div class='crm-entity' data-entity='Timetrackinvoice' data-id='{$invoice['id']}'>"
          . "<div class='crm-editable' data-type='text' data-field='deposit_date'>" . substr($invoice['deposit_date'], 0, 10) . '</div>'
          . '</div>',
        'deposit_reference' => "<div class='crm-entity' data-entity='Timetrackinvoice' data-id='{$invoice['id']}'>"
          . "<div class='crm-editable' data-type='text' data-field='deposit_reference'>" . $invoice['deposit_reference'] . '</div>'
          . '</div>',
        'generate' => CRM_Utils_System::href('<i class="fa fa-search" aria-hidden="true" title="' . E::ts('View punches', ['escape' => 'js']) . '"></i>', 'civicrm/contact/search/custom', ['csid' => $csid, 'case_id' => $case_id, 'invoice_id' => $invoice['invoice_id'], 'force' => 1, 'crmSID' => '6_d']) . ' '
          . CRM_Utils_System::href('<i class="fa fa-pencil" aria-hidden="true" title="' . E::ts('Edit invoice', ['escape' => 'js']) . '"></i>', 'civicrm/timetrack/invoice', ['invoice_id' => $invoice['invoice_id']]) . ' '
          . ' ' . CRM_Utils_System::href('<i class="fa fa-file-word-o" aria-hidden="true" title="' . E::ts('Export invoice as a text document', ['escape' => 'js']) . '"></i>', 'civicrm/timetrack/invoice/generate', ['invoice_id' => $invoice['invoice_id']]) . ' '
          . ' ' . CRM_Utils_System::href('<i class="fa fa-files-o" aria-hidden="true" title="' . E::ts('Copy invoice as new', ['escape' => 'js']) . '"></i>', 'civicrm/timetrack/invoice', ['invoice_id' => $invoice['invoice_id'], 'action' => 'clone'])
      ];
    }

    $smarty->assign('timetrack_rows', $rows);

    return $smarty->fetch('CRM/Timetrack/Page/Snippets/AccordionTable.tpl');
  }

  /**
   * Returns a rendered HTML table overview of the invoicing, per task.
   */
  public function getInvoiceTaskOverview($case_id) {
    $smarty = CRM_Core_Smarty::singleton();

    $smarty->assign('timetrack_header_idcss', 'caseview-invoice-task-recap');
    $smarty->assign('timetrack_header_title', E::ts('Invoicing, per task'));

    $rows = [];

    $headers = [
      'title' => E::ts('Task'),
      'estimate' => E::ts('Estimate'),
    ];

    $rows = [];
    $tasks = [];

    // Fetch all tasks on the project, to make sure we list them all in the overview,
    // not just list tasks that have been invoiced already.
    $result = civicrm_api3('Timetracktask', 'get', [
      'case_id' => $case_id,
      'skip_open_case_check' => 1,
      'option.limit' => 0,
    ]);

    foreach ($result['values'] as $key => $val) {
      $rows[$key] = [
        'title' => $val['title'],
        'estimate' => $val['estimate'],
        'total' => 0,
      ];
    }

    $total = [
      'title' => E::ts('Total'),
      'estimate' => 0,
    ];

    $dao = CRM_Core_DAO::executeQuery('SELECT o.ledger_bill_id, o.title, t.title as ktask_title, t.estimate, t.id as ktask_id, l.hours_billed
      FROM korder_line l
      INNER JOIN korder o ON (o.id = l.order_id)
      INNER JOIN ktask t ON (t.id = l.ktask_id)
      WHERE t.case_id = %1
      GROUP BY t.id, o.id
      ORDER BY o.id ASC', [
      1 => [$case_id, 'Positive'],
    ]);

    while ($dao->fetch()) {
      $headers[$dao->ledger_bill_id] = '#' . $dao->ledger_bill_id;

      if (!isset($total[$dao->ktask_id])) {
        $total[$dao->ktask_id] = 0;
      }

      if (!isset($tasks[$dao->ktask_id])) {
        $tasks[$dao->ktask_id] = 0;
      }

      if (!isset($total[$dao->ledger_bill_id])) {
        $total[$dao->ledger_bill_id] = 0;
      }

      $total[$dao->ledger_bill_id] += $dao->hours_billed;

      $rows[$dao->ktask_id][$dao->ledger_bill_id] = $dao->hours_billed;
      $tasks[$dao->ktask_id] += $dao->hours_billed;
    }

    $headers['total'] = E::ts('Total');
    $headers['available'] = E::ts('Available');

    // Calculate the total time invoiced, per task
    foreach ($tasks as $key => $val) {
      $rows[$key]['total'] = $val;
    }

    // Calculate the total estimates, per task
    // as well as the available budget left.
    foreach ($rows as $key => $val) {
      $total['estimate'] += $val['estimate'] ?: 0;
      $rows[$key]['available'] = ($val['estimate'] ?: 0) - $val['total'];
    }

    // Now calculate the total of totals, and total available budget.
    $total['total'] = 0;
    $total['available'] = 0;

    foreach ($rows as $key => $val) {
      $total['total'] += $val['total'];
      $total['available'] += $val['available'];
    }

    $rows[] = $total;

    $smarty->assign('timetrack_headers', $headers);
    $smarty->assign('timetrack_rows', $rows);
    return $smarty->fetch('CRM/Timetrack/Page/Snippets/AccordionTable.tpl');
  }

  public function getUnbilledHours($case_id) {
    // TODO: move to API ?
    $dao = CRM_Core_DAO::executeQuery('SELECT sum(duration) as total
      FROM kpunch
      INNER JOIN ktask on (ktask.id = kpunch.ktask_id AND ktask.case_id = %1)
      WHERE korder_id is NULL', [
      1 => [$case_id, 'Positive'],
    ]);

    $dao->fetch();
    return $dao->total;
  }

  /**
   * Returns (and caches) the Custom Search ID for the TimetrackPunches search.
   */
  private function getPunchesCSID() {
    if ($this->csid) {
      return $this->csid;
    }

    $this->csid = civicrm_api3('CustomSearch', 'getsingle', [
      'name' => 'CRM_Timetrack_Form_Search_TimetrackPunches',
      'return' => 'value',
    ])['value'];

    return $this->csid;
  }

}
