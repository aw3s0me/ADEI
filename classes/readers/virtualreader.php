<?php

class VIRTUALReader extends READER {
 var $cache;

 var $items;
 var $groups;
 
 var $srctree;
 
 var $cache_reader;
 var $reader_access_by_cache_readers;
 
 const SOURCE_TREE_ID = "srctree";
 const COMPLEX_ID = "-1";
 const MISSING_ID = "-2";
 const SRCTREE_ID = "-3";
 
 function __construct(&$props) {
    parent::__construct($props);
    
    if ($this->dbname) {
	if (!strcmp($this->dbname, VIRTUALReader::SOURCE_TREE_ID)) {
	    $virtual = $this->req->GetProp('virtual', false);
	    if ($virtual) $prop = $virtual;
	    else $prop = "srctree";

	    $srctree = $this->req->GetProp($prop);
	} else {
	    $srctree = $this->opts->Get("srctree", false);
	    if ($this->srctree === false) {
		throw new ADEIException(translate("Invalid database (%s) is specified", $this->dbname));
	    }
	}
	if (strcmp($this->dbname, VIRTUALReader::SOURCE_TREE_ID)) $flags = 0;
	else $flags = SOURCETree::IGNORE_UIDS|SOURCETree::IGNORE_BADITEMS|SOURCETree::EXPAND_SERVERS;
	
	$this->srctree = new SOURCETree($srctree, false, $flags);
	//$this->srctree->CastProps(array($this, "CastProps"));
    }
    
    $this->cache_reader = false;
 }

 function CreateJointGroup($flags = 0) {
    return $this->CreateGroup($req = array("db_group" => VIRTUALReader::COMPLEX_ID));
 }

 function DisableReaderAccess() {
    $this->reader_access_by_cache_readers = false;
 }

/* Probably we don't need that at all
 function ForceCachePostfix(array $props) {
    //I really have no clue at the moment what how the requests should be passed
    //from super class to abstracted real classes. Should implemented when such 
    //classes are arriving
    throw ADEIException(translate("CACHEReader abstraction interface of VirtualReader is incomplete. SetDefaultPostifx function should be implemented"));
 }
*/


 function GetGroupServerID(LOGGROUP $grp = NULL) {
    $grp = $this->CheckGroup($grp);

    try {
	$greq = $this->srctree->GetGroupRequest($grp);
    } catch (ADEIException $ae) {
	return $this->srvid;
    }
    
    return $greq->props['db_server'];
 }

 function GetGroupDatabaseName(LOGGROUP $grp = NULL) {
    $grp = $this->CheckGroup($grp);

    try {
	$greq = $this->srctree->GetGroupRequest($grp);
    } catch (ADEIException $ae) {
	return $this->dbname;
    }

    return $greq->props['db_name'];
 }

 function GetGroupID(LOGGROUP $grp = NULL) {
    $grp = $this->CheckGroup($grp);

    try {
	$greq = $this->srctree->GetGroupRequest($grp);
    } catch (ADEIException $ae) {
	return $grp->gid;
    }

    return $greq->props['db_group'];
 }

/*
 function GetGroupReader(LOGGROUP $grp = NULL) {
    $grp = $this->CheckGroup($grp);

    try {
	$greq = $this->srctree->GetGroupRequest($grp);
    } catch (ADEIException $ae) {
	return $this;
    }
    
    return $this->CreateReader($greq);
    
 }
*/

 function GetGroupTitle(LOGGROUP $grp = NULL, $just_group = false) {
    $grp = $this->CheckGroup($grp);

    try {
	$greq = $this->srctree->GetGroupRequest($grp);
    } catch (ADEIException $ae) {
	return parent::GetGroupTitle($grp, $just_group);
    }

    $rdr = $this->CreateReader($greq);
    $group = $greq->CreateGroup();
    return $rdr->GetGroupTitle($group, $just_group);
 }

 function GetDatabaseList($flags = 0) {
    $dblist = $this->opts->ListConfiguredDatabases();

    $res = array();
    foreach ($dblist as $db) {
	$name = $this->opts->GetSpecific("name", _("Unnamed Virtual Group"), $this->srvid, $db);
	
	$res[$db] = array(
	    "name" => $name
	);
    }
        
    $res[VIRTUALReader::SOURCE_TREE_ID] = array(
	"name" => _("Source Tree")
    );
    
    return $res;
 }

 function CreateAxes($flags = 0) {
    $axes = new GRAPHAxes($this->req, $flags);

    $req = $this->srctree->GetSourceRequests();
    foreach ($req as $sreq) {
	$rdr = $this->CreateReader($sreq);
	$extra = $rdr->CreateExtraAxes();
	if ($extra) $axes->Add($extra);
    }
    
    return $axes;
 }

 function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
    $flags &= ~REQUEST::CONTROL;

    $req = $this->srctree->GetRequests();

    $res == array();
    if ($flags&REQUEST::LIST_COMPLEX) {
	$res[VIRTUALReader::COMPLEX_ID] = array();
    }

    if (($flags&REQUEST::LIST_COMPLEX)&&($flags&REQUEST::NEED_INFO)) {
	if ((!$grp)||(!strcmp($grp->gid, VIRTUALReader::COMPLEX_ID))) {
	    $compute = 1;
	    $first = false;
	    $last = false;
	    $records = false;
	    $all_items = array();
	} else $compute = 0;
    } else $compute = 0;
    
    foreach ($req as $gid => $greq) {
	if ((!$compute)&&($grp)&&(strcmp($grp->gid, $gid))) continue;

	$rdr = $this->CreateReader($greq);
	$group = $rdr->CreateGroup();
	$info = $rdr->GetGroupInfo($group, $flags&~REQUEST::NEED_ITEMINFO);

        if ($flags&REQUEST::NEED_INFO) {
	    if ($compute) {
		if ($first === false) {
		    $first = $info['first'];
		} else {
		    $first = min($first, $info['first']);
		}

		if ($last === false) {
		    $last = $info['last'];
		} else {
		    $last = max($last, $info['last']);
		}
	        
		if ($flags&REQUEST::NEED_COUNT) {
	    	    if ($records === false) {
			$records = $info['records'];
		    } else {
			$last = max($records, $info['records']);
		    }
		}
	    }
	
	    if ($flags&REQUEST::NEED_ITEMINFO) {
		$info['items'] = $this->GetItemList($group);
		if ($compute) {
		    foreach ($info['items'] as &$item) {
			array_push($all_items , $item);
		    }
		}
	    }
	}

	if (($grp)&&(strcmp($grp->gid, $gid))) continue;

	$info['gid'] = $gid;
	$res[$gid] = $info;
    }
    
    if (($flags&REQUEST::LIST_COMPLEX)&&(sizeof($res)>1)) {
	$cinfo = array(
	    'gid' => VIRTUALReader::COMPLEX_ID,
	    'name' => _("Joint Group"),
	    'complex' => 1
	);
	
        if ($flags&REQUEST::NEED_INFO) {
	    $opts = $this->req->GetGroupOptions($grp);
	    $limit = $opts->GetDateLimit();
	    
	    if ((is_int($limit[0]))&&($limit[0] > $first)) {
	        $cinfo['first'] = $limit[0];
	    } else {
	        $cinfo['first'] = $first;
	    }
	    
	    if ((is_int($limit[1]))&&($limit[1] < $last)) {
		$cinfo['last'] = $limit[1];
	    } else {
	        $clast['last'] = $last;
	    }

	    if ($records !== false) {
		$cinfo['records'] = $records;
	    }

	    if ($flags&REQUEST::NEED_ITEMINFO) {
		$cinfo['items'] = $all_items;
	    }
	}
	
	foreach ($cinfo as $key => $val) {
	    $res[VIRTUALReader::COMPLEX_ID][$key] = $val;
	}
	
    } else {
	unset($res[VIRTUALReader::COMPLEX_ID]);
    }
 
    if (($flags&REQUEST::LIST_VIRTUAL)&&(!sizeof($res))) {
	if (strcmp($this->dbname, VIRTUALReader::SOURCE_TREE_ID)) {
	    $res[VIRTUALReader::MISSING_ID] = array(
		'id' => VIRTUALReader::MISSING_ID,
		'name' => _("No groups")
	    );
	} else {
	    $res[VIRTUALReader::SRCTREE_ID] = array(
		'id' => VIRTUALReader::SRCTREE_ID,
		'name' => _("Source Tree"),
		'complex' => 1
	    );
	}
    }

    return $grp?$res[$grp->gid]:$res;
 }

 function CreateGroup(array &$ginfo = NULL, $flags = 0) {
    $grp = parent::CreateGroup($ginfo, $flags);
    if ($grp->gid < 0) {
	$grp->MarkComplex();
    }
    return $grp;
 }

 function GetMaskList(LOGGROUP $grp = NULL, $flags = 0) {
    $grp = $this->CheckGroup($grp, $flags);

     if (strcmp($grp->gid, VIRTUALReader::SRCTREE_ID)) {
        return parent::GetMaskList($grp, $flags);
     }
     
    return array_merge(
	parent::GetMaskList($grp, $flags),
	array(
	    "all" => array(
		'id' => "all",
		'name' => _("All"),
		'mask' => "all"
	    )
	)
    );

 }

 function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    $flags &= ~REQUEST::CONTROL;

    $grp = $this->CheckGroup($grp, $flags);
    if (!$mask) $mask = $this->CreateMask($grp, $info = NULL, $flags);

    if ($grp->gid < 0) {
      if (!strcmp($grp->gid, VIRTUALReader::COMPLEX_ID)) {
	$res = array();
	
	$req = $this->srctree->GetRequests($mask);
	foreach ($req as $greq) {
	    $rdr = $this->CreateReader($greq);
	    $group = $rdr->CreateGroup();
	    $res = array_merge($res, $rdr->GetItemList($group, $msk = NULL, $flags));
	}
	return $res;
      } else if (!strcmp($grp->gid, VIRTUALReader::MISSING_ID)) {
	    return array(0 => array(
	    'id' => 0,
	    'name' => _("No items")
	));
      } else if (!strcmp($grp->gid, VIRTUALReader::SRCTREE_ID)) {
	    return array(0 => array(
	    'id' => 0,
	    'name' => _("Source Tree")
	));
      }
    }

    $req = $this->srctree->GetGroupRequest($grp, $mask);
    $rdr = $this->CreateReader($req);
    $group = $rdr->CreateGroup();
    $mask = $rdr->CreateMask();

    return $rdr->GetItemList($group, $mask, $flags);
 }

 function CreateInterval(LOGGROUP $grp = NULL, array &$iinfo = NULL, $flags = 0) {
    $grp = $this->CheckGroup($grp, $flags);

    if (!$iinfo) {
	if ($this->req instanceof DATARequest)
	    $iinfo = $this->req->GetIntervalInfo();
    }

    if ($grp->gid < 0) {
      if ((!strcmp($grp->gid, VIRTUALReader::COMPLEX_ID))||(!strcmp($grp->gid, VIRTUALReader::SRCTREE_ID))) {

	$req = $this->srctree->GetRequests();
	$ivls = array();
	foreach ($req as $greq) {
	    $rdr = $this->CreateReader($greq);
	    $group = $greq->CreateGroup();
	    array_push($ivls, $rdr->CreateInterval($group, $iinfo));
	    unset($reader);
	}

        $ivl = new INTERVAL($iinfo);
	$ivl->ApplyIntervals($ivls);
	return $ivl;
      }
    }

    $req = $this->srctree->GetGroupRequest($grp, $mask);
    $rdr = $this->CreateReader($req);
    $group = $rdr->CreateGroup();

    $ivl = $rdr->CreateInterval($group, $iinfo);
    $this->req->LimitInterval($ivl, $group);
    return $ivl;
 }

 function ConvertToCacheReader(CACHEDB $cache = NULL) {
    $this->cache_reader = true;
    $this->reader_access_by_cache_readers = true;
 }

 function GetCachePostfix(LOGGROUP $grp) {
    if (!strcmp($grp->gid, VIRTUALReader::COMPLEX_ID)) {
	throw new ADEIException(translate("CACHE could not be created on complex groups"));
    }

    try {
	$greq = $this->srctree->GetGroupRequest($grp, $mask);
    } catch (ADEIException $ae) {
        if (!strcmp($grp->gid, VIRTUALReader::MISSING_ID)) {
	    throw new ADEIException(translate("The source tree is empty, please, select something"));
	} else if (!strcmp($grp->gid, VIRTUALReader::SRCTREE_ID)) {
	    throw new ADEIException(translate("CACHE could not be created for source tree"));
	} else {
	    throw $ae;
	}
    }
    
    return $greq->props;
 }

 private function CreateReader(SOURCERequest $req, $info_reader = true) {
    if ($this->cache_reader) {
	    /* Conversion is not needed, used only for local stuff */
	$rdr = $req->CreateCacheReader();
	if (!$this->reader_access_by_cache_readers) $rdr->DisableReaderAccess();
	return $rdr;
    } else return $req->CreateReader();
 }
  
 function CreateCache(LOGGROUP $grp = NULL, $flags = 0) {
    $grp = $this->CheckGroup($grp, $flags);
    
    $greq = $this->GetCachePostfix($grp);
    
/*
    Older version
    $cache = new CACHE(new GROUPRequest($greq), $rdr = NULL, $flags);
*/

    $props = $this->req->props;
    $props['db_group'] = $grp->gid;
    $props['base_mask'] = $greq['db_mask'];
    $req = new GROUPRequest($props);
    unset($greq['db_mask']);
     
    $cache = new CACHE($req, $this, $flags);
    $cache->SetDefaultPostfix($greq);

    return $cache;
 }

 function CreateRequestSet(LOGGROUP $grp = NULL, MASK $mask = NULL, $type = "GROUPRequest") {
    $grp = $this->CheckGroup($grp);

    if (!($grp->gid < 0)) {
	if ((strcmp($grp->gid, VIRTUALReader::COMPLEX_ID))&&(strcmp($grp->gid, VIRTUALReader::SRCTREE_ID))) {
	    return parent::CreateRequestSet($grp, $mask, $type);
	}
    }

    if (!$mask) $mask = $this->CreateMask($grp);
    return $this->srctree->GetRequests($mask, array($this, "CastProps"), $type);
 }


 function CreateCacheSet(LOGGROUP $grp = NULL, MASK $mask = NULL) {
    $grp = $this->CheckGroup($grp);

    if (!($grp->gid < 0)) {
	if ((strcmp($grp->gid, VIRTUALReader::COMPLEX_ID))&&(strcmp($grp->gid, VIRTUALReader::SRCTREE_ID))) {
	    return parent::CreateCacheSet($grp, $mask);
	}
    }

    if (!$mask) $mask = $this->CreateMask($grp);
/*
    Old method
    $reqlist = $this->srctree->GetRequests($mask);
*/
    $reqlist = $this->srctree->GetRequests($mask, array($this, "CastProps"));
    return $this->req->CreateCacheSet($reqlist);
 }

 function GetRawData(LOGGROUP $grp = NULL, $from = 0, $to = 0, DATAFilter $filter = NULL, &$filter_data = NULL) {
    if ($grp->gid < 0) {
	if (!strcmp($grp->gid, VIRTUALReader::COMPLEX_ID)) {
	    throw new ADEIException(translate("Data could not be retrieved from complex groups"));
        } else if (!strcmp($grp->gid, VIRTUALReader::MISSING_ID)) {
	    throw new ADEIException(translate("The source tree is empty, please, select something"));
	} else if (!strcmp($grp->gid, VIRTUALReader::SRCTREE_ID)) {
	    throw new ADEIException(translate("Data could not be retrieved from source tree"));
	}
    }

    if ($filter) {
	$mask = $filter->GetItemMask();
	$rate = $filter->GetSamplingRate();
	$limit = $filter->GetVectorsLimit();
    } else {
	$mask = NULL;
	$rate = false;
	$limit = 0;
    }
    
    $req = $this->srctree->GetGroupRequest($grp, $mask);
    $rdr = $this->CreateReader($req);
    $group = $rdr->CreateGroup();

    $mask = $rdr->CreateMask();
    $super_filter = $this->CreateDataFilter($group, $mask, $rate, $limit, $filter);

    return $rdr->GetRawData($group, $from, $to, $super_filter, $filter_data);
 }

 function HaveData(LOGGROUP $grp = NULL, $from = 0, $to = 0) {
    $grp = $this->CheckGroup($grp);

    if (!strcmp($grp->gid, VIRTUALReader::COMPLEX_ID)) {
	$res = array();
	
	$req = $this->srctree->GetRequests($mask);
	foreach ($req as $greq) {
	    $rdr = $this->CreateReader($greq);
	    $group = $rdr->CreateGroup();
	    if ($rdr->HaveData($group, $from, $to)) return true;
	}
	
	return false;
    } else if (!strcmp($grp->gid, VIRTUALReader::MISSING_ID)) {
	return false;
    }


    $req = $this->srctree->GetGroupRequest($grp, $mask);
    $rdr = $this->CreateReader($req);
    $group = $rdr->CreateGroup();
    
    return $rdr->HaveData($group, $from, $to);
 }

 function Export(DATAHandler $h = NULL, LOGGROUP $grp = NULL, MASK $mask = NULL, INTERVAL $ivl = NULL, $resample = 0, $opts = 0, $dmcb = NULL) {
    if ($grp->gid < 0) {
	if (!strcmp($grp->gid, VIRTUALReader::COMPLEX_ID)) {
	    throw new ADEIException(translate("Data could not be retrieved from complex groups"));
        } else if (!strcmp($grp->gid, VIRTUALReader::MISSING_ID)) {
	    throw new ADEIException(translate("The source tree is empty, please, select something"));
	} else if (!strcmp($grp->gid, VIRTUALReader::SRCTREE_ID)) {
	    throw new ADEIException(translate("Data could not be retrieved from source tree"));
	}
    }

    $req = $this->srctree->GetGroupRequest($grp, $mask);
    $rdr = $this->CreateReader($req);
    $group = $rdr->CreateGroup();

    $mask = $rdr->CreateMask();
    return $rdr->Export($h, $group, $mask, $ivl, $resample, $opts, $dmcb);
 }
 
 function CastProps(&$props, $id) {
    $props = array_merge(
	$this->req->props, array(
	    'db_group' => $id,
	    'db_name' => $this->dbname,	// do we need that?
	    'db_server' => $this->srvid, // and that
	    'db_mask' => $props['real_mask']
	)
    );

/*    
    $props['db_group'] = $id;
    $props['db_server'] = $this->srvid;
    $props['db_name'] = $this->dbname;
    
    $props['db_mask'] = $props['real_mask'];
    unset($props['real_mask']);
*/
 }
}

?>