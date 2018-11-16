CRM.$(function($) {
  // Mainly copied from the sample in:
  // dist/dhtmlxscheduler/samples/06_timeline/02_lines.html

  scheduler.locale.labels.timeline_tab = "Timeline";
  scheduler.locale.labels.section_contact_id = "Contact";
  scheduler.locale.labels.section_ktask_id = "Task";
  scheduler.config.details_on_create = true;
  scheduler.config.details_on_dblclick = true;
  scheduler.config.xml_date = "%Y-%m-%d %H:%i";
  scheduler.config.show_loading = true;

  var sections = [];
  var contact_ids = [];

  scheduler.createTimelineView({
    name: "timeline",
    x_unit: "minute",
    x_date: "%H:%i",
    x_step: 30,
    x_size: 24,
    x_start: 16,
    x_length: 48,
    y_unit: CRM.timetrack.users,
    y_property: "contact_id",
    render: "bar"
  });

  scheduler.config.lightbox.sections = [
    { name: "contact_id", height: 23, type: "select", options: CRM.timetrack.users, map_to: "contact_id" },
    { name: "ktask_id", height: 23, type: "select", options: CRM.timetrack.tasks, map_to: "ktask_id" },
    { name: "description", height: 130, map_to: "text", type: "textarea" , focus: true },
    { name: "time", height: 72, type: "time", map_to: "auto" }
  ];

  // http://stackoverflow.com/questions/3066586/get-string-in-yyyymmdd-format-from-js-date-object
  Date.prototype.yyyymmdd = function() {
    var yyyy = this.getFullYear().toString();
    var mm = (this.getMonth()+1).toString(); // getMonth() is zero-based
    var dd  = this.getDate().toString();
    return yyyy + (mm[1]?mm:"0"+mm[0]) + (dd[1]?dd:"0"+dd[0]); // padding
  };

  d = new Date();
  scheduler.init('timetrackscheduler', d, "timeline");

  // Load data dynamically, one week at the time.
  scheduler.setLoadMode('week');
  scheduler.load(CRM.url('civicrm/timetrack/timeline-data'), 'json');

  // Handling of new punches or edits (events on the timeline).
  // If we don't append a '?', it will suffix an ID at the end of the URL, causing a 404,
  // because it assumes a normal REST API, ex: /api/v1/punch/1234
  // The ID is also useful when deleting a punch.
  var dp = new dataProcessor(CRM.url('civicrm/timetrack/timeline-data') + '?id=');
  dp.init(scheduler);
  dp.setTransactionMode("REST");

  dp.attachEvent("onAfterUpdate", function(id, action, tid, response) {
    if (action == 'error') {
      CRM.alert(response.error_message, ts('Error'), 'error');

      scheduler.getEvent(tid)._text_style = 'color: black; background: red;';
      scheduler.updateEvent(tid);
    }
    else if (action == 'deleted') {
      CRM.alert(ts('Punch #%1 has been deleted.', {1: tid}), ts('Deleted'), 'success');
    }
    else if (action == 'updated') {
      CRM.alert(ts('Punch #%1 has been updated.', {1: tid}), ts('Updated'), 'success');
    }
    else if (action == 'inserted') {
      CRM.alert(ts('Punch #%1 has been added.', {1: tid}), ts('Added'), 'success');
    }
    else {
      CRM.alert(ts('Unexpected action %1', {1: action}), ts('Warning'), 'warning');
      console.log(response);
    }
  });

});
