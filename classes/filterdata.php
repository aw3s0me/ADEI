<?php

class FILTERData implements Iterator {
 var $data;
 var $filter;
 var $filter_data;
 
 function __construct(Iterator $unfiltered, DATAFilter $filter, $filter_data = NULL) {
    $this->data = $unfiltered;
    $this->filter = $filter;
    if (is_array($filter_data)) $this->filter_data = $filter_data;
    else $this->filter_data = array();
 }
 
 function rewind() {
    $this->data->rewind();
    $this->filter->Start($this->filter_data);
    
    if ($this->data->valid()) {
	$this->time = $this->data->key();
	$this->values = $this->data->current();
	if ($this->filter->ProcessVector($this->filter_data, $this->time, $this->values)) {
	    $this->next();
	}
    }
 }
 
 function next() {
    do {    
	$this->data->next();
	if ($this->data->valid()) {
	    $this->time = $this->data->key();
	    $this->values = $this->data->current();
	} else {
	    break;
	}
#	echo $this->time . ", " . sizeof($this->values) . "\n";
    } while ($this->filter->ProcessVector($this->filter_data, $this->time, $this->values));
 }
 
 function valid() {
    return $this->data->valid();
 }
 
 function key() {
    return $this->time;
 }
 
 function current() {
    return $this->values;
 }
}


?>