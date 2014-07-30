<?php
require($EXCEL_WRITER_PATH . "Spreadsheet/Excel/Writer.php");

class EXCELHandler extends DATAHandler {
 var $xls;
 var $xls_time_format;
 var $xls_time_subsec_format;
 var $xls_data_format;
 var $sheet;

 var $row;

 var $time_format;
 var $time_subsec_format;
 var $data_format;
 var $time_width;
 var $data_width;
 
 var $string_format;
 var $tmpfile = false;
  
 const MAX_TITLE_CHARS = 31;
 
 function __construct(&$opts = NULL, STREAMHandler $h  = NULL) {
    global $CSV_DATE_FORMAT;
    global $EXCEL_DATE_FORMAT;
    global $EXCEL_SUBSEC_FORMAT;
    
    $this->content_type = "application/vnd.ms-excel";
    $this->extension = "xls"; 

    parent::__construct($opts, $h);
    
    $this->multigroup = true;
    $this->filewriter = true;

    if ($opts) {
	$this->time_format = $opts['date_format'];
	$this->time_width = $opts['date_width'];
	$this->time_subsec_format = $opts['subsec_format'];
	$this->time_subsec_width = $opts['subsec_width'];
	$this->data_format = $opts['value_format'];
	$this->data_width = $opts['value_width'];
    }

    if (!$this->time_format) $this->time_format = $EXCEL_DATE_FORMAT;
    if (!$this->time_width) $this->time_width = 20;
    if (!$this->time_subsec_format) $this->time_subsec_format = $EXCEL_SUBSEC_FORMAT;
    if (!$this->time_subsec_width) $this->time_width = 26;
    if (!$this->data_format) $this->data_format = "0.0000E+##";
    if (!$this->data_width) $this->data_width = 12;
    
    if (preg_match('/^\s*text\s*(\((.*)\))?\s*$/i', $this->time_subsec_format, $m)) {
	$this->time_subsec_format = false;
	if ($m[2]) $this->string_format = $m[2];
	else $this->string_format = $CSV_DATE_FORMAT;
    }
 }

 function SequenceStart($flags = 0) {
    parent::SequenceStart($flags);
    
    if ((!$this->h)||($this->h->stdout)) {
	$this->tmpfile = false;
	$this->xls = new Spreadsheet_Excel_Writer();
    } else {
	$this->tmpfile = GetTmpFile("adei_stream_excel_", "xls");
	$this->xls = new Spreadsheet_Excel_Writer($this->tmpfile);
    }

    $this->xls_time_format = $this->xls->addFormat();
    $this->xls_time_format->setNumFormat($this->time_format);
    if ($this->time_subsec_format) {
	$this->xls_time_subsec_format = $this->xls->addFormat();
	$this->xls_time_subsec_format->setNumFormat($this->time_subsec_format);
    }
    $this->xls_data_format = $this->xls->addFormat();
    $this->xls_data_format->setNumFormat($this->data_format);
 }

 function GroupStart($title, $subseconds = false, $flags = 0) {
    $val = substr(preg_replace('/[^\w\d_]+/', '_', $title), 0, EXCELHandler::MAX_TITLE_CHARS);
    $this->sheet = $this->xls->addWorksheet($val);
    if ($this->sheet instanceof PEAR_Error) {
	throw new ADEIException(translate("Error encountered while creating Excel sheet (%s). Info: %s", $title, $this->sheet->toString()));
    }

    $this->row = 0;
    
    parent::GroupStart($title, $subseconds, $flags);
 }

 function SequenceEnd($flags = 0) {
    $this->xls->close();
    
    if ($this->tmpfile) {
	if ($this->filemode)
	    $this->h->WriteData($this->tmpfile);
	else
	    $this->h->WriteFile($this->tmpfile);
	unlink($this->tmpfile);
    }
    
    parent::SequenceEnd($flags);
    
 }

 function DataHeaders(&$names, $flags = 0) {
    $this->sheet->write(0,0,"Date/Time");
    $this->sheet->setColumn(0,0,$this->time_width);
    
    foreach (array_keys($names) as $i) {
	$this->sheet->write(0, $i + 1, $names[$i]['name']);
        $this->sheet->setColumn(0, $i + 1, $this->data_width);
    }
 }

 function DataVector(&$time, &$values, $flags = 0) {
    if ($this->subseconds) {
	if ($this->time_subsec_format) {	
	    $val = dsMathPreciseAdd($time, 2209161600) / 86400; /* 86400 * 25569 */
    	    $this->sheet->write(++$this->row, 0, $val, $this->xls_time_subsec_format);
	} else {
	    $subsec = strchr(sprintf("%.6F", $time), '.');
	    $val = date($this->string_format, $time) . $subsec;
	    $this->sheet->write(++$this->row, 0, $val);
	}
    } else {
	$val = ($time + 2209161600) / 86400; /* 86400 * 25569 */
    	$this->sheet->write(++$this->row, 0, $val, $this->xls_time_format);
    }
    
    foreach (array_keys($values) as $i) {
	$this->sheet->write($this->row, $i + 1, $values[$i], $this->xls_data_format);
    }
 }
 
}

?>