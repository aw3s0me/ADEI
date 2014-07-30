<?php

class STREAMOutput extends STREAMHandler implements STREAMSequenceInterface, STREAMWriterInterface {
 var $count;
 
 function __construct($object = NULL, STREAMHandler $next = NULL, $flags = 0) {
    if (is_object($object)) {
	if ((!$object)||($object instanceof STREAMObjectInterface))
	    parent::__construct($object, $next, $flags);
	else
	    throw new ADEIException(translate("Argument 1 passed to STREAMOutput constructor must implement STREAMObjectInterface, the '%s' is supplied", get_class($object)));
    } else
	parent::__construct(new IO($object), $next, $flags);
 }

 function SequenceStart(&$args = NULL) {
    if (($args)&&($args['expected_blocks'] > 1))
	throw new ADEIException(translate("Invalid STREAM. The multiple groups are not supported by STREAMOutput"));

    $this->count = 0;

    parent::SequenceStart($args);
 }
 
 function Start(&$args = NULL) {
    if ($this->count++)
	throw new ADEIException(translate("Invalid STREAM. The multiple groups are not supported by STREAMOutput"));

    parent::Start($args);

    $this->object->Open();
 }

 function End() {
    $this->object->Close();

    parent::End();
 }
}


?>