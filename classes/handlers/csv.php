<?php

class CSVHandler extends DATAHandler {
 var $separator;

 var $date_format;

 function __construct(&$opts = NULL, STREAMHandler $h  = NULL) {
    global $CSV_SEPARATOR;
    global $CSV_DATE_FORMAT;

    $this->content_type = "text/csv";
    $this->extension = "csv"; 

    parent::__construct($opts, $h);
    
    if ($opts) {
	$this->separator = $opts['separator'];
	$this->date_format = $opts['date_format'];
	
	if ($opts['accept_null_values']) {
	    $this->nullwriter = true;
	}
    }
    
    if (!$this->separator) $this->separator = $CSV_SEPARATOR;
    if (!$this->date_format) $this->date_format = $CSV_DATE_FORMAT;
 }

 function DataHeaders(&$names, $flags = 0) {
    $this->h->Write("Date/Time");
    foreach ($names as $header) {
	$this->h->Write($this->separator . " " . preg_replace("/" . $this->separator . "/", " ", $header['name']));
    }
    $this->h->Write("\r\n");
 }
 
 function DataVector(&$time, &$values, $flags = 0) {
    if ($this->subseconds) {
/*
        if (is_float($unix_time)) $subsec = strchr(sprintf("%F", $unix_time), '.');
        else $subsec = strchr($unix_time, '.');
*/
        $subsec = strchr(sprintf("%.6F", $time), '.');
        $this->h->Write(date($this->date_format, $time) . $subsec);
    } else  {
        $this->h->Write(date($this->date_format, $time));
    }

    foreach ($values as $value) {
	$this->h->Write($this->separator . " " . $value);
    }

    $this->h->Write("\r\n");
 }
}

?>