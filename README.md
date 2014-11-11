Timetrack
=========

General idea: you have activities and can track time with activities.
However, some activities are done over various moments in a day/week/month,
and you want to track your time in a more granular way.

This extension adds a new entity for "punches", which have a begin date/time,
duration and comment. The punches are linked to tasks/activities of a case.

Punches can then be invoiced to the client (making it easy to know which work
has been invoied or not). The extension can generate invoices in OpenDocument
(odt) format using the tinybutstrong library (see "Invoicing").

Timetrack also includes reports and new APIs to manipulate the punches.

To download the latest version of this module:  
https://github.com/mlutfy/ca.bidon.timetrack

Status
======

This extension is not usable out of the box. It still piggy-backs on a few
features from "kproject" (see "History").

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

The "order" in kproject becomes an "invoice" in CiviCRM. We deprecated the
idea of tracking 'work orders'. The task estimates should be seen as the
work orders.

For reference:
https://www.drupal.org/project/kproject

Invoicing
=========

To keep it simple, since invoices can be quite an art and need to look good,
Timetrack takes an ordinary OpenDocument file (odt) as a template in order to
generate invoices.

Your OpenDocument file can have the following tokens that will be filled-in by
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

Advice: LibreOffice/OpenOffice might wrap your text in a "span", depending on
how you entered the text. Type the tokens all at once. If you have to delete
a typo, restart from the beginning. Otherwise, the template engine might not
be able to recognize the token, such as: "[t.<span>title</span>]". If in
doubt, unzip the .odt file and inspect it with a text editor.

Status & Todo
=============

WARNING: this extension is highly experimental. At the moment, it still relies
heavily on a patched version of kproject. You still need to create a new 'contract'
from the kproject UI (node/add/kcontract), then create a Case in CiviCRM, and link
the two, by putting the node ID of the contract in a specific custom field.

Tasks and punches:

* Convert the contracts (kcontract) to be linked to civicrm_case, instead of node.
* Punches should refer to the contact_id, instead of Drupal uid.

Invoices:

* Add JS to recalculate row-totals automatically (qty*unit)
* Have a way to list the punch details for a task? (popup?)
* Have a per-task default cost (hourly fee), currently hardcoded.
* Add a "public note" field, for a text to add on the invoice sent to the client?
* Add a "private note" field, for internal notes? (not shown on the final invoice)
* Rename the "hours_billed" in the DB to just "qty".

Misc:

* Convert all unix timestamp fields to mysql datetime (ex: task begin/end, punch begin).
* Config UI for the invoice template file (currently the path of the template is hardcoded).

General assumptions that might need fixing:

* Assuming that cases have only 1 client contact, ex: api/v3/Timetrackinvoice.php get.

Support
=======

Please post bug reports in the issue tracker of this project on github:  
https://github.com/mlutfy/ca.bidon.timetrack/issues

For general questions and support, please post on the "Extensions" forum:  
https://forum.civicrm.org/index.php/board,57.0.html

This is a community contributed extension written thanks to the financial
support of organisations using it, as well as the very helpful and collaborative
CiviCRM community.

If you appreciate this module, please consider donating 10$ to the CiviCRM project:  
http://civicrm.org/participate/support-civicrm

While I do my best to provide volunteer support for this extension, please
consider financially contributing to support or development of this extension
if you can.

Commercial support via Coop SymbioTIC:  
https://www.symbiotic.coop

Or you can send me the equivalent of a beer:  
https://www.bidon.ca/en/paypal

License
=======

Distributed under the terms of the GNU Affero General public license (AGPL).
See LICENSE.txt for details.

(C) 2014 Mathieu Lutfy <mathieu@bidon.ca>

Includes code based on "kproject"  
https://drupal.org/project/kproject  
https://redmine.koumbit.net/projects/kproject

(C) 2008-2011 Yann Rocq  
(C) 2008-2011 Samuel Vanhove

Bundles the "tinybutstrong" library and the "openbts" plugin, distributed
under the terms of the GNU Lesser General public license (LGPL).  
http://www.tinybutstrong.com/  
http://www.tinybutstrong.com/support.php#licence
