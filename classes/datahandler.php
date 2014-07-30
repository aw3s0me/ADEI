<?php
interface DATAHandlerInterface {
    public function SequenceStart($flags = 0);
    public function GroupStart($title, $subseconds = false, $flags = 0);
    public function GroupEnd($flags = 0);
    public function SequenceEnd($flags = 0);

    public function Start($vector_length, $flags = 0);
    public function DataHeaders(&$names, $flags = 0);
    public function DataVector(&$time, &$values, $flags = 0);
    public function End($flags = 0);

    public function GetContentType();
    public function GetExtension();
}


abstract class DATAHandler implements DATAHandlerInterface {
 var $h;
 var $output;
 var $info;

 var $content_type = false;
 var $extension = false;
 
 var $vector_length;
 var $subseconds;
 var $tz, $saved_tz;

 var $processed_groups;
 var $processed_vectors;
 
 var $multigroup = false;
 var $filewriter = false;
 var $filemode = false;
 var $nullwriter = false;
 
 function __construct(&$opts, STREAMWriterInterface $h = NULL) {
    $this->output = $h;
    $this->info = &$opts;

    if (isset($opts['content_type'])) 
	$this->content_type = $opts['content_type'];
    if (isset($opts['extension'])) 
	$this->extension = $opts['extension'];

    if ($opts) {
	$this->tz = $opts['timezone'];
    }

    if (!$this->tz) $this->tz = "UTC";
 }
 
 function SetOutput(STREAMWriterInterface $h) {
    $this->output = $h;
 }
 
 function SequenceStart($flags = 0) {
    if ($this->output) $this->h = $this->output;
    else {
	$this->h = new IO();
	$this->h->Open();
    }

    $this->saved_tz = date_default_timezone_get ();
    date_default_timezone_set($this->tz);

    $this->processed_groups = 0;
 }
 
 function GroupStart($title, $subseconds = false, $flags = 0) {
    $this->subseconds = $subseconds;
 }
 
 function GroupEnd($flags = 0) {
    $this->processed_groups++;
 }
 
 function SequenceEnd($flags = 0) {
    date_default_timezone_set($this->saved_tz);

    if (!$this->output) {
	$this->h->Close();
	unset($this->h);
    }
 }
 

 function Start($vector_length, $flags = 0) {
    $this->vector_length = $vector_length;
    $this->processed_vectors = 0;
 } 

 function DataHeaders(&$names, $flags = 0) {
 }
 
 function DataVector(&$time, &$values, $flags = 0) {
    $this->processed_vectors++;
 }

 function End($flags = 0) {
 }

 function GetContentType() {
    return $this->content_type;
 }
 function GetExtension() {
    return $this->extension;
 }

 function RequestFilemode() {
    if ($this->filewriter)
	$this->filemode = true;
    else
	throw new ADEIException(translate("Filemode is not supported by the handler"));

    return true;
 }
 
}

require("handlers/csv.php");
?>