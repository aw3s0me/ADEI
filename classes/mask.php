<?php

class MASK {
 var $id;	// MaskID
 var $ids;	// List of items
 
 var $reader_mark;

 function __construct(&$props = NULL, READER $reader = NULL, LOGGROUP $grp = NULL, $flags = 0) {
    if ($flags&REQUEST::CONTROL) $prop_name = "control_mask";
    else $prop_name = "db_mask";

    if (is_array($props)) {
	if (isset($props[$prop_name])) $mask = $props[$prop_name];
	else $mask=true;
    } else {
	$mask = $props;
    }
 
    if ($mask === true) {
	$this->ids = false;
	$this->id = "all";
    } else if (($mask===false)||(!strcmp($mask,"none"))) {
	$this->ids = array();
	$this->id = "none";
    } else if ((!strcmp($mask,"all"))||(strlen($mask)==0)) {
	$this->ids = false;
	$this->id = "all";
    } else {
	$this->ids = preg_split("/,/", $mask);
	if (count($this->ids)) {
	    $this->id = "custom";
	} else {
	    $this->ids = false;
	    $this->id = "all";
	} 
    }

    $this->reader_mark = false;
 }
 
 function SetIDs($ids, $id = "custom") {
    $this->ids = $ids;
    $this->id = $id;
 }
 
 function Check($id) {
    if (($this->ids===false)||(in_array("$id", $this->ids))) return true;
    return false;
 }

 function CheckStandard($id) {
    if ($this->ids===false) {
        if (is_numeric($id)) return true;
    } else {
        if (in_array("$id", $this->ids)) return true;
    }

    return false;
 }

 function Get($id) {
    if ($this->ids===false) return $id;
    return $this->ids[$id];
 }

 function GetIDs() {
    return $this->ids;
 }
 
 function GetProp() {
    if ($this->ids) return implode(",", $this->ids);
    else if ($this->ids === false) return "all";
    else return "";
 }
 
 function SetReaderMark($mark) {
    $this->reader_mark = $mark;
 }
 
 function GetReaderMark() {
    return $this->reader_mark;
 }
 
 function IsFull() {
    if ($this->ids === false) return true;
    return false;
 }
 
 function IsEmpty() {
    if ((is_array($this->ids))&&(!$this->ids)) return true;
    return false;
 }
 
 function IsCustom() {
    if (is_array($this->ids)) {
        foreach ($this->ids as $id) {
            if (!is_numeric($id)) return true;
        }
    }
    return false;
 }
 
 function Superpose(MASK $mask = NULL) {
    if ((!$mask)||($mask->IsFull())) return $this;
    else if ($this->IsFull()) return $mask;
    
    $res = array();
    foreach ($mask->ids as $id) {
	if (isset($this->ids[$id])) {
	    array_push($res, $this->ids[$id]);
	} else {
	    throw new ADEIException(translate("Invalid mask (%s) is passed for supperposing. The base mask is (%s)", $mask->GetProp(), $this->GetProp()));
	}
    }
    
    $mask = new MASK();
    $mask->SetIDs($res);
    return $mask;
 }
}
?>