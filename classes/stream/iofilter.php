<?php

class IOFilter extends FILTER implements STREAMObjectInterface {
 var $sink;
 var $sink_open;
 var $outlet;
 var $outlet_open;

 var $copymode;
 var $resource_close;
 var $extra_data = "";
 
 var $block_mode;
 
 function __construct(&$info = NULL, STREAMWriterInterface $output = NULL, $sink = NULL, $outlet = 0, $flags = 0) {
    parent::__construct($info, $output);

    $this->extra_data = false;
    
    $this->CreateFilter($sink, $outlet);
    
    if ($flags&STREAM::GIFT) $this->resource_close = true;
    else $this->resource_close = false;
 }
 
 /* 
 outlet:
    - NULL	: create standard
    - resourse	: create standard and initialize
    - WriterI	: use writer interface
    - #number	: dublicate sink
    - false	: no outlet
 */
     
 function CreateFilter($sink = NULL, $outlet = 0) {
    unset($this->sink);
    unset($this->outlet);

    if (is_resource($sink)) {
	$this->sink = new IO($sink, is_int($outlet));
	$this->sink_open = true;
    } elseif ($sink instanceof STREAMWriterInterface) {
	$this->sink = $sink;
	$this->sink_open = false;
    } elseif ($sink) {
        throw new ADEIException(translate("Unsupported sink is supplied for the FILTER"));
    } else {
	$this->sink = new IO($NULL=NULL, is_int($outlet));
	$this->sink_open = true;
    }    
	
    if ($outlet === false) {
	$this->outlet = false;
	$this->outlet_open = false;
	$this->filewriter = true;
	$this->copymode = false;
    } elseif (is_int($outlet)) {
	$this->outlet = $this->sink;
	$this->outlet_open = false;
	$this->filewriter = false;
	$this->copymode = true;
    } elseif (is_resource($args[0])) {
	$this->outlet = new IO($outlet, true);
	$this->outlet_open = true;
	$this->filewriter = false;
	$this->copymode = false;
    } elseif ($outlet instanceof STREAMWriterInterface) {
	$this->outlet = $outlet;
	$this->outlet_open = false;
	$this->filewriter = false;
	$this->copymode = false;
    } elseif ($outlet) {
        throw new ADEIException(translate("Unsupported outlet is supplied for the FILTER"));
    } else {
	$this->outlet = new IO($NULL=NULL, true);
	$this->outlet_open = true;
	$this->filewriter = false;
	$this->copymode = false;
    }
 }

 function OpenPipes(&$pipes) {
    if ($this->sink_open) {
	$this->sink->Open($pipes[0], $flags);
    }
    if ($this->outlet_open) {
	$this->outlet->Open($pipes[1], $flags);
    }
 }

 function ClosePipes(STREAMWriterInterface $h = NULL) {
    if ($this->output) $h = $this->output;

	    // if outlet is copy of sink, we have outlet_open false
    if ($this->sink_open) {
	$this->sink->Close();
    }

    if ($this->outlet_open) {
	return $this->outlet->Close($h);
    }
 }

 function Open(&$args = NULL, &$pipes = NULL, $flags = 0) {
    parent::Open($args);

    if ($pipes) {
	$this->OpenPipes($pipes, $flags);
	$this->block_mode = false;
    } else $this->block_mode = true;
 }

 function BlockStart(&$args = NULL, &$pipes = NULL, $flags = 0) {
    if ($this->block_mode) {
	if ($pipes)
	    $this->OpenPipes($pipes, $flags);
	else
	    throw new ADEIException(translate("The actual pipes are not supplied IOFilter"));
    }
 }

 function BlockEnd(STREAMWriterInterface $h = NULL) {
    if ($this->block_mode) {
	return $this->ClosePipes($h);
    }
 }

 function Close(STREAMWriterInterface $h = NULL) {
    if (!$this->block_mode) {
	$res = $this->ClosePipes($h);
	if ($res) return $res . parent::Close($h);
    }
    return parent::Close($h);
 }
 
 function WriteData(&$data, $flags = 0) {
    $this->sink->Write($data);
 }

 function ReadData($limit = 0, $flags = 0) {
    if ($this->outlet) {
	if ($this->output)
	    return new ADEIException(translate("The filter have STREAMWriter configured in. Therethore read requests are not permited"));
	
	if ($this->extra_data) {
	    $res = $this->extra_data . $this->outlet->ReadData($limit, $flags);
	    $this->extra_data = false;
	    return $res;
	}
	return $this->outlet->ReadData($limit, $flags);
    } else
	throw new ADEIException(translate("The filter outlet is not configured"));
 }
 
 function StreamData(STREAMWriterInterface $h = NULL, $flags = 0) {
    if ($this->outlet) {
	if ($this->output) $h = $this->output;
	else if (!$h) throw new ADEIException(translate("The data routing is failed (there is no output specified for FILTER)"));

	if ($this->extra_data) {
	    $h->WriteData($this->extra_data);
	    $this->extra_data = false;
	}
	$this->outlet->StreamData($h, $flags);
    } else
	throw new ADEIException(translate("The filter outlet is not configured"));
 }

 function EOS() {
    if ($this->outlet) {
	if ($this->extra_data) return false;
	return $this->outlet->EOS();
    } else
	throw new ADEIException(translate("The filter outlet is not configured"));
 }
 
}


?>