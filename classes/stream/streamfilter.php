<?php

class STREAMFilter extends STREAMHandler implements STREAMSequenceInterface, STREAMWriterInterface {
 var $joiner;
 var $count;
 
 function __construct(FILTER $object, STREAMHandler $next = NULL, $flags = 0) {
    parent::__construct($object, $next, $flags);

    $this->joiner = $this->object->IsJoiner();
 }

 function Connect(STREAMHandler $next) {
    $this->object->SetOutput($next);

    return parent::Connect($next);
 }


 function SequenceStart(&$args = NUL) {
    if ($this->joiner) {
	$new_args = $args;
	$new_args['expected_blocks'] = 1;
	parent::SequenceStart($new_args);
	
	if ($this->saved_next)
	    $this->object->SetOutput($this->next);

	parent::Start($args);

	$this->count = 0;
	$this->object->Open($args);
    } else {
	parent::SequenceStart($args);

	if ($this->saved_next)
	    $this->object->SetOutput($this->next);
    }
 }
 
 function Start(&$args = NULL) {
    if ($this->joiner) {
	if ($this->count) {
	    $new_args = $args;
	    $new_args['block_number'] = $this->count;
	    unset($args);
	    $args = &$new_args;
	}
	$this->count++;
    } else {
	parent::Start($args);
	$this->object->Open($args);
    }

    $this->object->BlockStart($args);
 }

 function End() {
    $this->object->BlockEnd($this->next);

    if (!$this->joiner) {
	$this->object->Close($this->next);
	parent::End();
    }
 }

 function SequenceEnd() {
    if ($this->joiner) {
	$this->object->Close($this->next);
	parent::End();
    }

    if ($this->saved_next)
	$this->object->SetOutput($this->saved_next);
	
    parent::SequenceEnd();
 }

 function WriteData(&$data, $flags=false) {
    $this->object->WriteData($data, $flags);
    if (!$this->object->filewriter) $this->object->StreamData($this->next);
 }

 function RequestFilemode() {
    $this->filemode = $this->object->RequestFilemode();
    return $this->filemode;
 }

}

?>