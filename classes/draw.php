<?php
require($ADEI_ROOTDIR . "/classes/drawtext.php");

class DRAW_JPGraphAxisHolder {
 var $axis;
 var $draw;

 var $start;
 var $length;
 var $configured;
 var $log;

 var $format_type;
 
 function __construct(DRAW $draw, GRAPHAxis $axis, Axis $jpaxis) {
    $this->draw = $draw;
    $this->axis = $axis;
    $this->jpaxis = $jpaxis;
    $this->log = $axis->IsLogarithmic();
    $this->configured = false;
    $this->format_type = 0;
 }

 function Configure() {
    if ($this->configured) return;
    
    $length = dsMathPreciseSubstract($this->jpaxis->scale->GetMaxVal(), $this->jpaxis->scale->GetMinVal());
    $precision = $length/5;

    $this->length = $length;
    $this->format_type = $this->GetFormatType($this->jpaxis->scale->GetMinVal(), $dummy)|$this->GetFormatType($this->jpaxis->scale->GetMaxVal(), $dummy);

    if ($format_type) {
	    // x.xxe... (3 major numbers)
	$val_precision = max(abs($this->jpaxis->scale->GetMinVal()), abs($this->jpaxis->scale->GetMaxVal())) / 1000;
    } else {
	// xxx.xx (at least 5 major numbers)
	$val_precision = max(abs($this->jpaxis->scale->GetMinVal()), abs($this->jpaxis->scale->GetMaxVal())) / 10000;
    }
    $val_precision = pow(10,floor(log10($val_precision)));


    if ($precision < $val_precision) {
	if (!$this->log) {
	    $format = $this->GetFormat($this->jpaxis->scale->GetMinVal(), $dummy);
	    $this->start = sprintf($format, $this->jpaxis->scale->GetMinVal());
	    if ($this->start > $this->jpaxis->scale->GetMinVal()) {
		if ($format == "%.2e") {
		    $pos = stripos($value, 'e');
		    if ($pos !== false) {
			$substract = "0.01" . substr($this->start, $pos);
			$this->start = sprintf($format, $this->start - $substract);
		    }
		} else {
		    $pos = floor(log10(abs($this->start)));
		    if ($pos < 0) $pos = 0;
		    $pos = 5 - $pos - 1;	// number of fp positions
		    if ($pos > 0) $substract = pow(10, -$pos);
		    else $substract = 1;
		    $this->start = sprintf($format, $this->start - $substract);
		}
	    }
	}

	$this->format_type = 
	    $this->GetFormatType(
		($this->jpaxis->scale->GetMinVal()<$this->start)?dsMathPreciseSubstract($this->start, $this->jpaxis->scale->GetMinVal()):dsMathPreciseSubstract($this->jpaxis->scale->GetMinVal(), $this->start),
		$dummy
	    ) | 
	    $this->GetFormatType(
		($this->jpaxis->scale->GetMaxVal()<$this->start)?dsMathPreciseSubstract($this->start, $this->jpaxis->scale->GetMaxVal()):dsMathPreciseSubstract($this->jpaxis->scale->GetMaxVal(), $this->start),
		$dummy
	    ); 
    } else {
	$this->start = false;
    }

    $this->configured = true;
 }
  
 function GetFormatType($value, &$newvalue) {
    $avalue = abs($value);

	// Fixing buggy 0
    if ((!$this->log)&&(($avalue <= 1E-6)||(($this->format_type)&&($avalue <= 1E-3)))) {
#	echo $avalue . " , " . $length . "!!!!\n";
	if ($this->format_type) {
	    if (($avalue * 1E+3) <= $this->length) $value = 0;
	} else {
	    if (($avalue * 1E+6) <= $this->length) $value = 0;
	}
    } else  if ($avalue != 0) {
	if ($this->format_type) {
	    $val = substr(sprintf("%.2e", $avalue), 0, 4);
	    if (($val)&&(abs($val-round($val))<=1.01E-2)) {
		$digits = round(log($val/$avalue, 10));
		$value = round($value, $digits);
	    }
	} else {
	    $val = substr(sprintf("%.5e", $avalue), 0, 7);
	    if (($val)&&(abs($val-round($val))<=1.01E-5)) {
		$digits = round(log($val/$avalue, 10));
		$value = round($value, $digits);
	    }
	}
    }
    
    $newvalue = $value;
    
    if (($avalue > 9999)||(($value)&&($avalue<0.0001))) return 1;
    return 0;
 }
 
 function GetFormat($value, &$newvalue) {
    $type = $this->GetFormatType($value, $newvalue);
    if ($this->format_type === 1) $type = 1;
    
    /* In 'e' format .2 specifies number of positions after comma and
    in 'g' format it specifies overal number of numbers (exlcuding decimal
    point) */
    switch($type) {
	case 1:
	    $format="%.2e";
	break;
	default:
	    $format="%.5g";//"%.3g";
    }
    
    return $format;
 }
 
 function YLabelFormat($value) {
//    if ($this->log) {
//	return sprintf("%.1e", $value);
//    }
	//$value = -1111000.111111;
    $this->Configure();

    if ($this->start !== false) {
	if ($value < $this->start) {
	    $value = dsMathPreciseSubstract((float)$this->start, $value);
	    $format = $this->GetFormat($value, $value);
	    return "{$this->start}\n" . sprintf("-$format", $value);
	} else {
	    $value = /*$value - $this->start;*/ dsMathPreciseSubstract($value, (float)$this->start);
	    $format = $this->GetFormat($value, $value);
	    return "{$this->start}\n" . sprintf("+$format", $value);
	}
    }
    
    $format = $this->GetFormat($value, $value);
    return sprintf($format, $value);
 }
};

class DRAW extends DRAWText {
 var $reader;
 var $spec;

 var $graph_axes;
 var $graph_margin; 
 var $graph_interval;
 var $graph_yaxis_size;

 var $time_limit;
 var $use_cache_timewindow;
 var $set_time_axes;
 
 var $aggregator;		// Aggregating algorithm
 var $interpolate_gaps;		// Do not indicate missing data, do linear interpolation instead
 var $allowed_gap;		// The allowed gaps between data, report if greater (if no interpolation)

 var $show_marks;		// Set marks on the graph if data gaps are detected
 var $show_gaps;		// Present information about gaps in the data on graph
 
 var $precision_mode;		// Requested precision
 var $precision;		// Actual graph precision in pixels

 var $plot_mode;		// Plotting mode (standard ADEI mode or Munin mode)

 var $hide_axes;		// Flag indicating what axes should be hiden
 
 const MARKS_NEVER = 0;
 const MARKS_DEFAULT = 1;
 const MARKS_GAPS = 2;
 const MARKS_ALWAYS = 3;
 
 const SHOW_NONE = 0;
 const SHOW_EMPTY = 1;
 const SHOW_POINTS = 2;
 const SHOW_GAPS = 3;

 const PRECISION_DEFAULT = 0;
 const PRECISION_LOW = -1;
 const PRECISION_HIGH = -2;
 
 const PLOT_STANDARD = 0;
 const PLOT_CUSTOM = 1;

 const MIN_LOG = 1e-10;

 function __construct(DATARequest $props = NULL) {
    global $GRAPH_INTERPOLATE_DATA_GAPS;
    global $GRAPH_ACCURACY_MARKS_IF_GAPS;
    
    global $GRAPH_INDICATE_MISSING_DATA;
    global $GRAPH_INDICATE_DATA_DENSITY;

    if ($props) parent::__construct($props);
    else parent::__construct(new DATARequest());

    if (($this->width<32)||($this->height<32)) {
	throw new ADEIException(translate("The graph dimmensions (%ux%u) are too small, use at least 32x32", $this->width, $this->height));
    }

/*  DS, ToDo: We should restore some variables before we can reuse files 
    if (is_file($TMP_PATH . "/" .  $this->tmpfile)) $this->ready = true;
    else */

    $this->reader = $this->req->CreateReader();

    $opts = $this->req->GetOptions();
    $this->use_cache_timewindow = $opts->Get('use_cache_timewindow');
    $this->time_limit = $opts->GetDateLimit();
    $this->set_time_axes = !$opts->Get('optimize_time_axes');

    if (isset($this->req->props['precision']))
	$this->precision_mode = $this->FindPrecisionMode($this->req->props['precision']);
    else
	$this->precision_mode = 0;
    
    if (isset($this->req->props['aggregation']))
	$this->aggregator = CACHE::FindAggregator($this->req->props['aggregation']);
    else
	$this->aggregator = CACHE::TYPE_AUTO;
    
    
    if (isset($this->req->props['show_marks']))
	$this->show_marks = $this->FindMarksMode($this->req->props['show_marks']);
    else {
	if ($GRAPH_ACCURACY_MARKS_IF_GAPS)
	    $this->show_marks = DRAW::MARKS_GAPS;
	else 
	    $this->show_marks = DRAW::MARKS_DEFAULT;
    }

    if (isset($this->req->props['show_gaps']))
	$this->show_gaps = $this->FindGapsMode($this->req->props['show_gaps']);
    else {
	if ($GRAPH_INDICATE_DATA_DENSITY) {
	    eval("\$this->show_gaps = DRAW::$GRAPH_INDICATE_DATA_DENSITY;");
	} else {
	    $this->show_gaps = $GRAPH_INDICATE_DATA_DENSITY;
	}
    }
    if (isset($this->req->props['interpolate']))
	$this->interpolate_gaps = $this->req->props['interpolate'];
    else
	$this->interpolate_gaps = $opts->Get('graph_interpolate', $GRAPH_INTERPOLATE_DATA_GAPS);
	
    $this->allowed_gap = $opts->Get('maximal_allowed_gap', false);
    
    $this->hide_axes = $this->req->GetProp('hide_axes', false);
 }

 static function FindMarksMode($mode) {
    $name = "DRAW::MARKS_" . strtoupper($mode);
    if (defined($name)) return constant($name);
    else if ((!$mode)||(($mode>0)&&($mode<=DRAW::MARKS_ALWAYS))) return $mode;
    
    throw new ADEIException(translate("Unknown marks mode (%s) is specified", $mode));
 }
 
 static function FindGapsMode($mode) {
    $name = "DRAW::SHOW_" . strtoupper($mode);
    if (defined($name)) return constant($name);
    else if ((!$mode)||(($mode>0)&&($mode<=DRAW::SHOW_GAPS))) return $mode;
    
    throw new ADEIException(translate("Unknown gaps mode (%s) is specified", $mode));
 }
 
 static function FindPrecisionMode($mode) {
    $name = "DRAW::PRECISION_" . strtoupper($mode);
    if (defined($name)) return constant($name);
    else if ((!$mode)||(($mode<0)&&($mode>=DRAW::PRECISION_HIGH))||($mode>0)) return $mode;

    throw new ADEIException(translate("Unsupported precision (%s) is specified", $mode));
 }

 static function FindPlotMode($mode) {
    $name = "DRAW::PLOT_" . strtoupper($mode);
    if (defined($name)) return constant($name);
    else if ((!$mode)||(($mode<0)&&($mode>=DRAW::PLOT_CUSTOM))||($mode>0)) return $mode;

    throw new ADEIException(translate("Unsupported plotting mode (%s) is specified", $mode));
 }
 
 function GetTmpFile() {
    global $GRAPH_LOWPRECISION_UPDATE_RATE;
    global $ADEI_SESSION;
    global $TMP_PATH;


    $props = $this->req->props;
    unset($props['window']);
    unset($props['format']);
    unset($props['mask_mode']);
    unset($props['resample']);
    unset($props['filename']);
    unset($props['pageid']);


    if (isset($this->req->props['precision']))
	$precision_mode = $this->FindPrecisionMode($this->req->props['precision']);
    else
	$precision_mode = 0;

    if ($precision_mode == DRAW::PRECISION_LOW) {
	$dir = "clients/ffffffffffffffffffffffffffffffff/draw." . $this->req->props['db_server'] . "__" . $this->req->props['db_name'] . "__" . $this->req->props['db_group'] . "/" . md5(serialize($props)) . "/";
	$file = ($GRAPH_LOWPRECISION_UPDATE_RATE*round(time()/$GRAPH_LOWPRECISION_UPDATE_RATE))  . "_" . ($this->req->props['window']?$this->req->props['window']:0);
    } else {
	$dir = "clients/" . $ADEI_SESSION . "/draw." . $this->req->props['db_server'] . "__" . $this->req->props['db_name'] . "__" . $this->req->props['db_group'] . "/" . md5(serialize($props)) . "/";
	$file = time() . "_" . ($this->req->props['window']?$this->req->props['window']:0);
    }
    
    if (!is_dir($TMP_PATH . "/" .  $dir)) {
	if (!@mkdir($TMP_PATH . "/" . $dir, 0755, true)) 
	    throw new ADEIException(translate("DRAW class have not access to the temporary directory"));
    }

/*  
    We need to restore information for GetScaleInfo before we could set an ready
    flag.
    if (file_exists($TMP_PATH . "/" . $dir . $file . ".png")) $this->ready = true;
    else */ $this->ready = false;

    return $dir . $file . ".png";
 }
 
 function PrepareInterval(READER $reader, LOGGROUP $grp, CACHESet $caches, INTERVAL $iv = NULL, array $legend_range_limit = NULL ) {
    if ($iv) return $iv;

    try {
	$interval = $caches->CreateInterval($this->req, $this->use_cache_timewindow);
	$interval->Limit($this->time_limit[0], $this->time_limit[1]);
	if ($legend_range_limit) $interval->Limit($legend_range_limit[0], $legend_range_limit[1]);
    } catch (ADEIException $ae) {
	throw $ae->Clarify(false, ADEIException::INVALID_REQUEST);
    }

    if (($empty = $interval->IsEmpty())||($interval->GetWindowSize()==0)) {
	$grinfo = $reader->GetGroupInfo($grp, REQUEST::LIST_COMPLEX|REQUEST::LIST_VIRTUAL);
	$grname = $grinfo['name'];
	$dbname = $reader->req->GetSourceTitle();
	
	if ($empty == INTERVAL::EMPTY_SOURCE) {
	    throw new ADEIException(translate("CACHESet (%s -- %s) contains no data at all", $dbname, $grname), ADEIException::NO_DATA);
	} else {
//	    $iv = $this->req->CreateInterval();
	    if ($empty == INTERVAL::EMPTY_WINDOW) {
		throw new ADEIException(translate("CACHESet (%s -- %s) have no data available in the specified time slice", $dbname, $grname), ADEIException::NO_DATA);	
	    } else if (!$interval->GetRequestWindowSize()) {
		throw new ADEIException("Empty window is requested", ADEIException::INVALID_REQUEST);
	    } else {
		$from = date('c',floor($interval->GetRequestWindowStart())); 
		$to = date('c',ceil($interval->GetRequestWindowEnd()));

		throw new ADEIException(translate("CACHESet (%s -- %s) have no data available between %s and %s ", $dbname, $grname, $from, $to), ADEIException::NO_DATA);
	    }
	}
    }

    return $interval;
 }

 function GetAgregatingProperties(CACHESet $caches, INTERVAL $iv) {
    global $GRAPH_MAX_POINTS_PER_GRAPH;
    global $GRAPH_MAX_APPROXIMATION_INTERVAL;
    global $GRAPH_AUTOAGGREGATION_MINMAX_THRESHOLD;
    
    $size = $caches->GetWidth();
    $points = $this->width * $size;

    switch ($this->precision_mode) {
	case 0:
        case DRAW::PRECISION_LOW:
	    if ($points > $GRAPH_MAX_POINTS_PER_GRAPH) {
		$limit = floor($this->width * $GRAPH_MAX_POINTS_PER_GRAPH / $points);

		$intivl = $this->width / $limit;
		if ($intivl > $GRAPH_MAX_APPROXIMATION_INTERVAL) {
		    $limit = ceil($this->width / $GRAPH_MAX_APPROXIMATION_INTERVAL);
		}
	    } else $limit = $this->width;
	break;
	case DRAW::PRECISION_HIGH:
    	    $limit = $this->width;
	break;
	default:
    	    $limit = round($this->width / $this->precision_mode);
    }
    
    $precision = ceil(2 * $this->width / $limit);

//    $res = $this->cache->resolution->Get($iv, $limit /* amount is here */);
    
    if ($this->aggregator) $aggregator = $this->aggregator;
    else {
	if ($precision < $GRAPH_AUTOAGGREGATION_MINMAX_THRESHOLD) $aggregator = CACHE::TYPE_MINMAX;
//	elseif (!$this->cache->resolution->GetWindowSize($res)) $aggregator = CACHE::TYPE_MINMAX;
	else  $aggregator = CACHE::TYPE_MEAN;
    }
    
    if ($aggregator != CACHE::TYPE_MINMAX) {
	$precision = ceil($this->width / $limit);
    }

    return array(
	"type" => $aggregator,
	"limit" => $limit,
	"amount" => $limit,
	"precision" => $precision
    );
 }

  
 function PrepareGroupData(CACHEWrapper $cachewrap, INTERVAL $iv, GRAPHAxes $axes, array &$cfg, $cache_flags = 0, $need_items = false) {
    global $GRAPH_MAX_AXES;
    
    //print_r($cachewrap->cache->req->props);
    if ($need_items) {
	if ($need_items === true) $item_flags = REQUEST::NEED_AXISINFO;
	else $item_flags = REQUEST::NEED_AXISINFO|$need_items;
    } else $item_flags = REQUEST::NEED_AXISINFO|REQUEST::ONLY_AXISINFO;
    
    $itemlist = $cachewrap->GetItemList($item_flags);

    $points = $cachewrap->GetPoints($iv, $cfg, $cache_flags);
    if ($this->allowed_gap !== false) $points->SetOption("allowed_gap", $this->allowed_gap);

    $size = $cachewrap->GetWidth();
    
    $time = array();
    $values = array();
    $axislist = array();
    $min = array(); $max = array();
    $at_min = array(); $at_max = array();
    $log_scale = array();


    $mvalue = array();

    $amount = 0;
    $missing = true; 		// last point was missing
    $amount_on_missing = false;
    $time_on_addon = false;

	// Disable Axes is above limit to save space
    for ($i=0;$i<$size;$i++) {
	$axislist[$i] = is_array($itemlist[$i])?$itemlist[$i]['axis']:false;
    }

    if (($GRAPH_MAX_AXES)&&(sizeof(array_unique($axislist)) > $GRAPH_MAX_AXES)) {
	for ($i=0;$i<$size;$i++) {
	    $axislist[$i] = false;
	}
    }
    
    for ($i=0;$i<$size;$i++) {
	$values[$i] = array();
	
	$empty[$i] = true;
//	$axislist[$i] = is_array($itemlist[$i])?$itemlist[$i]['axis']:false;
	$axis = $axes->GetAxis($axislist[$i]);
	$range = $axis->GetRange();
	$log_scale[$i] = $axis->IsLogarithmic();

	
	if (($range[0])||($range[1])) {
	    $min[$i] = $range[0];
	    $max[$i] = $range[1];

	    if (($log_scale[$i])&&($min[$i] <= 0)) {
	        $min[$i] = max(DRAW::MIN_LOG, $min[$i]);
	        $max[$i] = max(DRAW::MIN_LOG, $max[$i]);
	        $axis->SetRange($min[$i], $max[$i]);
	    }

	    $at_max[$i] = false;
	    $at_min[$i] = false;
	} else {
	    $min[$i] = 0;
	    $max[$i] = 0;
	}
    }

    foreach($points as $t => $v) {
	if ($v) {
	    if ($this->show_gaps) {
		$gapinfo = $points->missing_data();
		if ($gapinfo) array_push($mvalue, 1);
		else array_push($mvalue, 0);
	    }

	    $amount++;

	    if ($missing) {
		$amount_on_missing = $amount;
		$missing = false;
	    }

	    array_push($time, $t);
	    
	    foreach ($v as $key => $value) {
	        if ($log_scale[$key]) {
	            if ($value < 0) $value = NULL;
	        }

		if (($min[$key])||($max[$key])) {
		    if ($at_max[$key]) {
			if ($value > $max[$key]) {
			    $value = NULL;
			} else {
			    if (end($values[$key]) === NULL) {
				$values[$key][sizeof($values[$key]) - 1] = $max[$key];
			    }
			    
			    $at_max[$key] = false;
			    if ($value < $min[$key]) {
				$value = $min[$key];
				$at_min[$key] = true;
			    }
			}
		    } elseif ($at_min[$key]) {
			if ($value < $min[$key]) {
			    $value = NULL;
			} else {
			    if (end($values[$key]) === NULL) {
				$values[$key][sizeof($values[$key]) - 1] = $min[$key];
			    }

			    $at_min[$key] = false;
			    if ($value > $max[$key]) {
				$value = $max[$key];
				$at_max[$key] = true;
			    }
			}
		    } else {
			if ($value > $max[$key]) {
			    $value = $max[$key];
			    $at_max[$key] = true;
			} elseif ($value < $min[$key]) {
			    $value = $min[$key];
			    $at_min[$key] = true;
			}
		    }
		} 

		if ($value !== NULL) $empty[$key] = false;
		array_push($values[$key], $value);
	    }
	} else {
	    if (!$missing) {
		if ($amount === $amount_on_missing) {
		    if ($this->show_gaps) {
			    /* Yes, this point is set only to prolong last single (standalone) point to interval,
			    so it would be visualized by jpgraph. But in reality it is not a point */
			array_push($mvalue, 2);
		    }

		    $pos = sizeof($values[0]) - 1;
		    array_push($time, $time[$pos]);
		    for ($i=0;$i<$size;$i++) {
			array_push($values[$i], $values[$i][$pos]);
		    }
			
		    $amount_on_missing = false;
		}
		
		if ($this->show_gaps) {
		    array_push($mvalue, 2);
		}

		array_push($time, $t);
		for ($i=0;$i<$size;$i++) {
		    array_push($values[$i], false);
		}


		$missing = true;
	    }
	}
    }
    
    if ((!$missing)&&($amount === $amount_on_missing)) {
    	if ($this->show_gaps) {
	    array_push($mvalue, 2);
	}

	$pos = sizeof($values[0]) - 1;
	array_push($time, $time[$pos]);
	for ($i=0;$i<$size;$i++) {
	    array_push($values[$i], $values[$i][$pos]);
	}
    }

   for ($i=0;$i<$size;$i++) {
   	if ($empty[$i]) $values[$i] = NULL;
   }
    
    

    if ($amount != sizeof($time)) {
	$have_gaps = true;
    } else if ($points) {
	$have_gaps = $points->missing_points();
    }

    if ($points) {
        $operation_info = $points->GetOperationInfo();
    }

    return array(
	'time' => &$time,		// 1D array with timestamps
	'values' => &$values,		// 2D array with channel values
	'axis' => &$axislist,		// 1D array requiered axis id
	'amount' => $amount,		// Number of records returned
	'have_gaps' => $have_gaps,	// flag indicating precense of missing values
	'resolution' => $operation_info["resolution"],	// resolution used
	'aggregation' => $operation_info["aggregation"],// aggregation mode
	'items' => &$itemlist,
	'gaps' => &$mvalue,
	'name' => $need_items?$cachewrap->GetGroupTitle():false
    );
 }


 function CheckGroupData(&$info) {
    if (!sizeof($info['time'])) return false;
    
    foreach ($info['values'] as $i=> &$channel) {
	$empty_data = true;
	if ($channel) {
	    foreach ($channel as $val) {
	        if ($val !== NULL) {
		    $empty_data = false;
		    break;
	        }
	    }
	}
	if ($empty_data) {
	    unset($info['values'][$i]);
	    unset($info['axis'][$i]);
	    unset($info['items'][$i]);
	}
    }
    
    return (sizeof($info['values']) > 0);
 }


 function PrepareData(READER $reader, MASK $mask = NULL, INTERVAL &$iv = NULL, $need_items = false) {
    $axes = $this->reader->CreateAxes();

    $group = $reader->CreateGroup();
    $caches = $reader->CreateCacheSet($group, $mask);


    $iv = $this->PrepareInterval($reader, $group, $caches, $iv);

    $cfg = $this->GetAgregatingProperties($caches, $iv);
    $flags = ($this->interpolate_gaps?0:CACHE::REPORT_EMPTY)|(($this->show_gaps==DRAW::SHOW_GAPS)?CACHE::MISSING_INFO:0);

    $data = array();

    foreach ($caches as $key => $cache) {
	$info = $this->PrepareGroupData($cache, $iv, $axes, $cfg, $flags, $need_items);

	if (!$this->CheckGroupData($info)) continue;

	$data[] = $info;
    }

    $spec = $this->CreateGraphSpec($this->reader, $axes, $iv, $data, $cfg);
    $this->CheckData($reader, $group, $iv, $caches, $data, $spec);

    
    return array($axes, &$data, &$spec);
 }
 
 function CreateGraphSpec(READER $reader, GRAPHAxes $axes, INTERVAL $iv = NULL, array &$data, array &$cfg) {
    $max_amount = 0;		// Maximal amount of data points among physical groups
    $min_amount = 1E+200;	// Minimal amount of data points among physical groups
    $have_gaps = false;		// If any of physical groups have data gaps
    $from = false;		// First data point
    $to = false;		// Last data point
    
    $size = 0;			// Number of channels to be displayed

    if (!$data) {
	return array(
	    'from' => 0,
	    'to' => 0,
	    'size' => 0,
	    'amount' => array(0, 0),
	    'precision' => 0,
	    'have_gaps' => 0,
	    'dbname' => $reader->dbname
	);
    }

    foreach ($data as $key => &$info) {
	$max_amount = max($max_amount, $info['amount']);
	$min_amount = min($min_amount, $info['amount']);

	if ($info['have_gaps']) $have_gaps = true;
	
        $from1 = $info['time'][0];
	$to1 = $info['time'][sizeof($info['time'])-1];

	if (($from===false)||($from1 < $from)) $from = $from1;
	if (($to===false)||($to1 > $to)) $to = $to1;
	
	$size += sizeof($info['values']);
    }


    if ($cfg['type'] == CACHE::TYPE_MINMAX) {
        $precision = ceil(2 * $this->width / $min_amount);
    } else {
	$precision = ceil($this->width / $min_amount);
    }

    return array(
	'from' => $from,
	'to' => $to,
	'size' => $size,
	'amount' => array($min_amount, $max_amount),
	'precision' => $precision,
	'have_gaps' => $have_gaps,
	'dbname' => $reader->dbname
    );
 }


 function CheckData(READER $reader, LOGGROUP $grp, INTERVAL $iv, CACHESet $caches, array &$data, array &$spec) {
    global $GRAPH_MAX_CACHE_GAP;
    
    if ((!$spec['amount'][1])||(!$data)) {
	$grinfo = $reader->GetGroupInfo($grp, REQUEST::LIST_COMPLEX|REQUEST::LIST_VIRTUAL);
	$grname = $grinfo['name'];
	$dbname = $reader->req->GetSourceTitle();

        if ($caches->IsEmpty()) {
	    throw new ADEIException(translate("CACHESet (%s -- %s) is empty: No channels are selected", $dbname, $grname), ADEIException::NO_DATA);
	} else {
	    $from = date('c',floor($iv->GetWindowStart())); 
	    $to = date('c',ceil($iv->GetWindowEnd()));

	    throw new ADEIException(translate("CACHESet (%s -- %s) have no data available between %s and %s ", $dbname, $grname, $from, $to), ADEIException::NO_DATA);
	}
    }

    if ($GRAPH_MAX_CACHE_GAP) {
	if ((($from - $iv->window_start) < $GRAPH_MAX_CACHE_GAP)||
	    (($to - $iv->window_start - $iv->window_size) < $GRAPH_MAX_CACHE_GAP)) {
	    $range1 = date("c", ceil($iv->window_start)) . " and " . date("c", floor($iv->window_start + $iv->window_size));
	    $range2 = date("c", ceil($from)) . " and " . date("c", floor($to));
	    throw new ADEIException(translate("The data is missing in CACHE: Needed: $range1, Available: $range2"));
	}
    }
 }

 function FindAxisRange(GRAPHAxis $axis, array &$data, array &$spec, $set = false) {
    list($min, $max) = $axis->GetRange();

    if ((!$min)&&(!$max)) {
	    $realmin = 1E+200;
	    $realmax = -1E+200;

	    foreach ($data as &$info) {
		foreach ($info['values'] as $key => &$channel) {
		    if ($aid) {
			if (strcmp($aid, $info['axis'][$key])) continue;
		    } else {
			if (isset($info['axis'][$key])) continue;
		    }
		    
		    $realmin1 = min($channel);
		    $realmax1 = max($channel);
		    if ($realmin1 < $realmin) $realmin = $realmin1;
		    if ($realmax1 > $realmax) $realmax = $realmax1;
		}
	    }


	    if ($realmin == $realmax) {
		if ($realmin > 0) {
	    	    $px = $realmax / $this->height;
		    $min = 0;
		    $max = 2*$realmax;
		} elseif ($realmin < 0) {
		    $px = (-$realmax) / $this->height;
		    $min = 2*$realmin;
		    $max  = 0;
		} else {
		    $px = 1 / $this->height;
		    $max = 1;
		    $min = -1;
		}
	    } else {
		$px = ($realmax - $realmin) / $this->height;
		$min = $realmin - 4 * $px;
		$max = $realmax + 4 * $px;
	    }

	    if ($set) {
	        $axis->SetRange($min, $max);
	    }
    }
    return array($min, $max);
 }
 
 function AnalyzeGapsRequirements(GRAPHAxes $axes, array &$data, array &$spec) {
    if ($this->show_gaps == DRAW::SHOW_GAPS) $show_gaps = DRAW::SHOW_GAPS;
    else if (($this->show_gaps == DRAW::SHOW_POINTS)||(($spec['have_gaps'])&&($this->show_gaps == DRAW::SHOW_EMPTY))) $show_gaps = DRAW::SHOW_POINTS;
    else $show_gaps = 0;

    $spec['gaps'] = $show_gaps;
    
    return $show_gaps;
 }

 function AnalyzeMarksRequirements(GRAPHAxes $axes, array &$data, array &$spec) {
    global $GRAPH_ACCURACY_MARKS_OUTSET;
    global $GRAPH_ACCURACY_MARKS_MULTIOUTSET;

    switch ($this->show_marks) {
	case DRAW::MARKS_ALWAYS:
	    $marks = true;
	break;
	case DRAW::MARKS_GAPS:
	    if ($spec['have_gaps']) {
		$marks = true;
		break;
	    }
	case DRAW::MARKS_DEFAULT:
	    if ($spec['size'] > 1) {
		if (($GRAPH_ACCURACY_MARKS_MULTIOUTSET)&&($spec['precision'] > $GRAPH_ACCURACY_MARKS_MULTIOUTSET)) {
		    $marks = true;
		    break;
		}
	    } else {
		if (($GRAPH_ACCURACY_MARKS_OUTSET)&&($spec['precision'] > $GRAPH_ACCURACY_MARKS_OUTSET)) {
		    $marks = true;
		    break;
		}
	    }
	case DRAW::MARKS_NEVER:
	    $marks = false;
    }
	
    $spec['marks'] = $marks;
    return $marks;
 }


 function ConfigureTimeAxis(INTERVAL $iv, array &$data, array &$spec) {
    global $GRAPH_SUBSECOND_THRESHOLD;

    $from = $spec['from'];
    $to = $spec['to'];
    
    if (($this->set_time_axes)||($spec['from'] == $spec['to'])) {
	$spec['from'] = $iv->GetRequestWindowStart();
	$spec['to'] = $iv->GetRequestWindowEnd();
    }

    $spec['length'] = dsMathPreciseSubstract($spec['to'], $spec['from']);

    $from = $spec['from'];
    $to = $spec['to'];
    $length = $spec['length'];

    if ($length > $GRAPH_SUBSECOND_THRESHOLD) {
	$this->scale_start = 0;
	$this->scale_coef = 1;
    }

    $afrom = getdate($from);
    $ato = getdate($to);


    $axis_info = array(
	'ticks' => false
    );
    
    if (($this->set_time_axes)&&($from != $to)) {
	$axis_info['from'] = $from;
	$axis_info['to'] = $to;
    } else {
	    // According to jpgraph.php SetScale default values
	$axis_info['from'] = 1;
	$axis_info['to'] = 1;
    }
    

    if ($length > 315360000) { // 10 years 
        $date_format = 'Y';
        $label_interval = 1;
	$date_title = "";
    } elseif ($length > 31104000) { // 1 year
	
	if ($afrom['year'] == $ato['year']) {
	    $date_format = 'M';
	    $label_interval = 2;
	    $date_title = $afrom['year'];
	} else {
	    $date_format = 'M, Y';
	    $label_interval = 2;
	    
	    $date_title = $afrom['year'] . " - " . $ato['year'];
	}
    } elseif ($length > 1036800) { // 12 days
	$date_format = 'M d';
	$label_interval = 3;
	
	if ($afrom['year'] == $ato['year']) {
	    if ($afrom['mon'] == $ato['mon']) {
		$date_title = $afrom['month'] . ", " . $afrom['year'];
	    } else {
		$date_title = $afrom['year'];
	    }
	} else {
	    $date_title = $afrom['year'] . " - " . $ato['year'];
	}
    } elseif ($length > 86400) { // 1 day
	$date_format = 'M d, H:i';
	$label_interval = 4;

	if ($afrom['year'] == $ato['year']) {
	    if ($afrom['mon'] == $ato['mon']) {
		$date_title = $afrom['month'] . ", " . $afrom['year'];
	    } else {
		$date_title = $afrom['year'];
	    }
	} else {
	    $date_title = $afrom['year'] . " - " . $ato['year'];
	}
    } elseif ($length > 14400) { // 4 hours
	$date_format = 'H:i';
	$label_interval = 2;

	if ($afrom['year'] == $ato['year']) {
	    if ($afrom['mon'] == $ato['mon']) {
		if ($afrom['mday'] == $ato['mday']) {
		    $date_title = $afrom['month'] . " " . $afrom['mday'] . ", " . $afrom['year'];
		} else {
		    $date_title = $afrom['month'] . " " . $afrom['mday'] . " - " . $ato['mday'] . ", " . $afrom['year'];
		}
	    } else {
		$date_title = date("M", $from) . " " . $afrom['mday'] . " - " . date("M", $to) . " " . $ato['mday'] . ", " . $afrom['year'];
	    }
	} else {
	    $date_title = date("M j, Y", $from) . " - " . date("M j, Y", $to);
	}
    } else if ($length > $GRAPH_SUBSECOND_THRESHOLD) {
	$date_format = 'H:i:s';
	$label_interval = 4;

	if ($afrom['year'] == $ato['year']) {
	    if ($afrom['mon'] == $ato['mon']) {
		if ($afrom['mday'] == $ato['mday']) {
		    $date_title = $afrom['month'] . " " . $afrom['mday'] . ", " . $afrom['year'];
		} else {
		    $date_title = $afrom['month'] . " " . $afrom['mday'] . " - " . $ato['mday'] . ", " . $afrom['year'];
		}
	    } else {
		$date_title = date("M", $from) . " " . $afrom['mday'] . " - " . date("M", $to) . " " . $ato['mday'] . ", " . $afrom['year'];
	    }
	} else {
	    $date_title = date("M j, Y", $from) . " - " . date("M j, Y", $to);
	}
    } else {
	$ifrom = floor($from);
	if (is_float($from)) $rfrom = substr(printf("%.9F", $from - $ifrom),2);
	else {
	    $pos = strpos($from, ".");
	    if ($pos === false) $rfrom = 0;
	    else $rfrom = substr($from, $pos + 1);
	}

	$date_title = date("M j, Y H:i:s", $ifrom);
	if ($rfrom) {
	    $date_title .= "." . $rfrom;
	    $rfrom = "0.$rfrom";
	}
	
	if ($length > $this->width/1000)  {
	    $coef = 1000;
	    $suffix = "ms";
	}
	elseif ($length > $this->width/1000000)  {
	    $coef = 1000000;
	    $suffix = "us";
	} else {
	    $coef = 1000000000;
	    $suffix = "ns";
	}

	$reallength = floor($length*$coef);
	$rem = ($reallength + 10) % 1000;
	if ($rem<20) $ilength = $reallength - ($rem - 10);
	else $ilength = $reallength;
	
	$date_title .= " + " . $ilength . $suffix;

	$first_num = substr($ilength, 0, 1);
	$second_num = substr($ilength, 1, 1);
	if ($second_num>4) {
	    if ((++$first_num)==10)
		$istep = "1" . str_repeat("0", strlen($ilength) - 1);
	    else 
		$istep = $first_num . str_repeat("0", strlen($ilength) - 2);
	} else $istep = $first_num . str_repeat("0", strlen($ilength) - 2);


	$axis_info['from'] = 0;
	$axis_info['to'] = $reallength; // + 1;
	$axis_info['ticks'] = array($istep, $istep/4);
	

	$ticks = floor(($ilength+1) / $istep);

	if ($this->width < 350) {
	    if ($this->width < 70) $lpt = $ticks;
	    else $lpt = ceil ($ticks / ($this->width / 70));
	} else $lpt = round($ticks / 5);
	
	$date_format = false;
	$label_interval = $lpt;
	
	// Replacing timings with offsets from interval begining (in ns/us/ms)
	foreach ($data as &$gdate) {
	  foreach ($gdate['time'] as &$t) {
	    strstr($t, ".");

	    $it = floor($t);
	    $pos = strpos($t, ".");
	    if ($pos === false) $rt = 0;
	    else $rt = "0." . substr($t, $pos + 1);
	    
	    $t = ($it - $ifrom)*$coef + floor(($rt - $rfrom)*$coef);
	  }
	}
	
	$this->scale_start = $from;
	$this->scale_coef = $coef;
    }


    $axis_info['date_title'] = $date_title;
    $axis_info['date_format'] = $date_format;
    $axis_info['label_interval'] = $label_interval;

    return $axis_info;
 }

 function ConfigureYAxis(GRAPHAxes $axes, INTERVAL $iv, array &$data, array &$spec) {
    return array(
	'min' => 1,
	'max' => 1,
    );
 }

 function ConfigureAxis(GRAPHAxes $axes, INTERVAL $iv, array &$data, array &$spec) {
    foreach ($data as $key => &$info) {
        foreach ($info['values'] as $i => &$channel) {
	    $axis = $axes->GetAxis($info['axis'][$i]);
	    $axis->RegisterChannel($info['time'], $channel, $info['items'][$i]);
	}
    }
	
    $axes->Normalize();
    $axes->Enumerate();

    $spec['xaxis'] = $this->ConfigureTimeAxis($iv, $data, $spec);
    $spec['yaxis'] = $this->ConfigureYAxis($axes, $iv, $data, $spec);
    
    return array(&$spec['xaxis'], &$spec['yaxis']);
 }

 function GenerateTitle(array &$data, array &$spec) {
    $min_resolution = 1E+200;	// Minimal resolution used
    $max_resolution = 0;	// Maximal resolution used
    $aggregation = array();	// Aggregation algorithms used

    foreach ($data as &$info) {
	$max_resolution = max($max_resolution, $info['resolution']);
	$min_resolution = min($min_resolution, $info['resolution']);

	array_push($aggregation, $info['aggregation']);
    }
    
    $title = $this->req->props['db_name'];
    if ($min_resolution == $max_resolution) {
	$title .= ", res: $min_resolution";
    } else {
	$title .= ", res: $min_resolution-$max_resolution";
    }
    
    if (sizeof(array_unique($aggregation)) > 1) {
	$title .= ", MIXED";
    } else {
	switch ($aggregation[0]) {
	    case CACHE::TYPE_MINMAX:
		$title .= ", MMAX";
	    break;
	    case CACHE::TYPE_MEAN:
		$title .= ", MEAN";
	    break;
	}
    }

    if ($spec['precision']) $title .= ", acc. " . $spec['precision'] . "px";
    if ($spec['xaxis']['date_title']) $title = "{$spec['xaxis']['date_title']} ($title)";
    return $title;
 } 
 
 function ValueFormat($value, $length = 0) {
    $avalue = abs($value);

	// Fixing buggy 0
    if ($avalue < 1E-5) {
	if (($avalue * 1E+5) < $length) $value = 0;
    }
    
    if (($avalue > 999)||(($value)&&($avalue<0.01))) $format="%.2e";
    else $format="%.3g";
    
    return sprintf($format, $value);
 }
 
 function Create(MASK $mask = NULL, INTERVAL $iv = NULL) {
    global $GRAPH_MARGINS;
    
    global $GRAPH_DENSITY_PLOT_VALID_SIZE;
    global $GRAPH_DENSITY_PLOT_VALID_COLOR;
    global $GRAPH_DENSITY_PLOT_INVALID_SIZE;
    global $GRAPH_DENSITY_PLOT_INVALID_COLOR;
    global $GRAPH_DENSITY_POINTS_TYPE;
    global $GRAPH_DENSITY_POINTS_SIZE;
    global $GRAPH_DENSITY_POINTS_COLOR;
    global $GRAPH_DENSITY_POINTS_OUTLINE;

    global $JPGRAPH_VERSION;
    
    if ($this->ready) return;

    list($axes, $data, $spec) = $this->PrepareData($this->reader, $mask, $iv);

    $draw_modes = array();

    foreach($data as $info)
    {
	$items = $info['items'];
	foreach($items as $item)
	{
	    array_push($draw_modes, $item['draw_mode']);
	}
    }

    $this->plot_mode = $this->FindPlotMode($this->req->props['plot_mode']);

    $this->AnalyzeGapsRequirements($axes, $data, $spec);
    $this->AnalyzeMarksRequirements($axes, $data, $spec);
    
    list($xaxis, $yaxis) = $this->ConfigureAxis($axes, $iv, $data, $spec);


    $this->spec = &$spec;


    $this->graph = new Graph($this->width, $this->height, "auto");
    $this->graph->SetTickDensity(TICKD_SPARSE, TICKD_SPARSE);

    if ($xaxis['date_format']) {
        $this->graph->SetScale("datlin", 0, 1, $xaxis['from'], $xaxis['to']);
	//$this->graph->xaxis->scale->Update($this->graph, $xaxis['from'], $xaxis['to']);
	$this->graph->xaxis->scale->SetDateFormat($xaxis['date_format']);
    } else {
        $this->graph->SetScale("linlin", 0, 1, $xaxis['from'], $xaxis['to']);
	if ($xaxis['ticks']) {
	    $this->graph->xscale->ticks->Set($xaxis['ticks'][0], $xaxis['ticks'][1]);
	}
    }
    if ($xaxis['label_interval']) {
	$this->graph->xaxis->SetTextLabelInterval($xaxis['label_interval']);
    }
    
    if ($this->hide_axes) {
	if (strcasecmp($this->hide_axes,'Y')) $hide_x = true;
	else $hide_x = false;
	$hide_y = true;
    } else {
	$hide_x = false;
	$hide_y = false;
    }

    $this->graph_margin = array();
    
    if ($hide_y) {
	$this->graph_yaxis_size = 0;
	if ($hide_x) {
	    $this->graph_margin[0] = 0;
	    $this->graph_margin[2] = 0;
	} else {
	    $this->graph_margin[0] = $GRAPH_MARGINS['right'] + $GRAPH_MARGINS['axis'];
	    $this->graph_margin[2] = $GRAPH_MARGINS['right'];
	}

	$this->graph->yaxis->HideLabels();
    } else {
	$this->graph_yaxis_size = $GRAPH_MARGINS['axis'];
	$this->graph_margin[0] = $GRAPH_MARGINS['left'] + $this->graph_yaxis_size * $axes->GetAxesNumber();
	$this->graph_margin[2] = $GRAPH_MARGINS['right'];
    }
    
    if ($hide_x) {
	$this->graph_margin[1] = 0;
	$this->graph_margin[3] = 1;
	$this->graph->xaxis->HideLabels();
    } else {
	$this->graph_margin[1] = $GRAPH_MARGINS['top'];
	$this->graph_margin[3] = $GRAPH_MARGINS['bottom'];

        $title = $this->GenerateTitle($data, $spec);
	$this->graph->title->Set($title);

        $this->graph->xaxis->SetPos("min");
	//$this->graph->xaxis->SetLabelAngle(0);
        $this->graph->xaxis->SetFont(FF_ARIAL, FS_NORMAL, 8);
	//$this->graph->xaxis->scale->SetTimeAlign(MINADJ_15);

	// We can use SetLabelFormatCallback for higher control
	//$this->graph->yaxis->SetLabelFormat('%0.5g');
	//$this->graph->SetYDeltaDist($this->graph_yaxis_size);
    }


    
    $this->graph->img->SetMargin($this->graph_margin[0], $this->graph_margin[2], $this->graph_margin[1], $this->graph_margin[3]);
    $this->graph->xaxis->SetLabelMargin(13);

    foreach ($axes as $axis_i => $axis) {

	$empty_axis = true;
	if(intval($this->plot_mode) === DRAW::PLOT_STANDARD)
	{
	    foreach ($axis as $i => $plot_data) {
		$plot = new LinePlot($plot_data[1], $plot_data[0]);
	    
		$color = $axis->GetChannelColor($i);
		if ($color) $plot->SetColor($color);

		$weight = $axis->GetChannelProperty($i, "weight");
		if ($weight) $plot->SetWeight($weight);
	    
		if ($spec['marks']) {
		    $prop = $axis->GetChannelProperty($i, "mark_type");
		    if ($prop) $plot->mark->SetType($prop);
		    $prop = $axis->GetChannelProperty($i, "mark_size");
		    if ($prop) $plot->mark->SetSize($prop);
		    $prop = $axis->GetChannelProperty($i, "mark_fill");
		    if ($prop) $plot->mark->SetFillColor($prop);
		}

		$this->graph->AddY($axis_i, $plot);
		$empty_axis = false;
	    }
	}
	else if(intval($this->plot_mode) === DRAW::PLOT_CUSTOM)
	{
	    foreach($data as $info)
	    {
		$acc_lineplot_array = array();
		$lineplot_array = array();
		$items = $info['items'];
		$time = $info['time'];
		$values = $info['values'];
	    
		foreach ($axis as $i => $plot_data) {
    		    $plot = new LinePlot($plot_data[1], $plot_data[0]);

		    $color = $axis->GetChannelColor($i);
		    if ($color) $plot->SetColor($color);

		    $weight = $axis->GetChannelProperty($i, "weight");
		    if ($weight) $plot->SetWeight($weight);
	    
		    if ($spec['marks']) {
			$prop = $axis->GetChannelProperty($i, "mark_type");
			if ($prop) $plot->mark->SetType($prop);
			$prop = $axis->GetChannelProperty($i, "mark_size");
			if ($prop) $plot->mark->SetSize($prop);
			$prop = $axis->GetChannelProperty($i, "mark_fill");
			if ($prop) $plot->mark->SetFillColor($prop);
		    }
		    if($items[$i]['draw_mode'] !== "LINE")
		    {
			$plot->SetFillColor($color);
			array_push($acc_lineplot_array, $plot);
		    }
		    else
		    {
			array_push($lineplot_array, $plot);
		    }

		    $empty_axis = false;
		}
	    
		if(!empty($acc_lineplot_array))
		{
		    $accplot = new AccLinePlot($acc_lineplot_array);
		
		    $this->graph->AddY($axis_i, $accplot);
		}
	        if(!empty($lineplot_array))
		{
		    foreach($lineplot_array as $plot)
		    {
			$this->graph->AddY($axis_i, $plot);
		    }
		}
	    }
	}
	
	if ($empty_axis) {
	    $plot = new LinePlot(array(0), array($spec['from']));
	    $this->graph->AddY($axis_i, $plot);
	}

	$range = $axis->GetRange();
	$scale = $axis->IsLogarithmic()?"log":"lin";
	if ($range) {
	    if ($axis->IsLogarithmic()) {
	        $this->graph->SetYScale($axis_i, $scale, (log10(max(DRAW::MIN_LOG, $range[0]))), (log10(max(DRAW::MIN_LOG, $range[1]))));
	    } else {
	        $this->graph->SetYScale($axis_i, $scale, $range[0], $range[1]);
	    }
	} else if ($empty_axis) $this->graph->SetYScale($axis_i, $scale, 0, 1);
	else $this->graph->SetYScale($axis_i, $scale);
    
	$color = $axis->GetColor();
	$this->graph->ynaxis[$axis_i]->SetColor($color);
	
	if ($hide_y) {
	    $this->graph->ynaxis[$axis_i]->HideLabels();
	} else {
	    $this->graph->ynaxis[$axis_i]->SetPos("min");
	    $this->graph->ynaxis[$axis_i]->SetTickSide(SIDE_RIGHT);
	    $this->graph->ynaxis[$axis_i]->SetLabelSide(SIDE_LEFT);
	    $this->graph->ynaxis[$axis_i]->SetPosAbsDelta(-$axis_i * $this->graph_yaxis_size - 2);

	    if (!$range) $this->graph->ynaxis[$axis_i]->scale->SetGrace(0.1,0.1);

    	    $title = $axis->GetTitle();
	    if ($title) {
		$this->graph->ynaxis[$axis_i]->SetTitle($title, "high");
		$this->graph->ynaxis[$axis_i]->SetTitleSide(SIDE_LEFT);
		if ($JPGRAPH_VERSION > 2) {
		    $this->graph->ynaxis[$axis_i]->SetTitleMargin(15 - $this->graph_yaxis_size);
		} else {
		    $this->graph->ynaxis[$axis_i]->SetTitleMargin($this->graph_yaxis_size-20);
		}
		$this->graph->ynaxis[$axis_i]->title->SetColor($color);
		$this->graph->ynaxis[$axis_i]->HideLastTickLabel();
	    }
	}

	// We can use SetLabelFormatCallback for higher control
	//$this->graph->ynaxis[$axis_i]->SetLabelFormat('%0.5g');
	$this->graph->ynaxis[$axis_i]->SetLabelFormatCallback(array(new DRAW_JPGraphAxisHolder($this, $axis, $this->graph->ynaxis[$axis_i]),  "YLabelFormat"));
    }

/*
    $plot = new LinePlot(array(0), array($spec['from']));
    $this->graph->AddY(1, $plot);
    $this->graph->SetYScale(1, 'lin', $range[0], $range[1]);
        $this->graph->ynaxis[1]->SetPos("min");
        $this->graph->ynaxis[1]->SetTitleSide(SIDE_LEFT);
        $this->graph->ynaxis[1]->SetTickSide(SIDE_RIGHT);
        $this->graph->ynaxis[1]->SetLabelSide(SIDE_LEFT);
	$this->graph->ynaxis[1]->SetPosAbsDelta(-1 * 60 - 2);
*/
    
    $something_shown = false;
    if ($spec['gaps']) {
	//$px = ($max - $min) / $this->height; $realmax = $max + 2*$px;
	$realmax = 1 + 3/$this->height;
    
	switch($spec['gaps']) {
	 case DRAW::SHOW_GAPS:
	    $gtime = array();
	    $gvalue = array();
	
	    foreach ($data as &$info) {
		$flag = 0;
		foreach ($info['gaps'] as $idx => &$val) {
		    if ($val) {
			if ($flag) $flag++;
			else {
			    array_push($gtime, $info['time'][$idx]);
			    array_push($gvalue, $realmax);
			    $flag = 1;
			}
		    } else {
			if ($flag) {
			    array_push($gtime, $info['time'][$idx]);
			    array_push($gvalue, $realmax);
		    
			    array_push($gtime, $info['time'][$idx]);
			    array_push($gvalue, false);
		    
			    $flag = 0;
			}
		    }
		}
		if (sizeof($gtime) > 0) {
		    $something_shown = true;

		    $plot = new LinePlot($gvalue, $gtime);
		    $plot->SetColor($GRAPH_DENSITY_PLOT_INVALID_COLOR);
		    $plot->SetWeight($GRAPH_DENSITY_PLOT_INVALID_SIZE);
		    $this->graph->Add($plot);
		}
	    }
	 break;
	 case DRAW::SHOW_POINTS:
	    $something_shown = true;

	    foreach ($data as &$info) {
		foreach ($info['gaps'] as $idx => &$val) {
		    if ($val>1) $val = false;
		    else $val = $realmax;
		}
		
//		print_r($info['gaps']);
		$plot = new LinePlot($info['gaps'], $info['time']);
		$plot->SetColor($GRAPH_DENSITY_PLOT_VALID_COLOR);
		$plot->SetWeight($GRAPH_DENSITY_PLOT_VALID_SIZE);

		if ($GRAPH_DENSITY_POINTS_TYPE) {
		    eval("\$mtype=$GRAPH_DENSITY_POINTS_TYPE;");
		} 


		$plot->mark->SetType($mtype);
		$plot->mark->SetColor($GRAPH_DENSITY_POINTS_OUTLINE);
		$plot->mark->SetFillColor($GRAPH_DENSITY_POINTS_COLOR);
		$plot->mark->SetSize($GRAPH_DENSITY_POINTS_SIZE);
		$this->graph->Add($plot);
	    }
	}
    }

    if (!$something_shown) {
	$plot = new LinePlot(array(0), array($spec['from']));
	$this->graph->Add($plot);
    }
    
    $this->graph->yaxis->Hide();

    
    $this->graph_interval = $iv;
    $this->graph_axes = $axes;
    $this->precision = $spec['precision'];
 }

 function GetScaleInfo() {
    global $GRAPH_MARGINS;

    //$num = $this->graph_axes->GetAxesNumber();

    $aids = array();
    $ymin = array();
    $ymax = array();
    $ylog = array();
    $yzoom = array();
    $colors = array();
    $names = array();
    foreach ($this->graph_axes as $i => $axis) {
	array_push($aids, $axis->aid);
	
	if ($axis->GetRange()) array_push($yzoom, 1);
	else array_push($yzoom, 0);
	
	array_push($colors, $axis->GetColor());
	array_push($names, $axis->GetName());

        array_push($ymin, $this->graph->ynaxis[$i]->scale->GetMinVal());
        array_push($ymax, $this->graph->ynaxis[$i]->scale->GetMaxVal());
        array_push($ylog, $axis->IsLogarithmic()?1:0);
    }

    return array(
//	"a" => date("c", $this->graph->xaxis->scale->scale[1]),
	"xmin" => dsMathPreciseAdd($this->graph->xaxis->scale->scale[0] / $this->scale_coef, $this->scale_start),
	"xmax" => dsMathPreciseAdd($this->graph->xaxis->scale->scale[1] / $this->scale_coef, $this->scale_start),
	"imin" => $this->graph_interval->GetIntervalStart(),
	"imax" => $this->graph_interval->GetIntervalEnd(),
	"yzoom" => $yzoom,
	"ymin" => $ymin,
	"ymax" => $ymax,
	"ylog" => $ylog,
	"axis" => $aids,
	"color" => $colors,
	"name" => $names,
	"precision" => $this->precision,
	"margins" => $this->graph_margin,
	"axis_size" => $this->graph_yaxis_size
    );
 }

 function CreateLegendSpec($x, $y) {
    if ($x === false) {
	if (isset($this->req->props['x'])) $x = $this->req->props['x'];
	else throw new ADEIException(translate("The X coordinate is not specified"));

    }
    if ($y === false) {
	if (isset($this->req->props['y'])) {
	    if (is_array($this->req->props['y'])) $y = $this->req->props['y'];
	    else $y = explode(",", $this->req->props['y']);
	}
	else throw new ADEIException(translate("The Y coordinate is not specified"));
    }
    
    
    if (isset($this->req->props['xmin'])) $xmin = $this->req->props['xmin'];
    else throw new ADEIException(translate("The XMin is not specified"));
    if (isset($this->req->props['xmax'])) $xmax = $this->req->props['xmax'];
    else throw new ADEIException(translate("The XMax is not specified"));
    if (isset($this->req->props['ymin'])) {
        if (is_array($this->req->props['ymin'])) $ymin = $this->req->props['ymin'];
        else $ymin = explode(",", $this->req->props['ymin']);
    } else throw new ADEIException(translate("The YMin is not specified"));
    if (isset($this->req->props['ymax'])) {
        if (is_array($this->req->props['ymax'])) $ymax = $this->req->props['ymax'];
        else $ymax = explode(",", $this->req->props['ymax']);
    } else throw new ADEIException(translate("The YMax is not specified"));

    return array(
	'x' => $x,
	'y' => $y,
	'xmin' => $xmin,
	'xmax' => $xmax,
	'ymin' => &$ymin,
	'ymax' => &$ymax
    );
 }

 function GetLegendSpecXInterval(array &$spec) {
    return array($spec['xmin'], $spec['xmax']);
 }

 function GetLegendSpecYInterval(array &$spec, $axispos) {
    global $GRAPH_DELTA_SIZE;

		    /* Do not complaining until support in JS */
    if (isset($spec['ymin'][$axispos])) $ymin = $spec['ymin'][$axispos];
    else $ymin = $spec['ymin'][0];
    if (isset($spec['ymax'][$axispos])) $ymax = $spec['ymax'][$axispos];
    else $ymax = $spec['ymax'][0];
    if (isset($spec['y'][$axispos])) $y = $spec['y'][$axispos];
    else $y = $spec['y'][0];
    
		
    $ywin = (($ymax - $ymin)  * $GRAPH_DELTA_SIZE) / $this->height;
    $min = $y - $ywin / 2;
    $max = $y + $ywin / 2;
    
    return array($min, $max);
 }
 
 function ComputeDeltaSize(array &$cfg) {
    if ($cfg["limit"] > 0) {
	/*
	    "4" is due to the fact what maximal approximation error is two
	    intervals. See description in cache.php.
	    FIXME: limit could be decreased due to the border points
	*/

	if ($cfg['type'] == CACHE::TYPE_MINMAX) {
	    $delta_size = 4 * $this->width / $cfg["limit"]; 
	} else {
	    $delta_size = 2 * $this->width / $cfg["limit"]; 
	}

	if ($delta_size < $GRAPH_DELTA_SIZE) {
	    $delta_size = $GRAPH_DELTA_SIZE;
	}
    } else {
    	$delta_size = $GRAPH_DELTA_SIZE;
    }

    return $delta_size;
 }

 function GetLegendInterval(array &$spec, array &$cfg) {
    $spec['delta_size'] = $this->ComputeDeltaSize($cfg);

    $iv = new INTERVAL();
    $iv->SetupInterval($spec['xmin'], $spec['xmax']);
    $iv->SetupWindow(INTERVAL::WINDOW_NEIGHBOURHOOD, $spec['x'],  (dsMathPreciseSubstract($spec['xmax'], $spec['xmin']) * $spec['delta_size']) / $this->width);
    $iv->EnableFlexibility();
    
    return $iv;
 }
 
 function LegendSlow(array &$spec,  INTERVAL $iv, MASK $mask = NULL, INTERVAL $ivl = NULL) {
    $x = $spec['x']; $y = $spec['y'];
    $xmin = $spec['xmin']; $xmax = $spec['xmax'];
        
    list($axes, $data, $data_spec) = $this->PrepareData($this->reader, $mask, $ivl, true);
    $axes->Enumerate();

    $from = $iv->GetWindowStart();
    $to = $iv->GetWindowEnd();

    $result = array();
    $itempositions = array_fill(0, $axes->GetAxesNumber(), 0);	// counters by axis
    $itempos = array();						// itemid in group => itemid in axis
    foreach ($data as $gid => &$info) {
	foreach ($info['time'] as $first => $tm) {
	    if ($tm > $from) break;
	}

	if ($tm > $from) {
	    for ($last = $first; ((isset($info['time'][$last]))&&($info['time'][$last]<$to)); $last++);
	    $size = $last - $first;
	} else $first = false;

	if (($first !== false)&&($size>0)) $point_mode = true;
	else {
	    $point_mode = false;
	    $approximation_mode = false;
	    
	    if ($first > 0) {
		$x1 = $info['time'][$first - 1];
		$x2 = $info['time'][$first];

		$check1 = true;
		if (!$this->interpolate_gaps) {
		    $distance = max(dsMathPreciseSubstract($x, $x1), dsMathPreciseSubstract($x2, $x));
		    if (($this->allowed_gap)&&($distance>$this->allowed_gap)) $check1 = false;
		    if (($info['resolution'])&&($distance > 2*$info['resolution'])) $check1 = false;
		}

		if (($check1)&&(dsMathPreciseCompare($x1, $xmin)>=0)&&(dsMathPreciseCompare($x2, $xmax)<=0)) {
		    $coef = dsMathPreciseSubstract($x, $x1) / dsMathPreciseSubstract($x2, $x1);
		    $approximation_mode = true;
		}
	    }
	}
	
	if ($info['aggregation'] == CACHE::TYPE_MINMAX) $value_type = "value";
	else $value_type = "mean";

	$group_result = array();
        foreach ($info['values'] as $cid => &$channel) {
	    $axis = $axes->GetAxis($info['axis'][$cid]);
    	    $axispos = $axis->GetPosition();
	    $itempos[$i] = $itempositions[$axispos]++;

	    list($min, $max) = $this->GetLegendSpecYInterval($spec, $axispos);
	    
#	    echo max($channel) . "\n";
	    if ($point_mode) {
		$slice = array_slice($channel, $first, $size);
		$slice = array_filter($slice, create_function('$val', '
		    if (is_numeric($val)) return true;
		    return false;
		'));
		
		if ($slice) {
		    $have_data = true;
		    $vmin = min($slice);
		    $vmax = max($slice);
		} else {
		    $have_data = false;
		}
		
		if (($have_data)&&((($vmax<$min)||($vmin>$max))==false)) {
		    array_push($group_result, array(
			"id" => $info['items'][$cid]['id'],
			"name" => $info['items'][$cid]['name'],
			"mode" => "interval",
			"interval" => array(
			    "from" => $iv->GetWindowStart(),
			    "to" => $iv->GetWindowEnd(),
			    "resolution" => $info['resolution']
			),
			$value_type => array(
			    "min" => $this->ValueFormat($vmin, abs($vmax)),
			    "max" => $this->ValueFormat($vmax, abs($vmin))
			),
			"color" => $axis->GetChannelColor($itempos[$i])
		    ));
		}
	    } else if ($approximation_mode) {	// approximation
		list($min, $max) = $this->GetLegendSpecYInterval($spec, $axispos);

		$y1 = $channel[$first - 1];
		$y2 = $channel[$first];
		$y = $y1 + $coef*($y2 - $y1); //DS.Precision
		
		if (($y>$min)&&($y<$max)) {
		    array_push($group_result, array(
				"id" => $info['items'][$cid]['id'],
				"name" => $info['items'][$cid]['name'],
				"mode" => "approximation",
				"approximation" => array(
				    "x1" => $x1,
				    "y1" => $this->ValueFormat($y1, $y2),
				    "x2" => $x2,
				    "y2" => $this->ValueFormat($y2, $y1)
				),
				"color" => $axis->GetChannelColor($itempos[$i])
		    ));
		}
	    }
	}

	if ($group_result) {
	    array_push($result, array(
		"title" => $info['name'],
		"results" => $group_result
	    ));
	}
    }

    return $result;
 }
 
 function Legend($x = false, $y = false, MASK $mask = NULL, INTERVAL $ivl = NULL) {
    global $GRAPH_DELTA_SIZE;
    global $GRAPH_FAST_LEGEND;
    
    $spec = $this->CreateLegendSpec($x, $y);
    list ($xmin, $xmax) = $this->GetLegendSpecXInterval($spec);
    $x = $spec['x']; $y = $spec['y'];
    
    $group = $this->reader->CreateGroup();
    $caches = $this->reader->CreateCacheSet($group, $mask);
    $ivl = $this->PrepareInterval($this->reader, $group, $caches, $ivl, array($xmim, $xmax));
    $cfg = $this->GetAgregatingProperties($caches, $ivl);
    $iv = $this->GetLegendInterval($spec, $cfg);

    if ((!$GRAPH_FAST_LEGEND)||($cfg['type'] == CACHE::TYPE_MEAN)) {
	return $this->LegendSlow($spec, $iv, $mask, $ivl);
    }
    

    $cache_flags = CACHE::TRUNCATE_INTERVALS;
    $itemlist_flags = REQUEST::NEED_AXISINFO;

    $axes = $this->reader->CreateAxes();

    $a = array_merge($cfg, array(
	'limit' => 1,
	'amount' => 0
    ));
    
    $result = array(); $notfound = true;
    $caches->EnumerateAxes($axes, CACHESet::CACHE_ITEMS|$itemlist_flags);
    $itempositions = array_fill(0, $axes->GetAxesNumber(), 0);	// counters by axis
    $itempos = array();						// itemid in group => itemid in axis
    foreach ($caches as $key => $cachewrap) {
	$cache = $cachewrap->GetCache();
	$size = $cachewrap->GetWidth();
	
	$list = $cachewrap->GetItemList($itemlist_flags);
	
	$a['resolution'] = $cache->resolution->Get($ivl, $cfg['amount']);
	$points = $cachewrap->GetIntervals($iv, $a, $cache_flags);
        $operation_info = $points->GetOperationInfo();

	$group_result = array();
	foreach($points as $t => $v) {
	    for ($i=0;$i<$size;$i++) {
		$axis = $axes->GetAxis($list[$i]['axis']);
		$axispos = $axis->GetPosition();
		$itempos[$i] = $itempositions[$axispos]++;
		
		list($min, $max) = $this->GetLegendSpecYInterval($spec, $axispos);
	    
		if ((($v["max" .$i]<$min)||($v["min" .$i]>$max))==false) {
		    array_push($group_result, array(
			"id" => $i,
			"name" => $list[$i]['name'],
			"mode" => "interval",
			"interval" => array(
			    "from" => $iv->GetWindowStart(),
			    "to" => $iv->GetWindowEnd(),
			    "resolution" => $operation_info['resolution']
			),
			"value" => array(
			    "min" => $this->ValueFormat($v["min$i"]),
			    "max" => $this->ValueFormat($v["max$i"]),
			    "mean" => $this->ValueFormat($v["mean$i"])
			),
			"color" => $axis->GetChannelColor($itempos[$i])
		    ));
		}
	    }

	    $notfound = false;
	    break;
	}
	
	unset($points);
    
	/* Trully saying this is suboptimal on the rapidly changing data, where
	MINMAX jumping up and down. GetNeighbors will get just latest point, 
	but MINMAX could use as an end point, just previous one */
	if ($notfound) {
		// Indexing items
	    for ($i=0;$i<$size;$i++) {
		$axis = $axes->GetAxis($list[$i]['axis']);
		$axispos = $axis->GetPosition();
		$itempos[$i] = $itempositions[$axispos]++;
	    }

	    $neighbors = $cachewrap->GetNeighbors($x);
	    if ((sizeof($neighbors['left']) > 0)&&(sizeof($neighbors['right']) > 0)) {
		$keys1 = array_keys($neighbors['left']); $x1=$keys1[0];
		$keys2 = array_keys($neighbors['right']);$x2=$keys2[0];

		$check1 = true;
		if (!$this->interpolate_gaps) {
		    /* We don't check expected gap, since otherwise this points
		    would be found before neighbors lookup */
/*
		    echo "$x $x1\n";
		    echo ($x - $x1) . "\n";
		    echo $this->allowed_gap . "\n";
*/
		    $distance = max(dsMathPreciseSubstract($x, $x1), dsMathPreciseSubstract($x2, $x));
		    if (($this->allowed_gap)&&($distance>$this->allowed_gap)) $check1 = false;
		    if (($operation_info['resolution'])&&($distance > 2*$operation_info['resolution'])) $check1 = false;
		}
		

		if (($check1)&&(dsMathPreciseCompare($x1, $xmin)>=0)&&(dsMathPreciseCompare($x2, $xmax)<=0)) {
		    $coef = dsMathPreciseSubstract($x, $x1) / dsMathPreciseSubstract($x2, $x1);

		    for ($i=0;$i<$size;$i++) {
			$axis = $axes->GetAxis($list[$i]['axis']);
			$axispos = $axis->GetPosition();
			
			list($min, $max) = $this->GetLegendSpecYInterval($spec, $axispos);

			$y1 = $neighbors['left'][$x1][$i];
			$y2 = $neighbors['right'][$x2][$i];
			
			$y = $y1 + $coef*($y2 - $y1); //DS.Precision
		
			if (($y>$min)&&($y<$max)) {
			    array_push($group_result, array(
				"id" => $i,
				"name" => $list[$i]['name'],
				"mode" => "approximation",
				"approximation" => array(
				    "x1" => $x1,
				    "y1" => $this->ValueFormat($y1),
				    "x2" => $x2,
				    "y2" => $this->ValueFormat($y2)
				),
				"color" => $axis->GetChannelColor($itempos[$i])
			    ));
			}
		    }
		}
	    }
	}
	
	
	if ($group_result) {
	    array_push($result, array(
		"title" => $cachewrap->GetGroupTitle(),
		"results" => $group_result
	    ));
	}
    }
    
//    print_r($legend);
    
    return $result;
 }
}

?>