<?php
abstract class VIRTUALStreamObject extends VIRTUALStreamWriter implements STREAMObjectInterface {
 function GetContentType() {
    return false;
 }
 
 function GetExtension() {
    return false;
 }
 
 function GetSpecials() {
    return array();
 }

 public function BlockStart(&$args = NULL) {
 }
 
 public function BlockEnd() {
 }
 
 public function IsJoiner() {
    return false;
 }
}

?>