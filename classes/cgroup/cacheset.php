<?php

abstract class CACHESet implements iterator {
 const CACHE_ITEMS = 0x80000000; //(REQUEST::FLAG_MASK + 1);
 const EXACT_FLAGS = 0x40000000;//(REQUEST::FLAG_MASK + 1) << 1;

 function IsEmpty() {
    return true;
 }
  
 function CreateInterval(REQUEST $req = NULL, $cache_timewindow = false) {
    $ivls = array();

    if ($cache_timewindow) {
	if ($req) $iinfo = $req->GetIntervalInfo();
	else $iinfo = NULL;//array();

//	if (!$req) $req = new DATARequest($args = array());

	foreach ($this as $cachewrap) {
	    $cache = $cachewrap->GetCache();
	    $ivl = new INTERVAL($iinfo);
	    $ivl->ApplyCache($cache);
	    $cache->LimitInterval($ivl);
	    array_push($ivls, $ivl);
	}
    } else {
	if ($req) $iinfo = $req->GetIntervalInfo();
	else $iinfo = NULL;//array();

	foreach ($this as $cachewrap) {
	    $cache = $cachewrap->GetCache();
	    array_push($ivls, $cache->CreateInterval($iinfo));
	}
    }
    
    $ivl = new INTERVAL($iinfo);
    $ivl->ApplyIntervals($ivls);

    return $ivl;
 }
 
 function GetWidth() {
    $res = 0;
    foreach ($this as $cachewrap) {
	$res += $cachewrap->GetWidth();
    }
    return $res;
 }
 
 function GetData(INTERVAL $ivl, $type, $sampling = 3600) {
    /* 
	Following approach is expected:
    1. Getting intervals
	a) For each group found maximal cache level, which could divide the 
	requested sampling rate.
	b) Create join request selecting data from multiple tables and providing
	it once 3600 seconds
    2. Use interval->point conversions
	MEAN, MIN, MAX, SUM (which is MEAN * N)
	complex thing like MMAX should not be included
    3. Passed back to CACHESetReader
*/
    // Performs selective joins a
 }
 
 function CreateAxes(GRAPHAxes $axes, $flags = 0) {
    foreach ($this as $cachewrap) {
	$cachewrap->CreateAxes($axes);
    }
 }

 function EnumerateAxes(GRAPHAxes $axes, $flags = 0) {
    $this->CreateAxes($axes);
    $axes->Enumerate();
 }
}

class REQUESTListCacheSet extends CACHESet {
 var $list;
 
 function __construct(REQUESTList $list) {
    $this->list = $list;
 }

 function IsEmpty() {
    return $this->list->IsEmpty();
 }

  
 function rewind() {
    $this->list->rewind();
 }
 
 function current() {
    $req = $this->list->current();
    $cache = $req->CreateCache();
    return new CACHEWrapper($cache);
 }
 
 function key() {
    return $this->list->key();
 }
 
 function next() {
    $this->list->next();
 }

 function valid() {
    return $this->list->valid();
 }
}

class SIMPLECacheSet extends CACHESet {
 var $cache, $mask;
 var $valid;

 function __construct(CACHE $cache, MASK $mask = NULL) {
    $this->cache = $cache;
    $this->mask = $mask;
    $this->rewind();
 }

 function IsEmpty() {
    return false;
 }

 function rewind() {
    $this->valid = true;
 }
 
 function next() {
    $this->valid = false;
 }

 function valid() {
    return $this->valid;
 }    
 
 function current() {
    if ($this->valid) {
	return new CACHEWrapper($this->cache, $this->mask);
    }
 }

 function key() {
    return 0;
 }
}
?>