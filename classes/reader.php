<?php

interface READERInterface {
    public function __construct(&$props);

    public function GetDatabaseList($flags = 0);
    public function GetGroupList($flags = 0);
    public function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0);
    public function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0);
    public function GetExperimentList($flags = 0);
    public function GetMaskList(LOGGROUP $grp = NULL, $flags = 0);
    
    public function CreateGroup(array &$ginfo = NULL, $flags = 0);
    public function CreateInterval(LOGGROUP $group = NULL, array &$iinfo = NULL, $flags = 0);
    public function CreateMask(LOGGROUP $grp = NULL, array &$minfo = NULL, $flags = 0);
    
    public function GetGroups();
    public function GetGroupSize(LOGGROUP $grp = NULL, $flags = 0);
    
    public function ImportUnixTime($unix_time);
    public function ExportUnixTime($db_time);

    public function HaveData(LOGGROUP $grp = NULL, $from = false, $to = false);
    public function GetRawData(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, &$filter_data = NULL);
    public function GetRawAlarms(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, &$filter_data = NULL);

    public function GetData(LOGGROUP $grp = NULL, $from = false, $to = false, MASK $mask = NULL, $resample = 0, $limit = 0);
    public function GetAlarms(LOGGROUP $grp = NULL, $from = false, $to = false, MASK $mask = NULL, $mode = 0, $severity = false, $limit = false);

    public function PushData(LOGGROUP $grp, $time, $data);
    public function RemoveData(LOGGROUP $grp, $time);
    
    public function Backup(&$binfo);
}

interface DATAExtractionInterface {
 public function __construct($mask, array $opts);
 public static function GetItemList(array $base_item, $flags);

    // Indexes in returned array should fit ones returned by GetItemList
 public function ExtractItem(&$data, $time, $id, &$value);
}


abstract class READER implements READERInterface {
 var $req;
 var $server;
 var $opts;

 var $srvid, $dbname;

 var $no_default_time_moudle;	// Setting to false prevents creation default timemodule
 var $time_format;
 var $time_zone;
 var $time_module;

 var $gmt_timezone;
 
 var $group_class;
 
 var $groups;			// Per group options, configs, etc...
 
 const BACKUP_FULL = 1;

 const ALARM_MODE_FULL_LIST = 1;
 const ALARM_MODE_ACTIVE_LIST = 2;

 function __construct(&$props) {
    if ($props instanceof REQUEST) {
	$this->req = &$props;
	$this->server = $this->req->GetServerInfo(REQUEST::NEED_INFO);
	
	$this->srvid = $props->props['db_server'];
    } else {
	$this->req = false;
	$this->server = &$props;

	$this->srvid = false;
    }
    
    if (isset($this->server['database']))
	$this->dbname = $this->server['database'];
    else 
	$this->dbname = false;
	
    $this->opts = new OPTIONS($props);

    $this->gmt_timezone = new DateTimeZone("GMT");
    
    $time_module = $this->opts->Get('time_module');
    if ($time_module) {
        ADEI::RequireClass("time/$time_module");
	
	$time_options = $this->opts->Get('time_options');
	$cl = strtoupper($time_module);
	$this->time_module = new $cl($this, $time_options);
    } else {
	$time_format = $this->opts->Get("time_format");
	if ($time_format) $this->time_format = $time_format;
	else if (!$this->no_default_time_module) $this->time_format = "U.u";
	else $this->time_format = false;

        $time_zone = $this->opts->Get("timezone");
	if ($time_zone) $this->time_zone = new DateTimeZone($time_zone);
	else $this->time_zone = new DateTimeZone("GMT");

	if ($this->time_format) {
	    $this->time_module = new READERTime($this, array(
		'format' => $this->time_format,
	        'timezone' => $this->time_zone
	    ));
	}
    }
    
    $this->group_class = false;
    $this->group_parameters = array();
 }

/*
 function GetServerID() {
    return $this->srvid;
 }
 
 function GetDatabaseName() {
    return $this->dbname;
 }
 
 function GetGroupID(LOGGROUP $grp = NULL) {
    $grp = $this->CheckGroup($grp);
    return $grp->gid;
 }
*/

 function ImportTime(DateTime $dt) {
    return $this->time_module->ImportTime($dt);
 }

 function ExportTime($db_time) {
    return $this->time_module->ExportTime($db_time);
 }

 function ImportUnixTime($unix_time) {
    return $this->time_module->ImportUnixTime($unix_time);
 }
 
 function ExportUnixTime($db_time) {
    return $this->time_module->ExportUnixTime($db_time);
 }
 
 function GetSourceTitle() {
    $server = $this->req->GetServerConfig();

    $title = $server['title'];
    if ($this->dbname) {
	$dblist = $this->GetDatabaseList();
	$title .= " - " . $dblist[$this->dbname]["name"];
    }
    return $title;
 }
 
 function GetGroupTitle(LOGGROUP $grp = NULL, $just_group = false) {
    $grp = $this->CheckGroup($grp);
    $groupinfo = $this->GetGroupInfo($grp);

    if ($just_group) return $groupinfo["name"];
    else {
	$title = $this->GetSourceTitle(); 
	return $title . " - " . $groupinfo["name"];
    }
 }

 function SortDatabaseList($list) {
    $server = $this->req->GetServerConfig();
    if ($server["multibase"]) {
	$res = array();
	
	foreach ($server['database'] as $db) {
	    if (is_string($server['multibase'])) {
		$filter = "/^(" . $db . ")" . $server['multibase'] . "/";
	    } else {
		$filter = "/^(" . $db . ")/";
	    }
	    
	    $sublist = array();
	    foreach ($list as $id=>$item) {
//		echo "$id $filter\n";
		if (preg_match($filter, $id)) {
		    $sublist[$id] = $item;
		}
	    }
	    
	    if ($sublist) {
		uasort($sublist, function ($a, $b) {
		    $la = strlen($a["name"]); $lb = strlen($b["name"]);
		    $cmpres = substr_compare($a["name"], $b["name"], 0, min($la, $lb));
		    if ($cmpres) return -$cmpres;
		    return ($la == $lb)?0:(($la < $lb)?-1:1);
		} );
		
		$res = array_merge($res, $sublist);
	    }
	}
	return $res;
    } else {
	return array_reverse($list);
    }
 }
 
 function GetDatabaseFilter($flags = 0) {
    if ($this->req)
	$server = $this->req->GetServerConfig();
    else
	throw new ADEIException(translate("The data source server is not specified"));


    $filter = "";
    foreach ($server['database'] as $db) {
	if ($filter) $filter .= "|$db";
	else $filter = $db;
    }

    if (($flags&REQUEST::LIST_WILDCARDED)&&($server['multibase'])) {
	if (is_string($server['multibase'])) {
	    $filter = "/^(" . $filter . ")" . $server['multibase'] . "/";
	} else {
	    $filter = "/^(" . $filter . ")/";
	}
    } else {
	$filter = "/^(" . $filter . ")$/";
    }
    
    return $filter;
 }
 
 function GetDatabaseList($flags = 0) {
    if ($this->req)
	$server = $this->req->GetServerConfig();
    else
	throw new ADEIException(translate("The data source server is not specified"));

    $dblist = array();
    foreach ($server['database'] as $name) {
	$dblist[$name] = array(
	    'name' => gettext($name)
	);
    }
    
    return $dblist;
 }
 
 function GetGroupList($flags = 0) {
    return $this->GetGroupInfo($gr=NULL, $flags);
 }
 
 protected function AppendExtractedItems(LOGGROUP $grp, MASK $mask, array &$items, $flags = 0) {
    if ($flags&REQUEST::SKIP_GENERATED) return;
    
    $exts = $this->GetGroupOption($grp, "data_extractors");
    if ($exts) {
        $all_items = $this->GetItemList($grp, $mask = new MASK(), REQUEST::LIST_CUSTOM|REQUEST::SKIP_GENERATED);
        $lastid = 0;
        foreach ($all_items as &$item) {
            if ((!$item['custom'])&&($item['id'] > $lastid)) $lastid = $item['id'];
        }

        foreach ($exts as $ekey => $opts) {
            $filter_class = $opts['filter'];
	    if (!include_once("extractors/" . strtolower($filter_class) . ".php"))
                throw new ADEIException(translate("Unsupported extractor is configured: \"%s\"", $filter_class));

            if (isset($opts['item_mask'])) {
	        if (is_array($opts['item_mask'])) {
	            $key = $opts['item_mask']['key'];
	            $re = $opts['item_mask']['items'];
	        } else {
	            $key = "id";
	            $re = $opts['item_mask'];
	        }
	    } else {
	        $key = "id";
	        $re = "/./";
	    }

            if (isset($opts['output_mask'])) {
	        if (is_array($opts['output_mask'])) {
	            $fkey = $opts['output_mask']['key'];
	            $fre = $opts['output_mask']['items'];
	        } else {
	            $fkey = "id";
	            $fre = $opts['output_mask'];
	        }
	    } else {
	        $fkey = "id";
	        $fre = "/./";
	    }
	    
            foreach ($all_items as &$item) {
                if (!preg_match($re, $item[$key])) continue;

                $filter_items = $filter_class::GetItemList($item, $flags);
                foreach ($filter_items as $eid => $fi) {
                    if (!preg_match($fre, $fi[$fkey])) continue;

                    $fi['id'] = ++$lastid;
                    if (!$mask->Check($fi['id'])) continue;

                    if ($opts['title']) $fi['name'] = sprintf($opts['title'], $item['name']);
                    else if ($fi['name']) $fi['name'] = sprintf($fi['name'], $item['name']);
                    else  $fi['name'] = "{$item['name']} $filter_class";

                    $fi['item_type'] = 'extract';
                    $fi['item_extractor'] = $opts['filter'];
                    $fi['item_extractor_id'] = $ekey;
                    $fi['item_dependency'] = $item['id'];
                    $fi['extractor_item'] = $eid;
                    
                    foreach ($item as $ikey => $val) {
                        $fi["item_dependency_$ikey"] = $val;
                    }

                    array_push($items, $fi);
                }
            }
        }
    }
 }
 

 function GetAlarmList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    return array();
 }

 function GetExperimentList($flags = 0) {
    return array();
 }

 protected function AppendStandardMasks(LOGGROUP $grp, array &$mask, $flags = 0) {
    if (!$mask['all']) {
	$mask['all'] = array(
	    'id' => "all",
	    "name" => _("All")
	);
    
	if ($flags&REQUEST::NEED_INFO) {
	    $items = $this->GetItemList($grp);
	    foreach (array_keys($items) as $i) {
		if ($i) $all .= "," . $i;
		else $all = $i;
	    }
	    unset($items);
	
	    $mask['all']['mask'] = $all;
	} 
    }
 }
 
 function GetMaskList(LOGGROUP $grp = NULL, $flags = 0) {
    $grp = $this->CheckGroup($grp, $flags);
    
    $mask = array();
    $this->AppendStandardMasks($grp, $mask, $flags);
    return $mask;
 }

 protected function AddAxisInfo(LOGGROUP $grp, array &$items) {
    $axis_opt = $this->req->GetGroupOption("axis", $grp);
    if ($axis_opt) {
	foreach ($items as &$item) {
		// sure we want to override 0 axis
	    if ($item['axis']) continue;
	    
	    if (is_array($axis_opt)) {
    		foreach ($axis_opt as $re => $axis) {
		    if (!strcmp($re,"*")) {
			$item['axis'] = $axis;
		    } else {
			if ($item['uid']) {
			    if (preg_match($re, $item['uid'])) {
				$item['axis'] = $axis;
				break;
			    }
			}
		    }
		}
	    } else {
		$item["axis"] = $axis_opt;
	    }
	}
    }
 }

 function CheckGroup(LOGGROUP &$grp = NULL, $flags = 0) {
    if ($grp) {
	if (($this->group_class)&&(!$grp instanceof $this->group_class))
	    throw new ADEIException(translate("Invalid LOGGROUP supplied"));
    } else {
	$grinfo = $this->req->GetGroupInfo($flags);
	$grp = $this->CreateGroup($grinfo, $flags);
    }
    return $grp;
 }

 function CreateGroup(array &$ginfo = NULL, $flags = 0) {
    if (!$ginfo) $ginfo = $this->req->GetGroupInfo($flags);

    if ($this->group_class) return new $this->group_class($ginfo, $this, $flags);
    return new LOGGROUP($ginfo, $this, $flags);
 }

 function CreateJointGroup($flags = 0) {
    throw new ADEIException(translate("Only virtual readers are supporting joint groups"));
 }

 function CreateMask(LOGGROUP $grp = NULL, array &$minfo = NULL, $flags = 0) {
    if (!is_array($minfo)) {
	if ($this->req instanceof GROUPRequest)
	    $minfo = $this->req->GetMaskInfo($flags);
    }

    $grp = $this->CheckGroup($grp, $flags);
    return new MASK($minfo, $this, $grp, $flags);
 }
 
 function CreateInterval(LOGGROUP $grp = NULL, array &$iinfo = NULL, $flags = 0) {
    if (!$iinfo) {
	if ($this->req instanceof DATARequest)
	    $iinfo = $this->req->GetIntervalInfo();
    }
    $grp = $this->CheckGroup($grp, $flags);
    
    $ivl = new INTERVAL($iinfo, $this, $grp, $flags);
    $this->LimitInterval($ivl, $grp);
    
    return $ivl;
 }
 
 function LimitInterval(INTERVAL $ivl, LOGGROUP $grp = NULL) {
    $this->req->LimitInterval($ivl, $grp);
 }

 function CreateCache(LOGGROUP $grp = NULL, $flags = 0) {
    return new CACHE($this->req, $this, $flags);
 }

 function CreateCacheSet(LOGGROUP $grp = NULL, MASK $msk = NULL) {
    $cache = $this->CreateCache($grp);
    return $this->req->CreateSimpleCacheSet($msk, $cache);
 }
 
 function CreateRequestSet(LOGGROUP $grp = NULL, MASK $msk = NULL, $type = "GROUPRequest") {
    return $this->req->CreateSimpleRequestSet($msk, $grp, $type);
 }

 function CreateAxes($flags = 0) {
    return new GRAPHAxes($this->req, $flags);
 }
 
 function CreateExtraAxes($flags = 0) {
    return $this->CreateAxes($flags|GRAPHAxes::EXTRA_ONLY);
 }

 function GetGroups() {
    $groups = $this->GetGroupList(); 

    $list = array();
    foreach (array_keys($groups) as $group) {
    	$ginfo = array("db_group" => $group);
	array_push($list, $this->CreateGroup($ginfo));
    }

    return $list;
 }

 function GetGroupSize(LOGGROUP $grp = NULL, $flags = 0) {
    $grp = $this->CheckGroup($grp, $flags);

    $params = $this->GetGroupParameters($grp);

    if (!$params['width']) {
	$params['width'] = sizeof($this->GetItemList($grp, $mask = new MASK()));
    }

    return $params['width'];
 }
 
 function HaveData(LOGGROUP $grp = NULL, $from = false, $to = false) {
    return true;
 }

 function PushData(LOGGROUP $grp, $time, $data) {
    throw new ADEIException(get_class($this) . ". PushData is not implemented");
 }
 
 function RemoveData(LOGGROUP $grp, $time) {
    throw new ADEIException(get_class($this) . ". RemoveData is not implemented");
 }
    
 /* DS: check for invalid columns number */
 function Clean(&$lg, $from = false, $to = false) {
    $data = $this->GetData($lg, $from, $to);
    foreach ($data as $t => $value) {
	if (sizeof($value)==0) {
	    $this->RemoveData($lg, $t);
	}
	
	$check = true;
	foreach ($value as $v) {
	    if ($v) {
		$check = false;
		break;
	    }
	}
	
	if ($check) {
	    $this->RemoveData($lg, $t);
	}
    }
 }

 function GetOption($prop, $default = NULL) {
    return $this->req->GetOption($prop, $default);
 }
 
 function GetGroupOption(LOGGROUP $grp = NULL, $prop, $default = NULL) {
    return $this->req->GetGroupOption($prop, $grp, $default);
 }

 function GetGroupParameters(LOGGROUP $grp, $prm = false) {
    global $DEFAULT_MISSING_VALUE;
    
    if (!$this->group_parameters[$grp->gid]) $this->group_parameters[$grp->gid] = array();
    $group = &$this->group_parameters[$grp->gid];
/*
    switch ($prm) {
    }
*/    
    return $group;
 }

 function Backup(&$binfo) {
    throw new ADEIException(get_class($this) . ". Backup is not implemented");
 }
 
 function CreateDataFilter(LOGGROUP $grp = NULL, MASK $mask = NULL, $resample = false, $limit = 0, $additional_filter = NULL) {
    $grp = $this->CheckGroup($grp);

    $filters = $this->GetGroupOption($grp, "data_filters");
    if ((!$filters)&&(isset($this->data_filters))) $filters = $this->data_filters;
    
    $exts = $this->GetGroupOption($grp, "data_extractors");
    if ($exts) {
        $list = $this->GetItemList($grp, $mask);
        $extra = array();
        $ids = array();
        $extractors = array();
        $mapping = array();
        foreach ($list as &$item) {
            $id = $item['id'];
            
            if ($item['item_type']) {
                if (isset($item['item_dependency'])) {
                    $dep = $item['item_dependency'];
                    if ((!$mask)||(!$mask->CheckStandard($dep))) array_push($extra, $dep);
                }
                
                if (!isset($extractors[$item['item_extractor_id']])) 
                    $extractors[$item['item_extractor_id']] = array();
                
                if (!isset($extractors[$item['item_extractor_id']][$item['item_dependency']])) 
                    $extractors[$item['item_extractor_id']][$item['item_dependency']] = array();
                
                array_push($extractors[$item['item_extractor_id']][$item['item_dependency']], $item['extractor_item']);
                $mapping[$id] = array($item['item_extractor_id'], $item['item_dependency'], $item['extractor_item']);
            } else {
                array_push($ids, $item['id']);
                //$mapping[$id] = true;
            }
        }

        if ($extractors) {
            if ($filters) {
	        if ($additional_filter) $additional_filter = new READER_SUPERDataFilter($additional_filter, $this, $grp, $filters, $mask, $resample, $limit);
	        else $additional_filter = new READER_DATAFilter($this, $grp, $filters, $mask, $resample, $limit);
	    }

            $new_mask = new MASK();
            $new_mask->SetIDs(array_merge($ids, array_unique($extra)));
            
            $extra_filter = array(
                "class" => "EXTRACTORFilter",
                "mask" => $mask,
                "config"=> $exts,
                "extractors" => $extractors,
                "mappings" => $mapping,
            );

            $filters = array($extra_filter);
            
            $mask = $new_mask;
        }
    }

    if ($mask) {
        $custom_filters = $this->GetGroupOption($grp, "custom_data_filters");
        if (($custom_filters)&&($mask->IsCustom())) {
            if ($filters) $filters = array_merge($custom_filters, $filters);
            else $filters = $custom_filters;
        }
    }
    
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

 function GetData(LOGGROUP $grp = NULL, $from = false, $to = false, MASK $mask = NULL, $resample = false, $limit = 0) {
    $grp = $this->CheckGroup($grp);
    $filter = $this->CreateDataFilter($grp, $mask, $resample, $limit);
    return $this->GetFilteredData($grp, $from, $to, $filter);
 }

 function GetFilteredData(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, $filter_data = NULL) {
    $grp = $this->CheckGroup($grp);
    if (($filter)||($filter = $this->CreateDataFilter($grp))) {
	return $filter->ProcessReaderData($this, $grp, $from, $to, $filter_data);
    }

    return $this->GetRawData($grp, $from, $to, $filter, $filter_data);
 }

/*
 function GetIntervalData(LOGGROUP $grp = NULL, INTERVAL $ivl, DATAFilter $filter = NULL, $filter_data = NULL) {
    $from = $ivl->GetWindowStart();
    $to = $ivl->GetWindowEnd();
    $this->GetFilteredData($grp, $ivl, $filter, $filter_data);
 }
*/

 function CreateAlarmFilter(LOGGROUP $grp = NULL, MASK $mask = NULL, $mode = 0, $severity = false, $limit = false, $additional_filter = NULL) {
    try {
	$grp = $this->CheckGroup($grp);
    } catch (ADEIException $ae) {
	$grp = NULL;
    }

    if ($grp) {
	$filters = $this->GetGroupOption($grp, "alarm_filters");
    } else {
	$filters = $this->opts->Get("alarm_filters");
    }
    
    if ((!$filters)&&(isset($this->alarm_filters))) $filters = $this->alarm_filters;

    if ($severity === false) {
	if (isset($this->req->props['severity'])) {
	    $severity = $this->req->props['severity'];
	} else {
	    $severity = $this->req->GetOption("alarm_severity", 0);
	}
    }

    if ($limit === false) {
	if (is_numeric($this->req->props['limit'])) {
	    $limit = $this->req->props['limit'];
	} else {
	    $limit = 0;
	}
    }
    
    if (($mask == NULL)&&($this->req->props['alarm_mask'])) {
	$mask = new MASK($mask_info = array(
	    "db_mask" => $this->req->props['alarm_mask']
	));
    }

    if (($filters)||($mask)||($mode)||($limit)||($severity)) {
	if ($additional_filter) $filter = new READER_SUPERFilter($additional_filter, $this, $grp, $filters, $mask, 0, $limit);
	else $filter = new READER_DATAFilter($this, $grp, $filters, $mask, 0, $limit);
	
	$filter->SetProperty("alarm_list_mode", $mode);
	$filter->SetProperty("alarm_severity", $severity);
	
	return $filter;
    }
 }

 function GetRawAlarms(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, &$filter_data = NULL) {
    return new NOData(); 
 }

 function GetCurrentAlarms(LOGGROUP $grp = NULL, MASK $mask = NULL, $severity = false) {
    //$grp = $this->CheckGroup($grp);
    $filter = $this->CreateAlarmFilter($grp, $mask = NULL, READER::ALARM_MODE_ACTIVE_LIST, $severity);
    return $this->GetRawAlarms($grp, $from, $to, $filter);
 }
 
 function GetAlarmsDetailed(LOGGROUP $grp = NULL, $from = false, $to = false, MASK $mask = NULL, $severity = false, $limit = false) {
    //$grp = $this->CheckGroup($grp);
    $filter = $this->CreateAlarmFilter($grp, $mask, READER::ALARM_MODE_FULL_LIST, $severity, $limit);
    return $this->GetRawAlarms($grp, $from, $to, $filter);
 }

 function GetAlarms(LOGGROUP $grp = NULL, $from = false, $to = false, MASK $mask = NULL, $mode = 0, $severity = false, $limit = false) {
    //$grp = $this->CheckGroup($grp);
    $filter = $this->CreateAlarmFilter($grp, $mask, $mode, $severity, $limit);
    return $this->GetRawAlarms($grp, $from, $to, $filter);
 }
 
 function GetControlsFromData(LOGGROUP $grp = NULL, MASK $mask = NULL) {
    $grp = $this->CheckGroup($grp, REQUEST::CONTROL);

	/* If NULL is passed for mask, we need to construct common mask here, 
	otherwise GetItemList may use reader-specific 'all' mask, and GetData 
	will surely return all the data */
	
    if (!$mask) $mask = $this->CreateMask($grp, $info = NULL, REQUEST::CONTROL);
    
    $info = $this->GetItemList($grp, $mask, REQUEST::CONTROL|REQUEST::NEED_INFO);
    $data = $this->GetData($grp, false, false, $mask, false, -1);

    $res = array();
    
    $data->rewind();
    if ($data->valid()) {
	$row = $data->current();
	$timestamp = $data->key();
	
	foreach ($row as $key => $value) {
	    $res[$key] = array(
		'value' => $value,
		'timestamp' => $timestamp,
		'verified' => $timestamp,
		'obtained' => gettimeofday(true),
		'db_server' => $this->srvid,
		'db_name' => $this->dbname,
		'db_group' => $grp->gid,
		'id' => $info[$key]['id'],
		'name' => $info[$key]['name']
	    );

	    if ($info[$key]['sampling_rate']) {
		$res[$key]['sampling_rate'] = $info[$key]['sampling_rate'];
	    }
	    if ($info[$key]['uid']) {
		$res[$key]['uid'] = $info[$key]['uid'];
	    }
	}
    }
    unset($data);

    return $res;
 }
 
 function GetControls(LOGGROUP $grp = NULL, MASK $mask = NULL) {
    return $this->GetControlsFromData($grp, $mask);
 }

 function DisposeControlMask(LOGGROUP $grp = NULL, MASK $mask = NULL, array &$values) {
    if ((!$mask)||(!$mask->ids)) return $values;

    $res = array();
    
    $grp = $this->CheckGroup($grp, REQUEST::CONTROL);
    $controls = $this->GetControls($grp, $full_mask = new MASK());
    
    
    foreach ($controls as $id => &$info) {
	$res[$id] = $info['value'];
    }
    
    foreach ($values as $id => $value) {
	$res[$mask->ids[$id]] = $value;
    }

    $values = $res;
 }
 
 function SetControls(LOGGROUP $grp = NULL, MASK $mask = NULL, array &$info = NULL, $flags = 0) {
    if (!$info) $info = $this->req->props;

    $grp = $this->CheckGroup($grp, REQUEST::CONTROL);
    if (!$mask) $mask = $this->CreateMask($grp, $mask_info = NULL, REQUEST::CONTROL);

	// Allows to expect what $mask->ids are set if $mask is not NULL
    if (($mask)&&($mask->IsFull())) $mask = NULL;

    $checks = $this->req->GetGroupOption("control_checks", $grp);
    if ($checks) $checks = explode(",", $checks);
    else $checks = array();
    
    if ($info['controls_check']) {
	array_push($checks, explode(",", $info['controls_check']));
    }

    $controls = $this->GetItemList($grp, $msk = new MASK(), REQUEST::CONTROL|REQUEST::NEED_INFO);

    $uids = array();
    foreach ($controls as &$control) {
        if ($control['uid']) $uids[$control['uid']] = $control['id'];
    }
    
	// Analyzing parameters
    if (isset($info['control_values'])) {
	$values = explode(",", $info['control_values']);

	if ($mask) {
	    if (sizeof($values) != sizeof($mask->ids)) {
		throw new ADEIException(translate("Invalid number of set values is specified. The mask contains %u items, but %u is given in control_values", sizeof($mask->ids), sizeof($values)));
	    }
	} else {
	    if (sizeof($values) != sizeof($controls)) {
		throw new ADEIException(translate("Invalid number of set values is specified. The group contains %u items, but %u is given in control_values", sizeof($controls), sizeof($values)));
	    }
	}

	$value_mask = $mask;
    } else {
	$u_sets = array();
	$sets = array();
	
	if (isset($info['control_set'])) {
	    $expressions = explode(",", $info['control_set']);
	    foreach ($expressions as $expr) {
		if (preg_match("/^([^=]+)=(.*)$/", $expr, $m)) {
		    $u_sets[$m[1]] = $m[2];
		} else {
		    throw new ADEIException(translate("Invalid expression (%s) is passed in control_set parameter", $expr));
		}
	    }
	}
	
	foreach ($info as $key => $value) {
    	    if (preg_match("/^control_(u)?id_(.*)$/", $key, $m)) {
		if ($m[1]) {
	    	    $u_sets[$m[2]] = $value;
		} else if (preg_match("/\d+/", $m[2])) {
		    if ($mask) {
			throw new ADEIException(translate("The control values can't be referenced by ids if mask is specified, use uid notion"));
		    }
		    $sets[$m[2]] = $value;
		} else {
		    throw new ADEIException(translate("Non numeric channel id (%s) is specified", $m[2]));
		}
	    }
	}
	
	foreach ($u_sets as $uid => $value) {
	    if (isset($uids[$uid])) $id = $uids[$uid];
//	    else if ((is_numeric($uid))&&(preg_match("/^\d+$/", $uid))) $id = $uid;
	    else throw new ADEIException(translate("Invalid control (%s) is specified in control_set parameter", $uid));
	    
	    $sets[$id] = $value;
	}
	
	if (!$sets) {
	    throw new ADEIException(translate("The set values are not specified. Either control_values or control_set variables should be specified"));
	}
		    
	$values = array();
	$mask_ids = array();
	foreach ($sets as $id => $value) {
	    if ($mask) {
		$id = array_search($id, $mask->ids);
		if ($id === false) {
		    throw new ADEIException(translate("The control (%s) specified in control_set parameter is not present in the supplied mask", $id));
		}
	    } else {
		if ($id >= sizeof($controls)) {
		    throw new ADEIException(translate("Invalid control (%s) is specified in control_set parameter", $id));
		}
	    }
	    
	    array_push($values, $value);
	    array_push($mask_ids, $id);
	}

	    
	$value_mask = new MASK($info = array(
		"control_mask" => implode(",", $mask_ids)
	    ), 
	    $this, 
	    $grp,
	    REQUEST::CONTROL
	);

    }
        
    $writable_check = false;
    if ($value_mask) {
	foreach ($value_mask->ids as $id) {
	    if (!$controls[$id]['write']) {
		$writable_check = $id;
		break;
	    }
	}
    } else {
	foreach ($controls as &$control) {
	    if (!$control['write']) {
		$writable_check = $control['id'];
		break;
	    }
	}
    }
    
    if ($writable_check !== false) {
	throw new ADEIException(translate("The item %d in control group %s is not writable", $writable_check, $grp->gid));
    }

    $this->ControlStart($grp);
    try {
    
	    // check values
	foreach ($controls as $control) {
	}

	    // check other
	if ($checks) {
	    foreach ($checks as $check) {
/*		preg_replace_callback("/^([\w\d_-]+)$/",
		    array($this, "")
		    $check
		);*/
	    }
	}
	
	if (($flags&REQUEST::SKIP_CHECKS)==0) {
	    $before = $this->GetControls($grp, $value_mask);
	}
        $this->SetRawControls($grp, $value_mask, $values);
    } catch (ADEIException $ae) {
	$this->ControlCancel($grp);
	throw $ae;
    }

    $this->ControlFinish($grp);

    if ($flags&REQUEST::SKIP_CHECKS) return;

	// Waiting untill values are actually changed, takes time...
    $timeout = 0;
    foreach (array_keys($before) as $i) {
	if ($values[$i] != $before[$i]["value"]) {
	    if ($this->server['timeout']) $timeout = $this->server['timeout']/1000000 + gettimeofday(true);
	    else $timeout = 1 + gettimeofday(true);
	    break;
	}
    }
    
    do {
	$after = $this->GetControls($grp, $value_mask);
	foreach (array_keys($after) as $i) {
	    if ($after[$i]["value"] != $before[$i]["value"]) {
		$timeout = 0;
		break;
	    }
	}
    } while (gettimeofday(true)<$timeout);
    
    return $after;
 }


 
 function ControlStart(LOGGROUP $grp) {
    $this->control_lock = new LOCK("control");
    $this->control_lock->Lock(LOCK::BLOCK);
 }
 
 function ControlFinish(LOGGROUP $grp) {
    $this->control_lock->UnLock();
 }
 
 function ControlCancel(LOGGROUP $grp) {
    $this->control_lock->UnLock();
 }
 
 function SetRawControls(LOGGROUP $grp = NULL, MASK $mask = NULL, array &$values) {
    throw new ADEIException(translate("Control interface is not supported by the READER"));
 }
 
 protected function ExportData(DATAHandler $h = NULL, LOGGROUP $grp, MASK $mask, INTERVAL $ivl = NULL, $resample = 0, array &$names, $opts = 0, $dmcb = NULL) {
    global $DEFAULT_MISSING_VALUE;  
    $dm = new DOWNLOADMANAGER();
    if (!$h) $h = new CSVHandler();    
    
    $filter = $this->CreateDataFilter($grp, $mask, $resample, $ivl->GetItemLimit());
    if (!$h->nullwriter) {
	$filter->AddFilter(new NULLCorrector($this->GetGroupOption($grp, "null_value", $DEFAULT_MISSING_VALUE)));
    }
   
    $data = $this->GetFilteredData($grp, $ivl->GetWindowStart(), $ivl->GetWindowEnd(), $filter);

    $columns = sizeof($names);
    
    $h->Start($columns);
    $h->DataHeaders($names);    
    
    if($dmcb != NULL) {
      $action = "start";
      $current_time = $ivl->GetWindowStart();        
      $onepercent = ($ivl->GetWindowEnd() - $ivl->GetWindowStart()) / 100;
      $download = call_user_func_array($dmcb, array($action, ""));      
    }      
    
    foreach ($data as $time => $row) {	
      $h->DataVector($time, $row);     
      if($dmcb != NULL) {
	$action = "progress";
	$thisupdate = time();
	$updateinterval = $thisupdate - $lastupdate;
	if($time >= $current_time + $onepercent && $updateinterval >= 2) {		    
	  $current_time = $time;
	  $lastupdate = time();
	  $prog = round(($time - $ivl->GetWindowStart()) / $onepercent, 0);	
 	  if(!call_user_func_array($dmcb, array($action, $prog, $download))) {
 	    $action = "finish";
 	    call_user_func_array($dmcb, array($action, "cancelled"));
 	    unset($dmcb);
 	    break;
 	  }
	}
      }
    }     
    
    if($dmcb != NULL){
      $action = "finish";      
      call_user_func_array($dmcb, array($action, "", $download));       
    }    
  
    $h->End();
    
 }

 
 
 function Export(DATAHandler $h = NULL, LOGGROUP $grp = NULL, MASK $mask = NULL, INTERVAL $ivl = NULL, $resample = 0, $opts = 0, $dmcb = NULL) {
    $grp = $this->CheckGroup($grp);
    if (!$mask) $mask = $this->CreateMask($grp, $minfo = array());
   
    $names = $this->GetItemList($grp, $mask);
    return $this->ExportData($h, $grp, $mask, $ivl, $resample, $names, $opts, $dmcb);

 }

 function ExportCSV(STRINGHandler $h = NULL, LOGGROUP $grp = NULL, MASK $mask = NULL, INTERVAL $ivl = NULL, $resample = 0, $opts = 0) {
    return $this->Export(new CSVHandler($h), $grp, $msk, $ivl, $resample, $opts);
 }

}



class NULLReader implements READERInterface {
    function __construct(&$props) {}

    function GetDatabaseList($flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }

    function GetGroupList($flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }

    function GetExperimentList($flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function GetMaskList(LOGGROUP $grp = NULL, $flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function CreateGroup(array &$ginfo = NULL, $flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function CreateInterval(LOGGROUP $group = NULL, array &$iinfo = NULL, $flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function CreateMask(LOGGROUP $grp = NULL, array &$minfo = NULL, $flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function GetGroups() {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function GetGroupSize(LOGGROUP $grp = NULL, $flags = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function ImportUnixTime($unix_time) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function ExportUnixTime($db_time) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }

    function HaveData(LOGGROUP $grp = NULL, $from = false, $to = false) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function GetData(LOGGROUP $grp = NULL, $from = false, $to = false, MASK $mask = NULL, $resample = 0, $limit = 0) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }

    function GetRawData(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, &$filter_data = NULL) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }

    function GetAlarms(LOGGROUP $grp = NULL, $from = false, $to = false, MASK $mask = NULL, $mode = 0, $severity = false, $limit = false) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function GetRawAlarms(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, &$filter_data = NULL) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }

    function GetFilteredData(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, $filter_data = NULL) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function PushData(LOGGROUP $grp, $time, $data) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function RemoveData(LOGGROUP $grp, $time) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
    
    function Backup(&$binfo) {
	throw new ADEIException(translate("%s is not implemented", get_class($this) . ". " . __METHOD__));
    }
}

class NOData implements Iterator {
 function rewind() {}
 function next() {}
 function valid() {
    return false;
 }
 function key() {
    return false;
 }
 function current() {
    return false;
 }
}

require($ADEI_ROOTDIR . "/classes/readers/dbreader.php");
require($ADEI_ROOTDIR . "/classes/download.php");
?>