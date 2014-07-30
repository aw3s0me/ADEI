<?php

class SUMExtractor implements DATAExtractionInterface {
 function __construct($mask, array $opts) {
 }

 static function GetItemList(array $base_item, $flags) {
    return array(
        array("id" => "sum", "name" => "%s (summation)")
    );
 }
 
 
 function ExtractItem(&$data, $time, $id, &$value) {
    if (is_array($value)) {
        return array(array_sum($value));
    }
    return array(NULL);
 }
}


?>