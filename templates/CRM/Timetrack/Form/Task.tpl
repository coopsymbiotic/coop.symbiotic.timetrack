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

{literal}
<script>
  cj(function() {
    // TODO: adapted from templates/CRM/Custom/Form/ContactReference.tpl
    // There is probably a better way of doing this?
    var url = {/literal}"{crmURL p='civicrm/ajax/rest' q='className=CRM_Contact_Page_AJAX&fnName=getContactList&json=1' h=0}"{literal};

    cj('#leadautocomplete').autocomplete(url, {
      width : 250,
      selectFirst : false,
      elementId: '#leadautocomplete',
      matchContains: true,
      formatResult: {/literal}validate{$element_name|replace:']':''|replace:'[':'_'|replace:'-':'_'}{literal},
      max: {/literal}{crmSetting name="search_autocomplete_count" group="Search Preferences"}{literal}
    }).result(function(event, data) {
      cj('input[name="lead"]').val(data[1]);
    });

    cj('#leadautocomplete').click(function() {
      cj(this).val('');
    });
  });

  function validate{/literal}{$element_name|replace:']':''|replace:'[':'_'|replace:'-':'_'}{literal}( Data, position ) {
    if ( Data[1] == 'error' ) {
      cj(this.elementId).parent().append("<span id='"+ (this.elementId).substr(1) +"_error' class='hiddenElement messages crm-error'>" + "{/literal}{ts escape='js'}Invalid parameters for contact search.{/ts}{literal}" + "</span>");
      cj(this.elementId + '_error').fadeIn(800).fadeOut(5000, function( ){ cj(this).remove(); });
      Data[1] = '';
    }
  }

</script>
{/literal}
