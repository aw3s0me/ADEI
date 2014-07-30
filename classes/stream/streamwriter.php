<?php
abstract class VIRTUALStreamWriter implements STREAMWriterInterface {
 function Write($data, $flags = 0) {
    return $this->WriteData($data, $flags);
 }

 function WriteFile($file, $flags = 0) {
    $handle = fopen($file, "rb");
    if ($handle) {
	while (!feof($handle)) {
	    $this->WriteData(fread($handle, STREAM::BUFFER_SIZE));
	}
	fclose($handle);
    } else 
	throw new ADEIException(translate("File (%s) is not accessible", $file));
 }
}
?>