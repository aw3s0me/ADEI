<?php

class STREAMFileReader extends STREAMHandler implements STREAMSequenceInterface, STREAMWriterInterface {
 function WriteData(&$data) {
    if (!$this->next)
	throw new ADEIException(translate("STREAMFileReader could not be a last element in the chain"));

    $this->next->WriteFile($data);
 }
}


?>