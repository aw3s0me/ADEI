<?php

## Configuration

$config = array (
    "db_server" => "katrin", 
    "db_name" => "hauptspektrometer"
);
## We expecting GMT timezone, if it is not the case please specify 
## valid timezone
#$timezone = "Europe/Berlin";

## By default, we are inserting everything found in CSV file, if you need
## only to add missing values, please specify the writeout interval in 
## seconds (i.e. if the gap between two records in the current database
## exceed this value, then it would be filled from CSV file).
#$frequency = 60;

## Try to clean invalid values from database (only for the data we have)
#$clean = 1;


require("../adei.php");

$adei_timezone = date_default_timezone_get();

function Find(&$ginfo, $sn) {
    $res = array();
    foreach (array_keys($ginfo) as $gid) {
	$size = sizeof($ginfo[$gid]['items']);
	for ($i=0;$i<$size;$i++) {
	    if (strstr($ginfo[$gid]['items'][$i]['name'], $sn)) {
		array_push($res, array(
		    "gid" => $gid,
		    "i" => $i
		));
	    }
	}
    }
    return $res;
}



if ((!isset($_SERVER['argc']))||($_SERVER['argc']<2)) {
    echo translate("You should supply CSV file as an argument") . "\n";
    exit;
}

$f = fopen($_SERVER['argv'][1],"r");
if (!$f) {
    echo translate("File \"%s\" is could not be openned", $_SERVER['argv'][1]) . "\n";
    exit;
}

if ($clean) {
    fgets($f);
    $from = 0; $to = 0;
    while (!feof($f)) {
	$string = fgets($f);

	$pos = strpos($string, ",");
	if (!$pos) continue;
    
	if ($timezone) date_default_timezone_set($timezone);
	$time = strtotime(substr($string, 0, $pos));
        if ($timezone) date_default_timezone_set($adei_timezone);
    
	if (!$from) $from = $to = $time;
	else {
	    if ($time > $to) $to = $time;
	    elseif ($time < $from) $from = $time;
	}
    }

    rewind($f);
}


$req = new SOURCERequest($config);
$reader = $req->CreateReader(REQUEST::READER_FORBID_CACHEREADER);
$ginfo = $reader->GetGroupList(REQUEST::NEED_INFO|REQUEST::NEED_ITEMINFO);

foreach (array_keys($ginfo) as $gid) {
    $size = sizeof($ginfo[$gid]['items']);
    $groups[$gid] = array("items"=>false, "size"=>$size);
    for ($i=0;$i<$size;$i++) $groups[$gid][$i] = -1;
}


$namestr = fgets($f);
$names = split(',', preg_replace('/(^\s+|\s+$)/', "", $namestr));
unset($names[0]);


foreach ($names as $pos => $name) {
    $sn = strstr($name, "\\");
    if ($sn) $sn = substr($sn, 1);
    else $sn = $name;
    
    $res = Find($ginfo, $sn);
    if ((!sizeof($res))&&(preg_match("/IST(WERT)?$/", $sn))) {
	$sn = preg_replace("/[._]?IST(WERT)?$/", "", $sn);
	$res = Find($ginfo, $sn);
    }

    if ((!sizeof($res))&&(preg_match("/_/", $sn))) {
	$sn = preg_replace("/^[^_]+_/", "", $sn);
	$res = Find($ginfo, $sn);
    }
    
    switch(sizeof($res)) {
	case 0: 
	    echo translate("Missing column   : %u(%s)", $pos, $name) . "\n";
	break;
	case 1:
	break;
	default:
	    echo translate("Multimatch column: %u(%s):", $pos, $name);
	    $output = false;
	    foreach ($res as $r) {
		if ($output) echo ", ";
		else $output = true;
		
		echo $r['gid'] . "(" . $ginfo[$r['gid']]['items'][$r['i']]['name'] . ")";
	    }
	    echo "\n";
    }
    
    foreach ($res as $r) {
	$groups[$r['gid']][$r['i']] = $pos;
	$groups[$r['gid']]['items'] = true;
    }
}

echo "\n";

$output = false;
foreach ($groups as $gid=>$group) {
    if (!$group['items']) {
	if ($output) echo ", ";
	else {
	    echo translate("Missing groups: ");
	    $output = true;
	}
	
	$gname = preg_replace('/(^\s+|\s+$)/', "", $ginfo[$gid]['name']);
	echo $gid . "(" . $gname . ")";
    } 
}

if ($output) echo "\n";

foreach ($groups as $gid=>$group) {
    if ($group['items']) {
	$output = false;
	for ($i=0;$i<$group['size'];$i++) {
	    if ($group[$i] < 0) {
		if ($output) echo ", ";
		else {
		    $gname = preg_replace('/(^\s+|\s+$)/', "", $ginfo[$gid]['name']);
		    echo translate("Group %u(%s) missing items: ", $gid, $gname);
		    $output = true;
		}
		echo $ginfo[$gid]['items'][$i]['name'];
	    }
	}
	
	if ($output) echo "\n";
    }
}


echo translate("Procceed[Y/n]? ");
$answer = fgets(STDIN);
if (preg_match("/[nN]/", $answer)) exit;


foreach ($groups as $gid=>&$group) {
    if ($group['items']) {
	$gconfig = $config;
	$gconfig["db_group"] = $gid;
	$lgs[$gid] = $reader->CreateGroup($gconfig);
	$min[$gid] = 0;
	
	if ($clean) {	
	    $reader->Clean($lgs[$gid], $from - $frequency - 1, $to + $frequency + 1);
	}
    }
}

while (!feof($f)) {
    $str = fgets($f);
    if (!$str) break;
    
    $values = split(',', preg_replace('/(^\s+|\s+$)/', "", $str));
    
    if ($timezone) date_default_timezone_set($timezone);
    $time = strtotime($values[0]);
    if ($timezone) date_default_timezone_set($adei_timezone);
    
#    print $values[0] . " = " . date("c", $time) . "\n";
#    echo $values[126] . "\n";
    unset($values[0]);
    
    foreach ($lgs as $gid=>&$lg) {
	if ($frequency) {
	    $data = $reader->GetData($lg, max($min[$gid], $time - $frequency), $time + $frequency);
	    $data->rewind();
	
	    if ($data->valid()) continue;
	}
	
	$min[$gid] = $time + 1;

	$data = array("values"=>array());

	for ($i=0;$i<$groups[$gid]["size"];$i++) {
	    if ($groups[$gid][$i])
		$data["values"][$i] = $values[$groups[$gid][$i]];
	    else
		$data["values"][$i] = 0;
	}
	
	$reader->PushData($lg, $time, $data);
    }
}


?>