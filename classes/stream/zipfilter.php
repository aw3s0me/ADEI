<?php
class ZIPFilter extends FILTER implements STREAMObjectInterface {
 var $zip;
 
 var $block_title;
 var $list;
 
 function __construct(&$info = NULL, STREAMWriterInterface $output = NULL) {
    $this->filereader = true;
    $this->filewriter = true;
    $this->joiner = true;
    
    $this->content_type = "application/x-zip-compressed";
    $this->extension = "zip";

    parent::__construct($info, $output);
 }

 function Open(&$args = NULL) {
    parent::Open($args);
    
    $this->zip = new ZipArchive();
/*
    Unfortunately, to avoid problems it is neccessary to open and close ZIP 
    archive on each operation.
    
    if ($this->zip->open($this->tmpfile, ZIPARCHIVE::CREATE) !== true)
	throw new ADEIException(translate("Can't create ZIP archive"));
*/
    
    $this->list = array();
 }
 
 function Close(STREAMWriterInterface $h = NULL) {
    if (!sizeof($this->list)) {
	if ($this->zip->open($this->tmpfile, ZIPARCHIVE::CREATE) !== true)
	    throw new ADEIException(translate("Can't open/create ZIP archive (%s)", $this->tmpfile));
	$this->zip->addFromString("no_data_available", "");
	$this->zip->close();
    }

/*
    $this->zip->close();
*/    

    parent::Close($h);
 }

 function BlockStart(&$args = NULL) {
    $this->block_title = preg_replace("/[^\w\d_]/", "_", $args['block_title']);
 }
 
 function WriteData(&$data) {
    $this->count++;
 
    if ((isset($this->block_title))&&(strlen($this->block_title)>0)) {
        if (preg_match("/(\.[^\/\\\\]+)\s*$/",$data,$m)) $title = $this->block_title . $m[1];
	else $title = $this->block_title;
    } else $title = basename($data);
    
    if (in_array($title, $this->list)) {
        if (preg_match("/^(.*)(\.[^\/\\\\]+)\s*$/",$title,$m)) {
	    $base = $m[1];
	    $ext = $m[2];
	} else {
	    $base = $title;
	    $ext = "";
	}
	
	$i = 0;
	do {
	    $title = $base . (++$i) . $ext;
	} while (in_array($title, $this->list));
    }
    
    array_push($this->list, $title);

    if ($this->zip->open($this->tmpfile, ZIPARCHIVE::CREATE) !== true)
	throw new ADEIException(translate("Can't open/create ZIP archive (%s)", $this->tmpfile));
    $this->zip->addFile($data, $title);
    $this->zip->close();
 }

 function RequestFilemode() {
    return false;
 }
}

?>