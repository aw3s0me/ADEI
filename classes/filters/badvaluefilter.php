<?php

/* Replaces 1999 values (INVALID) with NULL's */
class BADVALUEFilter extends BASEDataFilter implements SIMPLEDataFilter {
 var $badval;
 
 function __construct(&$opts = NULL) {
    if (is_array($opts)) {
	if (is_numeric($opts['badvalue'])) $this->badval = $opts['badvalue'];
	else $this->badval = NULL;
    } else $this->badval = NULL;
 }
 
 function ProcessVector(&$data, &$time, &$values) {
    foreach ($values as &$value) {
	if ($value == $this->badval) {
	    $value = NULL;
	}
    }
    
    return false;
 }
 
}



?>