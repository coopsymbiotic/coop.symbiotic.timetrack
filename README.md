Timetrack
=========

General idea: you have activities and can track time with activities.
However, you may have some activities are done over various moments in a
day/week/month, and you want to track your time in a more granular way.

This extension adds a new entities for "tasks" and "punches", which have
a begin date/time, duration and comment. The punches are linked to tasks
of a case.

Punches can then be invoiced to the client (making it easy to know which work
has been invoiced or not). The extension can generate invoices in OpenDocument
(odt) format using the tinybutstrong (opentbs) library (see "Invoicing").

Timetrack also includes reports, custom searches and new APIs to manipulate the
punches and make it easy to punch using 3rd-party systems, such as Mattermost.

Here are a few screenshots:

* https://www.bidon.ca/files/timetrack/timetrack-timeline.gif (warning: 2.5 MB)
* https://www.bidon.ca/files/timetrack/timetrack-week.jpg
* https://www.bidon.ca/files/timetrack/timetrack-search-inline-edit.gif (400 KB)
* https://www.bidon.ca/files/timetrack/timetrack-search-inline-edit2.gif (600 KB)

To download the latest version of this module:  
https://lab.civicrm.org/extensions/timetrack

Requirements
============

- CiviCRM 5.7+
- PHP 7.0+

Timetrack does not (yet) work with the CiviCase v5 extension.

Installation
============

Install as any other regular CiviCRM extension:

1- Download this extension and unpack it in your 'extensions' directory.
   You may need to create it if it does not already exist, and configure
   the correct path in CiviCRM -> Administer -> System -> Directories.

2- Enable the extension from CiviCRM -> Administer -> System -> Extensions.

History
=======

Timetrack is a partial rewrite of "kproject"[1], a time management tool written by
Koumbit.org. It was written as a Drupal 6 module, and was an awesome time
tracker, which included an IRC bot and a few planning features suitable for
small-medium organisations.

However, it was mostly used only by Koumbit, and they did not upgrade it to
Drupal 7. Since kproject implemented many CRM-ish features that are present in
CiviCRM, causing some duplication of information (list of clients, contact
information), this extension attempts to implement in CiviCRM some of the
features of kproject.

The "client" in kproject becomes a CiviCRM Contact, and the kproject contract
becomes a CiviCRM Case. In reality, Timetrack does not use many CiviCase features,
but it provides a good way to have multiple contracts for an organisation.

You can also create different case types depending on your type of contracts
(ex: consultation, support), and you can create standart timelines for them.

The "task", "punch" and "order" in kproject are mostly kept as is. Orders also
have an "order_line" in order to keep more granular tracking in invoices.

  [1] https://www.drupal.org/project/kproject

Punching using a bot
====================

The following assumes you generally understand how IRC and bots work.

IRC punching is done using the CiviCRM API. This extension provides
basic entities for 'Timetracktask', 'Timetrackpunch', etc.

Timetrack has been successfully tested with Mattermost using a Hubot
robot.

TODO: URL to Hubot script for Timetrack. Ping us if you need more information.

Invoicing
=========

Invoices can be quite an art and need to look good, Timetrack takes an ordinary
OpenDocument file (odt) as a template in order to generate invoices.

The OpenDocument file can have the following tokens that will be filled-in by
Timetrack. Since we are using the tinybutstrong library, the tokens have the
following syntax:

* `[var.ClientId]` ("contact ID" of the case contact)
* `[var.ClientName]` ("display name" of the case contact)
* `[var.ClientAddress1]` (primary address of the case contact)
* `[var.ClientAddress2]` (corresponds to "additional address 1" in CiviCRM)
* `[var.ClientAddress3]` (corresponds to "additional address 1" in CiviCRM)
* `[var.ClientCity]`
* `[var.ClientStateProvince]`
* `[var.ClientPostalCode]`
* `[var.LedgerId]` (ledger ID associated to an invoice)
* `[var.InvoiceId]` (auto-generated internal ID for invoices)
* `[var.InvoiceDate]`
* `[var.CaseId]`

The following tokens should be used in a row:

* `[t.title;block=table:table-row]`
* `[t.qty]`
* `[t.unit]`
* `[t.cost]`
* `[t.amount]`

The invoice subtotal can be found in [var.SubTotal].

Warning: LibreOffice/OpenOffice might wrap your text in a "span", depending on
how you entered the text. Type the tokens all at once. If you have to delete
a typo, restart from the beginning. Otherwise, the template engine might not
be able to recognize the token, such as: "[t.<span>title</span>]". If in
doubt, unzip the .odt file and inspect it with a text editor.

Finally, to define the location of your invoice template, you must define the
setting manually using the API:

```
$ drush cvapi Setting.create TimetrackInvoiceTemplateDefault='/path/to/invoice-template.odt';
```

The generator will respect the "preferred language" of the contact if you have
a template for that language. It must be defined using, for example:

```
$ drush cvapi Setting.create TimetrackInvoiceTemplateFR='/path/to/invoice-template-fr.odt';
```

where "FR" is extracted from the contact's preferred language (ex: "fr_CA" becomes "FR").

You can also use the API explorer (example.org/civicrm/api/explorer), instead of drush.

(yes, this is a bit weird, it was a quick hack and needs a UI)

Timetrack APIs
==============

TODO: needs documentation. See the 'api/v3' directory of this extension.

Status & Todo
=============

WARNING: this extension is highly experimental. I am trying to provide an
upgrade path from users of kproject, but not between experimental releases
of this extension. Below is a short list of things that need fixing.

Tasks and punches:

* [rc] Script to convert the contracts (kcontract) to be linked to civicrm_case, instead of node.
* [wishlist] Punching in a 'new' task should change it to 'open'.
* [wishlist] Quick punch form, not linked to a specific case.

Invoices:

* [rc] Rename the "hours_billed" in the DB to just "qty".
* [important] Have a per-task default cost (hourly fee), currently hardcoded.
* [wishlist] Have a way to list the punches specific to a task? (popup?)
* [wishlist] Add a "public note" field, for a text to add on the invoice sent to the client?
* [wishlist] Add a "private note" field, for internal notes? (not shown on the final invoice)

Misc:

* [rc] Implement "case merge" hook.
* [rc] Convert all unix timestamp fields to mysql datetime (ex: task begin/end, punch begin).
* [important] Config UI for the invoice template file (currently the path of the template is hardcoded).
* [wishlist] Bot integration (merge kpirc into bot_kproject?).
* [wishlist] Invoicing has some redundancy with CiviAccounts. Would be neat to integrate all that together.

General assumptions that might need fixing:

* Assuming that cases have only 1 client contact, ex: api/v3/Timetrackinvoice.php get.
* The system mostly works in hours. All durations displayed are usually in hours (as opposed to days).

Support
=======

Please post bug reports in the issue tracker of this project on CiviCRM's Gitlab:  
https://lab.civicrm.org/extensions/timetrack/issues

For general questions and support, please post on the CiviCRM Stack Exchange
and tag your question with "timetrack":  
http://civicrm.stackexchange.com

This extension was written thanks to the financial support of organisations
using it, as well as the very helpful and collaborative CiviCRM community.

While we do our best to provide volunteer support for this extension, please
consider financially contributing to support or development of this extension
if you can.

Support via Coop SymbioTIC:  
https://www.symbiotic.coop/en

License
=======

Distributed under the terms of the GNU Affero General public license (AGPL).
See LICENSE.txt for details.

(C) 2014-2019 Mathieu Lutfy <mathieu@bidon.ca>
(C) 2016-2019 Mathieu Lutfy <mathieu@symbiotic.coop>
(C) 2016-2019 Coop SymbioTIC <info@symbiotic.coop>

Includes code based on "kproject"  
https://drupal.org/project/kproject  

(C) 2008-2011 Yann Rocq  
(C) 2008-2011 Samuel Vanhove

Bundles the "tinybutstrong" library and the "openbts" plugin, distributed
under the terms of the GNU Lesser General public license (LGPL).  
http://www.tinybutstrong.com/  
http://www.tinybutstrong.com/support.php#licence

Bundles the "dhtmlxscheduler" library, distributed under the terms of the
GNU General Public License v2. (c) Dinamenta UAB  
http://dhtmlx.com/docs/products/dhtmlxScheduler/  
https://github.com/DHTMLX/scheduler/  
https://github.com/DHTMLX/scheduler/blob/master/license.txt
