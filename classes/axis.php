<?php

class GRAPHAxis implements Iterator {
 var $aid;
 var $title;
 var $mode;
 var $units;
 var $range;
 
 var $apos;		// Position in stack of axis
 var $anum;		// Number of channels in the stack
 
 var $channels;		// registered channels data
 var $chaninfo;		// registered channels information
 var $cur;		// Current channel (for iterating)

 const MODE_STD = 0;
 const MODE_LOG = 1;
 
 function __construct(array &$base = NULL, array &$user = NULL, $aid = false) {
    if ((!$aid)&&($aid !== 0)) {
	if ((isset($user["axis"]))&&(strlen($user["axis"])>0)) $aid = $user["axis"];
    }

    if (!$aid) {
	$this->aid = 0;
	$aprop = "axis";
    } else {    
	if (is_numeric($aid)) {
	    $this->aid = $aid;
	    $aprop = "axis$aid";
	} else {
	    $this->aid = $aid;
	    $aprop = $aid . "_axis";
	}
    }
    
    $this->title = $user[$aprop . "_name"]?$user[$aprop . "_name"]:$base["axis_name"];
    $this->units = $user[$aprop . "_units"]?$user[$aprop . "_units"]:$base["axis_units"];

    $mode = $user[$aprop . "_mode"]?$user[$aprop . "_mode"]:$base["axis_mode"];
    if ($mode) {
	if (is_numeric($mode)) $this->mode = $mode;
	else {
	    $name = "GRAPHAxis::MODE_" . strtoupper($mode);
	    if (defined($name)) $this->mode = constant($name);
	    else throw new ADEIException(translate("Unknown axis mode (%s) is specified", $mode));
	}
    } else {
	$this->mode = GRAPHAxis::MODE_STD;
    }

    $range = $user[$aprop . "_range"]?$user[$aprop . "_range"]:$base["axis_range"];
    if (($range)&&(preg_match("/(-?[^:]+):(-?[^:]+)/", $range, $m))) {
	$this->range = array($m[1], $m[2]);
    } else if ((1)||($aid === false)) { /*DS*/
	$ivl = new INTERVAL($user);
	if ($ivl->y_min < $ivl->y_max) {
	    $this->range = array($ivl->y_min, $ivl->y_max);
	} else {
	    $this->range = false;
	}
    } else {
	$this->range = false;
    }
    
    $this->channels = array();
    $this->chaninfo = array();
    $this->apos = false;
    $this->anum = false;
 }
 
 function SetPosition($pos, $num = false) {
    $this->apos = $pos;
    $this->anum = $num;
 }

 function GetPosition() {
    return $this->apos;
 }

 function SetRange($min, $max) {
    if (($min)||($max)) {
	$this->range = array($min, $max);
    } else {
	$this->range = false;
    }
 }
 
 function GetID() {
    return $this->aid;
 }
 
 function GetRange() {
    return $this->range;
 }
 
 function GetMode() {
    return $this->mode;
 }
 
 function IsLogarithmic() {
    return ($this->mode === GRAPHAxis::MODE_LOG);
 }

 function GetTitle() {
    if ($this->title) {
	if ($this->units) return "{$this->title} ({$this->units})";
	else return $this->title;
    } else if ($this->units) {
	return $this->units;
    } else if ($this->aid) {
	if (is_numeric($this->aid)) {
	    return translate("axis %d", $this->aid);
	} else {
	    return $this->aid;
	}
    } else {
	return "";
    }
 }
  
 function GetName() {
    if ($this->title) return $this->title;
    else if ($this->aid) return $this->aid;
    else return translate("default");
 }
 
 function GetColor() {
    global $AXES_COLORS;

    if (sizeof($AXES_COLORS)) {
	$color = $AXES_COLORS[$this->apos%sizeof($AXES_COLORS)][0];
    } else {
	$color = 0;
    }
        
    return $color;
 }
 
 function GetChannelColor($id) {
    global $AXES_COLORS;
    global $GRAPH_COLORS;
    
    if ((sizeof($AXES_COLORS))&&($this->anum > 1)) {
	$acid = $this->apos%sizeof($AXES_COLORS);
	$color = $AXES_COLORS[$acid][$id % sizeof($AXES_COLORS[$acid])];
    } else if (sizeof($GRAPH_COLORS)) {
	$color = $GRAPH_COLORS[$id%sizeof($GRAPH_COLORS)];
    } else {
	$color = 0;
    }
    return $color;
 }

 function GetChannelProperty($id, $prop, $default = false) {
    global $GRAPH_LINE_WEIGHT;
    global $GRAPH_ACCURACY_MARKS_COLOR;
    global $GRAPH_ACCURACY_MARKS_TYPE;
    global $GRAPH_ACCURACY_MARKS_SIZE;

    if (isset($this->chaninfo[$prop])) return $this->chaninfo[$prop];
    
    switch ($prop) {
     case "weight":
        if ($GRAPH_LINE_WEIGHT) return $GRAPH_LINE_WEIGHT;
     break;
     case "mark_type":
	if ($GRAPH_ACCURACY_MARKS_TYPE) {
	    eval("\$mtype=$GRAPH_ACCURACY_MARKS_TYPE;");
	} else if ($default) {
	    return $default;
	} else {
	    $mtype = MARK_FILLEDCIRCLE;
	}
	return $mtype;
     break;
     case "mark_size":
        if ($GRAPH_ACCURACY_MARKS_SIZE) return $GRAPH_ACCURACY_MARKS_SIZE;
     break;
     case "mark_fill":
        if ((sizeof($this->channels) > 1)||($this->anum > 1)) return $this->GetChannelColor($id);
	else if ($GRAPH_ACCURACY_MARKS_COLOR) return $GRAPH_ACCURACY_MARKS_COLOR;
     break;
    }
    
    return $default;
 }

 function RegisterChannel(array &$time, array &$values, array &$iid) {
//    echo sizeof($time) . ", " . sizeof($values) . "\n";
    array_push($this->channels, array(&$time, &$values));
    array_push($this->chaninfo, $iid);
 }

 function rewind() {
    $this->cur = 0;
 }

 function valid() {
    return isset($this->channels[$this->cur]);
 }
 
 function current() {
    return $this->channels[$this->cur];
 }
 
 function key() {
    return $this->cur;
 }
 
 function next() {
    $this->cur++;
 }

 function Normalize() {
    /* DS: We could like to adjust (or set) range in order to prevent
    overdrawing of the screen */
 }
}

/*
class UIAxis extends GRAPHAxis {
}
*/

?>