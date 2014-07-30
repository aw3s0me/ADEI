<?php

class TESTData implements Iterator {
 var $period, $multiplier;
 var $from, $to;
 var $resample, $nextsample;
 var $pos;
 
 var $items;

 var $start;
 
 const DEFAULT_START = "January 1, 2000";
   
 public function __construct(TESTReader &$reader, OPTIONS &$opts, &$items, INTERVAL &$ivl, $resample) {
    $period = $opts->Get('period');

    $this->multiplier = $reader->GetFractionalMultiplier($period);
    $this->period = $this->multiplier * $period;
    $this->resample = $this->multiplier * $resample;
    
    $from = $this->multiplier * $ivl->GetWindowStart();
    $ifrom = ceil($from);
    $rem = $ifrom % $this->period;
    if ($rem) $this->from = $ifrom + ($this->period - $rem);
    else $this->from = $ifrom;

    $this->to = $this->multiplier * $ivl->GetWindowEnd();
    
    $limit = $ivl->GetItemLimit();
    if ($limit) {
	if ($limit > 0) {
	    $to = $this->from + $this->period * $limit;
	    if ($to < $this->to) $this->to = $to;
	} else {
	    $from = $this->to + $this->period * $limit;
	    if ($from > $this->from) $this->from = $from;
	}
    }
    
    $this->items = &$items;

    $limit = $opts->GetDateLimit(TESTData::DEFAULT_START, time());
    $this->start = $limit[0]; 
 }
 
 function doResample() {
    for ($next = $this->pos + $this->period;(($next < $this->to)&&($next < $this->nextsample));$next += $this->period)
	$this->pos = $next;

    $this->nextsample += $this->resample;
    if ($this->nextsample < $this->pos) {
	$add = ceil(($this->pos - $this->nextsample) / $this->resample);
	$this->nextsample += $add * $this->resample;
    }
 }
 
 function rewind() {
    $this->pos = $this->from;
    
    if ($this->resample) {
	$this->nextsample = $this->resample*ceil($this->pos / $this->resample);
	$this->doResample();
    }
 }

 function current() {
    $x = ($this->pos - $this->start) / $this->multiplier;
    $period = $this->period / $this->multiplier;

    $max = getrandmax();
    
    $values = array();
    foreach ($this->items as &$item) {
	$y = 0;

	if (eval($item['func'] . ";") === false)
	    throw new ADEIException(translate("Invalid function(%s) is supplied to TESTReader", $item['func']));

	if ($item['noise']) $y += 2*$item['noise']*((rand() - $max/2)/$max);

	array_push($values, $y);
    }
    
    return $values;
 }

 function key() {
    $res = $this->pos / $this->multiplier;
    if (is_float($res)) return sprintf("%.8f", $res);
    return $res;
 }

 function next() {
    $this->pos += $this->period;

    if ($this->resample) $this->doResample();
 }

 function valid() {
    return ($this->pos < $this->to);
 }

}



class TESTReader extends READER {
 var $cache;

 var $items;
 var $groups;
 
 function __construct(&$props) {
    parent::__construct($props);

    $this->items = array(
	array(
	    "name" => _("Line"),
	    "func" => '$y=0',
	    "noise" => 0.1
	),
	array(
	    "name" => _("Sinus"),
	    "func" => '$y=sin(deg2rad($x/$period))'
	),
	array(
	    "name" => _("Cosinus"),
	    "func" => '$y=cos(deg2rad($x/$period))'
	)
    );
    

    if ($this->dbname) {
	$list = $this->opts->ListConfiguredGroups();
    
        if ($list) {
	    $this->groups = array();
	
	    foreach ($list as $gid) {
		$this->groups[$gid] = array();
	    }
	} else {
	    $this->groups = array(
		'default' => array(
		    'name' => _("Default Group")
		)
	    );
	}
    }

/*
    if (sizeof($list) > 1) {
	array_push($this->groups, array(
	    'id' => "-1",
	    'name' => _("Combined Group"),
	    'complex' => true
	);
    }
*/
 }

 function GetFractionalMultiplier($period) {
    $curtime = time();
    $lastindex = 0;
    
    $multiplier = 1;
    
    while ($period != round($period)) {
	$period *= 10;
	$multiplier *= 10;
	if (($curtime * $multiplier) < $lastindex)  {
	    throw new ADEIException(translate("The specified period (%f) is too fractional, try use number what could be easier rounded", $period / $multiplier));
	} else {
	    $lastindex = $curtime * $period;
	}
    } 

    return $multiplier;
 }
 
 function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
    $groups = array();

    foreach ($this->groups as $gid => &$group) {
	if (($grp)&&(strcmp($grp->gid, $gid))) continue;
	
	if ($group['name']) $name = $group['name'];
	else $name = false;
	
	if ((!$name)||($flags&REQUEST::NEED_INFO)) {
	    if ($grp) {
		$grtest = $grp;
		$opts = $this->opts;
	    } else {
		$ginfo = array("db_group" => $gid);
		$grtest = $this->CreateGroup($ginfo);
		$opts = $this->req->GetGroupOptions($grtest);
	    }
	    
	    if (!$name) {
		$name = $opts->Get('name', $gid);
	    }
	}

	$groups[$gid] = array(
	    'gid' => $gid,
	    'name' => $name
	);
    
	if ($flags&REQUEST::NEED_INFO) {
	    $limit = $opts->GetDateLimit(TESTData::DEFAULT_START, time());
	    $groups[$gid]['first'] = $limit[0]; 
	    $groups[$gid]['last'] = $limit[1]; 

	    if ($flags&REQUEST::NEED_COUNT) {
	    	$period = $opts->Get('period');
		$groups[$gid]['records'] = ($groups[$gid]['last'] - $groups[$gid]['first']) / $period;
	    }

	    if ($flags&REQUEST::NEED_ITEMINFO) {
		$groups[$gid]['items'] = $this->GetItemList($grtest);
	    }
	}
    }

    if (($grp)&&(!$groups)) {
	throw new ADEIException(translate("Invalid group (%s) is specified", $grp->gid));
    }

    return $grp?$groups[$grp->gid]:$groups;
 }
 
 function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    if ($flags&REQUEST::ONLY_AXISINFO) {
	if (!$this->req->GetGroupOptions($grp, "axis")) return array();
    }

    $grp = $this->CheckGroup($grp, $flags);
    if (!$mask) $mask = $this->CreateMask($grp, $info=NULL, $flags);

    $items = array();

    $opts = $this->req->GetGroupOptions($grp);
    $items = $opts->Get('items', $this->items);

    $res = array();

    for ($i = 0, $pos = 0; isset($items[$i]); $i++) {
	if (!$mask->Check($i)) continue;

	$res[$pos] = array(
	    "id" => $i,
	    "name" =>  $items[$i]['name']
	);

	if ($items[$i]['uid']) {
	    $res[$pos]['uid'] = $items[$i]['uid'];
	}
	
	$pos++;
    }
    
    if ($flags&REQUEST::NEED_AXISINFO) {
	$this->AddAxisInfo($grp, $res);
    }
    return $res;
 }

 function GetRawData(LOGGROUP $grp = NULL, $from = 0, $to = 0, DATAFilter $filter = NULL, &$filter_data = NULL) {
    $grp = $this->CheckGroup($grp);

    $ivl = $this->CreateInterval($grp);
    $ivl->Limit($from, $to);

    if ($filter) {
	$mask = $filter->GetItemMask();
	$resample = $filter->GetSamplingRate();
	$limit = $filter->GetVectorsLimit();
	if ($limit) $ivl->SetItemLimit($limit);

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
    

    $opts = $this->req->GetGroupOptions($grp);

    $items = $opts->Get('items', $this->items);
    if (($mask)&&(is_array($ids = $mask->GetIDs()))) {
	$tmp = array();	
	foreach ($ids as $id) {
	    if ($id >= sizeof($items))
	        throw new ADEIException(translate("Invalid item mask is supplied. The ID:%d refers non-existing item.", $id));

	    array_push($tmp, $items[$id]);
	}
	$items = $tmp;
    }
    
    return new TESTData($this, $this->req->GetGroupOptions($grp), $items, $ivl, $resample);
 }

 function HaveData(LOGGROUP $grp = NULL, $from = 0, $to = 0) {
    $grp = $this->CheckGroup($grp);

    $ivl = $this->CreateInterval($grp);
    $ivl->Limit($from, $to);
    
    $period = $this->req->GetGroupOption('period', $grp);

    $from = $ivl->GetWindowStart();
    $to = $ivl->GetWindowEnd();
    
    if (($from - $to) > 2 * $period) return true;
    else {
	$multiplier = $this->GetFractionalMultiplier($period);
	$period *= $multiplier; 
	
	$ifrom = ceil($multiplier * $from);
	$rem = $ifrom % $period;
	if ($rem) $curfrom = $ifrom + ($period - $rem);
	else $curfrom = $ifrom;
	
	if ($curfrom < $multiplier * $to) return true;
    }
    
    return false;
 }
}

?>