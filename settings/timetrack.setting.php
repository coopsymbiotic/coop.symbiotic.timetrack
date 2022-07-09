<?php

use CRM_Timetrack_ExtensionUtil as E;

return [
  'timetrack_help_url' => [
    'group_name' => 'domain',
    'group' => 'timetrack',
    'name' => 'timetrack_help_url',
    'type' => 'String',
    'html_type' => 'text',
    'default' => null,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Help URL on the Import Punches form'),
    'description' => E::ts('Link to direct users to a help page with more information, guidelines, etc.'),
    'settings_pages' => [
      'timetrack' => [
        'weight' => 5,
      ]
    ],
  ],
  'timetrack_hourly_rate_default' => [
    'group_name' => 'domain',
    'group' => 'timetrack',
    'name' => 'timetrack_hourly_rate_default',
    'type' => 'Float',
    'html_type' => 'text',
    'default' => 100,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Default hourly rate'),
    'description' => E::ts('Default hourly rate when creating cases (or if there is no case custom field set).'),
    'settings_pages' => [
      'timetrack' => [
        'weight' => 10,
      ]
    ],
  ],
  'timetrack_hourly_rate_cfid' => [
    'group_name' => 'domain',
    'group' => 'timetrack',
    'name' => 'timetrack_hourly_rate_cfid',
    'type' => 'Integer',
    'html_type' => 'text',
    'default' => null,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Hourly rate custom field ID'),
    'description' => E::ts('You can create a integer/text custom field on cases to set a per-case rate. Enter the custom field ID (the ID shown in the location bar when editing the custom field).'),
    'settings_pages' => [
      'timetrack' => [
        'weight' => 15,
      ]
    ],
  ],
  'timetrack_mattermost_slash_token' => [
    'group_name' => 'domain',
    'group' => 'timetrack',
    'name' => 'timetrack_mattermost_slash_token',
    'type' => 'String',
    'html_type' => 'text',
    'default' => '',
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Mattermost Slash Command Token'),
    'description' => E::ts('Validation token for slash commands sent from Mattermost.'),
    'settings_pages' => [
      'timetrack' => [
        'weight' => 20,
      ],
    ],
  ],
  'timetrack_gitlab_url' => [
    'group_name' => 'domain',
    'group' => 'timetrack',
    'name' => 'timetrack_gitlab_url',
    'type' => 'String',
    'html_type' => 'text',
    'default' => '',
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Gitlab URL'),
    'description' => E::ts('Gitlab URL (ex: https://lab.example.org) if using auto-responder.'),
    'settings_pages' => [
      'timetrack' => [
        'weight' => 30,
      ],
    ],
  ],
  'timetrack_invoice_filename_prefix' => [
    'group_name' => 'domain',
    'group' => 'timetrack',
    'name' => 'timetrack_invoice_filename_prefix',
    'type' => 'String',
    'html_type' => 'text',
    'default' => 'invoice',
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('PDF Invoice filename prefix'),
    'description' => E::ts('By default, when a PDF is generated, it has the form invoice_[ledgerID]_[invoiceDate]_suffix.pdf'),
    'settings_pages' => [
      'timetrack' => [
        'weight' => 35,
      ],
    ],
  ],
  // @todo custom fields for case token and response
];
