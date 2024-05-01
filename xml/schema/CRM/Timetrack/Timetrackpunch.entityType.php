<?php
// This file declares a new entity type. For more details, see "hook_civicrm_entityTypes" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
return [
  [
    'name' => 'Timetrackpunch',
    'class' => 'CRM_Timetrack_DAO_Timetrackpunch',
    // @todo Rename the table one day
    'table' => 'kpunch',
  ],
];
