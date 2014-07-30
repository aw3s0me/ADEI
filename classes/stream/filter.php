<?php

abstract class FILTER extends VIRTUALStreamReaderWriter implements STREAMObjectInterface {
 var $info;
 var $output;

 var $joiner = false;
 var $content_type = "application/binary";
 var $extension = false;
 var $specials = false;

 var $blockmode;
 
 var $filewriter = false;
 var $filereader = false;
 var $tmpfile;
 
 var $filemode = false;

 function __construct(&$info = NULL, STREAMWriterInterface $output = NULL) {
    $this->info = &$info;

    if (isset($info['joiner'])) 
	$this->joiner = $info['joiner'];

    if (isset($info['content_type'])) 
	$this->content_type = $info['content_type'];

    if (isset($info['extension'])) 
	$this->extension = $info['extension'];

    $this->output = $output;
 }
 
 function SetOutput(STREAMWriterInterface $output = NULL) {
    $this->output = $output;
 }
 
 function Open(&$args = NULL) {
    if ($this->filewriter) {
	if (($args)&&($args['tmpfile'])) $this->tmpfile = $args['tmpfile'];
	else $this->tmpfile = GetTmpFile("adei_appfilter_", $this->GetExtension());
    }
 }


 function Close(STREAMWriterInterface $h = NULL) {
    if ($this->filewriter) {
	if ($this->output) $h = $this->output;

	if ($this->filemode) {
	    if ($h) $h->WriteData($this->tmpfile);
	    else throw new ADEIException(translate("The STREAM should be supplied to the FILTER while operating in the filemode"));
	} else {
	    if ($h) $h->WriteFile($this->tmpfile);
	    return file_get_contents($this->tmpfile);
	}
	unlink($this->tmpfile);
    }
 }

 function BlockStart(&$args = NULL) {
 }

 function BlockEnd(STREAMWriterInterface $h = NULL) {
 }


 function ReadData($limit = 0) {
    if ($this->filewriter)
	return "";
    else
	throw new ADEIException(translate("The ReadData function is not implemented within FILTER class"));
 }

 function EOS() {
    if ($this->filewriter)
	return false;
    else
	throw new ADEIException(translate("The EOS function is not implemented within FILTER class"));
 } 

 function GetSpecials() {
    return $this->specials;
 }
 
 function GetContentType() {
    return $this->content_type;
 }
 
 function GetExtension() {
    return $this->extension;
 }
 
 function IsJoiner() {
    return $this->joiner;
 }

 function RequestFilemode() {
    if ($this->filewriter) {
	$this->filemode = true;
	return true;
    }
    return false;
 }

}

require("iofilter.php");
require("appfilter.php");
require("zipfilter.php");

?>