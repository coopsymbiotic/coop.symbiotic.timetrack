CRM.$(function($) {
  /**
   * When the hours billed or the hourly rate/cost is changed, update the line item total.
   */
  $('.crm-timetrack-lineitem-hoursbilled input').on('change', function() {
    timetrackRecalculateTotal(cj(this));
  });

  $('.crm-timetrack-lineitem-cost input').on('change', function() {
    timetrackRecalculateTotal(cj(this));
  });

  function timetrackRecalculateTotal($this) {
    var hoursbilled = parseFloat($this.closest('tr').find('.crm-timetrack-lineitem-hoursbilled input').val());
    var unitcost = parseFloat($this.closest('tr').find('.crm-timetrack-lineitem-cost input').val());

    var total = Math.round(hoursbilled * unitcost * 100) / 100;
    $this.closest('tr').find('.crm-timetrack-lineitem-amount input').val(total);
  }
});
