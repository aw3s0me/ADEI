<?php

interface VIEWInterface {
 function IsApplicable();
 function GetOptions();
 function GetView();
};

abstract class VIEW implements VIEWInterface {
 var $req;
 var $options;
 
 function __construct(REQUEST $req  = NULL, $options) {
    $this->req = $req;
    $this->options = $options;
 }

 function IsApplicable() {
    return true;
 }
 function GetOptions() {
    return array();
 }
};

?>