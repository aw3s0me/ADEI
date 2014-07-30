<?php

class CHANNELView extends VIEW {
 var $title = "Channel Info";
 
 function __construct(REQUEST $req  = NULL, $options) {
    parent::__construct($req, $options);
 }

 function ValueFormat($value, $length = 0) {
    $avalue = abs($value);

	// Fixing buggy 0
    if ($avalue < 1E-5) {
	if (($avalue * 1E+5) < $length) $value = 0;
    }
    
    if (($avalue > 999)||(($value)&&($avalue<0.01))) $format="%.2e";
    else $format="%.3g";
    
    return sprintf($format, $value);
 }
 
 function GetOptions() {
    return array(
        "input" => array("label" => _("Filter"), "id" => "filter", "type" => "text")
    );
 }
 
 function GetView() {
    $req = $this->req->CreateDataRequest();
    $filter = $req->GetProp("view_filter");

    $rdr = $req->CreateReader();
    $group = $rdr->CreateGroup();
    $caches = $rdr->CreateCacheSet($group, $mask);
    $axes = $rdr->CreateAxes();
    $caches->EnumerateAxes($axes, CACHESet::CACHE_ITEMS|REQUEST::NEED_AXISINFO);
//    $iv = $caches->CreateInterval($req);
    $iv = new INTERVAL($req->props);
    
    $rescfg = array(
        'limit' => 1,
        'amount' => 0,
    );

    $result = array();
    foreach ($caches as $key => $cachewrap) {
	$cache = $cachewrap->GetCache();
        $ivl = $cache->CreateInterval();
	$size = $cachewrap->GetWidth();
	
	$list = $cachewrap->GetItemList(REQUEST::NEED_AXISINFO);
	$points = $cachewrap->GetIntervals($ivl, $rescfg, CACHE::TRUNCATE_INTERVALS);
        $operation_info = $points->GetOperationInfo();

	$group_result = array();
        $itempositions = array_fill(0, $axes->GetAxesNumber(), 0);
        $have_points = false;
	foreach($points as $t => $v) {
	    for ($i=0;$i<$size;$i++) {
		$axis = $axes->GetAxis($list[$i]['axis']);
		$axispos = $axis->GetPosition();
		$itempos = $itempositions[$axispos]++;

                
                if (($filter)&&(!stristr($list[$i]['name'], $filter))) continue;
		array_push($group_result, array(
		    "id" => $i,
		    "name" => $list[$i]['name'],
			"interval" => array(
			    "from" => $ivl->GetWindowStart(),
			    "to" => $ivl->GetWindowEnd(),
			    "resolution" => $operation_info['resolution']
			),
			"value" => array(
			    "min" => $this->ValueFormat($v["min$i"]),
			    "max" => $this->ValueFormat($v["max$i"]),
			    "mean" => $this->ValueFormat($v["mean$i"])
			),
			"color" => $axis->GetChannelColor($itempos)
		));
	    }
	    $have_points = true;
	    break;
	}
	unset($points);

	if (!$have_points) {
	    for ($i=0;$i<$size;$i++) {
		$axis = $axes->GetAxis($list[$i]['axis']);
		$axispos = $axis->GetPosition();
		$itempos = $itempositions[$axispos]++;

                
                if (($filter)&&(!stristr($list[$i]['name'], $filter))) continue;

		array_push($group_result, array(
		    "id" => $i,
		    "name" => $list[$i]['name'],
			"interval" => array(
			    "from" => $ivl->GetWindowStart(),
			    "to" => $ivl->GetWindowEnd(),
			    "resolution" => $operation_info['resolution']
			),
			"color" => $axis->GetChannelColor($itempos)
		));
	    }
	}

        if ($group_result) {
	    array_push($result, array(
		"title" => $cachewrap->GetGroupTitle(),
		"results" => $group_result
	    ));
	}

    }

    return $result;
 }
};

?>