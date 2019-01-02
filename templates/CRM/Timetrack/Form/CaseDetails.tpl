<div class="timetrack-case-details">
  <div class="crm-section">
    <div class="label">{$form.alias.label}</div>
    <div class="content">{$form.alias.html} <span class="description">{ts}for punching on IRC/chat{/ts}</span></div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.estimate.label}</div>
    <div class="content">{$form.estimate.html} <span class="description">{ts}hours{/ts}</span></div>
    <div class="clear"></div>
  </div>
</div>

{if $tplFile == 'CRM/Case/Form/Case.tpl'}
  {literal}
  <script type="text/javascript">
    CRM.$(function($) {
      $('form#Case > .crm-case-form-block > table.form-layout').after($('.timetrack-case-details'));
    });
  </script>
  {/literal}
{else}
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
{/if}
