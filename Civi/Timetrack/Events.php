<?php

namespace Civi\Timetrack;

use \Civi\DataExplorer\Event\DataExplorerEvent;
use CRM_Timetrack_ExtensionUtil as E;

class Events {

  static public function fireDataExplorerBoot(DataExplorerEvent $event) {
    $sources = $event->getDataSources();
    $sources['punch'] = E::ts('Timetrack Punchs');
    $sources['invoice'] = E::ts('Timetrack Invoices');
    $event->setDataSources($sources);

    $filters = $event->getFilters();
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
    $event->setFilters($filters);
  }

}
