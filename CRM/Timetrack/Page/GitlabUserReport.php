<?php

use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Page_GitlabUserReport extends CRM_Core_Page {
  protected $contact_id;
  protected $client;

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Gitlab Issues'));

    $this->contact_id = CRM_Core_Session::getLoggedInContactID();

    if ($cid = CRM_Utils_Request::retrieveValue('cid', 'Positive')) {
      $this->contact_id = $cid;
    }

    $display_name = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('first_name')
      ->addWhere('id', '=', $this->contact_id)
      ->execute()
      ->single()['first_name'];
    $this->assign('display_name', $display_name);

    $issues = $this->getGitlabIssues($this->contact_id);
    $this->assign('issues', $issues);
    $this->assign('issues_count', count($issues));

    $project_stats = $this->getGitlabProjectStats($issues);
    $this->assign('project_stats', $project_stats);
    $this->assign('project_count', count($project_stats));

    parent::run();
  }

  private function getGitlabClient() {
    if ($this->client) {
      return $this->client;
    }

    $gl_url = Civi::settings()->get('timetrack_gitlab_url');
    $gl_token = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('Gitlab.Gitlab_User_Token')
      ->addWhere('id', '=', $this->contact_id)
      ->execute()
      ->first()['Gitlab.Gitlab_User_Token'];

    if (!$gl_token) {
      throw new Exception(E::ts('Gitlab User Token not found.'));
    }

    require_once E::path('vendor/autoload.php');

    $this->client = new \Gitlab\Client();
    $this->client->setUrl($gl_url);
    $this->client->authenticate($gl_token, \Gitlab\Client::AUTH_HTTP_TOKEN);
    return $this->client;
  }

  private function getGitlabIssues(): array {
    $client = $this->getGitlabClient();

    $criteria = [
      'state' => 'opened',
      'scope' => 'assigned-to-me',
      'order_by' => 'updated_at',
      'sort' => 'desc',
      'per_page' => 50,
    ];

    $pager = new Gitlab\ResultPager($client);
    $issues = $pager->fetchAll($client->issues(), 'all', [null, $criteria]);

    // Flatten the labels for smarty
    foreach ($issues as $issue) {
      $labels = '';
      if (count($issue['labels'])) {
        $labels = '[' . implode(', ', $issue['labels']) . '] ';
      }
      $issue['all_labels'] = $labels;
    }

    return $issues;
  }

  /**
   *
   */
  private function getGitlabProjectStats($issues) {
    static $project_cache = [];
    $client = $this->getGitlabClient();
    $project_stats = [];
    $issues_by_project = [];

    foreach ($issues as $issue) {
      if (!isset($issues_by_project[$issue['project_id']])) {
        $issues_by_project[$issue['project_id']] = 0;
      }

      $issues_by_project[$issue['project_id']]++;
    }

    arsort($issues_by_project);

    foreach ($issues_by_project as $project_id => $nb_issues) {
      if (empty($project_cache[$project_id])) {
        $project = $client->projects()->show($project_id);
        $project_cache[$project_id] = $project;
      }

      $project_stats[$project_id] = [
        'nb_issues' => $nb_issues,
        'project' => $project_cache[$project_id],
      ];
    }

    return $project_stats;
  }

}
