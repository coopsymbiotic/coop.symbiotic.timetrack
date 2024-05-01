<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from coop.symbiotic.timetrack/xml/schema/CRM/Timetrack/Timetrackpunch.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:739acab73d7721c034bff4446b6aa9d7)
 */
use CRM_Timetrack_ExtensionUtil as E;

/**
 * Database access object for the Timetrackpunch entity.
 */
class CRM_Timetrack_DAO_Timetrackpunch extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '1.0';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'kpunch';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique Timetrackpunch ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * Author of the punch.
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $contact_id;

  /**
   * Task associated to the punch (FK to ktask).
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $ktask_id;

  /**
   * Start date of the punch.
   *
   * @var string
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $begin;

  /**
   * Duration (in seconds) of the punch.
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $duration;

  /**
   * Punch comment, provides more information on what was done.
   *
   * @var string
   *   (SQL type: varchar(255))
   *   Note that values will be retrieved from the database as a string.
   */
  public $comment;

  /**
   * FIXME: probably never used. Intended to distinguish volunteer/paid internally?
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $billable_intern;

  /**
   * FIXME: probably never used. Whether to bill the client for this or not.
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $billable_client;

  /**
   * FIXME: probably never used. Rate at which to invoice.
   *
   * @var float|string|null
   *   (SQL type: decimal(20,2))
   *   Note that values will be retrieved from the database as a string.
   */
  public $rate;

  /**
   * Invoice/order reference.
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $order_reference;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'kpunch';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Timetrackpunches') : E::ts('Timetrackpunch');
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'timetrack_punch_id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Timetrack Punch ID'),
          'description' => E::ts('Unique Timetrackpunch ID'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.id',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'readonly' => TRUE,
          'add' => '1.0',
        ],
        'contact_id' => [
          'name' => 'contact_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Contact ID'),
          'description' => E::ts('Author of the punch.'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.contact_id',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
        'ktask_id' => [
          'name' => 'ktask_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Ktask ID'),
          'description' => E::ts('Task associated to the punch (FK to ktask).'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.ktask_id',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
        'begin' => [
          'name' => 'begin',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('Begin'),
          'description' => E::ts('Start date of the punch.'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.begin',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
        'duration' => [
          'name' => 'duration',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Duration'),
          'description' => E::ts('Duration (in seconds) of the punch.'),
          'required' => FALSE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.duration',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
        'comment' => [
          'name' => 'comment',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Comment'),
          'description' => E::ts('Punch comment, provides more information on what was done.'),
          'required' => FALSE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.comment',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
        'billable_intern' => [
          'name' => 'billable_intern',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Billable Intern'),
          'description' => E::ts('FIXME: probably never used. Intended to distinguish volunteer/paid internally?'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.billable_intern',
          'default' => '1',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
        'billable_client' => [
          'name' => 'billable_client',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Billable Client'),
          'description' => E::ts('FIXME: probably never used. Whether to bill the client for this or not.'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.billable_client',
          'default' => '1',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
        'rate' => [
          'name' => 'rate',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => E::ts('Rate'),
          'description' => E::ts('FIXME: probably never used. Rate at which to invoice.'),
          'precision' => [
            20,
            2,
          ],
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.rate',
          'dataPattern' => '/^\d+(\.\d{2})?$/',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
        'order_reference' => [
          'name' => 'order_reference',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Order Reference'),
          'description' => E::ts('Invoice/order reference.'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'kpunch.order_reference',
          'table_name' => 'kpunch',
          'entity' => 'Timetrackpunch',
          'bao' => 'CRM_Timetrack_DAO_Timetrackpunch',
          'localizable' => 0,
          'add' => '1.0',
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, '', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, '', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [
      'index_contact_id' => [
        'name' => 'index_contact_id',
        'field' => [
          0 => 'contact_id',
        ],
        'localizable' => FALSE,
        'sig' => 'kpunch::0::contact_id',
      ],
      'index_ktask_id' => [
        'name' => 'index_ktask_id',
        'field' => [
          0 => 'ktask_id',
        ],
        'localizable' => FALSE,
        'sig' => 'kpunch::0::ktask_id',
      ],
      'index_order_reference' => [
        'name' => 'index_order_reference',
        'field' => [
          0 => 'order_reference',
        ],
        'localizable' => FALSE,
        'sig' => 'kpunch::0::order_reference',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
