<?php

class STREAMFileWriter extends STREAMHandler implements STREAMSequenceInterface, STREAMWriterInterface {
 var $filename;
 
 function __construct(IO $object, STREAMHandler $next = NULL, $flags = 0) {
    if (!$object->output)
	$this->filename = false;
    elseif (is_string($object->output)) {
	$this->filename = $object->output;
    } else
	throw new ADEIException(translate("Invalid IO object is passed to the constructor of STREAMFilemodeConverter. Could not guess output file name."));

    parent::__construct($object, $next, $flags);
 }

 function Start(&$args = NULL) {
    parent::Start($args);

    if ($this->object->output)
	$this->object->Open();
    else {
	if (($args)&&($args['extension']))
	    $this->filename = GetTmpFile("adei_stream_filewriter_", $args['extension']);
	else
	    $this->filename = GetTmpFile("adei_stream_filewriter_");
	
	$this->object->Open($this->filename);
    }
 }

 function End() {
    $this->object->Close();
    $this->next->WriteData($this->filename);
    parent::End();
 }
}


?>