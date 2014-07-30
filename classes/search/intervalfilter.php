<?php

class INTERVALSearchFilter extends BASESearchFilter {
 public function __construct($value) {
    parent::__construct(INTERVAL::ParseInterval($value));
 }
 
 public function FilterResult(&$info, &$rating) {
    $info['props']['window'] = $this->value;
    return false;
 }
}

?>