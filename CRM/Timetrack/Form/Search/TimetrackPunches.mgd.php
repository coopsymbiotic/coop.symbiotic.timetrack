<?php
// This file declares a managed database record of type "CustomSearch".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC/Hook+Reference
return [
  0 => [
    'name' => 'CRM_Timetrack_Form_Search_TimetrackPunches',
    'entity' => 'CustomSearch',
    'params' => [
      'version' => 3,
      'label' => 'Timetrack punches',
      'description' => 'Timetrack punches',
      'class_name' => 'CRM_Timetrack_Form_Search_TimetrackPunches',
    ],
  ],
];
