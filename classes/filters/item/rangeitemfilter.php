<?php

class RANGEItemFilter implements SIMPLEItemFilter {
 var $min, $max;
 
 function __construct(array &$opts = NULL) {
    if (is_numeric($opts['min'])) $this->min = $opts['min'];
    else $this->min = false;

    if (is_numeric($opts['max'])) $this->max = $opts['max'];
    else $this->max = false;
 }
 
  function ProcessItem(&$data, $time, $id, &$value) {
    if (($this->min !== false)&&($value < $this->min)) $value = NULL;
    else if (($this->max !== false)&&($value > $this->max)) $value = NULL;

    return false;
 }
}



?>