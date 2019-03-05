<?php
use CRM_Timetrack_ExtensionUtil as E;

/**
 * The Timetrackpunchlist API deals with a list of punches as a set.
 * Suggested workflow:
 *
 * 1. Call `Timetrackpunchlist.preview text=...` to see what will be done
 * 2. Examine the output
 * 3. Call `Timetrackpunchlist.import text=...` to actually import the list.
 */

/**
 * Timetrackpunchlist.preview API specification (optional)
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_timetrackpunchlist_preview_spec(&$spec) {
  $spec['text']['api.required'] = 1;
}

/**
 * Timetrackpunchlist.preview API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_timetrackpunchlist_preview($params) {
  if (empty($params['text'])) {
    throw new API_Exception(/*errorMessage*/
      'Missing required parameter: text', /*errorCode*/
      1000);
  }

  $txt = $params['text'];
  $txt = preg_replace('/\s*\n+\s*/', "\n", $txt);
  $txt = preg_replace("/[ \t]+/", " ", $txt);
  $lines = explode("\n", $txt);

  $piPat = '\!pi -s';
  $dowDatePat = '[A-Z][a-z][a-z]\s+\d+\/\d+';
  $ymdDatePat = '\d\d\d\d-\d\d-\d\d';
  $datePat = '(' . $ymdDatePat . '|' . $dowDatePat . ')';
  $timePat = '(\d?\d:\d\d)';
  $durPat = '(\d+\.?\d*)(H|h|hr|M|m|min)';
  $aliasPat = '(\S+)';
  $commentPat = '(.*)';

  $defaults = [];
  $defaults['contact_id'] = isset($params['contact_id'])
    ? $params['contact_id']
    : CRM_Core_Session::singleton()->get('userID');

  $punches = [];
  foreach ($lines as $lineNo => $line) {
    $punch = NULL;

    $error_defaults = [
      'error' => 1,
      'lineNo' => (1 + $lineNo),
      'line' => $line,
    ];

    if (empty($line) || $line{0} === '#') {
      continue;
    }
    elseif (preg_match("/^($piPat )?$datePat $timePat\\+$durPat $aliasPat $commentPat/", $line, $m)) {
      $punch = $defaults + [
          'begin' => ['date' => $m[2], 'time' => $m[3]],
          'duration' => ['qty' => $m[4], 'unit' => $m[5]],
          'alias' => $m[6],
          'comment' => $m[7],
        ];
    }
    elseif (preg_match("/^($piPat )?$timePat\\+$durPat $aliasPat $commentPat/", $line, $m)) {
      $punch = $defaults + [
          'begin' => ['date' => CRM_Utils_Time::getTime('Y-m-d'), 'time' => $m[2]],
          'duration' => ['qty' => $m[3], 'unit' => $m[4]],
          'alias' => $m[5],
          'comment' => $m[6],
        ];
    }
    else {
      $punch = $error_defaults + ['message' => 'Failed to parse line'];
    }

    // The above parsing may yield expressions in non-canonical formats.
    // Validate/normalize each field - and (if necessary) generate an error.

    if (isset($punch['begin']['date']) && preg_match('/^([A-Z][a-z][a-z])\s+(\d+)\/(\d+)$/', $punch['begin']['date'], $mDate)) {
      // Translate "Dow mm/dd" to "yyyy-mm-dd".
      $dow = $mDate[1];
      $date = sprintf("%s-%02d-%02d", CRM_Utils_Time::getTime('Y'), $mDate[2], $mDate[3]);
      if ($dow === date('D', strtotime($date . ' ' . $punch['begin']['time']))) {
        $punch['begin']['date'] = $date;
      }
      else {
        $punch = $error_defaults + ['message' => 'Day-of-week does not match date'];
      }
    }

    if (isset($punch['begin']['date']) && !preg_match("/^{$ymdDatePat}$/", $punch['begin']['date'])) {
      $punch = $error_defaults + ['message' => 'Malformed date'];
    }

    if (is_array($punch['begin'])) {
      $punch['begin'] = $punch['begin']['date'] . ' ' . $punch['begin']['time'];
    }

    if (isset($punch['duration'])) {
      try {
        $punch['duration'] = _civicrm_api3_timetrackpunchlist_normdur($punch['duration']);
      }
      catch (API_Exception $e) {
        $punch = $error_defaults + ['message' => 'Malformed duration'];
      }
    }

    if (isset($punch['alias'])) {
      $alias = _civicrm_api3_timetrackpunchlist_expand_alias($punch['alias']);
      if ($alias) {
        $punch['alias'] = $alias;
      }
      else {
        $punch = $error_defaults + ['message' => 'Unrecognized task alias (' . $punch['alias'] . ')'];
      }
    }

    if (empty($punch['error'])) {
      foreach (['contact_id', 'comment', 'alias', 'begin', 'duration'] as $field) {
        if (empty($punch[$field])) {
          $punch = $error_defaults + ['message' => 'Missing required field: ' . $field];
        }
      }
    }

    $punches[] = $punch;
  }

  return civicrm_api3_create_success($punches, $params, 'Timetrackpunchlist', 'preview');
}

/**
 * Timetrackpunchlist.import API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_timetrackpunchlist_import_spec(&$spec) {
  _civicrm_api3_timetrackpunchlist_preview_spec($spec);
}

/**
 * Timetrackpunchlist.import API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_timetrackpunchlist_import($params) {
  $plannedItems = civicrm_api3_timetrackpunchlist_preview($params);

  foreach ($plannedItems['values'] as $itemNum => $item) {
    if (!empty($item['error'])) {
      throw new \API_Exception("Cannot execute plan: error in item #" . (1 + $itemNum));
    }
  }

  $result = [];
  CRM_Core_Transaction::create(TRUE)
    ->run(function () use ($plannedItems, &$result) {
      foreach ($plannedItems['values'] as $itemNum => $item) {
        $params = $item + ['check_permissions' => 1];
        $create = civicrm_api3('Timetrackpunch', 'create', $params);
        $result[$itemNum] = $create['values'];
      }
    });

  return civicrm_api3_create_success($result, $params, 'Timetrackpunchlist', 'import');
}

function _civicrm_api3_timetrackpunchlist_normdur($duration) {
  $min = NULL;
  switch ($duration['unit']) {
    case 'h':
    case 'hr':
    case 'H':
      $min = round(60 * $duration['qty']);
      break;

    case 'm':
    case 'min':
    case 'M':
      $min = $duration['qty'];
      break;
  }

  if (!is_numeric($min)) {
    throw new \API_Exception("Unrecognized duration");
  }
  return round($min * 60);
}

function _civicrm_api3_timetrackpunchlist_expand_alias($alias) {
  if (isset(Civi::$statics['_civicrm_api3_timetrackpunchlist_mock_regex']) && preg_match(Civi::$statics['_civicrm_api3_timetrackpunchlist_mock_regex'], $alias)) {
    return $alias;
  }

  try {
    $r = civicrm_api3('Timetracktask', 'get', ['alias' => $alias]);
    if (count($r['values']) !== 1) {
      return FALSE;
    }
    foreach ($r['values'] as $task) {
      return dirname($alias) . '/' . $task['title'];
    }
  }
  catch (API_Exception $e) {
    return FALSE;
  }
}
