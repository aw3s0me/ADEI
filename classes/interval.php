<?php


/*
 ParseInterval (Supported Forms):
  2008
  2006 - 2008
  January, 2008			(1)
  January - March, 2008 	(1)
  January 24, 2008		(1)
  January 24 - 25, 2008		(1)
  January 24, 2008 14 - 15	(1)
  24 January, 2008		(1)
  14:00 - 15:00, 24 January 2008(1)
  2008, January
  2008, January - February
  2008, January 24
  2008, January 24 - 25
  
 <any date format> + 30 seconds 	minutes, hours, days, (ISO since php 5.3)
 <format1> - <format2>		(2)
 timestamp1 - timestamp2
 timestamp.us1 - timestamp.us2
  

  (1) year could be omitted meaning this year
  (2) formats should not contain dashes
*/

class INTERVAL {
 var $interval_start, $interval_end, $interval_items;
 var $window_start, $window_size, $window_items;
 var $request_window_start, $request_window_size;
 var $y_min, $y_max;
 
 var $item_limit;

 var $empty_source;	// Source does not set data range
 var $empty_window;	// Window/interval mistmatch or empty window
 
 
 const EMPTY_WINDOW = 1;
 const EMPTY_SOURCE = 2;
 
 const NAME_FORMAT_DEFAULT = 0;
 const NAME_FORMAT_HUMAN = 0;
 const NAME_FORMAT_ISO8601 = 1;
 
 
 const EPOCH_START = -2208988800.;
 
 /*
    We can configure for types of windows:
    <---> 	no limits (all_mode, end_mode)
    ---->	specified window_size from the end (end_mode)
    |--->	specified window_start without size limit (all_mode)
    |--|	specified start and size limits ()
    
    WINDOW:
	specified size 	=> !end_mode
	specified start => !all_mode

    INTERVAL:
	specified begin => !end_mode
	specified_end => !end_mode, !all_mode
	
    end_mode:	INTERVAL: no end		WINDOW: no start
    all_mode:	INTERVAL: no end 		WINDOW: no size
    start_mode:	INTERVAL: no start		WINDOW: -
    
    mode = 2, Unlocked by INTERVAL and WINDOW
    mode = 1, Unlocked by INTERVAL
    mode = 0, Locked by INTERVAL
    
    We expecting after Apply, the intervals are only allowed to grow.
 */
 
 var $end_mode;
 var $all_mode;
 
 var $start_mode;

 var $end_by_data;	// Indicates if experiment ends on last record or current time 
 
 var $sequence;
 
// var $flags;
 var $flexibility;

 const NEED_AMOUNT = 1;
 const FLAG_FLEXIBLE = 2;
 const FLAG_REDUCIBLE = 6;

 const WINDOW_RIGHT = 1;
 const WINDOW_LEFT = 2;
 const WINDOW_NEIGHBOURHOOD = 3;	// Diameter as a size
 const WINDOW_MAXIMUM = 4;

 const SEQUENCE_AUTO = 1;
 const SEQUENCE_FORWARD = 1;
 const SEQUENCE_BACKWARD = 2;
 const SEQUENCE_UNSORTED = 3;
 const SEQUENCE_RANDOM = 4;
 
 const MODE_LOCKED = 0;			// Exact value is defined (in experiment)
 const MODE_WINDOWLOCKED = 1;		// Locked by Window but not Interval
 const MODE_UNLOCKED = 2;
 

 function __construct(array &$props = NULL, READER &$reader = NULL, LOGGROUP &$grp = NULL, $flags = 0) {
    if (($props)&&(isset($props['experiment']))&&(preg_match("/(-?\d*\*?)-(-?\d*\*?)/", $props['experiment'], $m))&&(($m[1]!==0)||($m[2]!==0))) {
        if (is_numeric($m[1])) $this->interval_start = $m[1];
	else $this->interval_start = false;
	
	if (is_numeric($m[2])) $this->interval_end = $m[2];
	else $this->interval_end = false;
	
	if (is_numeric($m[2])) {
	    $this->all_mode = INTERVAL::MODE_LOCKED;
	    $this->end_mode = INTERVAL::MODE_LOCKED;
	    $this->start_mode = INTERVAL::MODE_LOCKED;
	} else {
	    if (is_numeric($m[1])) $this->start_mode = INTERVAL::MODE_LOCKED;
	    else $this->start_mode = INTERVAL::MODE_UNLOCKED;
	    $this->end_mode = INTERVAL::MODE_UNLOCKED;
	    $this->all_mode = INTERVAL::MODE_UNLOCKED;
	}
	
	if(($m[1] == "*")||($m[2] == "*")) $this->end_by_data = false;
	else $this->end_by_data = true;
    } else {
	$this->all_mode = INTERVAL::MODE_UNLOCKED;
	$this->end_mode = INTERVAL::MODE_UNLOCKED;
	$this->start_mode = INTERVAL::MODE_UNLOCKED;
	
	$this->interval_start = false;
	$this->interval_end = false;

	$this->end_by_data = true;
    }
    
    $this->item_limit = 0;


    if (($props)&&(isset($props['window']))) {
	if (preg_match("/^(-?\d*)(,(-?\d*))?$/", $props['window'], $m)) {
	    if ($m[1] < 0) {
		$this->window_size = 0;
		$this->item_limit = $m[1];
	    } else {
		$this->window_size = $m[1];
	    }

	    if ($m[2]) {
		$this->item_limit = $m[3];
	    }
    
	    if (($this->all_mode == INTERVAL::MODE_LOCKED)||($this->end_mode == INTERVAL::MODE_LOCKED)) {
		if ($this->window_size) {
		    $this->window_start = dsMathPreciseSubstract($this->interval_end, $this->window_size);
		
		    if ($this->interval_start > $this->window_start) {
			// Invalid values (tolerable)
			$this->window_start = false;
			$this->window_size = false;
		    }
		} else {
		    $this->window_start = $this->interval_start;
		    $this->window_size = dsMathPreciseSubstract($this->interval_end, $this->interval_start);
		}
	    } else {
		$this->window_start = false;
	    }
	    
	    if (($m[1]>0)&&($this->all_mode)) $this->all_mode = INTERVAL::MODE_WINDOWLOCKED;
	
	    $this->y_min = 0;
	    $this->y_max = 0;
	} elseif (preg_match("/^((-?[\d.]*)-(-?[\d.]*))?(,([+\-.eE\d]+):([+\-.eE\d]+))?(,(-?\d+))?$/", $props['window'], $m)) {
	    if ($m[7]) {
		$this->item_limit = $m[8];
	    }

	    if ($m[1]) {
		if (is_numeric($m[2])) {
		    $this->window_start = $m[2];
		    $have_start = true;
		} else {
		    $this->window_start = $this->interval_start;
		    $have_start = false;
		}

		if (is_numeric($m[3])) {
		    if ($this->window_start === false) {
			throw new ADEIException(translate("WINDOW without start but with definite end is not supported yet"));
		    }

		    $this->window_size = dsMathPreciseSubstract($m[3], $this->window_start);
		    $have_size = true;
		} else if ($this->end_mode == self::MODE_LOCKED) {
		    $this->window_size = dsMathPreciseSubstract($this->interval_end, $this->window_start);
		    $have_size = true;
		} else {
		    $this->window_size = false;
		    $have_size = false;
		}
		
		if ((($this->window_start<$this->interval_start)&&($this->all_mode == INTERVAL::MODE_LOCKED)||($this->end_mode == INTERVAL::MODE_LOCKED))||($this->window_size<0)) {
			// Invalid values
		    if (($this->window_size<0)&&(isset($m[3]))) {
			throw new ADEIException(translate("Invalid Window (%s) is specified", $m[1]));
		    } else {
			throw new ADEIException(translate("Window (%s) is specified outside of experiment (%s) range", $m[1], $props['experiment']));
		    }
		    /*
			$this->window_start = false;
			$this->window_size = false;
		    */
		}
		
		    // interval_start is 0 if still unlocked, so don't care
		if (($have_start)&&($this->end_mode)) $this->end_mode = INTERVAL::MODE_WINDOWLOCKED;
		if (($have_size)&&($this->all_mode)) $this->all_mode = INTERVAL::MODE_WINDOWLOCKED;
	    }
	    
	    if ($m[4]) {
		$this->y_min = $m[5];
		$this->y_max = $m[6];
		if ($this->y_min > $this->y_max) {
		    $this->y_min = 0;
		    $this->y_max = 0;
		}
	    }
	} else if (preg_match("/^([+\-.eE\d]+):([+\-.eE\d]+)$/", $props['window'], $m)) {
	    $this->window_size = false;
	    $this->window_start = false;
	    
	    $this->y_min = $m[1];
	    $this->y_max = $m[2];
	    if ($this->y_min > $this->y_max) {
		$this->y_min = 0;
		$this->y_max = 0;
	    }
	} else if (!$props['window']) {
	    $this->window_size = false;
	    $this->window_start = false;
	    
	    $this->y_min = 0;
	    $this->y_max = 0;
	} else {
	    throw new ADEIException(translate("Invalid INTERVAL, Unsupported window format: \"%s\"", $props['window']));
	}
    } else {
	if (($this->all_mode == INTERVAL::MODE_LOCKED)||($this->end_mode == INTERVAL::MODE_LOCKED)) {
	    $this->window_start = $this->interval_start;
	    $this->window_size = dsMathPreciseSubstract($this->interval_end, $this->interval_start);
	} else {
	    $this->window_size = false;
	    $this->window_start = false;
	}
	
	$this->y_min = 0;
	$this->y_max = 0;
    }

    $this->interval_items = -1;
    $this->window_items = -1;
    
    $this->empty_window = false;
    $this->empty_source = false;
    
    $this->sequence = INTERVAL::SEQUENCE_AUTO;

    $this->request_window_start = $this->window_start;
    $this->request_window_size = $this->window_size;
        
    if (($reader)&&($grp)) $this->ApplyReader($reader, $grp, $flags);
    
    $this->flexibility = $flags&(INTERVAL::FLAG_FLEXIBLE|INTERVAL::FLAG_REDUCIBLE);
 }

 private static function FindMonthByName($month) {
    $month_names = array("jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec");
    return array_search(strtolower(substr($month, 0, 3)), $month_names) + 1;
 }

 private static function PICalc($str, $y1, $y2 = false, $m1 = false, $m2 = false, $d1 = false, $d2 = false, $t1 = false, $t2 = false) {
	$duration = 0;	
    
	if (is_numeric($d1)) {
	    if (is_numeric($d2)) {
		if ($d1 > $d2) {
		    throw new ADEIException(translate("Invalid interval (%s) is specified: Start-day is above of end-day", $str));
		}
	    } else $d2 = $d1;
	    
	    if (!$t1) $duration = 86400;
	    
	    $madd = 0;
	} else {
	    $d1 = 1;
	    $d2 = 1;
	    
	    $madd = 1;
	}


	if ($m1) {
	    $m1 = INTERVAL::FindMonthByName($m1);
	    if ($m2) {
		$m2 = INTERVAL::FindMonthByName($m2) + $madd;
	        if ($m1 > $m2)
		    throw new ADEIException(translate("Invalid interval (%s) is specified: Start-month is above of end-month", $str));
	    } else $m2 = $m1 + $madd;
	    
	    if ($m2 == 13) {
		$m2 = 1;

		$yadd = 1;
	    } else {
		$yadd = 0;
	    }
	} else {
	    $m1 = 1;
	    $m2 = 1;
	    
	    $yadd = 1;
	}

	if (!is_numeric($y1)) {
	    $y1 = date('Y');
	    if (strtotime("{$y1}-{$m1}-01T00:00:00") > date('U')) $y1--;
	}
	
	if (is_numeric($y2)) {
	    if ($y1 > $y2)
		throw new ADEIException(translate("Invalid interval (%s) is specified: Start-year is above of end-year", $str));

	    $y2 += $yadd;
	} else $y2 = $y1 + $yadd;

	if ($t1) {
	    if (!$t2) {
		throw new ADEIException(translate("Invalid interval (%s) is specified", $str));
	    }
	    
	    $num = substr_count($t1, ":");
	    for ($i=$num; $i<2; $i++) $t1 .= ":00";
	    $num = substr_count($t2, ":");
	    for ($i=$num; $i<2; $i++) $t2 .= ":00";
	    
	} else {
	    $t1 = "00:00:00";
	    $t2 = "00:00:00";
	}


	$from = "{$y1}-{$m1}-{$d1}T${t1}";
	$to = "{$y2}-{$m2}-{$d2}T${t2}";
	
	return array($from, $to, $duration);
 }
 
 static function ParseInterval($str) {
    $month = "(Jan(uary|\.)?|Feb(ruary|\.)?|Mar(ch|\.)?|Apr(il|\.)?|May(\.)?|Jun(e|\.)?|Jul(y|\.)?|Aug(ust|\.)?|Sep(tember|\.)?|Oct(ober|\.)?|Nov(ember|\.)?|Dec(ember|\.)?)";
    $year = "(\d{4})";
    $day = "(\b\d{1,2}\b)";
    $plus = "\+\s*(?<plus_value>\d+)\s*(?<plus_units>(s|sec|second|seconds|m|min|minute|minutes|h|hour|hours|d|day|days)\.?)?";
    $timesep = ":";
    $time = "\d{1,2}({$timesep}\d{1,2}({$timesep}\d{1,2})?)?";
    $timestamp = "[\d.]+";

    $space = "\s*";
    $dash = "$space-$space";
    $hcomma = "[\s,;.T]+";
    $ycomma = "[\s,;]+";
    $dcomma = "[\s,;]+";
    
    if (preg_match("/^{$space}(?<year>{$year}){$space}$/", $str, $m)) {
	// 2008
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year']);
    } else if (preg_match("/^{$space}(?<year1>{$year})$dash(?<year2>{$year}){$space}$/", $str, $m)) {
	// 2006 - 2008
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year1'], $m['year2']);

    } else if (preg_match("/^{$space}(?<from>{$timestamp}){$space}-{$space}(?<to>{$timestamp}){$space}$/", $str, $m)) {
	if ((!is_numeric($m['from']))||(!is_numeric($m['to'])))
	    throw new ADEIException(translate("Invalid interval (%s) is specified", $str));
	if ($m['from'] > $m['to']) 
	    throw new ADEIException(translate("Invalid interval (%s) is specified: Start is above the end", $str));

	return "${m['from']}-${m['to']}";
    } else if (preg_match("/^{$space}(?<month>$month)({$ycomma}(?<year>{$year}))?{$space}$/i", $str, $m)) {
	// Feb., 2008
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year'], false, $m['month']);
    } else if (preg_match("/^{$space}(?<month1>$month){$dash}(?<month2>$month)({$ycomma}(?<year>{$year}))?{$space}$/i", $str, $m)) {
	// Feb. - Mar. 2008
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year'], false, $m['month1'], $m['month2']);
    } else if (preg_match("/^{$space}(?<month>$month){$dcomma}(?<day>$day)({$ycomma}(?<year>{$year}))?({$hcomma}(?<time1>{$time}){$space}-{$space}(?<time2>$time))?{$space}$/i", $str, $m)) {
	// Feb 21, 2008 15:00 - 17:00
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year'], false, $m['month'], false, $m['day'], false, $m['time1'], $m['time2']);
    } else if (preg_match("/^{$space}(?<month>$month){$dcomma}(?<day1>$day){$dash}(?<day2>$day)({$ycomma}(?<year>{$year}))?{$space}$/i", $str, $m)) {
	// Feb 21 - 23, 2008
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year'], false, $m['month'], false, $m['day1'], $m['day2']);
    } else if (preg_match("/^{$space}((?<time1>{$time}){$space}-{$space}(?<time2>$time){$hcomma})?(?<day1>$day)({$dash}(?<day2>$day))?{$dcomma}(?<month>$month)({$ycomma}(?<year>{$year}))?{$space}$/i", $str, $m)) {
	// 21 - 23 Feb 2008 , 21 Feb 2008, 15:00:00 - 16:00:01 21 Feb 2008
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year'], false, $m['month'], false, $m['day1'], $m['day2'], $m['time1'], $m['time2']);
    } else if (preg_match("/^{$space}((?<year>{$year}){$ycomma})?(?<month1>$month)({$dash}(?<month2>$month))?{$space}$/i", $str, $m)) {
	// 2008, Feb. - Mar.
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year'], false, $m['month1'], $m['month2']);
    } else if (preg_match("/^{$space}((?<year>{$year}){$ycomma})?(?<month>$month){$dcomma}(?<day1>$day)(({$dash}(?<day2>$day))|({$hcomma}(?<time1>{$time}){$space}-{$space}(?<time2>$time)))?{$space}$/i", $str, $m)) {
	// 2008, Feb 21 - 23     2008, Feb 21 15:00 - 17:00
	list($from,$to,$duration) = INTERVAL::PICalc($str, $m['year'], false, $m['month'], false, $m['day1'], $m['day2'], $m['time1'], $m['time2']);
    } else if (preg_match("/^{$space}(?<from>.*){$plus}?{$space}$/i", $str, $m)) {
	// <date> +<duration>
	$from = $m['from'];
	
	$duration = $m['plus_value'];
	switch (strtolower(substr($m['plus_units'],0,1))) {
	    case "m":
		$duration *= 60;
	    break;
	    case "h":
		$duration *= 3600;
	    break;
	    case "d":
		$duration *= 86400;
	    break;
	}
    } else {
	if (substr_count($str, "-") == 1) $divider = "-";
	else if (preg_match_all("/\s-\s/", $str, $m) == 1) $divider = "\s+-\s+";
	else throw new ADEIException(translate("Unreckognized interval (%s) is specified", $str));
	
	list($from, $to) = preg_split("/$divider/", $str);
    }

#    echo "$from - $to + $duration\n";
    try {
	$fdate = new DateTime($from);
	if ($to) $tdate = new DateTime($to);
	else $tdate = new DateTime($from);
    } catch (Exception $e) {
	 throw new ADEIException(translate("Unreckognized interval (%s) is specified", $str));    
    }

    $from = $fdate->format("U");
    $to = ($tdate->format("U") + $duration);

    if ($from > $to)
	throw new ADEIException(translate("Invalid interval (%s) is specified: Start is above the end", $str));

    return "$from-$to";
 }
 
 
 function SetupInterval($from, $to) {
    $this->interval_start = $from;
    $this->interval_end = $to;

    if ($to) {
/*        if ($from) {*/
	    $this->all_mode = INTERVAL::MODE_LOCKED;
	    $this->end_mode = INTERVAL::MODE_LOCKED;
	    $this->start_mode = INTERVAL::MODE_LOCKED;
/*	} else {
	    throw new ADEIException(translate("Invalid INTERVAL '%s': have end but no start", "$from-$to"));
	}*/
    } else {
	if ($from) $this->start_mode = INTERVAL::MODE_LOCKED;
	else $this->start_mode = INTERVAL::MODE_UNLOCKED;

	$this->end_mode = INTERVAL::MODE_UNLOCKED;
	$this->all_mode = INTERVAL::MODE_UNLOCKED;
    }

    $this->window_start = false;
    $this->window_size = false;

    $this->empty_source = false;
    $this->empty_window = false;
    
    $this->end_by_data = true;
 }
 
 function SetupWindow($type = INTERVAL::WINDOW_MAXIMUM, $center = false, $size = 0, $flags = 0) {
    switch ($type) {
	case INTERVAL::WINDOW_RIGHT:
	    $this->window_start = $center;
	    $this->window_size = $size;
	    
	    if (($this->window_start)&&($this->end_mode)) $this->end_mode = INTERVAL::MODE_WINDOWLOCKED;
	    if (($this->window_size)&&($this->all_mode)) $this->all_mode = INTERVAL::MODE_WINDOWLOCKED;
	    
	    $this->sequence = INTERVAL::SEQUENCE_FORWARD;
	break;
	case INTERVAL::WINDOW_LEFT:
	    if ($size) {
		if ($center !== false) {
		    $this->window_start = dsMathPreciseSubstract($center, $size);
		    if ($this->end_mode) $this->end_mode = INTERVAL::MODE_WINDOWLOCKED;
		} else {
		    $this->window_start = false;
		}
		if ($this->all_mode) $this->all_mode = INTERVAL::MODE_WINDOWLOCKED;
	    } else {
		$this->window_start = $center;

		if (($center)&&($this->end_mode)) $this->end_mode = INTERVAL::MODE_WINDOWLOCKED;
	    }
	    $this->window_size = $size;
	    $this->sequence = INTERVAL::SEQUENCE_BACKWARD;
	break;
	case INTERVAL::WINDOW_MAXIMUM:
/*
	    if (($this->interval_end!==false)&&($this->interval_start!==false)) {
	        $this->window_start = $this->interval_start;
		$this->window_size = dsMathPreciseSubstract($this->interval_end, $this->interval_start);
	    } elseif ($this->interval_start!==false) {
	        $this->window_start = $this->interval_start;
		$this->window_size = false;
	    } elseif ($this->interval_end===false) {
	        $this->window_start = false;
		$this->window_size = false;
	    } else {
	        $this->window_start = 0;
		$this->window_size = $this->interval_end;
		//throw new ADEIException(translate("Invalid INTERVAL: have end but no start"));
	    }
*/
	    $this->window_start = false;
	    $this->window_size = false;
	    $this->sequence = INTERVAL::SEQUENCE_FORWARD;
	break;
	//return; /* Updating is not needed */
	case INTERVAL::WINDOW_NEIGHBOURHOOD:
	    if ($size) {
		if ($center !== false) {
		    $this->window_start = dsMathPreciseSubstract($center, $size / 2);
		    if ($this->end_mode) $this->end_mode = INTERVAL::MODE_WINDOWLOCKED;
		} else {
		    $this->window_start = false;
		}
		$this->window_size = $size;
		if ($this->all_mode) $this->all_mode = INTERVAL::MODE_WINDOWLOCKED;
	    } else {
		$this->window_start = $center;
		$this->window_size = $size;

		if (($center)&&($this->end_mode)) $this->end_mode = INTERVAL::MODE_WINDOWLOCKED;
	    }
	    $this->sequence = INTERVAL::SEQUENCE_AUTO;
	break;
	default:
	    throw new ADEIException(translate("Invalid window type '%s' is specified", $type));
    }
    
    $this->UpdateWindow(true);
 }

 function SetSequence($seq = false) {
    if ($seq === false) $this->sequence = INTERVAL::SEQUENCE_AUTO;
    else $this->sequence = $seq;
 }

 function EnableFlexibility($reducible = false) {
    if ($reducible) {
	$this->flexibility = (INTERVAL::FLAG_FLEXIBLE|INTERVAL::FLAG_REDUCIBLE);
    } else {
	$this->flexibility = INTERVAL::FLAG_FLEXIBLE;
    }
 }

 function UpdateWindow($forbid_empty_window = false) {
/*
    echo date('c', $this->GetWindowStart()) . "\n";
    echo date('c', $this->GetIntervalStart()) . "\n";
    echo date('c', $this->GetWindowEnd()) . "\n";
    echo date('c', $this->GetIntervalEnd()) . "\n";
*/    
    if ($this->empty_window) return;
    
    if ($this->interval_start !== false) {
	if ($this->window_start === false) {
	    $this->window_start = $this->interval_start;
	} else if ($this->interval_start > $this->window_start) {
	    $this->window_size -= dsMathPreciseSubstract($this->interval_start, $this->window_start);
	
	    if ($this->window_size <= 0) {
		if ($this->window_size < 0) {
		    $this->window_start = false;
	    	    $this->window_size = false;	

		    $this->empty_window = true;
		}

		if ($forbid_empty_window) {
		    throw new ADEIException(translate("The WINDOW is out of the INTERVAL '%s' range", $this->interval_start . "-" . $this->interval_end));
		}
	    } else {
		$this->window_start = $this->interval_start;
	    }

	    $this->window_items = -1;
	}
    }

    if ($this->interval_end !== false) {
	if ($this->window_start === false) {
	    if ($this->window_size !== false) {
		$this->window_start = dsMathPreciseSubstract($this->interval_end, $this->window_size);
	    }
	} else if ($this->window_size === false) {
	    $this->window_size = dsMathPreciseSubstract($this->interval_end, $this->window_start);
	} else if (($this->window_start + $this->window_size) > $this->interval_end) {
	    if ($this->end_mode == INTERVAL::MODE_UNLOCKED) {
		$this->window_start = dsMathPreciseSubstract($this->interval_end, $this->window_size);
	    } else if ($this->interval_end <= $this->window_start) {
		$this->window_start = false;
		$this->window_size = false;

		$this->empty_window = true;
	    
		if ($forbid_empty_window)
		    throw new ADEIException(translate("The WINDOW is out of the INTERVAL '%s' range", $this->interval_start . "-" . $this->interval_end));
	    } else {
		$this->window_size = dsMathPreciseSubstract($this->interval_end, $this->window_start);
	    }
	    $this->window_items = -1;
	}
    }
 }

 function Apply($first, $last, $records = false) {
    $end_mode = 0;
    if (!is_numeric($first)||(!is_numeric($last))) {
	$this->empty_source = true;
	$this->window_start = false;
	$this->window_end = false;
    } else { 
	$this->empty_source = false;
    
	if ($records !== false) $this->interval_items = $records;

	if ($this->start_mode) {
	    $this->interval_start = $first;
	}

	if (($this->end_mode)||($this->all_mode)) {
	    if ($this->end_by_data) $this->interval_end = $last;
	    else $this->interval_end = ceil(date("U.u"));
	}

	if (!$this->end_by_data) {
	    if ($this->end_mode == INTERVAL::MODE_WINDOWLOCKED) {
		$start = $this->GetWindowStart();
		if ($start < $this->interval_start) $this->interval_start = $start;
		
		if ($this->all_mode == INTERVAL::MODE_WINDOWLOCKED) {
		    $end = $this->GetWindowEnd();
		    if ($end > $this->interval_end) $this->interval_end = $end;
		}
	    }
	}
	
	if ($this->end_mode == INTERVAL::MODE_UNLOCKED) {
	    if ($this->all_mode == INTERVAL::MODE_UNLOCKED) {
	        $this->window_start = $this->interval_start;
	        $this->window_size = dsMathPreciseSubstract($this->interval_end, $this->interval_start);
	    } else {
		$this->window_start = dsMathPreciseSubstract($this->interval_end, $this->window_size);
	    	$this->UpdateWindow();
	    }
	} else {
	    if ($this->all_mode == INTERVAL::MODE_UNLOCKED) {
	        $this->window_size = dsMathPreciseSubstract($this->interval_end, $this->window_start);
	        $this->UpdateWindow();
	    } else {
	        $this->UpdateWindow();
	    }
	}
    }
 }
 
 function ApplyReader(READER &$reader, LOGGROUP &$grp, $flags = 0) {
    if (($flags&INTERVAL::NEED_AMOUNT)||($this->end_mode)||($this->all_mode)) {
	if (flags&INTERVAL::NEED_AMOUNT) {
	    $info = $reader->GetGroupInfo($grp, REQUEST::NEED_INFO|REQUEST::NEED_COUNT);
	    $this->Apply($info['first'], $info['last'], $info['records']);
	} else {
	    $info = $reader->GetGroupInfo($grp, REQUEST::NEED_INFO);
	    $this->Apply($info['first'], $info['last']);
	}
    } else {
        $this->UpdateWindow();
    }
 }
 
 function ApplyCache(CACHE &$cache, $flags = 0) {
    if (($flags&INTERVAL::NEED_AMOUNT)||($this->end_mode)||($this->all_mode)) {
	if ($flags&INTERVAL::NEED_AMOUNT) {
	    $info = $cache->GetInfo(REQUEST::NEED_COUNT);
	    $this->Apply($info['first'], $info['last'], $info['records']);
	} else {
	    $info = $cache->GetInfo();
	    $this->Apply($info['first'], $info['last']);
	}
    } else {
        $this->UpdateWindow();
    }
 }

 function ApplyIntervals(array $ivls) {
    if (!$ivls) return;

    /* Ignoring items, limit */
    
    $istart = false;
    $iend = false;

    $wstart = false;
    $wend = false;
    
    $min = false;
    $max = false;
    
    $empty = true;
    $empty_source = true;
    
    for ($i = 0; $i < sizeof($ivls); $i++) {
	if ($ivls[$i]->IsEmpty()) {
	    if (!$ivls[$i]->empty_source) $empty_source = false;
	    continue;
	}
	
	$empty = false;

	$istart1 = $ivls[$i]->GetIntervalStart();
	$iend1 = $ivls[$i]->GetIntervalEnd();

	$wstart1 = $ivls[$i]->GetWindowStart();
	$wend1 = $ivls[$i]->GetWindowEnd();
    
	$min1 = $ivls[$i]->y_min;
	$max1 = $ivls[$i]->y_max;
	
	if (($istart1)||($iend1)) {
	    if (($istart === false)||($istart1 < $istart)) $istart = $istart1;
	    if (($iend === false)||($iend1 > $iend)) $iend = $iend1;
	}
	
	if (($wstart1)||($wend1)) {
	    if (($wstart === false)||($wstart1 < $wstart)) $wstart = $wstart1;
	    if (($wend === false)||($wend1 > $wend)) $wend = $wend1;
	}

	if (($min1)||($max1)) {
	    if (($min === false)||($min1 < $min)) $min = $min1;
	    if (($max === false)||($max1 > $max)) $max = $max1;
	}
    }
    
    if (($istart!==false)&&($iend!==false)) {
	$this->interval_start = $istart;
	$this->interval_end = $iend;
    }
    if (($wstart!==false)&&($wend!==false)) {
        $this->window_start = $wstart;
	$this->window_size = dsMathPreciseSubstract($wend, $wstart);
    }
    if (($min!==false)&&($max!==false)) {
	$this->y_min = $min;
	$this->y_max = $max;     
    }

    if ($empty) {
	if ($empty_source) $this->empty_source = true;
	else $this->empty_window = true;
    } else $this->UpdateWindow();
}
 
 function Limit($min = false, $max = false) {
    if (($min !== false)&&(($this->interval_start===false)||($min > $this->interval_start))) {
        $this->start_mode = INTERVAL::MODE_LOCKED;

	if (($this->interval_end!==false)&&($min > $this->interval_end))
	    $this->interval_start = $this->interval_end;
	else
	    $this->interval_start = $min;

	$update = 1;
    }
    if (($max !== false)&&(($this->interval_end===false)||($max < $this->interval_end))) {
        $this->all_mode = INTERVAL::MODE_LOCKED;
        $this->end_mode = INTERVAL::MODE_LOCKED;
        $this->start_mode = INTERVAL::MODE_LOCKED;

	if (($this->interval_start!==false)&&($max < $this->interval_start)) {
	    $this->interval_end = $this->interval_start;
	} else {
	    $this->interval_end = $max;
	}

	$update = 1;
    }

    if ($update) {
	$this->interval_items = -1;
	$this->UpdateWindow();
    }

    if ($max !== false) {
	$this->all_mode = INTERVAL::MODE_LOCKED;
	$this->end_mode = INTERVAL::MODE_LOCKED;
    } elseif ($min !== false) $this->end_mode = INTERVAL::MODE_LOCKED;
 }
 
 function GetSQL(CACHEDB &$cache, $table = false, $limit = 0, $use_subseconds = false, $sequence = false, $sampling = 0) {
    global $MYSQL_FORCE_INDEXES;

    $res = array();

    if (!$limit) $limit = $this->GetItemLimit();
    
    if ($limit)
	$res['limit'] = " LIMIT " . abs($limit);
    else
	$res['limit'] = "";

    if ($sequence === false) $sequence = $this->sequence;

    $from = $this->GetWindowStart();
    $ifrom = floor($from);

    if (($sequence == INTERVAL::SEQUENCE_UNSORTED)/*||($limit == 1)*/) {
	$res['sort'] = "";
    } elseif (($limit < 0)||($sequence == INTERVAL::SEQUENCE_BACKWARD)) {
	$res['sort'] = "ORDER BY `id` DESC";
    } else {
	$res['sort'] = "ORDER BY `id` ASC";
    }

    if ($use_subseconds) {
        $res['list'] = "EXTENDED_UNIX_TIMESTAMP(time) AS timestamp, `ns`";
    } else {
        $res['list'] = "EXTENDED_UNIX_TIMESTAMP(time) AS timestamp";
    }

    if ($this->IsEmpty()) {
	$res['cond'] = "WHERE (`id` = NULL)";
	return $res;
    }

    if ($this->window_size > 0) {
	$to = $this->GetWindowEnd();
	$ito = floor($to);
	
	if ($use_subseconds) {
	    if ($from == $ifrom) $nfrom = 0;
	    else $nfrom = round(1000000000*("0" . strstr($from, ".")));
	    if ($to == $ito) $nto = 0;
	    else $nto = round(1000000000*("0" . strstr($to, ".")));
	} else {
	    $nfrom = 0;
	    $nto = 0;
	}

	$res['cond'] = "WHERE ((`id` >= ADEI_TIMESTAMP($ifrom, $nfrom)) AND (`id` < ADEI_TIMESTAMP($ito, $nto)))";
    } else {
	if ($this->sequence == INTERVAL::SEQUENCE_BACKWARD) $sign = '<';
	else $sign = '>';

	$sqlfrom = $cache->SQLTime($ifrom);

	if (($use_subseconds)&&($ifrom != $from)) {
	    $nfrom = round(1000000000*("0" . strstr($from, ".")));
	} else {
	    $nfrom = 0;
	}
	$res['cond'] = "WHERE `id` $sign ADEI_TIMESTAMP($ifrom, $nfrom)";
    }

    if ($sampling) {
	if (!$table)
	    throw new ADEIException(translate("Ineternal Error: The table name should be passed to query generator if resampling is needed"));

	$sampling *= 1000000000;
/*
	The first command is terribly slow, and the second should be avoided
	because we want floating timestamp start.

        $groupping = "FLOOR((`id` - ADEI_TIMESTAMP($ifrom, $nfrom)) / $sampling)";
        $groupping = "FLOOR((`id` - 1000000000*$from) / $sampling)";
*/

        $groupping = "FLOOR(`id` / $sampling)";

	if ($MYSQL_FORCE_INDEXES) $idx_fix = "FORCE INDEX (id)";
	else $idx_fix = "";
	
        $res['join'] = ", (SELECT MAX(`id`) AS tmptbl_id FROM `$table` $idx_fix {$res['cond']} GROUP BY $groupping) AS tmptbl";
	$res['cond'] = "WHERE tmptbl.tmptbl_id = `id`";
    }

    $res['index'] = "id";

/*    
    if ($use_subseconds) {
        $res['list'] = "EXTENDED_UNIX_TIMESTAMP(time) AS timestamp, `ns`";

	if (($sequence == INTERVAL::SEQUENCE_UNSORTED))
	    $res['sort'] = "";
	elseif (($limit < 0)||($sequence == INTERVAL::SEQUENCE_BACKWARD))
	    $res['sort'] = "ORDER BY `time` DESC, `ns` DESC";
	else
	    $res['sort'] = "ORDER BY `time` ASC, `ns` ASC";
    } else {
        $res['list'] = "EXTENDED_UNIX_TIMESTAMP(time) AS timestamp";

	if (($sequence == INTERVAL::SEQUENCE_UNSORTED))
	    $res['sort'] = "";
	elseif (($limit < 0)||($sequence == INTERVAL::SEQUENCE_BACKWARD))
	    $res['sort'] = "ORDER BY `time` DESC";
	else 
	    $res['sort'] = "ORDER BY `time` ASC";
    }


    if ($this->window_size > 0) {
	$to = dsMathPreciseAdd($this->window_start, $this->window_size);
	$ito = floor($to);

	if (($use_subseconds)&&(($ifrom != $from)||($ito != $to))) {
	    if ($ifrom == $ito) {
		$sqlfrom = $cache->SQLTime($ifrom);
		if ($from == $ifrom) $nfrom = 0;
		else $nfrom = round(1000000000*("0" . strstr($from, ".")));
		if ($to == $ito) $nto = 0;
		else $nto = round(1000000000*("0" . strstr($to, ".")));

	        $res['cond'] = "WHERE ((`time` = $sqlfrom) AND (`ns` >= $nfrom) AND (`ns` < $nto))";
	    } else {
		$cond = "";
	        if ($ifrom != $from) {
		    $nfrom = round(1000000000*("0" . strstr($from, ".")));
		    $sqlfrom = $cache->SQLTime($ifrom);
		    $cond = "((`time` = $sqlfrom) AND (`ns` >= $nfrom))";
		    $ifrom++;
		}

		if ($ifrom != $ito) {
		    if ($cond) $cond .= " OR ";

		    $sqlfrom = $cache->SQLTime($ifrom);
	    	    $sqlto = $cache->SQLTime($ito);
    		    $cond .= "((`time` >= $sqlfrom) AND (`time` < $sqlto))";
		} else $sqlto = false;

		if ($ito != $to) {
		    if ($cond) $cond .= " OR ";
		    if (!$sqlto) $sqlto = $cache->SQLTime($ito);
		
		    $nto = round(1000000000*("0" . strstr($to, ".")));
		    $cond .= "((`time` = $sqlto) AND (`ns` < $nto))";
		}
	
		$res['cond'] = "WHERE ($cond)";
	    }
	} else {
	    $sqlfrom = $cache->SQLTime($ifrom);
	    $sqlto = $cache->SQLTime($ito);
    	    $res['cond'] = "WHERE ((`time` >= $sqlfrom) AND (`time` < $sqlto))";
	}
    } else {
	if ($this->sequence == INTERVAL::SEQUENCE_BACKWARD) $sign = '<';
	else $sign = '>';

	$sqlfrom = $cache->SQLTime($ifrom);

	if (($use_subseconds)&&($ifrom != $from)) {
	    $nfrom = round(1000000000*("0" . strstr($from, ".")));
	    $res['cond'] = "WHERE (((`time` = $sqlfrom) AND (`ns` $sign $nfrom)) OR (`time` $sign $sqlfrom))";
	} else {
	    $res['cond'] = "WHERE `time` $sign $sqlfrom";
	}
    }

    if ($sampling) {
	if (!$table)
	    throw new ADEIException(translate("Ineternal Error: The table name should be passed to query generator if resampling is needed"));

	if ($use_subseconds) {
	    $sampling*=1000000000;
	    $rfrom = ($from - $ifrom)*1000000000;	    
	    $groupping = "FLOOR(((EXTENDED_UNIX_TIMESTAMP(`time`) - $ifrom) * 1000000000 + (`ns` - $rfrom))/$sampling)";
	    $res['join'] = ", (SELECT MIN(time) AS tmptbl_time, MIN($groupping) AS tmptbl_idx FROM $table {$res['cond']} GROUP BY $groupping) AS tmptbl";
	    $res['cond'] = "WHERE tmptbl.tmptbl_time = `time` AND tmptbl.tmptbl_idx = $groupping";
	} else {
	    $groupping = "FLOOR((EXTENDED_UNIX_TIMESTAMP(`time`) - " . ($ifrom) . " )/$sampling)";
	    $res['join'] = ", (SELECT MIN(`time`) AS tmptbl_time FROM $table {$res['cond']} GROUP BY $groupping) AS tmptbl";
	    $res['cond'] = "WHERE tmptbl.tmptbl_time = `time`";
	}
    }

*/
    
#    echo print_r($res, true) . "\n";
#    exit;

    return $res;
 }
 
 function GetRequestEndTime() {
    $time = ceil(date("U.u"));
    if ($this->end_mode == INTERVAL::MODE_LOCKED) {
	if (($this->interval_end !== false)&&($this->interval_end < $time)) {
	    $time = $this->interval_end;
	}
    }
    return $time;
 }
 
 
 function GetRequestWindowStart() {
    if (!$this->end_by_data) {
	if ($this->request_window_start !== false) {
	    return $this->request_window_start;
	} else if ($this->request_window_size > 0) {
	    return dsMathPreciseSubstract($this->GetRequestEndTime(), $this->request_window_size);
	}
    }
    return $this->GetWindowStart();
 }
 
 function GetRequestWindowSize() {
    if (!$this->end_by_data) {
	if ($this->request_window_size > 0) {
	    return $this->request_window_size;
	} else if ($this->request_window_start !== false) {
	    return dsMathPreciseSubstract($this->GetRequestEndTime(), $this->request_window_start);
	}
    }
    return $this->GetWindowSize();
 }

 function GetRequestWindowEnd() {
    if (!$this->end_by_data) {
	if (($this->request_window_start !== false)||($this->request_window_size > 0)) {
	    if (($this->request_window_start !== false)&&($this->request_window_size > 0)) {
		return dsMathPreciseAdd($this->request_window_start, $this->request_window_size);
	    } else {
		return $this->GetRequestEndTime();
	    }
	}

    }
//    print_r($this);
    return $this->GetWindowEnd();
 }
 
 
 function GetWindowStart($default = NULL) {
    if ($this->IsEmpty()) {
	return 0;
	//throw new ADEIException(translate("INTERVAL window is empty"));
    }
    
    if ($this->window_start === false) {
	if ($default === NULL) {
	    throw new ADEIException(translate("INTERVAL window have undefined start"));
	} else if ($default === true) {
	    return WINDOW::EPOCH_START;
	}

	return $default;
    }
    
    return $this->window_start;
 }

 function GetWindowEnd($default = NULL) {
    if ($this->IsEmpty()) {
	return 0;
	//throw new ADEIException(translate("INTERVAL window is empty"));
    }

    if ($this->window_start === false) {
	if ($default === NULL) {
	    throw new ADEIException(translate("INTERVAL window have undefined start"));
	} else if ($default === true) {
	    return gettimeofday(true);
	}

	return $default;
    }

    if ($this->window_size === false) {
	if ($default === NULL) {
	    throw new ADEIException(translate("INTERVAL window have undefined size"));
	} else if ($default === true) {
	    return gettimeofday(true);
	}
	
	return $default;
    }

    return dsMathPreciseAdd($this->window_start, $this->window_size);
 }
 
 function GetWindowSize() {
    if ($this->IsEmpty()) {
	throw new ADEIException(translate("INTERVAL window is empty"));
    }

    if ($this->window_size === false) {
	throw new ADEIException(translate("INTERVAL window have undefined size"));
    }

    return $this->window_size;
 }
 
 function GetItemLimit() {
    return $this->item_limit;
 }
 
 function SetItemLimit($limit) {
    $this->item_limit = $limit;
 }
 
 function IsEmpty() {
    if ($this->empty_source) return INTERVAL::EMPTY_SOURCE;
    if ($this->empty_window) return INTERVAL::EMPTY_WINDOW;

    return false;
 }
 
 function GetIntervalStart() {
    if ($this->interval_start === false) {
	throw new ADEIException(translate("INTERVAL have undefined start"));
    }
    return $this->interval_start;
 }
 
 function GetIntervalEnd() {
    if ($this->interval_end === false) {
	throw new ADEIException(translate("INTERVAL have undefined end"));
    }
    return $this->interval_end;
 }

 function GetRequestWindowName($format = NAME_FORMAT_DEFAULT) {
    switch ($format) {
	case NAME_FORMAT_HUMAN:
	    throw new ADEIException(translate("Not implemented yet"));
	case NAME_FORMAT_ISO8601:
	    throw new ADEIException(translate("Not implemented yet"));
	default:
	    throw new ADEIException(translate("Unknown format is requested"));
    }
 }
 
 function GetName($format = NAME_FORMAT_DEFAULT) {
    switch ($format) {
	case NAME_FORMAT_HUMAN:
	    throw new ADEIException(translate("Not implemented yet"));
	break;
	case NAME_FORMAT_ISO8601:
	    if ($this->IsEmpty()) {
		return "empty";
	    } else if (($this->window_start!==false)&&($this->window_size!==false)) {
	        return date("Ymd\THis", floor($this->window_start)) . "-" . date("Ymd\THis", ceil($this->window_start + $this->window_size));
	    } else if ($this->window_size!==false) {
		$ws = $this->window_size;
		$msg = "";
		if ($ws >= 1) {
		    if ($ws > 86400) {
			$msg .= floor($ws / 86400) . "d";
			$ws = $ws % 86400;
		    }
		    if ($ws > 3600) {
			$msg .= floor($ws / 3600) . "h";
			$ws = $ws % 3600;
		    }
		    if ($ws > 60) {
			$msg .= floor($ws / 60) . "m";
			$ws = $ws % 60;
		    }
		    $msg .= floor($ws) . "s";
		} else if ($ws >= 0.001) {
		    $msg = floor($ws*1000) . "ms";
		} else if ($ws >= 0.000001) {
		    $msg = floor($ws*1000000) . "us";
		} else {
		    $msg = floor($ws*1000000000) . "ns";
		}
	    } else {
		return "everything";
	    }
	break;
	default:
	    throw new ADEIException(translate("Unknown format is requested"));
    }
 }
 
}

?>