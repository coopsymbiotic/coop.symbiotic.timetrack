Timetrack
=========

General idea: you have activities and can track time with activities.
However, some activities are done over various moments in a day/week/month,
and you want to track your time in a more granular way.

This extension adds a new entity for "punches", which have a begin date/time,
duration and comment. The punches are linked to tasks/activities of a case.

Timetrack also includes reports and new APIs to manipulate the punches.

To download the latest version of this module:
https://github.com/mlutfy/ca.bidon.timetrack

Requirements
============

- CiviCRM >= 4.4 (previous versions untested)

Installation
============

Install as any other regular CiviCRM extension:

1- Download this extension and unpack it in your 'extensions' directory.
   You may need to create it if it does not already exist, and configure
   the correct path in CiviCRM -> Administer -> System -> Directories.

2- Enable the extension from CiviCRM -> Administer -> System -> Extensions.

History
=======

Timetrack is a fork/rewrite of "kproject", a time management tool written by
Koumbit. It was written as a Drupal module, and was an awesome time tracker,
which included an IRC bot and a few planning features suitable for small-medium
organisations.

However, it was mostly used only by Koumbit, and they did not upgrade it to
Drupal 7. Since kproject implemented many CRM-ish features that are present in
CiviCRM, causing some duplication of information (list of clients, contact
information), this extension attempts to implement in CiviCRM some of the
features of kproject.

The "client" in kproject becomes a "contact" in CiviCRM (and you can create
new entity sub-types in CiviCRM, such as 'Clients' based off the 'Organisation'
entity).

The "contract" in kproject becomes a "case" in CiviCRM. You can create different
case types depending on your type of contracts (ex: consultation, support), and
you can create standart timelines for them.

The "task" in kproject becomes an "activity" in CiviCRM (not implemented yet).
They provide a general idea of the start/end work period on a specific issue.

The "punch" in kproject is kept as-is in CiviCRM, as a custom entity.
It provides granular information on the work done.

For reference:
https://www.drupal.org/project/kproject

Status & Todo
=============

WARNING: this extension is highly experimental. At the moment, it still relies
heavily on a patched version of kproject. You still need to create a new 'contract'
from the kproject UI (node/add/kcontract), then create a Case in CiviCRM, and link
the two, by putting the node ID of the contract in a specific custom field.

Todo:

* Convert the tasks to case activities (including the start/end dates, estimate,
  task category, lead and state).
* UI to add new/edit punches, linked to an activity.
* UI for billing of punches, creating new invoices (c.f. 'korder' sub-module of kproject).

Support
=======

Please post bug reports in the issue tracker of this project on github:
https://github.com/mlutfy/ca.bidon.timetrack/issues

For general questions and support, please post on the "Extensions" forum:
http://forum.civicrm.org/index.php/board,57.0.html

This is a community contributed extension written thanks to the financial
support of organisations using it, as well as the very helpful and collaborative
CiviCRM community.

If you appreciate this module, please consider donating 10$ to the CiviCRM project:
http://civicrm.org/participate/support-civicrm

While I do my best to provide volunteer support for this extension, please
consider financially contributing to support or development of this extension
if you can.

Commercial support via Coop SymbioTIC: <https://www.symbiotic.coop>

Or you can send me the equivalent of a beer: <https://www.bidon.ca/en/paypal>

License
=======

(C) 2014 Mathieu Lutfy <mathieu@bidon.ca>

Includes code based on kproject:

(C) 2008-2011 Yann Rocq
(C) 2008-2011 Samuel Vanhove

Distributed under the terms of the GNU Affero General public license (AGPL).
See LICENSE.txt for details.
