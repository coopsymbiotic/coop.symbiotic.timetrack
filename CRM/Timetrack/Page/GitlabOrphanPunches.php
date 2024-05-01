<?php

use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_Page_GitlabOrphanPunches extends CRM_Core_Page {
  protected $contact_id;
  protected $client;

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Gitlab Orphan Punches'));

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

    $punches = $this->getOrphanPunches();
    $this->assign('punches', $punches);
    $this->assign('punches_count', count($punches));

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

  private function getOrphanPunches(): array {
    $client = $this->getGitlabClient();
    $updated_after = date('c', strtotime("-90 days"));

    $criteria = [
      'order_by' => 'updated_at',
      'sort' => 'desc',
      'updated_after' => $updated_after,
      'per_page' => 50,
    ];

    $pager = new Gitlab\ResultPager($client);
    $issues = $pager->fetchAll($client->issues(), 'all', [null, $criteria]);
    $punches = [];

    // Flatten the labels for smarty
    foreach ($issues as $issue) {
      $notes = $client->issues()->showNotes($issue['project_id'], $issue['iid']);

      foreach ($notes as $note) {
        if ($note['updated_at'] > $updated_after) {
          if (preg_match('/^added (\d+\w+) of time spent/', $note['body'], $matches)) {
            $note['project'] = $this->getProject($note['project_id']);
            $note['issue'] = $issue;
            $note['time'] = $matches[1];
            $punches[] = $note;

            // Check for a corresponding punch
            // @todo
          }
        }
      }
    }

    // Reverse sort by updated_at
    usort($punches, function($a, $b) {
      return strcmp($b['updated_at'], $a['updated_at']);
    });

    return $punches;
  }

  private function getProject(int $project_id) : array {
    static $project_cache = [];
    $client = $this->getGitlabClient();

    if (empty($project_cache[$project_id])) {
      $project = $client->projects()->show($project_id);
      $project_cache[$project_id] = $project;
    }

    return $project_cache[$project_id];
  }

}
