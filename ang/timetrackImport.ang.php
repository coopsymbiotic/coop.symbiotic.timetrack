<?php
// This file declares an Angular module which can be autoloaded
// in CiviCRM. See also:
// http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules

return [
  'js' => [
    'ang/timetrackImport.js',
    'ang/timetrackImport/*.js',
    'ang/timetrackImport/*/*.js',
  ],
  'css' => ['ang/timetrackImport.css'],
  'partials' => ['ang/timetrackImport'],
  'requires' => ['crmUi', 'crmUtil', 'ngRoute'],
  'settings' => [
    'timetrack_help_url' => Civi::settings()->get('timetrack_help_url'),
  ],
  'basePages' => [],
];
