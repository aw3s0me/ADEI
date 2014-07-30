<?php

class CACHEReader extends READER {
 var $cache;

 var $reader_access;

 function __construct(&$props, CACHEDB $cache = NULL) {
    parent::__construct($props);
    if ($cache) $this->cache = $cache;
    else {
	if ($props instanceof SOURCERequest)
	    $this->cache = new CACHEDB($props);
	else
	    $this->cache = NULL;
    }
    
    $opts = $this->req->GetOptions();
    if ($opts->Get('use_cache_reader')) {
	$this->reader_access = false;
    } else {
	$this->reader_access = true;
    }
 }
 
 function DisableReaderAccess() {
    $this->reader_access = false;
 }


/*
 Not needed, I supose.
 function ForceCachePostfix(array $props) {
    if ($this->cache) $this->cache->SetDefaultPostfix($props);
    else throw new ADEIException(translate("This CACHEReader instance does not supports virtual abstraction"));
 }
*/

 function CreateGroup(array &$ginfo = NULL, $flags = 0) {
    if ($this->reader_access) {
	$rdr = $this->req->CreateReader();
	$grp = $rdr->CreateGroup($ginfo, $flags);
	unset($rdr);

	return $grp;
    }
    
    return parent::CreateGroup($ginfo, $flags);
 }

 function CreateMask(LOGGROUP $grp = NULL, array &$minfo = NULL, $flags = 0) {
    if ($this->reader_access) {
	$rdr = $this->req->CreateReader();
	$msk = $rdr->CreateMask($grp, $minfo, $flags);
	unset($rdr);

	return $msk;
    }
    
    return parent::CreateMask($grp, $minfo, $flags);
 }

 function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
    $groups = array();

    $res = $this->cache->ListCachedGroups();
    foreach ($res as $gid) {
	if (($grp)&&(strcmp($grp->gid, $gid))) continue;
	
	$groups[$gid] = array(
	    'gid' => $gid,
	    'name' => $gid
	);

	if ($flags&REQUEST::NEED_INFO) {
	    $postfix = $this->cache->GetCachePostfix($gid);
	    
	    $info = $this->cache->GetCacheInfo($postfix, $flags&REQUEST::FLAG_MASK);

	    if (!$info)
		throw new ADEIException(translate("The CACHE for group (%s) is empty", $grp->gid));

	    foreach ($info as $key => &$value) {
		$groups[$gid][$key] = $value;
	    }
	    
	    if ($flags&REQUEST::NEED_ITEMINFO) {
		$groups[$gid]['items'] = $this->cache->GetCacheItemList($postfix);
	    }
	}
    }

    return $grp?$groups[$grp->gid]:$groups;
 }
 
 function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    if ($flags&REQUEST::ONLY_AXISINFO) {
	if (!$this->req->GetGroupOptions($grp, "axis")) return array();
    }

    $grp = $this->CheckGroup($grp, $flags);

    $postfix = $this->cache->GetCachePostfix($grp->gid);
    if (!$mask) $mask = $this->CreateMask($grp, $info=NULL, $flags);

    return $this->cache->GetCacheItemList($postfix, $mask, 0);
 }

 function CreateDataFilter(LOGGROUP $grp = NULL, MASK $mask = NULL, $resample = false, $limit = 0, $additional_filter = NULL) {
    $grp = $this->CheckGroup($grp);

    if ($resample === false) {
	$resample = $this->GetGroupOption($grp, "resample", 0);
    }  

    if (($filters)||($mask)||($resample)||($limit)) {
	if ($additional_filter) $filter = new READER_SUPERDataFilter($additional_filter, $this, $grp, $filters, $mask, $resample, $limit);
	else $filter = new READER_DATAFilter($this, $grp, $filters, $mask, $resample, $limit);
	
	return $filter;
    }
    
    return $additional_filter;
 }

 function GetRawData(LOGGROUP $grp = NULL, $from = 0, $to = 0, DATAFilter $filter = NULL, &$filter_data = NULL) {
    $grp = $this->CheckGroup($grp);

    $postfix = $this->cache->GetCachePostfix($grp->gid);

    $ivl = $this->CreateInterval($grp);
    $ivl->Limit($from, $to);

    if ($filter) {
	$limit = $filter->GetVectorsLimit();
	$resample = $filter->GetSamplingRate();
	$mask = $filter->GetItemMask();
	
	if (isset($filter_data)) {
	    if ($mask) $filter_data['masked'] = true;
	    if ($resample) $filter_data['resampled'] = true;
	    if ($limit) $filter_data['limited'] = true;
	}
    } else {
	$mask = NULL;
	$resample = 0;
	$limit = 0;
    }

    if (!$mask) {
	$minfo = array("db_mask"=>"");
	$mask = $this->cache->CreateCacheMask($postfix, $minfo);
    }

    return $this->cache->GetCachePoints($postfix, $mask, $ivl, CACHEDB::TYPE_ALL, $limit, $resample);
 }

 function HaveData(LOGGROUP $grp = NULL, $from = 0, $to = 0) {
    $grp = $this->CheckGroup($grp);

    $postfix = $this->cache->GetCachePostfix($grp->gid);

    $ivl = $this->CreateInterval($grp);
    $ivl->Limit($from, $to);

    $mask = NULL;

    $points = $this->cache->GetCachePoints($postfix, $mask, $ivl, CACHEDB::TYPE_ALL, 1);

    $points->rewind();
    return $points->valid();
 }

 function Export(DATAHandler $h = NULL, LOGGROUP $grp = NULL, MASK $mask = NULL, INTERVAL $ivl = NULL, $resample = 0, $opts = 0, $dmcb = NULL) {
    $names = false;

    if ($this->reader_access) {
	$rdr = $this->req->CreateReader();

	$grp = $rdr->CheckGroup($grp);
	if (!$mask) $mask = $rdr->CreateMask($grp, $minfo = array());

	$names = $rdr->GetItemList($grp, $mask);
	unset($rdr);
    } else {
	$grp = $this->CheckGroup($grp);
	if (!$mask) $mask = $this->CreateMask($grp, $minfo = array());
    }

    if (!$names) $names = $this->GetItemList($grp, $mask);

    return $this->ExportData($h, $grp, $mask, $ivl, $resample, $names, $opts, $dmcb = NULL);
 }


 
}

?>