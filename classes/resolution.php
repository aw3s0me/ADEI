<?php

class RESOLUTION {
 var $cfg;
 
 function __construct($minimal = 0, &$config = false) {
    global $ADEI_CACHE;
    
    if ($minimal) {
	$this->cfg = array();

	if ($config) $cfg = &$config;
	else $cfg = &$ADEI_CACHE;

	foreach ($cfg as $res) {
	    if ($res['res'] < $minimal) break;
	    else array_push($this->cfg, $res);
	}
	array_push($this->cfg, array("min" => -1, "res" => 0));
    } else {
	if ($config) {
	    $this->cfg = $config;
	    array_push($this->cfg, array("min" => -1, "res" => 0));
	} else $this->cfg = &$ADEI_CACHE;
    }
}

 function RAW() {
    return sizeof($this->cfg) - 1;
 }
 
 function Minimal() {
    return sizeof($this->cfg) - 2;
 }
 
 function Smaller($res) {
    if (isset($this->cfg[$res + 1])) return $res + 1;
    return false;
 }
 
 function Larger($res) {
    if ($res > 0) return $res - 1;
    return false;
 }

 function Get(INTERVAL &$ivl, $amount = 0) {
    if ($amount) {
	for ($res = 0; isset($this->cfg[$res]);$res++)
	    if ((!$this->cfg[$res]["res"])||(($ivl->window_size/$this->cfg[$res]["res"])>$amount)) {
		return $res;
	    }
    } else {
	for ($res = 0; isset($this->cfg[$res]);$res++)
	    if ($ivl->window_size > $this->cfg[$res]["min"]) {
		return $res;
	    }
    }
    

/* 
    for ($res = 0; isset($this->cfg[$res]);$res++)
	if (($ivl->window_size > $this->cfg[$res]["min"])&&((!$this->cfg[$res]["res"])||(($ivl->window_size/$this->cfg[$res]["res"])>$amount)))
	    return $res;

    $f=fopen("/tmp/xxx3", "w");
    fprintf($f, $ivl->window_size . "\n");
    fprintf($f, "%i %i %i\n", $ivl->window_size, (!$this->cfg[$res-1]["res"]), (($ivl->window_size/$this->cfg[$res-1]["res"])>$amount));
    fprintf($f, print_r($this->cfg[$res-1], true));
    fprintf($f, print_r($ivl, true));
    fclose($f);
*/

    if ($ivl->window_size <= 0)
    	throw new ADEIException(translate("Invalid WINDOW %s is specified", $ivl->window_start . '+' . $ivl->window_size));
    else    
	throw new ADEIException(translate("Internal Error in module RESOLUTION"));
 }

 function GetWindowSize($res) {
    return $this->cfg[$res]["res"];
 }
}

array_push($ADEI_CACHE, array("min" => -1, "res" => 0));

?>