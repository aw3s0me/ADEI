<?php

interface STREAMSequenceInterface {
 public function SequenceStart(&$args = NULL);
 public function Start(&$args = NULL);
 public function End();
 public function SequenceEnd();
}

abstract class STREAMHandler extends VIRTUALStreamWriter implements STREAMSequenceInterface, STREAMWriterInterface {
 var $next;
 var $saved_next = false;

 var $object;
 var $flags;

 var $filemode = false;
 
 var $extension;
 
 function __construct(STREAMObjectInterface $object = NULL, STREAMHandler $next = NULL, $flags = 0) {
    $this->object = $object;
    $this->next = $next;
    $this->flags = $flags;
 }
 
 function Connect(STREAMHandler $next) {
    if ($this->next) $this->next->Connect($next);
    else $this->next = $next;

    return $next;
 }
 
 function ConnectFilter(FILTER $object, $flags = 0) {
    return $this->Connect(new STREAMFilter($object, NULL, $flags));
 }

 function ConnectOutput(STREAMObjectInterface $object, $flags = 0) {
    return $this->Connect(new STREAMOutput($object, NULL, $flags));
 }

 function ConnectFileWriter(IO $object, $flags = 0) {
    return $this->Connect(new STREAMFileWriter($object, NULL, $flags));
 }
 
 function SequenceStart(&$args = NULL) {
    if ($this->next) {
	if (($args)&&($args['expected_blocks']===1)) {
	    if ($this->next->flags&STREAM::OPTIONAL) { 
		$ptr = $this->next;
		while ($ptr->flags&STREAM::OPTIONAL) {
		    $filemode = $ptr->filemode;
		    $ptr = $ptr->next;
		}
		
		$filereader = false;
		if ($this->filemode) {
		    if (!$filemode) {
			$filereader = true;
		    }
		} else {
		    if ($filemode) {
			    /* Actually, we need optional filters only before 
			    STREAMOutput to suppress unneccessary grouping.
			    Since it doesn't need filemode predcessor we don't
			    expect such situation and don't want to implement it */
			throw new ADEIException(translate("This filter configuration is not supported yet"));
		    }
		}
		
		$this->saved_next = $this->next;
		if ($filereader) {
		    $this->next = new STREAMFileReader(NULL, $ptr);
		} else {
		    $this->next = $ptr;
		}
		
	    }
	}
	
	if ($this->object) {
	    $this->extension = $this->object->GetExtension();
	    if ($args) {
		if ($args['extension'] != $this->extension) {
		    $new_args = $args;
		    $new_args['extension'] = $this->extension;
		    $args = &$new_args;
		}
	    } else {
		$args = array('extension' => $this->extension);
	    }    
	} else
	    $this->extension = false;

	$this->next->SequenceStart($args);
    }
 }

 function Start(&$args = NULL) {
    if ($this->next) {
	if ($this->extension) {
	    if ($args) {
		if ($args['extension'] != $this->extension) {
		    $new_args = $args;
		    $new_args['extension'] = $this->extension;
		    $args = &$new_args;
		}
	    } else {
		$args = array('extension' => $this->extension);
	    }    
	}
	$this->next->Start($args);
    }
 }

 function End() {
    if ($this->next) $this->next->End();
 }
 
 function SequenceEnd() {
    if ($this->next) {
	$this->next->SequenceEnd();
	
	if ($this->saved_next) {
	    $this->next = $this->saved_next;
	    $this->saved_next = false;
	}
    }
 }

 function WriteData(&$data) {
    if ($this->object)
	$this->object->WriteData($data);
    else if ($this->next)
	$this->next->WriteData($data);
 }
 
 function WriteFile($file) {
    if ($this->object)
	$this->object->WriteFile($file);
    else if ($this->next)
	$this->next->WriteFile($file);
 }
 
 function RequestFilemode() {
    return false;
 }
  
 function GetContentType() {
    if ($this->next) {
	$type = $this->next->GetContentType();
	if ($type) return $type;
    }
    
    if ($this->object)
	return $this->object->GetContentType();
    
    return false;
 }
 
 function GetExtension() {
    if ($this->next) {
	$ext = $this->next->GetExtension();
	if ($ext) return $ext;
    }

    if ($this->object)
	return $this->object->GetExtension();

    return false;
 }
 
 function GetSpecials() {
    if ($this->object) {
	$myres = $this->object->GetSpecials();
    } else {
	$myres = false;
    }
    
    if ($this->next) {
	$res = $this->next->GetSpecials();
    } else {
	$res = false;
    }
    
    if ($res) {
	if (is_string($myres))
	    array_push($res, $myres);
	else if (is_array($myres))
	    $res = array_merge($res, $myres);
	else
	    throw new ADEIException(translate("Unsupported specials"));
	return $res;
    }
    
    if (is_string($myres)) return array($myres);
    return $myres;
 }
}


require("streamoutput.php");
require("streamfilter.php");
require("streamfilewriter.php");
require("streamfilereader.php");

?>