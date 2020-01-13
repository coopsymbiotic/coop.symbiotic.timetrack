<?php

class CRM_Dataexplorer_Explore_Generator_Punch_Sum extends CRM_Dataexplorer_Explore_Generator_Punch {

  function config($options = []) {
    $this->_select[] = "round(sum(duration)/60/60,2) as y";
    return parent::config($options);
  }

}
