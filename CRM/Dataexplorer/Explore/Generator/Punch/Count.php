<?php

class CRM_Dataexplorer_Explore_Generator_Punch_Count extends CRM_Dataexplorer_Explore_Generator_Punch {

  function config($options = []) {
    $this->_select[] = "count(*) as y";
    return parent::config($options);
  }

}
