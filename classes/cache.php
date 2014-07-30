<?php

require("cache/cachedb.php");
require("readers/cachereader.php");
require("cache/rawpoint.php");
require("cache/datainterval.php");
require("cache/datapoint.php");
require("cache/cachedata.php");

class CACHE extends CACHEDB {
 var $reader, $group;
 var $cache;

 var $items;				// Number of items in the group

 var $resolution;

 var $omit_raw_cache;
 var $fill_raw_first;
 var $optimize_empty_cache;
 var $ignore_invalid_data;
 
 var $all_mask;				// All MASK, used by FillCache(RAW) in 'fill_raw_first' mode
 
 var $current_raw_end; 			// exclusive, we have cached everything before specified second
 var $current_cache_resolution;		// current maximal resolution of the cache
 var $current_cache_end;		// current position at the max resolution
 
 var $trace_timings;			// report timings of cache operations (for performance profilling)
 var $data_filter;			// Filter for selecting out invalid records in the data source 
 
 var $lock;				// Cache Lock for process synchronization
 
 const DIM_AUTO = 0;
 const DIM_WINDOW = 1;
 const DIM_EXTENDEDWINDOW = 2;
 const DIM_INTERVAL = 3;

 const CREATE_UPDATER = 256;	/* Request for CACHE updates */

    // DATA Extracting (DATAInterval flags)
 const REPORT_EMPTY = 1;	/* Return empty (without any point inside) intervals */
 const TRUNCATE_INTERVALS = 2;	/* Allow truncating of non-raw cache intervals, raw ones are inequal anyway */
 const MISSING_INFO = 4;	/* Return information on data gaps in interval */
  
    // Caching flags
 const VERBOSE = 1;
  
 function __construct(GROUPRequest &$props, READER &$reader = NULL, $flags = 0) {
    global $DEFAULT_MISSING_VALUE;
    
    parent::__construct($props);

    $opts = $this->req->GetOptions();
    $this->md5_postfix = $opts->Get('use_md5_postfix');

    if ($reader) $this->reader = &$reader;
    else {
	if ($this->req->srv['disconnected']) {
	    if ($flags&CACHE::CREATE_UPDATER)
		throw new ADEIException(translate("It is not possible to create CACHE updater on disconnected data source"));
		
	    $this->reader = NULL;
	} else if ($this->cache_reader) {
	    $this->reader = NULL;	
	} else {
	    try {
		$this->reader = $props->CreateReader(REQUEST::READER_FORBID_CACHEREADER);
	    } catch (ADEIException $ae) {
		if ($flags&CACHE::CREATE_UPDATER) throw $ae;

		if ($opts->Get('overcome_reader_faults')) {
		    $this->reader = NULL;
		    $reader_exception = $ae;
		} else throw $ae;
	    }
	} 
	
	if (!$this->reader) {
	    try {
		$this->reader = $props->CreateCacheReader($this);
	    } catch (ADEIException $ae) {
    	        if ($reader_exception) {
		    $reader_exception->MergeRecoveryException($ae);
		    throw $reader_exception;
		}
		throw $ae;
	    }
	}
    }

    if ($this->reader) {    
	if ((!$this->reader->srvid)||(!$this->reader->dbname))
	    throw new ADEIException(translate("Invalid READER is supplied to the caching engine"));
    
	$grinfo = $props->GetGroupInfo();
	$this->group = $this->reader->CreateGroup($grinfo);


	$this->default_db_server = $this->reader->srvid;
	$this->default_db_name = $this->reader->dbname;
	$this->default_db_group = $this->group->gid;

	$this->SetDefaultPostfix();

/*
	We don't expect any changes here at the moment	
	$opts = $this->req->GetCustomOptions($this->default_db_server, $this->default_db_name, $this->default_db_group);
*/

	$mres = $opts->Get("min_resolution");
	if (($mres)&&(is_array($mres))) $mres = $mres[$this->group->gid];

	$cache_config = $opts->Get('cache_config');
	$this->resolution = new RESOLUTION($mres, $cache_config);
    } else {
	$this->group = false;
	$this->resolution = false;
    }

    $this->use_subseconds = !$opts->Get('ignore_subseconds');
    $this->omit_raw_cache = $opts->Get('omit_raw_cache');
    if ($this->omit_raw_cache) {
	throw new ADEIException(translate("Option 'omit_raw_cache' is not supported yet"));
	$this->fill_raw_first = false;
    } else $this->fill_raw_first = $opts->Get('fill_raw_first');
    $this->optimize_empty_cache = $opts->Get('optimize_empty_cache');
    $this->cache_reader = $opts->Get('use_cache_reader');
    $this->ignore_invalid_data = $opts->Get('ignore_invalid_data');


    $this->items = 0;


    if ($flags&CACHE::CREATE_UPDATER) {
	if ($opts->Get("disable_caching")) {
	    throw new ADEIException(translate("Caching is disabled"), ADEIException::DISABLED); 
	}
	
	$this->current_raw_end = false;
	$this->current_cache_end = false;
	$this->current_cache_resolution = false;

	if ($this->md5_postfix) $this->MD5Check();
	
	if ($this->fill_raw_first) $this->all_mask = false;
	
	$this->trace_timings = $opts->Get("trace_timings");

	$this->data_filter = $this->reader->CreateDataFilter($this->group);
    }
 }
 
 function SetDefaultPostfix($postfix = false) {
    parent::SetDefaultPostfix($postfix);

    $opts = $this->req->GetCustomOptions($this->default_db_server, $this->default_db_name, $this->default_db_group);

    $mres = $opts->Get("min_resolution");
    if (($mres)&&(is_array($mres))) $mres = $mres[$this->group->gid];

    $cache_config = $opts->Get('cache_config');
    $this->resolution = new RESOLUTION($mres, $cache_config);
    $this->use_subseconds = !$opts->Get('ignore_subseconds');

/*
    I have no good idea how it should be handled?
    $this->cache_reader = $opts->Get('use_cache_reader');
*/
 }
 
 function CreateInterval(array &$iinfo = NULL, $flags = 0) {
    if (!$this->reader)  return parent::CreateInterval($iinfo, $flags);
    
    if (!$iinfo) {
	if ($this->req instanceof DATARequest)
	    $iinfo = $this->req->GetIntervalInfo();
    }
    return $this->reader->CreateInterval($this->group, $iinfo, $flags);
 }

 function LimitInterval(INTERVAL $ivl) {
    $this->req->LimitInterval($ivl, $this->group);
 }

 function CreateMask(array &$minfo = NULL, $flags = 0) {
    if (!$this->reader)  return parent::CreateMask($minfo, $flags);

    if (!$minfo) {
	if ($this->req instanceof DATARequest)
	    $minfo = $this->req->GetMaskInfo($flags);
    }
    
    $mask = $this->reader->CreateMask($this->group, $minfo, $flags);
    if (!$mask->ids) $mask->ids = $this->GetCacheIDs();
    
    return $mask;
 }

 function CreateAxes($flags = 0) {
    if (!$this->reader) {
	throw new ADEIException(translate("Axes are not supported on disconnected caches"));
    }
    
    return $this->reader->CreateAxes($this->group, $flags);
 } 

 function FillCache($res, $start, $end, $optimize_empty, $subcall = false, $verbose = false) {
    if ($res === false) throw new ADEIException(translate("Invalid CACHE resolution"));
    
    $resolution = $this->resolution->GetWindowSize($res);
    $table = $this->GetTableName($resolution);

#    echo "FillCache: $resolution (start: $start)\n";

    if (!$this->items) {
	$this->items = $this->reader->GetGroupSize($this->group);
    }


    if ($subcall) $curdata = new DATA($this->items);

    if ($resolution) {
	$sections = ($end - $start) / $resolution;

	if (($this->trace_timings)&&($sections)) {
	    if (is_array($this->trace_timings)) {
		if (((!$this->trace_timings['limit_interval'])||(($end - $start) >= $this->trace_timings['limit_interval']))
		    &&((!$this->trace_timings['overall_only'])||(!$subcall))) {
			$tt_started = microtime(true);
			$tt_limit_time = $this->trace_timings['limit_processing_time'];
			$tt_limit_percentage = $this->trace_timings['limit_percentage'];
			$tt_exception = $this->trace_timings['raise_exception'];
		}
	    } else $tt_started = microtime(true);
	}

    
	if (($subcall)||($resolution != $this->current_cache_resolution)) {
	    try {
		$qres = $this->GetReady($table, $start, $end);
		if ($qres) {
		    $qres->rewind();
		    $cache_time = $qres->key();
		    if (is_numeric($cache_time)) $cache_ptr = $qres->current();
		}
	    } catch (ADEIException $ae) {
		unset($qres);
		$cache_time = false;
	    }
	} else $cache_time = false;

	if ($verbose) $procdate = date("d.m.Y", $start);
	for ($i=0, $substart = $start; $i<$sections; $i++, $substart += $resolution) {
	    if ((is_numeric($cache_time))&&($cache_time == $substart)) {
		if ($subcall) {
		    $curdata->PushRow($cache_ptr, $resolution);

		    $qres->next();
		    $cache_time = $qres->key();
		    if (is_numeric($cache_time)) $cache_ptr = $qres->current();
		} else {
		    $qres->next();
		    $cache_time = $qres->key();
		}

		$tt_started = false; // but ignoring if reusing only parts of subresolutions
	    } else {
		if (!$optimize_empty) {
		    if ($this->fill_raw_first) {
			$ivl = new INTERVAL();
			$ivl->SetupInterval($substart, $substart + $resolution);
			$ivl->SetupWindow();
			
			if (!$this->HavePoints($ivl)) {
			    if (!$empty_data) $empty_data = new DATA($this->items);
			    $query = $this->CreateQuery($table, $substart, $empty_data);
			    $this->Insert($resolution, $query);
			    continue;
		        }
		    } else {
			if (!$this->reader->HaveData($this->group, $substart, $substart + $resolution)) {
			    //echo "$substart (+$resolution)\n";
			    if (!$empty_data) $empty_data = new DATA($this->items);

			    $query = $this->CreateQuery($table, $substart, $empty_data);
			    $this->Insert($resolution, $query);
			    continue;
		    	}
		    }
		}
		
		$d = $this->FillCache($this->resolution->Smaller($res), $substart, $substart + $resolution, false, true);
		
		if ($subcall) $curdata->Push($d, $resolution);

		$query = $this->CreateQuery($table, $substart, $d);
		$this->Insert($resolution, $query);

//		if ($resolutin > 600) echo $resolution . "\n";
		
//		print_r($query);
//		echo "$resolution inserted\n";
	    }
		
		if ($verbose) {
		    $newdate = date("d.m.Y", $start);
		    if ($procdate != $newdate) {
			echo " " . $procdate;
			$procdate = $newdate;
		    }
		}

	}
	
	if ($subcall) $curdata->Finalize();
	
	if ($tt_started) {
	    $tt_started = microtime(true) - $tt_started;
	    $tt_percents = (100 * $tt_started / ($sections*$resolution));
	    
	    $report = true;
	    if (($tt_limit_time)&&($tt_started < $tt_limit_time)) $report = false;
	    if (($tt_limit_percentage)&&($tt_percents < $tt_limit_percentage)) $report = false;

	    if ($report) {
		$tt_src = "/" . $this->default_db_server . "/" . $this->default_db_name . "/" . $this->default_db_group . "/" . $resolution;
	    
		if ($curdata->n) {
		    $msg = translate("%s: %d seconds (%d records) is processed in %dms (%f%%)", $tt_src, $sections*$resolution, $curdata->n, 1000*$tt_started, $tt_percents);
		    if (($tt_exception)&&($tt_percents >= $tt_exception))
			throw new ADEIException($msg);
		    else
			log_message($msg);
		} else {
		    $msg = translate("%s: %d seconds is processed in %dms (%f%%)", $tt_src, $sections*$resolution, 1000*$tt_started, $tt_percents);
		    if (($tt_exception)&&($tt_percents >= $tt_exception))
			throw new ADEIException($msg);
		    else
			log_message($msg);
		}
	    }
	}
	
    } else if (($subcall)&&($this->fill_raw_first)) {
	$ivl = new INTERVAL();
	$ivl->SetupInterval($start, $end);
	$ivl->SetupWindow();
	$data = $this->GetAllPoints($this->all_mask, $ivl);
	
	$pretime = $start;
	foreach ($data as $time => $value) {
	    $curdata->PushValue($value, $time - $pretime);
	    $pretime = $time;
	}

	$curdata->Finalize($end - $pretime);
    } else {
//	printf ("Filling: %u %u\n", $start, $end);
	if ($this->trace_timings) {
	    if (is_array($this->trace_timings)) {
		if (((!$this->trace_timings['limit_interval'])||(($end - $start) >= $this->trace_timings['limit_interval']))
		    &&((!$this->trace_timings['overall_only'])||(!$subcall))) {
			$tt_started = microtime(true);
			$tt_limit_time = $this->trace_timings['limit_processing_time'];
			$tt_limit_percentage = $this->trace_timings['limit_percentage'];
			$tt_exception = $this->trace_timings['raise_exception'];
		    }
	    } else $tt_started = microtime(true);
	}

	$data = $this->reader->GetFilteredData($this->group, $start, $end, $this->data_filter);
	
	
//	echo "Starting!\n";

	$pretime  = $start;
	
	if ($subcall) {
	    foreach ($data as $time => $value) {
		if (sizeof($value) != $this->items) {
		    if (!sizeof($value)) continue;
		    throw new ADEIException(translate("Number of items in the group have changed from %u to %u (CACHE::Fill)", $this->items, sizeof($value)));
		}

		$curdata->PushValue($value, $time - $pretime);

		if ($time >= $this->current_raw_end) {
		    $query = $this->CreateQuery($table, $time, $value);
	    	    $this->Insert($resolution, $query);
		}
	    
		$pretime = $time;
	    }

	    $curdata->Finalize($end - $pretime);
	} else {
	    foreach ($data as $time => $value) {
		if (sizeof($value) != $this->items) {
		    if (!sizeof($value)) continue;
		    throw new ADEIException(translate("Number of items in the group have changed from %u to %u (CACHE::Fill)", $this->items, sizeof($value)));
		}
	
		    // Check for raw_end is not neccessary, for !subcall the 'Update' is did it for us
		$query = $this->CreateQuery($table, $time, $value);
	    	$this->Insert($resolution, $query);
	    
		$pretime = $time;
	    }
	}

	if ($tt_started) {
	    $tt_started = microtime(true) - $tt_started;
	    $tt_percents = (100 * $tt_started / ($end - $start));
	    
	    $report = true;
	    if (($tt_limit_time)&&($tt_started < $tt_limit_time)) $report = false;
	    if (($tt_limit_percentage)&&($tt_percents < $tt_limit_percentage)) $report = false;
	    
	    if ($report) {
		$tt_src = "/" . $this->default_db_server . "/" . $this->default_db_name . "/" . $this->default_db_group . "/0";
	    
		if ($curdata->n) {
		    $msg = translate("%s: %d seconds (%d records) is processed in %dms (%f%%)", $tt_src, $end - $start, $curdata->n, 1000*$tt_started, $tt_percents);
		    if (($tt_exception)&&($tt_percents >= $tt_exception))
			throw new ADEIException($msg);
		    else
			log_message($msg);
		} else {
		    $msg = translate("%s: %d seconds is processed in %dms (%f%%)", $tt_src, $end - $start, 1000*$tt_started, $tt_percents);
		    if (($tt_exception)&&($tt_percents >= $tt_exception))
			throw new ADEIException($msg);
		    else
			log_message($msg);
		}
	    }
	}

//	echo "Done";
    }
    
    return $curdata;
 }
 
 function PreLock($flags = 0) {
    if ($this->lock)
	throw new ADEIException(translate("Cache is already locked"));
	
    $lock = new LOCK("cache" . $this->default_postfix);
    if ($lock->Lock($flags|LOCK::EXCLUSIVE)) $this->lock = $lock;
    else if (flags&Lock::BLOCK) throw new ADEIException(translate("Unable to lock the cache"), ADEIException::BUSY);

    return $this->lock?true:false;
 }
 
 function UnLock() {
    if (!$this->lock)
	throw new ADEIException(translate("Cache is not locked"));
    
    $this->lock->UnLock();
    $this->lock = NULL;
 }
 
 function Update(INTERVAL &$ivl = NULL, $dim = DIM_AUTO, $flags = 0) {
    if (!$ivl) {
	if ($this->req instanceof DATARequest)
	    $iinfo = $this->req->GetIntervalInfo();
	else
	    $iinfo = NULL;

	$ivl = $this->reader->CreateInterval($this->group, $iinfo);
    }

    $res = $this->resolution->Get($ivl, 1);
    $resolution = $this->resolution->GetWindowSize($res);
/*
    $res = $this->resolution->Minimal($ivl);
    $resolution = $this->resolution->GetWindowSize($res);
*/

    if ($this->lock) $lock = NULL;
    else {
	$lock = new LOCK("cache" . $this->default_postfix);
        $lock->Lock(LOCK::BLOCK|LOCK::EXCLUSIVE);
    }


    if (!$this->omit_raw_cache) {
	if (!$this->current_raw_end) {
	    $info = $this->GetCacheInfo();
		/* 
		   Tricky. We can do '+1' that, since 
		    a) The interval end is determined by data available in the source database,
		    and therefore we have already in the source all the data before the interval end
		    b) we always perform complete seconds and incomplete second are not processed
		    
		    This means what if we have in RAW cache any value for given second, then we already
		    have all data for that second in the RAW cache.
		*/
	    if ($info) $this->current_raw_end = floor($info['last']) + 1;
	}
    }
    
    
    if (($resolution)&&((!$this->current_cache_end)||($this->current_cache_resolution != $resolution))) {
	$info = $this->GetTableInfo($resolution);
	if ($info) {
	    $this->current_cache_end = $info['last'] + $resolution;
	    $this->current_cache_resolution = $resolution;
	} else {
	    $this->current_cache_end = false;
	    $this->current_cache_resolution = false;
	}
    }


/*
    echo date('c', $this->current_raw_end) . "\n";
    echo date('c', $this->current_cache_end) . "\n";
    echo "here $resolution\n";
    exit;
*/
    
    switch ($dim) {
	case CACHE::DIM_AUTO:
	case CACHE::DIM_WINDOW:
	    $from = $ivl->GetWindowStart();
	    $to = $ivl->GetWindowEnd();
	break;
	case CACHE::DIM_EXTENDEDWINDOW:
	    $from = $ivl->window_start - $ivl->window_size;
	    if ($from < $ivl->interval_start) $from = $ivl->interval_start;
	    
	    $to = $ivl->window_start + 2 * $ivl->window_size;
	    if ($to > $ivl->interval_end) $to = $ivl->interval_end;
	break;
	case CACHE::DIM_INTERVAL:
	    $from = $ivl->interval_start;
	    $to = $ivl->interval_end;
	break;
	default:
	    if ($lock) {
		$lock->UnLock();    
	        unset($lock);
	    }

	    throw new ADEIException(translate("Invalid dimmension parameter is specified"));
    }
    
//    echo "$from - $to\n";

    if (/*($from < 315619200)*/((!$from)&&(!$to))||($to < $from)) {
	if ($lock) {
	    $lock->UnLock();    
	    unset($lock);
	}

	if ((!$from)&&(!$to)) {
	    return;
	    //throw new ADEIException(translate("Data source contains no data"));
	} else
	    throw new ADEIException(translate("Invalid data range (%d - %d) is detected by CACHE updating code", $from, $to));
    }
    
    if (($this->current_cache_end)&&($from < $this->current_cache_end)) {
	$from = $this->current_cache_end;
    }
    
    if ($resolution > 0) {
	$ifrom = ceil($from);
	$rem = $ifrom % $resolution;
	if ($rem) $curfrom = $ifrom + ($resolution - $rem);
	else $curfrom = $ifrom;
		
	$ito = floor($to);
	$rem = $ito % $resolution;
	if ($rem) $curto = $ito - ($rem);
	else $curto = $ito;
	
	
	if ($this->fill_raw_first) {
	    if ($this->current_raw_end > $from) $rawfrom = $this->current_raw_end;
	    else $rawfrom = $from;
	    
	    if ($rawfrom < $ito) {
	        $this->FillCache($this->resolution->RAW(), $rawfrom, $ito, $this->optimize_empty_cache);
		$this->current_raw_end = $ito;
	    }
	    
	    if (!$this->all_mask) $this->all_mask = $this->CreateMask($empty = array());
	}



/*
	echo $from . " - " . $to . "\n";
	echo $curfrom . " - " . $curto . "\n";
	echo date("c", $curfrom) . " - " . date("c", $curto) . "\n";
	echo "\n";
*/

	if ($from != $curfrom) {
	    $subres = $this->resolution->Minimal();
	    $subresolution = $this->resolution->GetWindowSize($subres);

	    if ($subresolution == $resolution) $subfrom = $curfrom;
	    else {
		$rem = $ifrom % $subresolution;
		if ($rem) $subfrom = $ifrom + ($subresolution - $rem);
		else $subfrom = $ifrom;
	    }
	    
	    if ($from != $subfrom) {
		if ($this->current_raw_end > $from) $rawfrom = $this->current_raw_end;
		else $rawfrom = $from;
		if ($rawfrom < $subfrom) {
//		    echo "$from - $subfrom (0)\n";
		    $this->FillCache($this->resolution->RAW(), $rawfrom, $subfrom, $this->optimize_empty_cache, false, ($flags&CACHE::VERBOSE)?1:0);
		}
	    }
	
	    while ($subresolution < $resolution) {
		$nextres = $this->resolution->Larger($subres);
	        $nextresolution = $this->resolution->GetWindowSize($nextres);
		
		if ($nextresolution == $resolution) $subnext = $curfrom;
		else {
		    $rem = $ifrom % $nextresolution;
		    if ($rem) $subnext = $ifrom + ($nextresolution - $rem);
		    else $subnext = $ifrom;
		}
		
		//echo "$subfrom - $subnext ($subresolution)\n";
		$this->FillCache($subres, $subfrom, $subnext, $this->optimize_empty_cache, false, ($flags&CACHE::VERBOSE)?1:0);
		
		$subres = $nextres;
		$subresolution = $nextresolution;
		$subfrom = $subnext;
	    }
	}

        $this->FillCache($res, $curfrom, $curto, $this->optimize_empty_cache, false, ($flags&CACHE::VERBOSE)?1:0);

	    // We are not filling last incomplete second
	if ($ito != $curto) {
	    $subres = $this->resolution->Smaller($res);
	    $subresolution = $this->resolution->GetWindowSize($subres);
	    
	    $subfrom = $curto;
	    
	    while ($subresolution > 0) {
		$rem = $ito % $subresolution;
		if ($rem) $subnext = $ito - $rem;
		else $subnext = $ito;
		
//		echo "$subfrom - $subnext\n";
		$this->FillCache($subres, $subfrom, $subnext, $this->optimize_empty_cache, false, ($flags&CACHE::VERBOSE)?1:0);
		
		$subres = $this->resolution->Smaller($subres);
	        $subresolution = $this->resolution->GetWindowSize($subres);

		$subfrom = $subnext;
	    }	
	    
	    if ($this->current_raw_end > $subfrom) $subfrom = $this->current_raw_end;
	    
	    if ($ito != $subfrom) {
//		echo "$subfrom - $ito\n";
	        $this->FillCache($subres, $subfrom, $ito, $this->optimize_empty_cache, false, ($flags&CACHE::VERBOSE)?1:0);
	    }
	}
	
	$this->current_raw_end = $ito;
    } else {
	$ito = floor($to);
	if ($this->current_raw_end > $from) $from = $this->current_raw_end;

	if ($from < $ito) {
	    $this->FillCache($res, $from, $ito, $this->optimize_empty_cache);
	    
	    $this->current_raw_end = $ito;
	}
    }

    if ($lock) {
	$lock->UnLock();
	unset($lock);
    }
 }
 

 /* limit - limts number of points, amount - selects appropriate resolution */
 function GetIntervals(MASK $mask = NULL, INTERVAL $ivl = NULL, $limit = 0, $amount = 0, $resolution = false, $flags = 0) {
    if (!$mask) $mask = $this->CreateMask();
    if (!$ivl) $ivl = $this->CreateInterval();

    if ($this->base_mask) {
	$mask = $this->base_mask->Superpose($mask);
    }

    return new DATAInterval($this, $mask, $ivl, is_numeric($amount)?$amount:0, is_numeric($limit)?$limit:0, $resolution, $flags);
 }
 
 function GetNeighbors(MASK $mask = NULL, $x, $amount = false, $flags = 0) {
    if (!$mask) $mask = $this->CreateMask();

    if ($this->base_mask) {
	$mask = $this->base_mask->Superpose($mask);
    }

    $ivl = new INTERVAL();
    
    $ivl->SetupWindow(INTERVAL::WINDOW_LEFT, $x);
    $left = array();
    
    $d = new RAWPoint($this, $mask, $ivl, ($amount===false)?1:$amount);
    foreach ($d as $tm => $val) {
	$left[$tm] = $val;
    }

    $ivl->SetupWindow(INTERVAL::WINDOW_RIGHT, $x);
    $right = array();

    $d = new RAWPoint($this, $mask, $ivl, ($amount===false)?1:$amount);
    foreach ($d as $tm => $val) {
	$right[$tm] = $val;
    }
    
    return array(
	'left' => &$left,
	'right' => &$right
    );
 }
 
 
 function GetTitle() {
    if ($this->reader) {
	return $this->reader->GetGroupTitle($this->group);
    } else {
	return "{$this->req->props['db_server']} -  {$this->req->props['db_name']} -  {$this->req->props['db_group']}";
    }
 }
 
 function GetGroupInfo($flags = 0) {
    return $this->reader->GetGroupInfo($this->group, $flags);
 }
 
 function GetItemList(MASK &$mask = NULL, $flags = 0) {
//    if (!$mask) $mask = $this->CreateMask();
    return $this->reader->GetItemList($this->group, $mask, $flags);
 }


 function GetExperimentList($flags = 0) {
    return $this->reader->GetExperimentList($flags);
 }

 function GetMaskList($flags = 0) {
    return $this->reader->GetMaskList($this->group, $flags);
 }

 function HavePoints(INTERVAL &$ivl = NULL, $resolution = 0) {
    if ($resolution)
	throw new ADEIException(translate("CACHE::HavePoints is only implemented for base resolution"));
    
    if (!$ivl) $ivl = $this->CreateInterval();
    $rawpoint = new RAWPoint($this, new MASK($props = array("db_mask"=>false)), $ivl, 1);
    $rawpoint->SetOption("sequence", INTERVAL::SEQUENCE_UNSORTED);
    $rawpoint->rewind();
    return $rawpoint->valid();
 }

 function GetPoints(MASK $mask = NULL, INTERVAL $ivl = NULL, $type = CACHEDB::TYPE_AUTO, $limit = 0, $amount = 0, $resolution = false, $flags = 0) {
    if (!$mask) $mask = $this->CreateMask();
    if (!$ivl) $ivl = $this->CreateInterval();

    if ($this->base_mask) {
	$mask = $this->base_mask->Superpose($mask);
    }

    if ($type == CACHEDB::TYPE_ALL) {
	return new RAWPoint($this, $mask, $ivl, $limit);
    } else {
	return new DATAPoint($this, $mask, $ivl, $type, is_numeric($amount)?$amount:0, is_numeric($limit)?$limit:0, $resolution, $flags);
    }
 }

    /* $limit < 0 => points from the end of interval, $limit > 0 => points from the begining of interval */
 function GetAllPoints(MASK $mask = NULL, INTERVAL $ivl = NULL, $limit = 0) {
    return $this->GetPoints($mask, $ivl, CACHEDB::TYPE_ALL, $limit);
 }

 function ExportCSV(STREAMHandler $h = NULL, MASK $mask = NULL, INTERVAL $ivl = NULL, $type = CACHEDB::TYPE_ALL, $limit = 0, $amount = 0, $resolution = false) {
    if (!$h) $h = new STREAMOutput();

    if (!$mask) $mask = $this->CreateMask();
    if (!$ivl) $ivl = $this->CreateInterval();

    $names = $this->GetItemList($mask);
    $data = $this->GetPoints($mask, $ivl, $type, $limit, $amount, $resolution);

    $h->SequenceStart();
    $h->Start();
    	
    $h->Write("Date/Time");
    foreach ($names as $name) {
	$h->Write(", " . preg_replace("/,/", " ", $name["name"]));
    }
    $h->Write("\r\n");

    $saved_tz = date_default_timezone_get ();
    date_default_timezone_set("UTC");
    foreach ($data as $time => $row) {
	$h->Write(date("d-M-y H:i:s", $time));
	//	echo date("m/d/Y H:i:s.u", $time);
	foreach ($row as $column) {
	    $h->Write(", " . $column);
	}
	$h->Write("\r\n");
    }
    
    $h->End();
    $h->SequenceEnd();
    
    date_default_timezone_set($saved_tz);
 }
}

?>