{crmScope extensionKey="coop.symbiotic.timetrack"}
  <h3>{ts 1=$display_name 2=$issues_count}Open Issues for %1: %2{/ts}</h3>

  {if $issues_count}
    <table>
      <thead>
        <tr>
          <th>{ts}Project{/ts}</th>
          <th>{ts}Title{/ts}</th>
          <th>{ts}Tags{/ts}</th>
          <th>{ts}Last Update{/ts}</th>
        </tr>
      </thead>
      {foreach from=$issues item=issue}
        <tr>
          <td><a href="{$issue.web_url}" target="_blank">{$issue.references.full}</a></td>
          <td>{$issue.title}</td>
          <td>{$issue.all_labels}</td>
          <td>{$issue.updated_at|substr:0:16}</td>
        </tr>
      {/foreach}
    </table>

    <h3>{ts 1=$project_count}Project Stats (%1){/ts}</h3>
    <table>
      {foreach from=$project_stats item=project}
        <tr>
          <td>{$project.nb_issues}</td>
          <td><a href="{$project.project.web_url}">{$project.project.name_with_namespace}</a></td>
        </tr>
      {/foreach}
    </table>
  {/if}
{/crmScope}
