<?php

namespace Civi\Timetrack;

use \Civi\DataExplorer\Event\DataExplorerEvent;
use CRM_Timetrack_ExtensionUtil as E;

class Events {

  static public function fireDataExplorerBoot(DataExplorerEvent $event) {
    $sources = $event->getDataSources();
    $sources['punch'] = E::ts('Timetrack Punch duration');
    $sources['invoice'] = E::ts('Timetrack Hours invoiced');
    $event->setDataSources($sources);
  }

}
