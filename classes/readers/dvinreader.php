<?php
class DVINReader extends DBReader {
 function GetDVINGroups() {
    $monitors = array();
    $groups = array();

    $sth = $this->db->Query("SELECT MonID, Name  FROM DVIN_MONITORS", DATABASE::FETCH_NUM);
    while ($row = $sth->fetch(PDO::FETCH_NUM)) {
	$monitors[$row[0]] = $row[1];
    }
	
    $sth = $this->db->Query("SELECT GrID, MonID, Name  FROM DVIN_GROUPS", DATABASE::FETCH_NUM);
    while ($row = $sth->fetch(PDO::FETCH_NUM)) {
        if ($monitors[$row[1]]) {
	    if (preg_match("/Monitor|Telescope/", $monitors[$row[1]])) {
		$monitor = preg_replace(
		    array("/\bOne\b/", "/\bTwo\b/", "/[^A-Z()\d]/"), 
		    array("1", "2", ""), 
		    $monitors[$row[1]]
		);
	    } else {
		$monitor = $monitors[$row[1]];
	    }
		
	    $groups[$row[0]] =  $monitor . ", " . $row[2];
	} else {
	    $groups[$row[0]] = $row[2];
	}
    }
    return $groups;
 }
 
 function GetDVINItems() {
    $names = array();

 }

 function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    $names = array();

    $items = parent::GetItemList($grp, $mask, $flags);
    if (!$items) return $items;
    
    try {
	$groups = $this->GetDVINGroups();
    
	$sth = $this->db->Query("SELECT ColumnName, GrID, Name  FROM DVIN_TIMESERIES", DATABASE::FETCH_NUM);
	if ($sth) {
	    while ($row = $sth->fetch(PDO::FETCH_NUM)) {
		if ($groups[$row[1]]) {
		    $group = $groups[$row[1]];
		    $names[$row[0]] = $group . ": " . $row[2];
		} else {
		    $names[$row[0]] = $row[2];
		}
	    }
	}
    } catch (ADEIException $ae) {
    }
    
    foreach ($items as &$item) {
	if ($names[$item['column']]) {
            $item['name'] = $names[$item['column']];
	}
    }

    if ($flags&REQUEST::NEED_AXISINFO) {
	$this->AddAxisInfo($grp, $items);
    }

    return $items;
 }

 function GetMaskList(LOGGROUP $grp = NULL, $flags = 0) {
    $list = array();

    try {
	$gids = array();
	
	$groups = $this->GetDVINGroups();

	$sth = $this->db->Query("SELECT ColumnName, GrID  FROM DVIN_TIMESERIES", DATABASE::FETCH_NUM);
        while ($row = $sth->fetch(PDO::FETCH_NUM)) {
	    $gids[$row[0]] = $row[1];
	}
	
	$items = parent::GetItemList($grp, $mask, $flags);
	foreach ($items as $iid => $item) {
	    $gid = $gids[$item['column']];
	    if (is_numeric($gid)) {
		if (!isset($list[$gid])) {
		    if ($groups[$gid]) $name = $groups[$gid];
		    else $name = "Mask $gid";
		    $list[$gid] = array(
			'id' => "maskid$gid",
			'name' => $name
		    );
	    	    if ($flags&REQUEST::NEED_INFO) {
			$list[$gid]['mask'] = "$iid";
		    }
		} else if ($flags&REQUEST::NEED_INFO) {
		    $list[$gid]['mask'] .= ",$iid";
		}
	    }
	}
    } catch (ADEIException $ae) {
    }


/*
    $resp = $this->db->Query("SELECT maskid, name, mask FROM masks WHERE gid=" . $grp->gid);
    foreach ($resp as $row) {
	if (!preg_match("/[\w\d]/", $row['name'])) $row['name'] = _("No name");

	$id = "maskid" . $row['maskid'];
	
	$list[$id] = array(
	    'id' => $id,
	    'name' => $row['name']
	);
	
	if ($flags&REQUEST::NEED_INFO) {
	    $list[$id]['mask'] = implode(",", $this->ParseMask($row['mask']));
	}
    }
*/

    return array_merge(
	parent::GetMaskList($grp, $flags),
	$list
    );

 }
}
?>