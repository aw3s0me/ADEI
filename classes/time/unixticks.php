<?php

/* DS: Rewrite with bcmath for 32bit system compatibility */
class UNIXTicks extends NULLReaderTime implements READERTimeInterface {
 var $correction;
 var $offset;           /* Value at start of unix epoch */
 var $mult;             /* Ticks per second */
 var $subsec_digits;    /* Number of digits in subsecond representation if applicable */

 function __construct(READER $reader = NULL, $opts = false) {
    parent::__construct($reader);

    $correction = 0;
    $offset = 0;
    $tps = 1;
    
    if ($opts) {
        if (is_int($opts['correction'])) $correction = $opts['correction'];
        if (is_int($opts['offset'])) $offset = $opts['offset'];
        if (is_int($opts['ticks_per_second'])) $tps = $opts['ticks_per_second'];
    }

    $this->offset = $offset - $correction;
    $this->mult = $tps;

    if ($tps > 1) {
        $this->subsec_digits = strlen($tps) - 1;
        if ("$tps" != ("1" . str_repeat('0', $this->subsec_digits))) $this->subsec_digits = false;
    } else {
        $this->subsec_digits = false;
    }

 }

 function ImportTime(DateTime $dt) {
    return floor($this->mult*$dt->format("U.u")) + $this->offset;
 }

 function ExportTime($db_time) {
    $ticks = $db_time - $this->offset;

    if ($ticks < 0) {
	$subsec = (-$ticks) % $this->mult;
	$timestamp =  ($ticks + $subsec) / $this->mult;
    } else {
	$subsec = $ticks % $this->mult;
	$timestamp =  ($ticks - $subsec) / $this->mult;
    }
	
    if ($subsec) {
        if ($this->subsec_digits) {
	    $len = strlen($subsec);
	    if ($len < $this->subsec_digits) $subsec = str_repeat('0', $this->subsec_digits - $len) . $subsec;
	} else {
	    $subsec = substr(sprintf("%F", $subsec / $this->mult), 2);
	}

        return new DateTime(strftime("%Y/%m/%d %H:%M:%S", $timestamp) . ".$subsec", $this->gmt_timezone);
    } else {
	return new DateTime("@$timestamp", $this->gmt_timezone);
    }
 }
 
 function GetTimeSlicingFunction($db_time_var, $width, $offset = 0) {
    if ($offset) {
	if (is_object($offset)) 
	    $off = $this->ImportTime($offset);
	else
	    $off = $this->ImportUnixTime($offset);

	return "($db_time_var - $off) / " . ($this->mult * $width);
    } 
    
    return "$db_time_var / " . ($this->mult * $width);
 }
}

?>