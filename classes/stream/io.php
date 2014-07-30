<?php
require("streamwriter.php");
require("streamobject.php");
require("streamreader.php");

class IO extends VIRTUALStreamReaderWriter implements STREAMObjectInterface {
 var $output;

 var $action;
 var $close;
 var $stdout;
 
 var $read;
 
 
 function __construct($output = NULL, $read_mode = false) {
    $this->output = $output;
    $this->read = $read_mode;
 }

 function Open(&$new_output = NULL, $flags = 0) {
    if ((!$new_output)||(is_array($new_output))) $output = $this->output;
    else $output = $new_output;
 
    if (is_resource($output)) {
        $this->action = $output;

	if ($flags&STREAM::GIFT) $this->close = true;
	else $this->close = false;
	$this->stdout = false;
    } elseif (is_string($output)) {
	$this->action = fopen($output, $this->read?"rw":"w");
	if (!$this->action)
	    throw new ADEIException(translate("Access to the stream outlet (%s) is failed", $action));

	$this->close = true;
	$this->stdout = false;
    } elseif ($output instanceof STREAMWriterInterface) {
	if (($this->read)&&(!($output instanceof STREAMReaderWriterInterface))) {
	    throw new ADEIException(translate("STREAMReader interface is not supported by object"));
	}

	$this->action = $output;

        if ($output instanceof STREAMSequenceInterface) {
	    $this->action->SequenceStart();
	    $this->action->Start();
	
	    $this->close = true;
	} else {
	    $this->close = false;
	}
	
    } elseif ($output) {
	throw new ADEIException(translate("Unsupported stream output"));
    } else {
	if ($this->read) {
	    throw new ADEIException(translate("There are no read possibility for STDOUT objects"));
	}
	$this->action = fopen("php://output", "w");
	$this->close = true;
	$this->stdout = true;
    }
 }

 function Close(STREAMWriterInterface $h = NULL) {
    if ($this->read) {
	if ($h) $this->Stream($h);
	else $res = $this->GetContent($h);	
    }
 
    if (is_resource($this->action)) {
	if ($this->close) {
	    fclose($this->action);
	    $this->close = false;
	}
    } else {
	if ($this->close) {
	    $this->action->End();
	    $this->action->SequenceEnd();
	}
    }
    
    unset($this->action);
    
    return $res;
 }

 function WriteFile($file) {
    if ($this->stdout) readfile($file);
    else parent::WriteFile($file, $flags);
 }

 function WriteData(&$data) {
    if (is_resource($this->action))
	fwrite($this->action, $data);
    else
	$this->action->WriteData($data, $flags);
 }
 
 function ReadData($limit = 0, $flags = 0) {
    if (!$this->read)
	throw new ADEIException(translate("The read is not supported"));
    
    $this->read = 2; // At least single read is performed
    
    if (is_resource($this->action)) {
	if (($flags&STREAM::BLOCK)||(stream_select($r=array($this->action), $w=NULL, $e=NULL, 0)>0)) {
	    if ($limit) return fread($this->action, $limit);
	    else return fread($this->action, STREAM::BUFFER_SIZE);
	} 
	return "";
    } else
	$this->action->ReadData($flags);
 }
 
 function EOS() {
    if (!$this->read)
	throw new ADEIException(translate("The read is not supported"));

    if (is_resource($this->action))
	return feof($this->action);
    else
	return $this->action->EOS($flags);
 }
}

?>