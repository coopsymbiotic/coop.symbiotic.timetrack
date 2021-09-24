<?php
use CRM_Timetrack_ExtensionUtil as E;

class CRM_Timetrack_BAO_Timetracktask extends CRM_Timetrack_DAO_Timetracktask {

  /**
   * Create a new Timetracktask based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Timetrack_DAO_Timetracktask|NULL
   *
  public static function create($params) {
    $className = 'CRM_Timetrack_DAO_Timetracktask';
    $entityName = 'Timetracktask';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
