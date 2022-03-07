<?php
use CRM_Timetrack_ExtensionUtil as E;

use Http\Adapter\Guzzle6\Client;

class CRM_Timetrack_Page_GitlabSupport extends CRM_Core_Page {

  public function run() {
    $alias = $_SERVER['HTTP_X_GITLAB_TOKEN'];
    $supported_hooks = ['Issue Hook', 'Merge Request Hook', 'Confidential Issue Hook', 'Note Hook', 'Confidential Note Hook'];

    if (!in_array($_SERVER['HTTP_X_GITLAB_EVENT'], $supported_hooks)) {
      Civi::log()->warning('Timetrack/Gitlab: received an unsupported webhook event type', [
        'token' => $_SERVER['HTTP_X_GITLAB_TOKEN'],
        'type' => $_SERVER['HTTP_X_GITLAB_EVENT'],
      ]);

      self::done("Unsupported webhook event type");
    }

    $body = file_get_contents('php://input');
    $data = json_decode($body, TRUE);

    if (empty($data)) {
      Civi::log()->warning('Timetrack/Gitlab: Received an empty body (or json parse error)', [
        'token' => $_SERVER['HTTP_X_GITLAB_TOKEN'],
        'type' => $_SERVER['HTTP_X_GITLAB_EVENT'],
      ]);

      self::done("Empty body?");
    }

    Civi::log()->warning('GET: ' . print_r($data, 1));

    $username = $data['user']['username'];
    $title = $data['object_attributes']['title'];
    $separator = ($data['event_type'] == 'merge_request' ? '!' : '#');
    $ref = $data['project']['path_with_namespace'] . $separator . $data['object_attributes']['iid'];

    // Ignore comments from bots (i.e. infinite loops)
    if (preg_match('/_bot$/', $username)) {
      self::done("OK");
    }

    // Get the Gitlab Base URL, ex: https://lab.example.org
    $gl_url = Civi::settings()->get('timetrack_gitlab_url');

    if (!$gl_url) {
      throw new Exception('Gitlab URL not set in timetrack settings');
    }

    // Fetch the project token from the secret (project alias)
    $result = civicrm_api3('Timetracktask', 'get', [
      'alias' => $alias,
      'sequential' => 1,
    ]);

    if (empty($result['values'][0])) {
      throw new Exception('Timetracktask not found for alias: ' . $alias);
    }

    // @todo FIXME custom field ID hardcoded 
    $case_id = $result['values'][0]['case_id'];

    $case = civicrm_api3('Case', 'get', [
      'id' => $result['values'][0]['case_id'],
      'return' => ['custom_69', 'custom_68'],
      'sequential' => 1,
    ]);

    if (empty($case['values'][0]['custom_69'])) {
      throw new Exception('Case ' . $case_id . ' does not have a gitlab project token');
    }
    if (empty($case['values'][0]['custom_68'])) {
      throw new Exception('Case ' . $case_id . ' does not have a response set');
    }

    // We do this late to avoid PHP library conflicts because for now lazyly bundling Guzzle
    require_once E::path('vendor/autoload.php');

    $gl_token = $case['values'][0]['custom_69'];
    $auto_response = $case['values'][0]['custom_68'];

    $client = \Gitlab\Client::create($gl_url)
      ->authenticate($gl_token, \Gitlab\Client::AUTH_URL_TOKEN);

    $issue_id = $data['issue']['iid'] ?? $data['object_attributes']['iid'] ?? NULL;

    if (!$issue_id) {
      throw new Exception('Issue ID not found: ' . print_r($data, 1));
    }

    // Use-cases
    // - Issue with URGENT (case-insensitive) in the title (either when the issue is created, or updated)
    // - Issue comment with URGENT (case-sensitive only) in the note body
    // - A label "urgent" is added to the issue

    // Detect if the user has triggered an urgent behaviour
    $is_urgent = FALSE;

    if ($data['event_type'] == 'issue' && !empty($data['changes'])) {
      // Check if an urgent label was added to the issue
      if (!empty($data['changes']['labels']) && !empty($data['changes']['labels']['current'])) {
        $already_urgent = FALSE;

        foreach ($data['changes']['labels']['previous'] as $key => $label) {
          if (preg_match('/urgent/i', $label['title'])) {
            $already_urgent = TRUE;
          }
        }

        if (!$already_urgent) {
          foreach ($data['changes']['labels']['current'] as $key => $label) {
            if (preg_match('/urgent/i', $label['title'])) {
              $is_urgent = TRUE;
            }
          }
        }
      }

      // Urgent added to title (works for new and existing issues, because 'previous' is empty if new issue)
      if (!empty($data['changes']['title']) && !preg_match('/urgent/i', $data['changes']['title']['previous'])) {
        if (preg_match('/urgent/i', $data['changes']['title']['current'])) {
          $is_urgent = TRUE;
        }
      }

      // Urgent added to the issue description - in this case, we only match URGENT in caps, to avoid too many false positives
      if (!empty($data['changes']['description']) && !preg_match('/URGENT/', $data['changes']['description']['previous'])) {
        if (preg_match('/URGENT/', $data['changes']['description']['current'])) {
          $is_urgent = TRUE;
        }
      }
    }

    // URGENT in the comment text (caps only, avoid too many false positives)
    if ($data['event_type'] == 'note' && !empty($data['object_attributes']['note']) && preg_match('/URGENT/', $data['object_attributes']['note'])) {
      $is_urgent = TRUE;
    }

    if ($is_urgent) {
      // Check if the issue subject needs an update
      $update_subject = FALSE;
      $issue_subject = NULL;

      if ($data['event_type'] == 'issue' && !preg_match('/urgent/i', $data['object_attributes']['title'])) {
        $update_subject = TRUE;
        $issue_subject = $data['object_attributes']['title'];
      }
      elseif (!empty($data['issue']) && !preg_match('/urgent/i', $data['issue']['title'])) {
        $update_subject = TRUE;
        $issue_subject = $data['issue']['title'];
      }

      if ($update_subject && $issue_subject) {
        $client->api('issues')->update($data['project']['id'], $issue_id, [
          'title' => 'URGENT ' . $issue_subject,
        ]);
      }

      // Post our auto-response even if the subject was already "URGENT"
      // Subject should be changed first, so that the email notification has "URGENT"
      if ($auto_response) {
        $client->api('issues')->addNote($data['project']['id'], $issue_id, $auto_response);
      }
    }

    self::done();
  }

  /**
   * Respond "OK" on the standard output.
   * @param string $output
   */
  private function done($output = 'OK') {
    echo $output;

    if ($output != 'OK') {
      CRM_Utils_System::setHttpHeader("Status", "500 Internal Server Error");
    }

    CRM_Utils_System::civiExit();
  }

}
