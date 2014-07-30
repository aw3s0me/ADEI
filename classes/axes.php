<?php

class GRAPHAxes implements Iterator {
 var $req;

 var $default_axis;	// Default axis
 var $axes;		// Already created axes
 var $aids;		// Sequence of axis ids
 var $cur;		// Iterator
 
 var $axis_info;	// Configured axes
 
 const PRIVATE_AXES = 0x0001;
 const EXTRA_ONLY = 0x0002;
 
 function __construct(REQUEST $props = NULL, $flags = 0) {
    global $ADEI_AXES;

    if ($props) $this->req = $props;
    else $this->req = new REQUEST();

    $this->default_axis = false;
    $this->axes = array();
    $this->aids = array();

    if ($flags&GRAPHAxes::EXTRA_ONLY)
        $this->axis_info = array();
    else
        $this->axis_info = $ADEI_AXES;
 }
 
 function Add(GRAPHAxes $axes) {
        // This is right order to overwrite similar extra axes with main ones
    $this->axis_info = array_merge($axes->axis_info, $this->axis_info);
 }
 
 function GetAxesInfo() {
    return $this->axis_info;
 }

 function GetAxis($aid = false) {
    //echo "Axis: $aid\n";
    if ((!$aid)&&($aid !== 0)) {
	if (!$this->default_axis) {
	    $this->default_axis = new GRAPHAxis($this->axis_info[0], $this->req->props);
	}
	return $this->default_axis;
    } else {
        if (isset($this->axes[$aid])) {
	    return $this->axes[$aid];
	} else {
	    $axis = new GRAPHAxis($this->axis_info[$aid], $this->req->props, $aid);
	    $this->aids[] = $aid;
	    $this->axes[$aid] = $axis;
	}
    }
    return $axis;

 }
 
 function GetAxisByPosition($i = 0) {
    if ($this->default_axis) {
	if ($i) $i--;
	else return $this->default_axis;
    }
    
    if (!isset($this->aids[$i])) {
	throw new ADEIException(translate("Access to axis number %d is failed, only %d axis is registered", $i, $this->GetAxesNumber()));
    }
    
    return $this->axes[$this->aids[$i]];
 }
 
 function ListAxes() {
    if ($this->default_axis) return array_merge(false, $this->aids);
    return $this->aids;
 }
 
 function ListCustomAxes() {
    return $this->aids;
 }
 
 function GetAxesNumber() {
    if ($this->default_axis) return sizeof($this->aids) + 1;
    return sizeof($this->aids);
 }

 function rewind() {
    if ($this->default_axis) $this->cur = 0;
    else $this->cur = 1;
 }

 function valid() {
    return ((!$this->cur)||(isset($this->aids[$this->cur-1])));
 }
 
 function current() {
    if ($this->cur > 0) {
	return $this->axes[$this->aids[$this->cur - 1]];
    } else {
	return $this->default_axis;
    }
 }
 
 function key() {
    if ($this->default_axis) return $this->cur;
    else return $this->cur - 1;
 }
 
 function next() {
    ++$this->cur;
 }
 

 function Enumerate() {
    if ($this->default_axis) {
	$num = sizeof($this->aids) + 1;
    } else {
	$num = sizeof($this->aids);
    }
    
    $i = 0;
    foreach ($this as $axis) {
	$axis->SetPosition($i++, $num);
    }
 }
 
 function Normalize() {
    /* DS: We could like to split default axis in order to prevent overfill
    of the screen */
    
    foreach ($this as $axis) {
	$axis->Normalize();
    }
 }
}

?>