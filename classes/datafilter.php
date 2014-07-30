<?php

interface SIMPLEDataFilter {
 public function Start(&$data);
 public function ProcessVector(&$data, &$time, &$values);
}

abstract class BASEDataFilter implements SIMPLEDataFilter {
 function Start(&$data) { }
}

require($ADEI_ROOTDIR . "/classes/filters/nullcorrector.php");

class DATAFilter {
 var $resample;
 var $limit;
 var $mask;
 var $details;

 var $filters;

 function __construct(MASK $mask = NULL, $resample = 0, $limit = 0) {
    $this->filters = array();
    $this->resample = $resample;
    $this->limit = $limit;
    $this->properties = array();
    if (($mask)&&($mask->ids)) $this->mask = $mask;
    else $this->mask = NULL;
 }
 
 function AddFilter(SIMPLEDataFilter $filter) {
    array_push($this->filters, $filter);
 }

 function AddReaderFilters(READER $rdr, LOGGROUP $grp = NULL, array $filters) {
    global $ADEI;
    
    foreach ($filters as $filter => &$opts) {
	if (($opts)&&(isset($opts['class']))) $filter = $opts['class'];

        $ADEI->RequireClass("filters/" . strtolower($filter), true);
	
	if (class_exists("READER_$filter")) {
	    $filter_class = "READER_$filter";
	    $this->AddFilter(new $filter_class($rdr, $grp, $this, $opts));
	} else {
	    $filter_class = "$filter";
	    $this->AddFilter(new $filter_class($opts));
	}
    }
 }
 
 function SetProperty($property, $value) {
    $this->properties[$property] = $value;
 }
 
 function GetProperty($property) {
    return $this->properties[$property];
 }
 
 function GetSamplingRate() {
    return $this->resample;
 }
 
 function GetVectorsLimit() {
    return $this->limit;
 }

 function GetItemMask() {
    return $this->mask;
 }
 
 function MaskVector(&$data, &$time, &$values) {
    if (isset($data['masked'])) return;
    
    $mask = $this->GetItemMask();
    
    if ($mask) {
	$masked = array();
	foreach ($mask->ids as $id)
	    array_push($masked, $values[$id]);

	$values = $masked;    
	$data['masked'] = true;
    }
 }

 function RealProcessVector(&$data, &$time, &$values, $auto_mask) {
    if (!$opts) $opts = array();
    
    foreach ($this->filters as $filter) {
	if ($filter->ProcessVector($data, $time, $values)) return true;
    }
    
    if ($auto_mask) $this->MaskVector($data, $time, $values);

    return false;
 }
 
 function ProcessVector($data, &$time, &$values, $auto_mask = true) {
    return $this->RealProcessVector($data, $time, $values, $auto_mask);
 }

 function RealStart(&$data) {
    foreach ($this->filters as $filter) {
	$filter->Start($data);
    }
 }

 function Start($data) {
    $this->RealStart($data);
 }
 
 function Process(Iterator $real_data, $filter_data) {
/*
        // DS: No dynamic masking
    if ($filter_data['masked']) {
        $filter_data['mask'] = $this->GetItemMask();
    }
*/
    return new FILTERData($real_data, $this, $filter_data);
 }
}

class SUPERDataFilter extends DATAFilter {
 var $filter;
 
 function __construct(DATAFilter $sub_filter, READER $rdr, LOGGROUP $grp, array $filters = NULL, MASK $mask = NULL, $resample = 0, $limit = 0) {
    parent::__construct($mask, $resample, $limit);
    $this->filter = $sub_filter;
 }
 
 function GetSamplingRate() {
    if ($this->resample) return $this->resample;
    return $this->filter->GetSamplingRate();
 }
 
 function GetVectorsLimit() {
    if ($this->limit) return $this->limit;
    return $this->filter->GetVectorsLimit();
 }
 
 function GetItemMask() {
    if ($this->mask) return $this->mask;
    return $this->filter->GetItemMask();
 }

 function ProcessVector($data, &$time, &$values, $auto_mask = true) {
    if (parent::RealProcessVector($data, $time, $values, false)) return true;
    if ($this->filter->RealProcessVector($data, $time, $values, false)) return true;

    if ($auto_mask) $this->MaskVector($data, $time, $values);
    return false;
 }

 function Start($data) {
    parent::RealStart($data);
    $this->filter->RealStart($data);
 }
}


?>