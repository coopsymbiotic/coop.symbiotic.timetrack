{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">
      {if $elementName eq 'begin' OR $elementName eq 'end'}
        {include file="CRM/common/jcalendar.tpl"}
      {else}
        {$form.$elementName.html}
      {/if}
    </div>
    <div class="clear"></div>
  </div>
{/foreach}

{* FOOTER *}
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
