<?php

class DATAPoint implements Iterator {
 var $cache;
 var $mask, $ids;
 var $ivl;
 var $type;

 var $limit, $amount;
 
 var $interval;
 var $ikey, $irow, $nextikey, $nextirow;
 var $key3, $row3;

 var $key, $row;
 var $gaps;		// Indicates if there gaps on the graph
 var $missing;		// Information on the data missing in the database

    // MINMAX specific
 var $started;
 var $state;		// Type of previous step (0 - 'min' value is taken, 1 - 'max' value is taken)
 var $nextivl;		// Indicates if we should read next interval
 var $empty_key;	// Cursor for reporting empty intervals
 
 var $operation_info;

 var $options;
 
 var $flags;

 var $expected_gap;	// Expected distance between points (0 - no known)
 var $allowed_gap;	// Do not report gaps bellow this threshold
   
 function __construct(CACHEDB $cache, MASK $mask, INTERVAL $ivl, $type, $amount, $limit, $resolution, $flags = 0) {
    $this->cache = &$cache;
    $this->ids = sizeof($mask->ids);
    $this->ivl = &$ivl;
    $this->mask = &$mask;
    
    $this->type = $type;
    $this->limit = $limit;
    $this->amount = $amount;

    if ($resolution === false) $this->resolution = $cache->resolution->Get($this->ivl, $this->amount);
    else $this->resolution = $resolution;
    
    $this->operation_info = array();
    
    $this->options = array();
    
    $this->flags = $flags;

    $resolution = $cache->resolution->GetWindowSize($this->resolution);

    $size = $ivl->GetWindowSize();
    if ($limit) {
	$mingap = $size / $limit;
	if ($resolution) {
	    $mingap = ceil($mingap / $resolution)*$resolution;
	}
    } else if ($resolution) {
	$mingap = $resolution;
    } else {
	$mingap = 0;
    }

    $this->expected_gap = $mingap;
    

    if (($mingap)&&(($this->flags&CACHE::REPORT_EMPTY)||($this->flags&CACHE::MISSING_INFO))) {
	$res = $cache->resolution->Minimal();
	$this->allowed_gap = $cache->resolution->GetWindowSize($res);
    } else {
	$this->allowed_gap = false;
    }

/*    
    echo $amount . " , " . $limit . "\n";
    echo $mingap . "\n";
    exit;
*/
 }

 function SetOption($option, $value = false) {
    switch ($option) {
	case "allowed_gap":
	    $this->$option = $value;
	break;
	default:
	    $this->options[$option] = $value;
    }
 } 

 function SetOptions($options) {
    $this->options = array_merge($this->options, $option);
 }
 

 function MINMAXFirst(&$key, &$ivl) {
    $this->key = $key;
    for ($i=0;$i<$this->ids;$i++) {
	$this->row[$i] = $ivl['min' . $i];
	$this->state[$i] = 0;
    }

    if (($this->allowed_gap)&&($ivl['maxgap'] > $this->allowed_gap))
        $this->missing = $ivl['maxgap'];
    else
	$this->missing = 0;
    
    $this->started = true;
 }
 
 function MINMAXLast(&$key, &$ivl) {
    $this->started = false;

    $this->key = $key;
    for ($i=0;$i<$this->ids;$i++) {
	if ($this->state[$i]) {
	    $this->row[$i] = $ivl['min' . $i];
	} else {
	    $this->row[$i] = $ivl['max' . $i];
	}
    }
    
    $this->missing = 0;
 }
 
 function MINMAXStep(&$key1, &$ivl1, &$key2, &$ivl2) {
    $votes = 0;
    
    for ($i=0;$i<$this->ids;$i++) {
	if ($this->state[$i]) {
	    if ($ivl2['max' . $i] > $ivl1['min' . $i]) {
		if (($key1)&&($ivl1['min' . $i] < $ivl2['min' . $i])) $votes++;

		$this->row[$i] = min($ivl1['min' . $i], $ivl2['min' . $i]);
		$this->state[$i] = 0;
	    } else {
		$this->row[$i] =  $ivl2['max' . $i];
	    }
	} else {
	    if ($ivl2['min' . $i] < $ivl1['max' . $i]) {
		if (($key1)&&($ivl1['max' . $i] > $ivl2['max' . $i])) $votes++;
		
		$this->row[$i] = max($ivl1['max' . $i], $ivl2['max' . $i]);
		$this->state[$i] = 1;
	    } else {
		$this->row[$i] =  $ivl2['min' . $i];
	    }
	}
    }
    if ($votes * 2 > $this->ids) 
	$this->key = $key1;
    else
	$this->key = $key2;


    if (($this->allowed_gap)&&($ivl2['maxgap'] > $this->allowed_gap))
        $this->missing = $ivl2['maxgap'];
    else
	$this->missing = 0;

 }
 
 function GetPoint(&$key1, &$ivl1, &$key2, &$ivl2) {
    $this->row = array();

    switch ($this->type) {
	 case CACHE::TYPE_MEAN:
	    if ($this->empty_key) {
		$nextkey = dsMathPreciseAdd($this->empty_key, $this->expected_gap);
		if ($nextkey > $key1) {
		    $this->empty_key = false;
		} else {
		    $this->key = $this->empty_key;
		    $this->row[0] = false;
		    $this->empty_key = $nextkey;

		    return false;
		}
	    }
	    
	    $this->missing = 0;

	    if ($ivl1) {
		if (($ivl2)&&(($this->flags&CACHE::REPORT_EMPTY)||($this->flags&CACHE::MISSING_INFO))) {
		    $key1end = dsMathPreciseAdd($key1, $ivl1['width']);
		    $distance = dsMathPreciseSubstract($key2, $key1end);
		    
		    if (($this->allowed_gap)&&($distance > $this->allowed_gap)) {
			$this->missing = $distance;
			
			if (($this->expected_gap)&&($distance > 2 * $this->expected_gap)) {
			    $this->gaps = true;
			
			    if ($this->flags&CACHE::REPORT_EMPTY) {
				$this->empty_key = dsMathPreciseAdd($key1end, $this->expected_gap);
			    }
			}
		    }
		}

		$ivl = &$ivl1;

		$this->key = dsMathPreciseAdd($key1, $ivl['width']/2);
	    } else {
		$ivl = &$ivl2;
		
		
		$this->key = dsMathPreciseAdd($key2, $ivl['width']/2);
	    } 

	    if (($this->allowed_gap)&&(!$this->missing)&&($ivl['maxgap'] > $this->allowed_gap))
    		$this->missing = $ivl['maxgap'];

	    
	    for ($i=0;$i<$this->ids;$i++)
		$this->row[$i] = $ivl['mean' . $i];
	 break;
	 case CACHE::TYPE_MINMAX:
	    if ($this->started) {
		$key1end = dsMathPreciseAdd($key1, $ivl1['width']);

		if ($ivl2) {
		    $distance = dsMathPreciseSubstract($key2, $key1end);
		    if (($this->expected_gap)&&($distance > 2 * $this->expected_gap)) {
		        $this->MINMAXLast($key1end, $ivl1);
			    
			if (($this->allowed_gap)&&($distance > $this->allowed_gap)) {
			    $this->missing = $distance;

			    $this->gaps = true;

			    if ($this->flags&CACHE::REPORT_EMPTY) {
				$this->empty_key = dsMathPreciseAdd($key1end, $this->expected_gap);
			    }
			}
			
			return false;
		    }
		    
		    if ($distance>0)
			$this->MINMAXStep($key1end, $ivl1, $key2, $ivl2);
		    else
			$this->MINMAXStep($key1end=false, $ivl1, $key2, $ivl2);
		} else if ($ivl1) {
		    $this->MINMAXLast($key1end, $ivl1);
		}
	    } else if ($ivl2) {
		if ($this->empty_key) {
		    $nextkey = dsMathPreciseAdd($this->empty_key, $this->expected_gap);
		    if ($nextkey > $key2) {
			$this->empty_key = false;
		    } else {
			$this->key = $this->empty_key;
			$this->row[0] = false;
			$this->empty_key = $nextkey;
			return false;
		    }
		} else if (($ivl1)&&($this->allowed_gap)) {
		    $key1end = dsMathPreciseAdd($key1, $ivl1['width']);
		    $distance = dsMathPreciseSubstract($key2, $key1end);
		    
		    if ($distance > $this->allowed_gap) {
			$this->gaps = true;
			if ($this->flags&CACHE::REPORT_EMPTY) {
			    $this->empty_key = dsMathPreciseAdd($key1end, $this->expected_gap);
			    return false;
			}
		    }
		}
		
		if (!$ivl2['width']) {
		    $this->key = $key2;
		    for ($i=0;$i<$this->ids;$i++) {
		        $this->row[$i] = $ivl2['min' . $i];
		    }
	    
		    $this->missing = 0;
		    if (($this->flags&CACHE::MISSING_INFO)&&($this->allowed_gap)) {
			if (($this->interval)&&($this->interval->valid())) {
			    $this->row3 = $this->interval->current();
			    $this->key3 = $this->interval->key();
			    $this->interval->next();
			    
			    $distance = dsMathPreciseSubstract($key3, $key2);
			    if ($distance->allowed_gap) $this->missing = $distance;
			}

		    }
		} else {
		    $this->MINMAXFirst($key2, $ivl2);
		}
	    } else {
		 /* if not started and no ivl2 - we are done */
		 unset($this->irow);
	    }
	break;
	 default:
	    throw new ADEIException(translate("Invalid interval aggregation mode (%u) is specified", $this->type));
    }
    return true;

//    print_r($ivl1);
 }
 
 function rewind() {
    $this->interval = new DATAInterval($this->cache, $this->mask, $this->ivl, $this->amount, $this->limit?($this->limit + 1):0, $this->resolution, CACHE::TRUNCATE_INTERVALS|(($this->flags&CACHE::MISSING_INFO)?CACHE::MISSING_INFO:0));
    if (sizeof($this->options) > 0) $this->interval->SetOptions($this->options);

    $this->started = false;
    $this->nextivl = true;
    $this->empty_key = false;
    
    $this->nextkey = false;
    $this->nextrow = false;
    
    $this->row3 = false;
    $this->key3 = false;
    
    $this->gaps = false;
    
    $this->interval->rewind();
    $this->next();
 }
 
 function current() {
    if ($this->row[0] === false) 
	return false;
	
    return $this->row;
 }
 
 function key() {
    return $this->key;
 }

 function missing_data() {
    return $this->missing;
 }
 
 function missing_points() {
    return $this->gaps;
 }
 
 function next() {
    if ($this->nextivl) {
	$this->irow = $this->nextirow;
	$this->ikey = $this->nextikey;
    
	if ($this->key3) {
	    $this->nextirow = $this->row3;
	    $this->nextikey = $this->key3;
	    
	    $this->key3 = false;
	    $this->row3 = false;
	} elseif ($this->interval) {
	    if ($this->interval->valid()) {
		$this->nextirow = $this->interval->current();
		$this->nextikey = $this->interval->key();
		$this->interval->next();
	    } else {
		$this->nextirow = false;
		$this->nextikey = false;
		$this->operation_info = $this->interval->GetOperationInfo();
		unset($this->interval);
	    }
	}
    }

    $this->nextivl = $this->GetPoint($this->ikey, $this->irow, $this->nextikey, $this->nextirow);
 }
 
 function valid() {
    return ($this->interval||$this->irow)?true:false;
 }

 function GetOperationInfo() {
    if ($this->interval) $ivlinfo = $this->interval->GetOperationInfo();
    else $ivlinfo = $this->operation_info;
    
    $ivlinfo['aggregation'] = $this->type;

    return $ivlinfo;
 }
}


?>