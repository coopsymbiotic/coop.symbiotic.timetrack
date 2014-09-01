<?php

class CRM_Timetrack_Utils {
  static function roundUpSeconds($seconds, $roundToMinutes = 15) {
    // 1- we round the seconds to the closest 15 mins
    // 2- we convert the seconds to hours, so 3600 seconds = 1h.
    return ceil($seconds / ($roundToMinutes * 60)) * ($roundToMinutes * 60) / 3600;
  }
}
