<?php

class LOGGROUP {
 var $gid;	// Group identificator
 var $complex;	// Flag indicating if group is complex, set by reader

 function __construct(array &$info, READER $reader = NULL, $flags = 0) {
    if ($flags&REQUEST::CONTROL) {
	if (isset($info["control_group"])) $this->gid = $info["control_group"];
	else $this->gid = false;
    } else {
	if (isset($info["db_group"])) $this->gid = $info["db_group"];
	else $this->gid = false;
    }
    $this->complex = false;
 }

 function MarkComplex() {
    $this->complex = true;
 }
 
 function IsComplex() {
    return $this->complex;
 }

 function GetGroupID() {
    return $this->gid;
 }
 
 function GetProp() {
    return $this->gid;
 }
};


?>