<?php

class ZEUSLogGroup extends LOGGROUP {
 var $table;
 
 function __construct(array &$info, ZEUS $zeus, $flags = 0) {
    parent::__construct($info, $zeus, $flags);
    
    if ($this->gid === false) $this->gid = 0;
    $this->table = "log" . $this->gid;
 }
}

class ZEUSMask extends MASK {
 function __construct(&$props = NULL, ZEUS $zeus, LOGGROUP $grp = NULL, $flags = 0) {
    if ((($flags&REQUEST::CONTROL)==0)&&(is_array($props))&&(preg_match("/^maskid(\d+)$/",$props["db_mask"], $m))) {

	$sql = "SELECT name, mask FROM masks WHERE maskid=" . $m[1];
	try {
	    $stmt = $zeus->db->Prepare($sql);
	    $stmt->bindColumn(1, $name);
	    $stmt->bindColumn(2, $mask);//, PDO::PARAM_LOB);
	    $stmt->execute();

	    $row = $stmt->fetch(PDO::FETCH_BOUND);
	    unset($stmt);
	    
	    if ($row) {
		$this->ids = $zeus->ParseMask($mask);
		return;
	    }
	} catch (PDOException $pe) {
	    $e = $this->dbh->errorInfo();
	    throw new ADEIException(translate("Execution of the SQL Query is failed. SQL Error: %u, Driver Error: %u, Message: %s ", $e[0], $e[1], $e[2]) . "[$sql]");
	}
	
    }
    
    parent::__construct($props);
 }
}

class ZEUSData implements Iterator {
 var $zeus;
 var $stmt;
 var $row;
 var $ts;

 function __construct(ZEUS $zeus, PDOStatement $stmt) {
    $this->zeus = $zeus;
    $this->stmt = $stmt;
    $this->ts = false;
 }

 function ParseBlob($data) {
    if ($this->zeus->char_mode) {
	$data = pack("H*", $data);
    }

    $kanz=(strlen($data)-4)/8;
    $bform='d'.$kanz.'ist/Vlen';
    $blob = unpack($bform,strrev($data));

    $res = array();
    for ($i=0;$i<$kanz;$i++) {
	if (($kanz > 1)||(isset($blob["ist".($kanz-$i)]))) {
	    $res[$i] = $blob["ist".($kanz-$i)];
	} else if (isset($blob["ist"])) {
	    $res[$i] = $blob["ist"];
	}

	if (is_nan($res[$i])) $res[$i] = NULL;
    }
    return $res;
 }

 
 function rewind() {
    try {
	$this->stmt->execute();
	$this->next();
    } catch(PDOException $e) {
	throw new ADEIException(translate("SQL request is failed with error") . ": " . $e->getMessage(), $e->getCode());
    }

 }
 
    // current element
 function current() {
    return $this->row;
 }
 
    // current key (PHP rounding to integer :()
 function key() {
    return $this->zeus->ExportUnixTime($this->ts);
 }
 
    // advvance to next (and returns it or false)
 function next() {
    try {
	$data = $this->stmt->fetch(PDO::FETCH_ASSOC);
	if ($data) {
	    $this->row = $this->ParseBlob($data['data']);
	    $this->ts = $data['ts'];
	} else $this->ts = false;
    } catch(PDOException $e) {
	throw new ADEIException(translate("SQL error") . ": " . $e->getMessage());
    }
 }
 
    // checks if there is current element
 function valid() {
    return $this->ts?true:false;
 }
}


class ZEUSAlarms implements Iterator {
 var $zeus;
 var $stmt;
 var $row;
 var $key;

 function __construct(ZEUS $zeus, PDOStatement $stmt) {
    $this->zeus = $zeus;
    $this->stmt = $stmt;
    $this->ts = false;
 }

 function rewind() {
    try {
	$this->stmt->execute();
	$this->key = 0;
	$this->next();
    } catch(PDOException $e) {
	throw new ADEIException(translate("SQL request is failed with error") . ": " . $e->getMessage(), $e->getCode());
    }

 }
 
 function current() {
    return $this->row;
 }
 
 function key() {
    return $this->key - 1;
 }
 
    // advvance to next (and returns it or false)
 function next() {
    try {
	$data = $this->stmt->fetch(PDO::FETCH_ASSOC);
	if ($data) {
	    $this->row = array(
		'id' => $data['messageid'],
		'in' => $this->zeus->ExportUnixTime($data['come']),
		'out' => ((isset($data['notactive']))&&($data['notactive']==0))?false:($data['go']?$this->zeus->ExportUnixTime($data['go']):false),
		'name' => $data['name'],
		'severity' => $data['mtype'],
	    );
	    if ($data['cond']) {
		$this->row['description'] = $data['cond'];
	    }

	    if ($data['count']) {
	    	$this->row['count'] = $data['count'];
	    }

	    $this->key++;
	} else $this->row = false;
    } catch(PDOException $e) {
	throw new ADEIException(translate("SQL error") . ": " . $e->getMessage());
    }
 }
 
 function valid() {
    return $this->row?true:false;
 }
}

class ZEUS extends READER {
 var $db;
 var $char_mode; /* Specifies whatever SQL_C_CHAR or SQL_C_BINARY is returned 
	by database while BLOB is requested */
			
 const TYPE_FLOAT = 1;
 const TYPE_INT32 = 2;

 var $data_filters = array (
    array (
        "class" => "LENGTHFilter",
	"add_missing_items" => true,
	"ignore_invalid_data" => true
    ),
    array (
        "class" => "NULLFilter",
	"use_missing_items" => true,
	"check_growth" => true,
	"check_precise" => true,
//	"ignore_invalid_data" => false
//	"tolerate_at_end_only" => true
    )
 );


 function __construct(&$props) {
    parent::__construct($props);    
    $this->db = new DATABASE($this->server);

    switch ($this->server["driver"]) {
	case "odbc":
	    $this->char_mode = true;
	    break;
	default:
	    $this->char_mode = false;
    }
 }
 
 function ConvertBinary(&$zeus_blob, $type = ZEUS::TYPE_FLOAT) {
    if ($this->char_mode) {
	$data = pack("H*", $zeus_blob);
    } else {
	$data = &$zeus_blob;
    }
    
    switch ($type) {
	case ZEUS::TYPE_FLOAT:
	    $kanz=(strlen($data)-4)/8;
	    $bform='d'.$kanz.'ist/Vlen';
	    break;
	case ZEUS::TYPE_INT32:
	    $kanz=(strlen($data)-4)/4;
	    $bform='V'.$kanz.'ist/Vlen';
	    break;
	default:
	    throw new ADEIException(translate("Unknown ZEUS type (%i) is specified", $type));
    }	
    $blob = unpack($bform,strrev($data));

    $res = array();
    for ($i=0;$i<$kanz;$i++) {
	if (($kanz > 1)||(isset($blob["ist".($kanz-$i)]))) 
	    $res[$i]=$blob["ist".($kanz-$i)];
	else 
	    $res[$i]=$blob["ist"];
    }
    return $res;
 }
    
 function CreateGroup(array &$ginfo = NULL, $flags = 0) {
    if (!$ginfo) $ginfo = $this->req->GetGroupInfo($flags);
    return new ZEUSLogGroup($ginfo, $this, $flags);
 }
 
 function CheckGroup(LOGGROUP $grp = NULL, $flags = 0) {
    if ($flags&REQUEST::CONTROL) {
	$grinfo = $this->req->GetGroupInfo($flags);
    	//$grp = parent::CreateGroup($grinfo, $flags);
	$grp = $this->CreateGroup($grinfo, $flags);
    } else {
	if ($grp) {
	    if (!$grp instanceof ZEUSLogGroup)
		throw new ADEIException(translate("Invalid LOGGROUP is supplied"));
	} else {
	    $grinfo = $this->req->GetGroupInfo($flags);
	    $grp = $this->CreateGroup($grinfo, $flags);
	}
    }
    return $grp;
 }
 
 function CreateMask(LOGGROUP $grp = NULL, array &$minfo = NULL, $flags = 0) {
    if ($minfo === NULL) {
	if ($this->req instanceof ITEMGroupRequest)
	    $minfo = $this->req->GetMaskInfo($flags);
    }
    
    if ($flags&REQUEST::CONTROL) {
	return parent::CreateMask($grp, $minfo, $flags);    
    } else {
	return new ZEUSMask($minfo, $this, $grp, $flags);
    }
 }

/* ToDo: 
 *  1. Add checks (if it is really Zeus database)
 */
 function GetDatabaseList($flags = 0) {
    $filter = $this->GetDatabaseFilter($flags);
    $res = $this->SortDatabaseList($this->db->GetDatabaseList($filter));

    foreach ($res as &$item) {
       $item['name'] = gettext($item['name']);
    }

    return $res;
 }
 
 function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
    if ($flags&REQUEST::NEED_INFO)
	$req_cols = "*";
    else
	$req_cols = "gid, name";

    if ($grp)
	$res = $this->db->Query("SELECT $req_cols FROM groups WHERE gid=" . $grp->gid);
    else
	$res = $this->db->Query("SELECT $req_cols FROM groups");

    $groups = array();
    if ($flags&REQUEST::NEED_INFO) {
	foreach ($res as $row) {
	    $gid = $row['gid'];
	    $groups[$gid] = $row;
        
	    $ginfo = array("db_group" => $gid);
	    $grzeus = $this->CreateGroup($ginfo);
	    
	    $groups[$gid]['__internal__'] = array();
	    
	    try {
		$req = "MIN(ts), MAX(ts)";
		if ($flags&REQUEST::NEED_COUNT) 
		    $req .= ", COUNT(ts)";

	        $valres = $this->db->Query("SELECT $req FROM " . $grzeus->table, DATABASE::FETCH_NUM);
		$vals = $valres->fetch(PDO::FETCH_NUM);
		$valres = NULL;
	    
	        if ($this->req) {
	    	    $opts = $this->req->GetGroupOptions($grzeus);
		    list($start_date, $end_date) = $opts->GetDateLimit();
	        } else {
		    $start_date = false; $end_date = false;
		}

		$groups[$gid]['first'] = $this->ExportUnixTime($vals[0]);
	        if (($start_date)&&($start_date > $groups[$gid]['first'])) {
	    	    $groups[$gid]['first'] = $start_date;
		    $groups[$gid]['__internal__']['first'] = $this->ImportUnixTime($start_date);
	        } else {
		    $groups[$gid]['__internal__']['first'] = $vals[0];
		}

	        $groups[$gid]['last'] = $this->ExportUnixTime($vals[1]);
		if (($end_date)&&($end_date < $groups[$gid]['last'])) {
	    	    $groups[$gid]['last'] = $end_date;
	    	    $groups[$gid]['__internal__']['last'] = $this->ImportUnixTime($end_date);
	        } else {
		    $groups[$gid]['__internal__']['last'] = $vals[1];
		}

	        if ($flags&REQUEST::NEED_COUNT) 
		    $groups[$gid]['records'] = $vals[2];
	    } catch (ADEIException $e) {
		$vals = false;
	    }

	    if ($flags&REQUEST::NEED_ITEMINFO) {
		$groups[$gid]['items'] = $this->GetItemList($grzeus, NULL, $flags);
	    }

	}
    } else {
	foreach ($res as $row) {
	    $groups[$row['gid']] = $row;
	}
    }

    return $grp?$groups[$grp->gid]:$groups;
 }
 
 function GetExperimentList($flags = 0) {
    $resp = $this->db->query("SELECT experiment, name, start, stop FROM marker");

    $list = array();
    foreach ($resp as $row) {
	if (!preg_match("/[\w\d]/", $row['name'])) $row['name'] = _("No name");
	    /* Read format */
	    
	$item = array();
	$item['start'] = $row['start'];
	$item['stop'] = $row['stop'];
	    
	if (preg_match("/[\w\d]/", $row['experiment'])) $have_exp = 1;
	else $have_exp = 0;
	if (preg_match("/[\w\d]/", $row['name'])) $have_name = 1;
	else $have_name = 0;
	    
	    
	if (($have_exp)&&($have_name)) $item['name'] = $this->db->RecodeMessage($row['experiment'] . ": " . $row['name']);
	else if ($have_exp) $item['name'] = $this->db->RecodeMessage($row['experiment']);
	else if ($have_name) $item['name'] = $this->db->RecodeMessage($row['name']);
	else $item['name'] = $item['start'] . " - " . $item['stop'];
	    
	array_push($list, $item);
    }

    return $list;
 }

 function GetMaskList(LOGGROUP $grp = NULL, $flags = 0) {
    $grp = $this->CheckGroup($grp, $flags);

    $list = array();
    
    $resp = $this->db->Query("SELECT maskid, name, mask FROM masks WHERE gid=" . $grp->gid);
    foreach ($resp as $row) {
	if (!preg_match("/[\w\d]/", $row['name'])) $row['name'] = _("No name");

	$id = "maskid" . $row['maskid'];
	
	$list[$id] = array(
	    'id' => $id,
	    'name' => $this->db->RecodeMessage($row['name'])
	);
	
	if ($flags&REQUEST::NEED_INFO) {
	    $list[$id]['mask'] = implode(",", $this->ParseMask($row['mask']));
	}
    }
    
    $timestamps = $this->req->GetGroupOption("timestamp_channels", $grp);
    if ($timestamps) {
	if ($timestamps === true) $timestamps = 2;

	$items = sizeof($this->GetItemList($grp, NULL, $flags));
	if ($items > $timestamps) {
	    for ($i = $timestamps; $i < $items; $i++) {
		if ($i == $timestamps) $all = $i; 
	        else $all .= "," . $i;
	    }
	}
	
	$list["all"] = array(
	    'id' => "all",
	    'name' => _("All"),
	    'mask' => $all
	);
    }

    return array_merge(
	parent::GetMaskList($grp, $flags),
	$list
    );
 }
 
 function ParseMask($mask) {
    $zeus_mask = $this->ConvertBinary($mask, ZEUS::TYPE_INT32);
		
    $this->ids = array();
    for ($i=0;isset($zeus_mask[$i]);$i++) {
	if ($zeus_mask[$i] < 2) {
	    array_push($this->ids, $i);
        }
    }

    return $this->ids;
 }

/*
 protected function ExtractItemInfo($i, $pos, $res, $names, $uid) {
    $info = array(
	"id" => $pos,
	"group" => $res['name'],
	"name" =>  $names[$i]
    );
    

    if (preg_match("/[\w\d]/", $info["name"])) {
	if ($uid) {
	    if (($uid === true)||(preg_match($uid, $info["name"]))) {
		$info["uid"] = $info["name"];
	    }
	}
    } else {
	if (preg_match("/[\w\d]/", $info["group"])) {
	    $info["name"] = "item" . $i;
	} else {
	    $info["name"] = "item" . $pos;
	}
    }

    if (preg_match("/[\w\d]/", $items[$rpos]["group"])) {
    	$info["name"] = $info["name"] . " [" . $info["group"] . "]";
    } 
 }
*/
  
 function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    if ($flags&REQUEST::ONLY_AXISINFO) {
	if (!$this->req->GetGroupOptions($grp, "axis")) return array();
    }
    
    $grp = $this->CheckGroup($grp, $flags);
    if (!$mask) $mask = $this->CreateMask($grp, $info = NULL, $flags);

    $uid = $this->opts->Get('channel_uids', false);

    $bids = array();
    $items = array();

    $resp = $this->db->Query("SELECT id FROM g2id WHERE gid=" . $grp->gid . " ORDER BY pos ASC");
    foreach ($resp as $row) {
	array_push($bids, $row['id']);
    }

    $pos = 0; $rpos = 0;
    foreach ($bids as $bid) {    

	$resp = $this->db->Query("SELECT length, name, itemnames FROM blocks WHERE bid=$bid");
	if ($resp) $res = $resp->fetch();
	else $res = false;
	    
	$names = preg_split("/\r?\n/",  $this->db->RecodeMessage($res['itemnames']));
	for ($i = 0; $i < $res['length']; $i++, $pos++) {
	    if (!$mask->Check($pos)) continue;
	    
	    $items[$rpos] = array(
		"id" => $pos,
		"block_name" =>  $this->db->RecodeMessage($res['name']),
		"chan_name" => $names[$i],
		"name" =>  $names[$i],
		"opc" => ""
	    );
	    if (preg_match("/[\w\d]/", $items[$rpos]["name"])) {
		if ($uid) {
		    if (($uid === true)||(preg_match($uid, $items[$rpos]["name"]))) {
			$items[$rpos]["uid"] = $items[$rpos]["name"];
		    }
		}
	    } else {
		if (preg_match("/[\w\d]/", $items[$rpos]["block_name"])) {
		    $items[$rpos]["name"] = "item" . $i;
		} else {
		    $items[$rpos]["name"] = "item" . $pos;
		}
	    }
	    if (preg_match("/[\w\d]/", $items[$rpos]["block_name"])) {
		$items[$rpos]["name"] = $items[$rpos]["name"] . " [" . $items[$rpos]["block_name"] . "]";
	    } 
	    
	    $rpos++;
	}
	unset($names);
    }

    if ($flags&REQUEST::NEED_AXISINFO) {
	$this->AddAxisInfo($grp, $items);
    }
    
    return $items;
 }

 function GetAlarmList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    $masktest = "";
    if ($mask) {
	$ids = $mask->GetIDs();
	if ($ids) {
	    $lastid = array_pop($ids);

	    foreach ($ids as $id) {
		$masktest .= "(messageid = $id) OR ";
	    }
	    
	    $masktest .= "(messageid = $lastid)";
	}
    }
    
    $columns = "messageid AS id, mtype AS severity, name";

    $selopts = array();
    if ($masktest) $selopts['condition'] = $masktest;

    $query = $this->db->SelectRequest("messages", $columns, $selopts);
    $resp = $this->db->Query($query);
    $res = array();

    if ($resp) {
	foreach ($resp as $row) {
	    $res[$row['id']] = $row;
	}
    }

    return $res;
 }


    /* ZEUS time is a LabVIEW time */
 function ImportUnixTime($unix_time) {
    if (!$unix_time) return 0;
    return ($unix_time + (2082837600+7200));
 }
 
 function ExportUnixTime($zeus_time) {
    if (!$zeus_time) return false;
    return ($zeus_time - (2082837600+7200));

 }
 
 function GetGroupSize(LOGGROUP $grp = NULL, $flags = 0) {
    $grp = $this->CheckGroup($grp, $flags);

    $params = $this->GetGroupParameters($grp);
    if ($params['width']) return $params['width'];


    $bids = array();
    
    try {    
	$resp = $this->db->Query("SELECT id FROM g2id WHERE gid=" . $grp->gid);
	foreach ($resp as $row) {
	    array_push($bids, $row['id']);
	}

	$size = 0;
	foreach ($bids as $bid) {    
	    $resp = $this->db->Query("SELECT length FROM blocks WHERE bid=$bid");
	    if ($resp) $res = $resp->fetch();
	    else $res = false;
	    if ($res) $size += $res['length'];
	}
    } catch (PDOException $e) {
	throw new Exception($e->getMessage());
    }
    
    $params['width'] = $size;
    
    return $size;
 }

    /* Between includes start point, but excludes end point */ 
 function GetRawData(LOGGROUP $grp = NULL, $from = 0, $to = 0, DATAFilter $filter = NULL, &$filter_data = NULL) {
    $grp = $this->CheckGroup($grp);
    
    $items = $this->GetItemList($grp, new MASK());

    if ((!$from)||(!$to)) {
        $ivl = $this->CreateInterval($grp);
	$ivl->Limit($from, $to);
	
	$from = $ivl->GetWindowStart();
	$to = $ivl->GetWindowEnd();
    }

    if ($filter) {
	$limit = $filter->GetVectorsLimit();
	$resample = $filter->GetSamplingRate();

	if (isset($filter_data)) {
	    if ($resample) $filter_data['resampled'] = true;
	    if ($limit) $filter_data['limited'] = true;
	}
    } else {
	$limit = 0;
	$resample = 0;
    }

    $selopts = array(
	"condition" => "ts BETWEEN " . $this->ImportUnixTime($from) . " and " . $this->ImportUnixTime($to)
    );
    
    if ($resample) {
	$selopts['sampling'] = array(
	    "slicer" => $this->time_module->GetTimeSlicingFunction("ts", $resample),
	    "selector" => "ts"
	);
    }
    
    if ($limit) {
	$selopts['limit'] = abs($limit);
	if ($limit > 0) {
	    $selopts['order'] = "ts ASC";
	} else {
	    $selopts['order'] = "ts DESC";
	    if ($limit < -1)
		throw new ADEIException(translate("Current version supports only selecting a single item from the end"));
	}
    } else {
	$selopts['order'] = "ts ASC";
    }
    
    $query = $this->db->SelectRequest($grp->table, "ts, data", $selopts);
    $stmt = $this->db->Prepare($query);
    
    return new ZEUSData($this, $stmt);
 }

 function GetRawAlarms(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, &$filter_data = NULL) {
    if (($this->server['driver']=="mysql")||($this->sqldrv['driver']=="mysql")) {
	// the subrequest should be formulated differently. 
	//throw new ADEIException(translate("Alarms are not supported in MySQL version yet"));
    }


    // If interval is not specified we are looking for the alarms in the 
    // interval where the data of current group is present
    //The group is optional and not needed in this reader
/*
    try {
	$grp = $this->CheckGroup($grp);
    } catch (ADEIException $ae) {
	$grp = false;
    }
*/

    if ((!$from)||(!$to)) {
	if ($grp) {
	    $ivl = $this->CreateInterval($grp);
	} else {
	    $ivl = new INTERVAL($this->req->props);
	}
	$ivl->Limit($from, $to);
	$from = $ivl->GetWindowStart(false);
	$to = $ivl->GetWindowEnd(false);
    }

    if ($filter) {
	$limit = $filter->GetVectorsLimit();
	$mode = $filter->GetProperty("alarm_list_mode");
	$severity = $filter->GetProperty("alarm_severity");
	$mask = $filter->GetItemMask();

	if (isset($filter_data)) {
	    if ($mask) $filter_data['masked'] = true;
	    if ($limit) $filter_data['limited'] = true;
	}
    } else {
	$limit = 0;
	$mode = 0;
	$severity = 0;
	$mask = NULL;
    }
    

    $masktest = "";
    if ($mask) {
	$ids = $mask->GetIDs();
	if ($ids) {
	    $lastid = array_pop($ids);

	    foreach ($ids as $id) {
		$masktest .= "(messageid = $id) OR ";
	    }
	    
	    $masktest .= "(messageid = $lastid)";
	}
    }

    switch ($mode) {
     case READER::ALARM_MODE_ACTIVE_LIST:
	$cond = "mtype >= $severity AND go = 0";
	if ($masktest) $cond .= " AND ($masktest)";
	
	$columns = array("messageid", "mtype", "name", "cond", "come");
	$selopts = array(
	    "condition" => $cond,
	    "order" => "mtype DESC, come DESC"
	);

	$query = $this->db->SelectRequest("messagelog", $columns, $selopts);
     break;
     case READER::ALARM_MODE_FULL_LIST:
        if ($from!==false) $from = $this->ImportUnixTime($from);
	if ($to!==false) $to = $this->ImportUnixTime($to);
	
	if (($from===false)&&($to===false)) {
	    $test = false;
	} else if ($from === false) {
            $test = "(come < $to)";
	} else if ($to === false) {
            $test = "((go = 0) OR (go > $from))";
	} else {
            $test = "(come BETWEEN $from and $to) OR ((come < $from) AND ((go = 0) OR (go > $from)))";
	}
	
	if ($test) $cond = "(mtype >= $severity) AND ($test)";
	else $cond = "(mtype >= $severity)";
	
	if ($masktest) $cond .= " AND ($masktest)";
	
	$columns = array("messageid", "mtype", "name", "cond", "come", "go");
	$selopts = array(
	    "condition" => $cond,
	    "order" => "mtype DESC, come DESC"
	);
	if ($limit) {
	    $selopts['limit'] = abs($limit);
	    if ($limit > 0) {
		$selopts['order'] = "come ASC";
	    } else {
		$selopts['order'] = "come DESC";
	    }
	} else {
	    $selopts['order'] = "come ASC";
	}

	$query = $this->db->SelectRequest("messagelog", $columns, $selopts);
     break;
     default:
        if ($from!==false) $from = $this->ImportUnixTime($from);
	if ($to!==false) $to = $this->ImportUnixTime($to);
	
	if (($from===false)&&($to===false)) {
	    $test = false;
	} else if ($from === false) {
            $test = "(come < $to)";
	} else if ($to === false) {
            $test = "((go = 0) OR (go > $from))";
	} else {
            $test = "(come BETWEEN $from and $to) OR ((come < $from) AND ((go = 0) OR (go > $from)))";
	}
	

	if ($test) $cond = "(mtype >= $severity) AND ($test)";
	else $cond = "(mtype >= $severity)";
	if ($masktest) $cond .= " AND ($masktest)";

        $columns = "messageid, MAX(mtype) AS mtype, MIN(come) AS come, MAX(go) AS go, COUNT(lid) AS count, MIN(go) AS notactive";
	$selopts = array(
	    "condition" => $cond,
	    "group" => "messageid"
	);
	
	$subquery = $this->db->SelectRequest("messagelog", $columns, $selopts);

	$columns = "result.*, messages.name";
	$selopts = array(
	    "condition" => "messages.messageid = result.messageid",
	    "order" => "mtype DESC, come ASC"
	);

	$query = $this->db->SelectRequest("($subquery) AS result, messages", $columns, $selopts);
    }
    
//    echo $query . "\n\n\n";
//    exit;
    $stmt = $this->db->Prepare($query);

    return new ZEUSAlarms($this, $stmt);
 }

 
 function RemoveData(LOGGROUP $grp, $time, $to = 0) {
    if ($to) {
	$this->db->Query("DELETE FROM " . $grp->table . " WHERE ts BETWEEN " . $this->ImportUnixTime($time) . " and " . $this->ImportUnixTime($to));
    } else {
	$dbtime = floor($this->ImportUnixTime($time));
	$this->db->Query("DELETE FROM " . $grp->table . " WHERE ts BETWEEN " . $dbtime . " and " . ($dbtime + 1));
    }
 }

 function PushData(LOGGROUP $grp, $time, $data) {
    $dbtime = $this->ImportUnixTime($time);
    $size = sizeof($data['values']);
    
    $res = "";
    for ($i=$size;$i>0;$i--) $res .= pack("d", $data['values'][$i-1]);
    $res .= pack("V", $size);
    $blob = strrev($res);

/*    
    $kanz=(strlen($blob)-4)/8;
    $bform='d'.$kanz.'ist/Vlen';
    $blob = unpack($bform,strrev($blob));

    $res = array();
    for ($i=0;$i<$kanz;$i++) {
	if (($kanz > 1)||(isset($blob["ist".($kanz-$i)]))) 
	    $res[$i]=$blob["ist".($kanz-$i)];
	else 
	    $res[$i]=$blob["ist"];
    }

//    print date("c", $time) . ".  " . $res[7] . " = " . $data['values'][7] . "\n";
    
*/

    $dst = $this->db->Prepare("INSERT INTO " . $grp->table . " (ts,data) VALUES(?,?)");
    $dst->bindParam(1, $dbtime);
    $dst->bindParam(2, $blob, PDO::PARAM_LOB);
    $dst->execute();

 }
 
 function Backup(&$binfo, $flags = 0) {
     global $ADEI_ROOTDIR;
     global $BACKUP_DB;

    if (is_array($dbinfo)) {
	$dbinfo = &$binfo;
    } else {
	$dbinfo = $BACKUP_DB;
	$dbinfo['database'] = $binfo;
    }	

    $lock = new LOCK("backup." . $this->srvid);
    $lock->Lock(LOCK::BLOCK);
    
    try {
	$zeus  = new ZEUS($dbinfo);
    } catch (ADEIException $e) {
	if ($flags&READER::BACKUP_FULL) {
	    $tmpinfo = $dbinfo;
	    unset($tmpinfo['database']);

	    $zeus = new ZEUS($tmpinfo);
	    $dblist = $zeus->db->ShowDatabases();
	    
	    foreach ($dblist as $row) {
		if (strtolower($row[0]) == strtolower($dbinfo['database'])) {
		    throw new ADEIException(translate("Broken or invalid database is specified for backup"));
		}
	    }

	    if ((strtolower($dbinfo['driver']) == "mysql")&&(strtolower($this->server['driver']) == "mysql")) {
		$cli = Database::GetConnectionString($this->server);
		$srv = Database::GetConnectionString($dbinfo);

		$zeus->db->CreateDatabase($dbinfo['database']);
		@system("mysqldump --compact $cli | mysql $srv", $res);
		if ($res) throw new ADEIException(translate("The initialization of the backup database is failed."));
		
		$zeus = new ZEUS($dbinfo);
	    } elseif (strtolower($dbinfo['driver']) == "mysql") {
		$sql = file_get_contents("$ADEI_ROOTDIR/sql/zeus71.sql");
		$zeus->db->CreateDatabase($dbinfo['database']);
		$zeus = new ZEUS($dbinfo);
		$zeus->db->Query($sql);
	    } else throw new ADEIException(translate("The initial backup database should setup manualy. The auto mode is only works for the MySQL at the moment"));
	} else throw $e;
    }

    $src_list = $this->GetGroupList(REQUEST::NEED_INFO|REQUEST::NEED_ITEMINFO);

    if (($this->char_mode)&&(!$zeus->char_mode)) $pack = 1;
    else $pack = 0;

    $zeus->db->Query("SET SQL_MODE=NO_AUTO_VALUE_ON_ZERO");

    if ($flags&READER::BACKUP_FULL) {
	$tables = array("config", "blocks", "groups", "items", "g2id", "marker", "masks", "messages", "loginfo", "opc");
	$blobs = array(
	    "config" => array(3),	// old ZEUS versions using invalid VARCHAR definition resulting in crap should check
	    "items" => array(3),
	    "masks" => array(3)
	);
	
	if ($this->opts->Get("lclb_is_varchar", false)) {
	    unset($blobs["config"]);
	}
	
	foreach ($tables as $table) {
	    $src = $this->db->Prepare("SELECT * FROM $table");
	    $src->execute();

	    $width = $src->columnCount();

	    $full_query="TRUNCATE TABLE $table;";

	    if ($pack) $cur_blobs = $blobs[$table];
	    else $cur_blobs = false;

	    if ($width > 1) $query = "?" . str_repeat(", ?", $width - 1);
	    else $query = "?";

	    $dst = $zeus->db->Prepare("INSERT INTO $table VALUES ($query)");
	    $row = array();
	    for ($i = 0; $i < $width; $i++) {
		$row[$i] = false;
		if (($cur_blobs)&&(in_array($i, $cur_blobs))) {
		    $src->bindColumn($i + 1, $row[$i], PDO::PARAM_LOB);
	    	    $dst->bindParam($i + 1, $row[$i], PDO::PARAM_LOB);
		} else {
		    $src->bindColumn($i + 1, $row[$i]);
	    	    $dst->bindParam($i + 1, $row[$i]);
	    	}
	    }
	    
	    $zeus->db->Query($full_query);
	    while ($src->fetch(PDO::FETCH_BOUND)) {
	    	if ($cur_blobs) {
	    	    foreach ($cur_blobs as $i) {
	    		$row[$i] = pack("H*", $row[$i]);
	    	    }
	    	}
		$dst->execute();
	    }
/*
	    $rows = $src->fetchAll(PDO::FETCH_NUM);
	    foreach ($rows as $row) {
		$query = "INSERT INTO $table VALUES(";
		foreach ($row as $i=>$value) {
		    if (($cur_blobs)&&(in_array($i, $cur_blobs))) {
#			echo "Value($table, $i): " . $value . "!\n";
//			$value = pack("H*", $value);
		        if ($i) $query .= ", 0x$value";
			else $query .= "0x$value";
		    } else {
		        if ($i) $query .= ", \"$value\"";
			else $query .= "\"$value\"";
		    }
		}
		$full_query .= "$query); ";
	    }
//	    echo $full_query . "\n";

	    $zeus->db->Query($full_query);
*/
	}


	
	$stmt = $zeus->db->Query("SHOW TABLES LIKE 'log%'");
	$log_tables_result = $stmt->fetchAll(PDO::FETCH_NUM); // loginfo additional
	$log_tables = array();
	foreach ($log_tables_result as $i=>$table) {
	    $log_tables[$i] = $table[0];
	}
	
	//print_r($log_tables);
	foreach ($src_list as &$grp) {
	    $table = "log" . $grp['gid'];
	    if (!in_array($table, $log_tables)) {
		$zeus->db->Query("CREATE TABLE $table (lid bigint(20) NOT NULL auto_increment PRIMARY KEY, ts double NOT NULL, data BLOB, INDEX(ts))");
	    }
	}
    }

    $dst_list = $zeus->GetGroupList(REQUEST::NEED_INFO|REQUEST::NEED_ITEMINFO);
    if ((sizeof($src_list) != sizeof($dst_list))||(sizeof($src_list['items'])!=sizeof($dst_list['items']))) {
	// or compare names (items by content not lenght, 'name', 'comment')
	throw new ADEIException(translate("The configuration of the current backup not in sync with the data source"));
    }

    /*$tables = array(
    	"messagelog" => "lid", 
	"syslog" => "lid"
    );*/
    $tables = array(
	"messagelog" => "lid"
    );
    $blobs = array();


    $list = $this->req->GetGroups();
    foreach ($list as $greq) {
	$grp = $greq->CreateGroup($this);

/*	
	// Support limited backup if time limiting options are set
	if ($dst_list[$grp->gid]['__internal__']['last']) $from = $dst_list[$grp->gid]['__internal__']['last'];
	else $from = false;

	if ($src_list[$grp->gid]['__internal__']['last']) $to = $src_list[$grp->gid]['__internal__']['last'];
	else $to = false;

	if (($to === false)||(($from !== false)&&(abs(round($from) - round($to))<2))) continue;

	if (($from !== false)&&($from > $to))
	    throw new ADEIException(translate("The backup had newer data than the source does"));
*/
	$stmt = $zeus->db->Query("SELECT MAX(lid), MAX(ts) FROM " . $grp->table);
	$row = $stmt->fetch(PDO::FETCH_NUM);
	if (($row)&&(isset($row[0]))) {
	    $first_lid = $row[0];
	    $first_ts = $row[1];
	} else $first_lid = false;

	if ($first_lid !== false) {
	    $stmt = $this->db->Query("SELECT MAX(lid) AS lid FROM " . $grp->table);
	    $row = $stmt->fetch(PDO::FETCH_BOTH); // FETCH_NUM looks to be corrupted with MSSQL/ODBC
	    if (($row)&&(isset($row['lid']))) $remote_last_lid = $row['lid'];
	    else $remote_last_lid = false;

	    if (($remote_last_lid)&&($remote_last_lid < $first_lid)) {
		$stmt = $this->db->Query("SELECT MIN(lid) AS lid FROM " . $grp->table . " WHERE ts > " . $first_ts);
		$row = $stmt->fetch(PDO::FETCH_NUM);
		if (($row)&&(isset($row['lid']))) $diff_lid = $first_lid + 1 - $row['lid'];
		else return;
	    } else $diff_lid = false;
	}
	
	try {
	    if ($first_lid === false) {
	        $src = $this->db->Prepare("SELECT lid,ts,data FROM " . $grp->table . " ORDER by lid ASC");
	    } else {
		if ($diff_lid === false) {
	    	    $src = $this->db->Prepare("SELECT lid,ts,data FROM " . $grp->table . " WHERE lid > " . $first_lid . " ORDER by lid ASC");
		} else {
	    	    $src = $this->db->Prepare("SELECT (lid + $diff_lid) AS lid, ts, data FROM " . $grp->table . " WHERE ts > " . $first_ts . " ORDER by lid ASC");
	    	}
	    }
/*
	    if ($from === false) {
	        $src = $this->db->Prepare("SELECT lid,ts,data FROM " . $grp->table . " ORDER by lid ASC");
	    } else {
	        $src = $this->db->Prepare("SELECT lid,ts,data FROM " . $grp->table . " WHERE ts > " . $from . " ORDER by lid ASC");
	    }
*/
	    if (!$src->execute()) {
		$einfo = $dst->errorInfo();
		throw new ADEIException("Failure while backing up group " . $grp->gid . ". SQL Error: " . $einfo[0] . ", Driver Error: " . $einfo[1] . ", Message: " . $einfo[2]);
	    }
	    $src->bindColumn(1, $lid);
	    $src->bindColumn(2, $ts);
	    $src->bindColumn(3, $data, PDO::PARAM_LOB);
	    
	    $dst = $zeus->db->Prepare("INSERT INTO " . $grp->table . " (lid,ts,data) VALUES (?, ?, ?)");
	    $dst->bindParam(1, $lid);
	    $dst->bindParam(2, $ts);
	    $dst->bindParam(3, $data, PDO::PARAM_LOB);
	
	    while ($src->fetch(PDO::FETCH_BOUND)) {
//		echo "$lid, $ts\n";
		if ($pack) $data = pack("H*", $data);

		if (!$dst->execute()) {
		    $einfo = $dst->errorInfo();
		    throw new ADEIException("Failure while backing up group " . $grp->gid . ", item " . $lid . "(" . date("r", ceil($this->ExportUnixTime($ts))) . ") SQL Error: " . $einfo[0] . ", Driver Error: " . $einfo[1] . ", Message: " . $einfo[2]);
		}
	    }
	} catch(PDOException $e) {
	    throw new ADEIException(translate("SQL request is failed with error") . ": " . $e->getMessage(), $e->getCode());
	}
    }

    
    foreach ($tables as $table => $id) {
	if ($pack) $cur_blobs = $blobs[$table];
	else $cur_blobs = false;

	$stmt = $zeus->db->Query("SELECT MAX($id) FROM " . $table);
	$row = $stmt->fetch(PDO::FETCH_NUM);
	if (($row)&&(isset($row[0]))) $first_lid = $row[0];
	else $first_lid = false;
	
	if ($first_lid === false) {	
	    $src = $this->db->Prepare("SELECT * FROM $table ORDER BY $id ASC");
	} else {
	    $src = $this->db->Prepare("SELECT * FROM $table WHERE $id > $first_lid ORDER BY $id ASC");
	}
	$src->execute();
	while ($row = $src->fetch(PDO::FETCH_NUM)) {
	    $query = "INSERT INTO $table VALUES(";
	    foreach ($row as $i=>$value) {
	        if (($cur_blobs)&&(in_array($i, $cur_blobs))) {
		    if ($i) $query .= ", 0x$value";
		    else $query .= "0x$value";
		} else {
		    if ($i) $query .= ", \"$value\"";
		    else $query .= "\"$value\"";
		}
	    }
	    $zeus->db->Query("$query)");
	}
    }

    $lock->UnLock();    
    unset($lock);

 }
}

?>