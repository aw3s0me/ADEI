<?php

class NULLItemFilter implements SIMPLEItemFilter {
  function ProcessItem(&$data, $time, $id, &$value) {
    if ($value === NULL) return true;    
    return false;
 }
}

?>