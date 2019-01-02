<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.invoice_from_id.label}</div>
  <div class="content">{$form.invoice_from_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.client_name.label}</div>
  <div class="content">{$form.client_name.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.title.label}</div>
  <div class="content">{$form.title.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.invoice_period_start.label}</div>
  <div class="content">{$form.invoice_period_start.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.invoice_period_end.label}</div>
  <div class="content">{$form.invoice_period_end.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.created_date.label}</div>
  <div class="content">{$form.created_date.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.total_punches.label}</div>
  <div class="content">{$form.total_punches.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.ledger_order_id.label}</div>
  <div class="content">{$form.ledger_order_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.ledger_bill_id.label}</div>
  <div class="content">{$form.ledger_bill_id.html}</div>
  <div class="clear"></div>
</div>

<table>
  <thead>
    <tr>
      <th>{ts}Task{/ts}</th>
      {if !$timetrack_invoice_options.invoice_other_only}
        <th>{ts}Hours{/ts}</th>
      {/if}
      <th>{ts}Quantity{/ts}</th>
      <th>{ts}Unit{/ts}</th>
      <th>{ts}Cost{/ts}</th>
      <th>{ts}Amount{/ts}</th>
    </tr>
  </thead>
  <tbody>
    {foreach item=task key=foo from=$invoice_tasks}
      {assign var="title" value='task_'|cat:$foo|cat:'_title'}
      {assign var="hours" value='task_'|cat:$foo|cat:'_hours'}
      {assign var="hoursbilled" value='task_'|cat:$foo|cat:'_hours_billed'}
      {assign var="unit" value='task_'|cat:$foo|cat:'_unit'}
      {assign var="cost" value='task_'|cat:$foo|cat:'_cost'}
      {assign var="amount" value='task_'|cat:$foo|cat:'_amount'}
      <tr>
        <td class="crm-timetrack-lineitem-title">{$form.$title.html}</td>
        {if !$timetrack_invoice_options.invoice_other_only}
          <td class="crm-timetrack-lineitem-hours">{$form.$hours.html}</td>
        {/if}
        <td class="crm-timetrack-lineitem-hoursbilled">{$form.$hoursbilled.html}</td>
        <td class="crm-timetrack-lineitem-unit">{$form.$unit.html}</td>
        <td class="crm-timetrack-lineitem-cost">{$form.$cost.html}</td>
        <td class="crm-timetrack-lineitem-amount">{$form.$amount.html}</td>
      </tr>
    {/foreach}
  </tbody>
</table>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.deposit_date.label}</div>
  <div class="content">{$form.deposit_date.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.state.label}</div>
  <div class="content">{$form.state.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.deposit_reference.label}</div>
  <div class="content">{$form.deposit_reference.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.details_public.label}</div>
  <div class="content">{$form.details_public.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.details_private.label}</div>
  <div class="content">{$form.details_private.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-buttons">
  {$form.buttons.html}
</div>
