<?php

class BLOBDecoderItemFilter implements SIMPLEItemFilter {
 var $decoder;
 var $format;
 
 function __construct(array &$opts = NULL) {
    if (isset($opts['decoder'])) $this->decoder = $opts['decoder'];
    else if (isset($opts['format'])) {
        $format = $opts['format'];
        $this->decoder = function($value) use ($format) { return unpack($format, $value); };
    } else throw new ADEIException(translate("The format not specified for BLOBDecoder"));
 }
 
 function ProcessItem(&$data, $time, $id, &$value) {
    if ($value) {
        $decoder = $this->decoder;
        $value = $decoder($value);
    }
 }
}

?>