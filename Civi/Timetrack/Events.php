<?php

namespace Civi\Timetrack;

use \Civi\DataExplorer\Event\DataExplorerEvent;
use CRM_Timetrack_ExtensionUtil as E;

class Events {

  static public function fireDataExplorerBoot(DataExplorerEvent $event) {
    $sources = $event->getDataSources();

    $sources['punches'] = [
      'label' => E::ts('Punches'),
      'items' => [
        'punchs-duration' => E::ts('Punch duration'),
      ],
    ];

    $event->setDataSources($sources);
  }

}
