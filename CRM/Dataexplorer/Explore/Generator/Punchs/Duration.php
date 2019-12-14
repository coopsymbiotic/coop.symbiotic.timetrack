<?php

class CRM_Dataexplorer_Explore_Generator_Punchs_Duration extends CRM_Dataexplorer_Explore_Generator_Punchs {
  function __construct() {
    parent::__construct();
  }

  function config($options = array()) {
    $this->_select[] = "round(sum(duration)/60/60,2) as y";
    return parent::config($options);
  }

  function data() {
    return parent::data();
  }

  function whereClause(&$params) {
    $where = parent::whereClause($params);
    return $where;
  }
}
