<?php

namespace Civi\Timetrack;

use \Civi\DataExplorer\Event\DataExplorerEvent;
use CRM_Timetrack_ExtensionUtil as E;

class Events {

  static public function fireDataExplorerBoot(DataExplorerEvent $event) {
    $sources = $event->getDataSources();
    $filters = $event->getFilters();
    $groups = $event->getGroupBy();

    $sources['punch'] = E::ts('Timetrack Punchs');
    $sources['invoice'] = E::ts('Timetrack Invoices');

    $cases = \Civi\Api4\CiviCase::get(false)
      ->addSelect('id', 'subject')
      ->addOrderBy('subject', 'ASC')
      ->execute()
      ->indexBy('id')
      ->column('subject');

    $filters['punchinvoiced'] = [
      'type' => 'items',
      'label' => 'Punch Invoiced',
      'items' => [
        1 => ts('Yes'),
        2 => ts('No'),
      ],
      'depends' => [
        'punch',
      ],
    ];
    $filters['punchcase'] = [
      'type' => 'items',
      'label' => 'Punch in Case',
      'items' => $cases,
      'depends' => [
        'punch',
      ],
    ];

    $groups += [
      'timetrack' => [
        'type' => 'items',
        'label' => E::ts('Punch'),
        'items' => [
          'contact' => E::ts('Contact'),
          'case' => E::ts('Case'),
          'task' => E::ts('Task'),
        ],
      ],
      'depends' => [
        'punch',
      ],
    ];

    $event->setDataSources($sources);
    $event->setFilters($filters);
    $event->setGroupBy($groups);
  }

}
