<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Timetrackpunchlist.Preview API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group e2e
 */
class api_v3_TimetrackpunchlistTest extends \CivixPhar\PHPUnit\Framework\TestCase implements \Civi\Test\EndToEndInterface {
  use \Civi\Test\Api3TestTrait;

  /**
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   * See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
   */
  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * The setup() method is executed before the test is executed (optional).
   */
  public function setUp() {
    parent::setUp();
    CRM_Utils_Time::setTime('2019-02-10');
    Civi::$statics['_civicrm_api3_timetrackpunchlist_mock_regex'] = ';^mock/\w+$;';
  }

  /**
   * The tearDown() method is executed after the test was executed (optional)
   * This can be used for cleanup.
   */
  public function tearDown() {
    CRM_Utils_Time::resetTime();
    parent::tearDown();
  }

  public function getPreviewExamples() {
    $exs = [];
    $glob = __DIR__ . '/Timetrackpunchlist/*.preview.txt';
    $txtFiles = glob($glob);
    foreach ($txtFiles as $txtFile) {
      $jsonFile = preg_replace('/\.txt$/', '.json', $txtFile);
      if (!file_exists($jsonFile)) {
        throw new \RuntimeException("Found $txtFile but not $jsonFile");
      }

      $exs[basename($txtFile)] = [
        file_get_contents($txtFile),
        json_decode(file_get_contents($jsonFile), 1)
      ];
    }

    return $exs;
  }

  /**
   * @dataProvider getPreviewExamples
   */
  public function testPreview($textInput, $expectArray) {
    $substitutes = ['@user_contact_id' => $this->loginAsAdmin()];
    $expectArray = array_map(function($item) use ($substitutes) {
      foreach (array_keys($item) as $k) {
        $value = $item[$k];
        if (isset($substitutes[$value])) {
          $item[$k] = $substitutes[$value];
        }
      }
      return $item;
    }, $expectArray);

    $result = civicrm_api3('Timetrackpunchlist', 'Preview', ['text' => $textInput]);
    $this->assertEquals($expectArray, $result['values']);
  }

  public function loginAsAdmin() {
    global $_CV;
    if (empty($_CV['ADMIN_USER'])) {
      throw new \RuntimeException("Missing ADMIN_USER");
    }
    $contactID = $this->callAPISuccess('Contact', 'getvalue', ['id' => '@user:' . $_CV['ADMIN_USER'], 'return' => 'id']);

    $session = CRM_Core_Session::singleton();
    $session->set('userID', $contactID);
    return $contactID;
  }

}
