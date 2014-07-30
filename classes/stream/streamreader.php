<?php
abstract class VIRTUALStreamReaderWriter extends VIRTUALStreamObject implements STREAMReaderInterface {
 var $output = false;

 function StreamData(STREAMWriterInterface $h = NULL, $flags = 0) {
    if ($this->output) $h = $this->output;
    else if (!$h) throw new ADEIException(translate("The data routing is failed (there is no output specified for STREAMReader)"));
    
    do {
	$data = $this->ReadData(STREAM::BUFFER_SIZE, $flags);
	if ($data) $h->WriteData($data, $flags);
    } while ($data);
 }
 
 function Stream(STREAMWriterInterface $h = NULL, $flags = 0) {
    if ($this->output) $h = $this->output;
    else if (!$h) throw new ADEIException(translate("The data routing is failed (there is no output specified for STREAMReader)"));

    while (!$this->EOS($flags)) {
	$data = $this->ReadData(STREAM::BUFFER_SIZE, $flags|STREAM::BLOCK);
	if ($data) $h->WriteData($data, $flags);
    }
 }
 
 function GetContent($flags = 0) {
    $res = "";
    while (!$this->EOS($flags)) {
	$data = $this->ReadData(0, $flags|STREAM::BLOCK);
	if ($data) $res .= $data;
    }
    return $res;
 }
}
?>