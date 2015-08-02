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
  var dp = new dataProcessor(CRM.url('civicrm/timetrack/timeline-data'));
  dp.init(scheduler);
  dp.setTransactionMode("POST");

  // Show feedback messages
  // TODO: might be cleaner to handle the actual save/create/delete from here?
  // (for better handling of error messages)
  scheduler.attachEvent("onEventDeleted", function(id, ev) {
    CRM.alert(ts('Punch #%1 has been deleted.', {1: ev.id}), ts('Deleted'), 'success');
  });

  scheduler.attachEvent("onEventChanged", function(id, ev) {
    CRM.alert(ts('The punch has been saved.'), ts('Saved'), 'success');
    return true;
  });

  scheduler.attachEvent("onEventAdded", function(id, ev){
    CRM.alert(ts('The punch has been saved.'), ts('Saved'), 'success');
    return true;
  });
});
