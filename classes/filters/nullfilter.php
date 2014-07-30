<?php

interface NULLFilterAllowedMaskReporterInterface {
 public function GetAllowedMask($timestamp);
}

class NULLFilter extends BASEDataFilter implements SIMPLEDataFilter {
 var $use_missing_items;
 var $limit;
 var $minimal;
 var $end_mode;
 var $precise;

 var $check_growth;		// This check allows sequential connection of new items
 var $current_missing_mask;	// This is for incremental growth check
 var $current_timestamp;
 var $rep;

 var $report_invalid_data;
 
 function __construct($opts, NULLFilterAllowedMaskReporterInterface $rep = NULL) {
    if (is_array($opts)) {
	$this->report_invalid_data = !$opts["ignore_invalid_data"];
        $this->use_missing_items = $opts["use_missing_items"];
	
	$this->limit = $opts["limit"];
	$this->minimal = $opts["minimal"];
	$this->end_mode = $opts["tolerate_at_end_only"];
	$this->check_growth = $opts["check_growth"];
	if ($this->check_growth) {
	    $this->current_timestamp = false;
	    $this->rep = $rep;
	}
    } else {
	$this->limit = 0;
    }

 }

 function ProcessVector(&$data, &$time, &$values) {
    if ($this->use_missing_items) {
	$missing = $data["missing_items"];
    } else {
	$missing = array();
	for ($i = 0; isset($values[$i]); $i++) {
	    if ($values[$i] === NULL) $missing[$i] = true;
	}
    }
    
#    echo $time . "\n";
#    if ($time > 1208779224) $missing[142] = true;

    if ($missing) {
	$width = sizeof($values);
	$missing_width = sizeof($missing);
	$present_width = $width - $missing_width;
	
	$invalid_data = false;
	if ((is_int($this->limit))&&($missing_width>$this->limit)) $invalid_data = true;
	elseif ((is_int($this->minimal))&&($this->minimal>$present_width)) $invalid_data = true;
	elseif (($this->end_mode)&&(min(array_keys($missing))<$present_width)) $invalid_data = true;
	elseif ($this->check_growth) {
	    if ($this->current_timestamp === false) {
		if ($this->rep) {
		    $this->current_missing = $this->rep->GetAllowedMask($time);
		    
#		    echo "here\n";
#		    print_r($this->current_missing);
		    
		    if (is_array($this->current_missing)) {
		        $diff = array_diff_key($missing, $this->current_missing);
			if (sizeof($diff)) $invalid_data = true;
#			echo "here$invalid_data\n";
		    }
		}
	    } elseif ($time > $this->current_timestamp) {
		$diff = array_diff_key($missing, $this->current_missing);
		if (sizeof($diff)) $invalid_data = true;
	    } else {
		/* We have unsequential access and, therefore, the growth filter
		could not be applied */
		$this->check_growth = false;
	    }
	    
	    if (!$invalid_data) {
		$this->current_missing = $missing;
		$this->current_timestamp = $timestamp;
	    }
	}
	
	if ($invalid_data) {
#	    echo "invalid data: $time\n";
#	    print_r($this->current_missing);
#	    exit;
	    if ($this->report_invalid_data) {
		throw new ADEIException(translate("Invlid source data is detected: Unsupported configuration of missing items, missing id's: %s", implode(", ", array_keys($missing))));
	    } else {
		return true;
	    }
	}
    }

#    if ($time > 1208779224) {
#    exit;
#    }
    return false;
 }
 
}

class READER_NULLFilter extends NULLFilter implements READER_SIMPLEDataFilter {
 var $skip;
 
 function __construct(READER $rdr, LOGGROUP $grp, DATAFilter $filter, &$opts = NULL) {
    if (($rdr instanceof CACHEReader)&&(!$opts['filter_cache_reader'])) {
	$this->skip = true;
    } else {
        $this->skip = false;
        if (is_array($opts)) {
	    if (($opts["check_precise"])&&(!($rdr instanceof CACHEReader))) {
		$rep = new NULLFilterAllowedMaskReporter($rdr, $grp);
	    } else {
		$rep = NULL;
	    }
	}

	parent::__construct($opts, $rep);
	

	if (!isset($opts["ignore_invalid_data"])) {
	    $this->report_invalid_data = !$rdr->GetGroupOption($grp, "ignore_invalid_data", false);
	}

    }
 }

 function ProcessVector(&$data, &$time, &$values) {
    if ($this->skip) return false;
    return parent::ProcessVector($data, $time, $values);
 }
}

class NULLFilterAllowedMaskReporter implements NULLFilterAllowedMaskReporterInterface {
 var $reader;
 var $group;
 
 function __construct(READER $rdr, LOGGROUP $grp) {
    $this->reader = $rdr->req->CreateCacheReader();
    $this->group = $grp;
 }
 
 function GetAllowedMask($timestamp) {
    try {
	$val = $this->reader->GetData($this->group, 0, $timestamp, NULL, 0, -1);
    } catch (Exception $e) {
	return false;
    }
    
    $val->rewind();
    if ($val->valid()) {
	$res = array();
	
	$data = $val->current();
	foreach (array_keys($data) as $i) {
	    if ($data[$i] === NULL) $res[$i] = true;
	}
	
	return $res;
    }

    return false;
 }
}


?>