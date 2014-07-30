<?php

class TANGOReader extends READER {
 function __construct(&$props) {
    parent::__construct($props);    
    $this->db = new DATABASE($this->server);
 }

 function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
    $req_cols = "ID AS gid, full_name AS name, writable";
    
    if ($grp)
	$valres = $this->db->Query("SELECT $req_cols FROM adt WHERE ID=" . $grp->gid);
    else
	$valres = $this->db->Query("SELECT $req_cols FROM adt");

    $res =  array();
    foreach ($valres as $row) {
        array_push($res, $row);
    }

    foreach ($res as $row) {
	$gid = $row['gid'];
	$groups[$gid] = $row;
	
	if ($flags&REQUEST::NEED_INFO) {
	    $ginfo = array("db_group" => $gid);
	    $grzeus = $this->CreateGroup($ginfo);

	    if ($flags&REQUEST::NEED_ITEMINFO) {
		$groups[$gid]['items'] = $this->GetItemList($grzeus, NULL, $flags);
	    }
	    
	    $req = "MIN(time), MAX(time)";
	    if ($flags&REQUEST::NEED_COUNT) 
		$req .= ", COUNT(time)";

            try {
	        $valres = $this->db->Query("SELECT $req FROM " . "att_$gid", DATABASE::FETCH_NUM);
	        $vals = $valres->fetch(PDO::FETCH_NUM);
	        $valres = NULL;
	    } catch (ADEIException $pe) {
	        continue;
	    }

	    
	    if ($this->req) {
	        $opts = $this->req->GetGroupOptions($grzeus);
		list($start_date, $end_date) = $opts->GetDateLimit();
	    } else {
		$start_date = false; $end_date = false;
	    }

	    $groups[$gid]['__internal__'] = array();

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
	}

    }

    return $grp?$groups[$grp->gid]:$groups;

 }

 function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    if ($flags&REQUEST::ONLY_AXISINFO) {
	if (!$this->req->GetGroupOptions($grp, "axis")) return array();
    }
    
    $grp = $this->CheckGroup($grp, $flags);
    if (!$mask) $mask = $this->CreateMask($grp, $info = NULL, $flags);

    $res = $this->db->Query("SELECT facility, full_name, writable FROM adt WHERE ID=" . $grp->gid);
    $row = $res->fetch();
/*
    $uid = $this->opts->Get('channel_uids', false);

    if ($uid) {
	$valres = $this->db->Query("SELECT name FROM adt WHERE id=" . $grp->gid, DATABASE::FETCH_NUM);
	$vals = $valres->fetch(PDO::FETCH_NUM);
	$valres = NULL;
        $name = $vals[0];

	if (($uid === true)||(preg_match($uid, $name))) {
		$items[$rpos]["uid"] = $items[$rpos]["name"];
	}
    }
*/
    $items = array(
        0 => array(
            'id' => 0,
            'name' => 'value',
            'uid' => $row['facility'] . "/" . $row['full_name']
        )
    );

    if ($row['writable'] > 0) {
	$items[1] = array(
            'id' => 1,
            'name' => 'setpoint'
        );
    }

    return $items;
 }

 function GetRawData(LOGGROUP $grp = NULL, $from = 0, $to = 0, DATAFilter $filter = NULL, &$filter_data = NULL) {
    $grp = $this->CheckGroup($grp);

    $res = $this->db->Query("SELECT writable FROM adt WHERE ID=" . $grp->gid);
    $row = $res->fetch();
    
    if ($row['writable'] > 0) $writable = 1;
    else $writable = 0;

    if ((!$from)||(!$to)) {
        $ivl = $this->CreateInterval($grp);
	$ivl->Limit($from, $to);
	
	$from = $ivl->GetWindowStart();
	$to = $ivl->GetWindowEnd();
    }


    if ($filter) {
	$mask = $filter->GetItemMask();
	$resample = $filter->GetSamplingRate();
	$limit = $filter->GetVectorsLimit();

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

    if ((!$mask)||(!is_array($ids = $mask->GetIDs()))) {
        $ids = array(0, 1);
    }
    
    $data_request = "";
    if (in_array(0, $ids)) {
	if ($writable) {
	    $data_request .=  "{$this->db->col_quote}read_value{$this->db->col_quote}, ";
	} else {
	    $data_request .=  "{$this->db->col_quote}value{$this->db->col_quote}, ";
	}
    }

    if (($writable)&&(in_array(1, $ids))) {
	$data_request .=  "{$this->db->col_quote}write_value{$this->db->col_quote}, ";
    }

    $data_request .= "time";

    $selopts = array(
	"condition" => "{$this->db->col_quote}time{$this->db->col_quote} BETWEEN " . $this->db->SQLTime($from) . " and " . $this->db->SQLTime($to)
    );
    
    if ($resample) {
	$selopts['sampling'] = array(
	    "slicer" => $this->time_module->GetTimeSlicingFunction("{$this->db->col_quote}time{$this->db->col_quote}", $resample),
	    "selector" => "{$this->db->col_quote}time{$this->db->col_quote}"
	);
    }
    
    if ($limit) {
	$selopts['limit'] = abs($limit);
	if ($limit > 0) {
	    $selopts['order'] = "{$this->db->col_quote}time{$this->db->col_quote} ASC";
	} else {
	    $selopts['order'] = "{$this->db->col_quote}time{$this->db->col_quote} DESC";
	}
    } else {
	$selopts['order'] = "{$this->db->col_quote}time{$this->db->col_quote} ASC";
    }
    
    $query = $this->db->SelectRequest("att_" . $grp->gid, $data_request, $selopts);
    $stmt = $this->db->Prepare($query);

    return new DATABASEData($this, $stmt, DATABASEData::EMPTY_ON_ERROR);
 }
}



?>