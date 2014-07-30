<?php

/* 
 Additional parameters:
    IO
	Open:		$flags = 0
	Close:		STREAMWriterInterface $h = NULL
    IOFilter
	Open:		$flags = 0 
    FILTER (all classes)
	BlockEnd: 	STREAMWriterInterface $h = NULL
	Close: 		STREAMWriterInterface $h = NULL
    
*/


interface STREAMWriterInterface {
 public function WriteData(&$data);
 public function WriteFile($file);
 public function Write($data);
}

interface STREAMReaderInterface {
 public function EOS();
 public function ReadData($limit = 0);
 public function StreamData(STREAMWriterInterface $h = NULL);
 public function Stream(STREAMWriterInterface $h = NULL);
}

interface STREAMObjectInterface {
 public function Open(&$args = NULL);
 public function Close();
 public function BlockStart(&$args = NULL);
 public function BlockEnd();

 public function GetExtension();
 public function GetContentType();
 public function GetSpecials();
}

require("io.php");
require("filter.php");
require("streamhandler.php");


class STREAM extends VIRTUALStreamWriter implements STREAMObjectInterface, STREAMWriterInterface {
 const BLOCK 		= 1;	// Allow blocking reads
 const GIFT 		= 2;	// Give ownership over passed objects (if any)
 
 const MULTIMODE	= 1;	// Request support of multiple blocks
 const FILESTART	= 2;	// Allow starting in filemode
 
 const OPTIONAL		= 1;	// Optional STREAMHandler
 
 const BUFFER_SIZE = 65536;

 var $queue;
 var $joiner;
 
 var $saved_queue = false;
 var $filereader;
 
 function __construct(&$info = NULL, STREAMObjectInterface $output = NULL, $flags = 0) {
    $this->filereader = false;

    $joiner = false;
    $optional = 0;
    
    $filter_info = &$info["filter"];

    if ((!$filter_info)&&($flags&STREAM::MULTIMODE)) {
	unset($filter_info);
	$filter_info = $this->GetDefaultJoinerInfo();
	$optional = STREAM::OPTIONAL;
    }
        
    while ($filter_info) {
	$flt = $this->CreateFilter($filter_info);
	if ($flt->joiner) $joiner = true;
	
	    // If only files are accepted by filter
	if ($flt->filereader) {
	    if ($this->queue)
		$filemode = $ptr->RequestFilemode();
	    elseif (($flags&STREAM::FILESTART)&&(!$optional)) {
		$this->filereader = true;
		$filemode = true;
	    } else 
		$filemode = false;
	    
	    if (!$filemode) {
/*		if ($this->queue) $this->queue->GetExtention();
		else ????
		$tmpfile = GetTmpFile("adei_stream_");
	        $iowriter = new IO($tmpfile);*/

		$iowriter = new IO();

		if ($this->queue) {
	    	    $ptr = $ptr->ConnectFileWriter($iowriter, $optional);
		} else {
		    $this->queue = new STREAMFileWriter($iowriter, NULL, $optional);
		    $ptr = $this->queue;
		}
	    }
	}

	if ($this->queue) {
	    $ptr = $ptr->ConnectFilter($flt, $optional);
	} else {
	    $this->queue = new STREAMFilter($flt, NULL, $optional);
	    $ptr = $this->queue;
	}

	$filter_info = &$filter_info["filter"];
	
	    // Adding optional default joiner
	if ((!$filter_info)&&(!$joiner)&&($flags&STREAM::MULTIMODE)) {
	    unset($filter_info);
	    $filter_info = $this->GetDefaultJoinerInfo();

	    $optional = STREAM::OPTIONAL;
	}
    }

    
    $this->joiner = $joiner;

	// Default output
    if (!$output) $output = new IO();
    if ($this->queue) $ptr->ConnectOutput($output);
    else $this->queue = new STREAMOutput($output);
 }
 
 function GetDefaultJoinerInfo() {
    return array(
	"type" => "ZIP",
	"optional" => true
    );
 }
 
 function CreateFilter(&$info) {
    if ($info) {
	if (is_array($info)) {
	    if (isset($info['type'])) {
		if (!@include_once(strtolower($info['type']) . 'filter.php')) {
	    	    throw new ADEIException(translate("Unsupported STREAM filter is configured: \"%s\"", $info['type']));
		}
		$handler = $info['type'] . "Filter";
	    } elseif (isset($info['app'])) {
		$handler = "APPFilter";
	    } else {
	        throw new ADEIException(translate("Unknown filter type"));
	    }
	    return new $handler($info);
	} else return new APPFilter(array("app" => $info));
    } 
 }
 
 
 function Open(&$args = NULL) {
    if (($args)&&($args['expected_blocks'] === 1)) {
	if ($this->queue->flags&STREAM::OPTIONAL) {
	    $ptr = $this->queue;
	    while ($ptr->flags&STREAM::OPTIONAL) $ptr = $ptr->next;
	    
	    $this->saved_queue = $this->queue;
	    $this->queue = $ptr;
	}
    }
    $this->queue->SequenceStart($args);
 }

 function BlockStart(&$args = NULL) {
    $this->queue->Start($args);
 }

 function BlockEnd() {
    $this->queue->End();
 }
  
 function Close() {
    $this->queue->SequenceEnd();
    if ($this->saved_queue) {
	$this->queue = $this->saved_queue;
	$this->saved_queue = false;
    }
 }

 function WriteData(&$data) {
    $this->queue->WriteData($data);
 }
 
 function WriteFile($file) {
    $this->queue->WriteFile($file);
 }

 function GetContentType() {
    return $this->queue->GetContentType();    
 }

 function GetExtension() {
    return $this->queue->GetExtension();
 }
 
 function GetSpecials() {
    $res = $this->queue->GetSpecials();
    if (!$res) return array();
    return $res;
 }
}

?>