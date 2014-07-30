<?php

class NULLCorrector extends BASEDataFilter implements SIMPLEDataFilter {
 var $value;
 
 function __construct($nullvalue) {
    $this->value = $nullvalue;
 }
 
 function ProcessVector(&$data, &$time, &$values) {
    foreach ($values as &$value) {
	if ($value === NULL) {
	    $value = $this->value;
	}
    }
    
    return false;
 }
 
}



?>