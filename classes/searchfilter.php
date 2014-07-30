<?php


/*
 SEARCHFilterInterface:
    FilterResult - should return TRUE if result should be filtered out
    and FALSE otherwise. The info & rating may be updated. However, it
    is expected what rating could only be decreased.
*/
interface SEARCHFilterInterface {
 public function __construct($value);
 public function FilterResult(&$info, &$rating);
 public function GetLimit();
}

abstract class BASESearchFilter implements SEARCHFilterInterface {
 var $value;
 public function __construct($value) {
    $this->value = $value;
 }
 
 public function GetLimit() {
    return $this->value;
 }
}

class SEARCHFilter {
 var $threshold;	// rating threshold to accept result [0..1]
 var $limits;		// Various filters limiting (or detailing) results
 
 var $mask;		// Masks limits processed by the calling module
 
 const DEFAULT_THRESHOLD = 0;
 
 public function __construct($threshold = false, $limits = false) {
    global $ADEI;
    
    $this->threshold = $threshold;
    $this->limits = array();
    $this->mask = array();
    
    if ($limits) {
      foreach ($limits as $key => $value) {
        try {
	    $ADEI->RequireClass("search/{$key}filter");
	} catch (ADEIException $ae) {
	    throw new ADEIException(translate("Unsupported search filter is speified: \"%s\"", $key));
	}
	
	$cl = strtoupper($key) . "SearchFilter";
	$this->limits[$key] = new $cl($value);
	
	
//	$filter = new 
	    
      }
    }
 }

 public function CleanMask() {
    $this->mask = array();
 }
  
 public function Mask($limit) {
    $this->mask[$limit] = true;
 }
 
 public function UnMask($limit) {
    unset($this->mask[$limit]);
 }
 
 public function GetLimit($limit, $default = false) {
    $this->Mask($limit);    
 
    if (isset($this->limits[$limit])) {
	return $this->limits[$limit]->GetLimit();
    } 
    return $default;
 }
 
 public function GetCannonicalLimits() {
    return $this->limits;
 }
 
 
 public function GetThreshold($default = false) {
    if ($this->threshold !== false) return $this->threshold;
    else if ($default !== false) return $default;
    else return SEARCHFilter::DEFAULT_THRESHOLD;
 }
 
 public function FilterResult(&$info, &$rating) {
    foreach ($this->limits as $key => $filter) {
	if ($this->mask[$key]) continue;
	
	if ($this->limits[$key]->FilterResult($info, $rating)) return true;
    }
    return false;
 }
}
?>