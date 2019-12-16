<?php

class CRM_Dataexplorer_Explore_Generator_Invoice_Invoiced extends CRM_Dataexplorer_Explore_Generator_Invoice {
  function __construct() {
    parent::__construct();
  }

  function config($options = array()) {
    $this->_select[] = "sum(hours_billed) as y";
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
