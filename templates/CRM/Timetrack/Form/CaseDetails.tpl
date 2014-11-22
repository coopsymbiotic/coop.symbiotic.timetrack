<div class="timetrack-case-details">
  <div class="crm-section">
    <div class="label">{$form.alias.label}</div>
    <div class="content">{$form.alias.html} <span class="description">for punching on IRC/chat</span></div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.estimate.label}</div>
    <div class="content">{$form.estimate.html} <span class="description">hours</span></div>
    <div class="clear"></div>
  </div>
</div>

{* TODO: remove this in 4.5, when we can place in form-body ? *}
{literal}
<script type="text/javascript">
  cj(function() {
    cj('form#Case > .crm-case-form-block > table.form-layout').after(cj('.timetrack-case-details'));
  });
</script>
{/literal}
