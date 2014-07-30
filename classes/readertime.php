<?php

interface READERTimeInterface {
 public function __construct(READER $reader = NULL, $opts = false);
 public function ImportTime(DateTime $dt);
 public function ExportTime($db_time);
 public function GetTimeSlicingFunction($db_time_var, $width, $offset = 0);
}


abstract class NULLReaderTime implements READERTimeInterface {
 var $gmt_timezone;

 public function __construct(READER $reader = NULL, $opts = false) {
    if ($reader) $this->gmt_timezone = $reader->gmt_timezone;
    else $this->gmt_timezone = new DateTimeZone("GMT");
 }

 public function ImportTime(DateTime $dt) {
    throw new ADEIException(translate("CLASS %s: Methods (%s) is not implemented", get_class($this),  __METHOD__));
 }

 public function ExportTime($db_time) {
    throw new ADEIException(translate("CLASS %s: Methods (%s) is not implemented", get_class($this),  __METHOD__));
 }

 public function GetTimeSlicingFunction($db_time_var, $width, $offset = 0) {
    throw new ADEIException(translate("CLASS %s: Methods (%s) is not implemented", get_class($this),  __METHOD__));
 }

 function ImportUnixTime($unix_time) {
    $itime = floor($unix_time);

    if ($itime==$unix_time) {
            // Time zone should be specified, esle "e" output is invalid
	return $this->ImportTime(new DateTime("@" . sprintf("%f", $itime), $this->gmt_timezone));
    } else {
	if (is_float($unix_time)) $subsec = strchr(sprintf("%F", $unix_time), '.');
	else $subsec = strchr($unix_time, '.');
	
	    // DS: Due to the bug in PHP (precision is limited to 10ns)
	if (strlen($subsec) > 9) $subsec = substr($subsec, 0, 9);
	return $this->ImportTime(new DateTime(strftime("%Y-%m-%dT%H:%M:%S", $itime) . $subsec, $this->gmt_timezone));
//	return $this->ImportTime(new DateTime(strftime("%Y/%m/%d %H:%M:%S", $itime) . $subsec, $this->gmt_timezone));
    }
 }
 
 function ExportUnixTime($db_time) {
    $dt = $this->ExportTime($db_time);
    return $dt->format("U.u");
 }
}

class READERTime extends NULLReaderTime implements READERTimeInterface {
 var $time_zone;
 var $time_format;
 
 public function __construct(READER $reader = NULL, $opts = false) {
    parent::__construct($reader, $opts);

    $this->time_format = $opts['format'];
    $this->time_zone = $opts['timezone'];
 }
 
 public function ImportTime(DateTime $dt) {
    $dt->setTimezone($this->time_zone);
    return $dt->format($this->time_format);
 }
 
 public function ExportTime($db_time) {
    if ($this->time_zone) {
        $ctz = date_default_timezone_get();
	date_default_timezone_set($this->time_zone->getName());
	switch ($this->time_format) {
	    case "U":
		$dt = new DateTime("@" . $db_time);
	    break;
	    default:
		$dt = new DateTime($db_time);
	}
	date_default_timezone_set($ctz);

//	$dt->setTimezone($this->gmt_timezone);
	return $dt;
    }

    switch ($this->time_format) {
	case "U":
	    return new DateTime("@" . $db_time);
	default:
	    return new DateTime($db_time);
    }
 }

 function GetTimeSlicingFunction($db_time_var, $width, $offset = 0) {
    if ($offset) {
	if (is_object($offset)) 
	    $off = $this->ImportTime($offset);
	else
	    $off = $this->ImportUnixTime($offset);

	return "($db_time_var - $off) / $width";
    } 
    
    return "$db_time_var / $width";
 }
}



?>