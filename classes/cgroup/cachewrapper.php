<?php
class CACHEWrapper {
 var $cache;
 var $mask;
 
 var $itemcache;
 
 var $DEFAULT_CONFIG;
 
 function __construct(CACHE $cache, MASK $mask = NULL) {
    $this->cache = $cache;
    $this->mask = $mask;

    $this->itemcache = false;

     $this->DEFAULT_CONFIG = array(
	'type' => CACHEDB::TYPE_AUTO,
	'limit' => 0,
	'amount' => 0
    );
 }
 
 function GetCache() {
    return $this->cache;
 }
 
 function GetMask() {
    return $this->mask;
 }

 function GetGroupProps() {
    return $this->cache->req->GetGroupProps();
 }
 
 function GetMaskIDs() {
    return $this->mask->GetIDs();
 }
 
 function GetWidth() {
    if (($this->mask)&&($this->mask->ids)) return sizeof($this->mask->ids);
    try {
        $mask = $this->cache->CreateMask();
    } catch (ADEIException $ae) {
        if ($ae->getCode() == ADEIException::NO_CACHE) return 0;
        throw $ae;
    }
    return sizeof($mask->ids);
 }

 private function GetCachedItems($flags, $query_flags = false) {
    if ($this->itemcache) {
	$cf = $this->itemcache['flags'];
	$exact_match = $query_flags&CACHESet::EXACT_FLAGS;
	if (((!$exact_match)&&(($cf&$flags) == $flags))||(($exact_match)&&($flags == $cf))) {
	    return $this->itemcache['items'];
	}

	if ($query_flags&CACHESet::CACHE_ITEMS) {
	    unset($this->itemcache);
	}
    }
    
    if ($query_flags === false) $query_flags = $flags;
    $items = $this->cache->GetItemList($this->mask, $query_flags);

    if ($query_flags&CACHESet::CACHE_ITEMS) {
	$this->itemcache = array(
	    'flags' => $query_flags,
	    'items' => &$items
	);
    }
    
    return $items;
 }

 function CreateAxes(GRAPHAxes $axes, $flags = 0) {
    $itemlist = $this->GetCachedItems(
	REQUEST::NEED_AXISINFO, 
	($flags&CACHESet::CACHE_ITEMS)?($flags|REQUEST::NEED_AXISINFO):(REQUEST::NEED_AXISINFO|REQUEST::ONLY_AXISINFO)
    );
    
    $size = $this->GetWidth();
    for ($i=0;$i<$size;$i++) {
	$axes->GetAxis(is_array($itemlist[$i])?$itemlist[$i]['axis']:false);
    }
 }
 
 function GetItemList($flags = 0) {
    return $this->GetCachedItems($flags);
 }

 function GetGroupTitle() {
    return $this->cache->GetTitle();
 }

 function GetIntervals(INTERVAL $ivl = NULL, array $cfg = NULL, $flags = 0) {
    if (isset($cfg['resolution'])) $resolution = $cfg['resolution'];
    else $resolution = $this->cache->resolution->Get($ivl, $cfg['amount']);

    try {
        $res = $this->cache->GetIntervals($this->mask, $ivl, $cfg['limit'], $cfg['amount'], $resolution, $flags);
    } catch (ADEIException $ae) {
        if ($ae->getCode() == ADEIException::NO_CACHE) return array();
        throw $ae;
    }
    
    return $res;
 }

 function GetNeighbors($x, array $cfg = NULL, $flags = 0) {
    try {
        $res = $this->cache->GetNeighbors($this->mask, $x, ($cfg&&isset($cfg['amount']))?$cfg['amount']:false, $flags);
    } catch (ADEIException $ae) {
        if ($ae->getCode() == ADEIException::NO_CACHE) return array();
        throw $ae;
    }

    return $res;
 }

 function GetPoints(INTERVAL $ivl = NULL, array $cfg = NULL, $flags = 0) {
    if (!$cfg) $cfg = &$this->DEFAULT_CONFIG;
    
    if (isset($cfg['resolution'])) $resolution = $cfg['resolution'];
    else $resolution = $this->cache->resolution->Get($ivl, $cfg['amount']);
    
    try {
        $res = $this->cache->GetPoints($this->mask, $ivl, $cfg['type'], $cfg['limit'], $cfg['amount'], $resolution, $flags);
    } catch (ADEIException $ae) {
        if ($ae->getCode() == ADEIException::NO_CACHE) return array();
        throw $ae;
    }
    return $res;
 }

 function GetAllPoints(INTERVAL $ivl = NULL, $limit = 0) {
    try {
        $res = $this->cache->GetAllPoints($this->mask, $ivl, CACHEDB::TYPE_ALL, $limit);
    } catch (ADEIException $ae) {
        if ($ae->getCode() == ADEIException::NO_CACHE) return array();
        throw $ae;
    }
    
    return $res;
 }
 
 function GetResolution() {
    return $this->cache->resolution;
 }
}
?>