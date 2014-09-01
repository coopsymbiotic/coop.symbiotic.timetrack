<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC/Hook+Reference
return array (
  0 => array (
    'name' => 'CRM_Timetrack_Report_Form_TimetrackDetails',
    'entity' => 'ReportTemplate',
    'params' => array (
      'version' => 3,
      'label' => "Timetrack details",
      'description' => "Timetrack details report.",
      'class_name' => 'CRM_Timetrack_Report_Form_TimetrackDetails',
      'report_url' => 'timetrack-details',
      'component' => '',
    ),
  ),
);
