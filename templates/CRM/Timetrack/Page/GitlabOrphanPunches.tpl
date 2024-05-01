{crmScope extensionKey="coop.symbiotic.timetrack"}
  <table>
    <thead>
      <tr>
        <th>{ts}Project{/ts}</th>
        <th>{ts}Issue{/ts}</th>
        <th>{ts}Date{/ts}</th>
        <th>{ts}Duration{/ts}</th>
        <th>{ts}Punch ID{/ts}</th>
      </tr>
    </thead>
    {foreach from=$punches item=punch}
      <tr>
        <td><a href="{$punch.project.web_url}" target="_blank">{$punch.project.name}</a></td>
        <td>{$punch.issue.title}</td>
        <td>{$punch.begin}</td>
        <td>{$punch.duration}</td>
        <td>{$punch.timetrack_id}</td>
      </tr>
    {/foreach}
  </table>
{/crmScope}
