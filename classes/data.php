<?php

/*
  missing - maximal empty interval (without data). It's precise up to existing
  caching levels and should be used only to make decision if there anything
  missing in the data or not. No support for subresolution data.
*/
class DATA {
    var $min, $max, $mean;
    var $items, $n, $missing;
    var $missing_counter;    

    var $resolution;
    
    function __construct($items) {
	$this->min = array(); $this->max = array();
	$this->mean = array(); 
	
	$this->n = 0;
	$this->missing = 0;
	$this->items = $items;
	
	$this->missing_counter = 0;
    }
    
    function Push(DATA &$v, $width) {
	if (!$v->n) {
	    $this->missing_counter += $width;
	    return;
	}
	
	$items = $v->items;
	if ($items != $this->items)
	    throw new ADEIException(translate("Number of items in the group have changed from %u to %u (Data::Push)", $this->items, $items));
	    
	if ($this->n) {
	    for ($i = 0; $i < $items; $i++) {
		if (($v->min[$i] !== NULL)&&(($v->min[$i] < $this->min[$i])||($this->min[$i] === NULL))) $this->min[$i] = $v->min[$i];
		if (($v->max[$i] !== NULL)&&(($v->max[$i] > $this->max[$i])||($this->max[$i] === NULL))) $this->max[$i] = $v->max[$i];
		
		if (($v->mean[$i] !== NULL)&&($this->mean[$i] !== NULL)) $this->mean[$i] = ($this->mean[$i] * $this->n + $v->mean[$i] * $v->n) / ($this->n + $v->n);
		else $this->mean[$i] = NULL;
	    }

	    if ($this->missing_counter) {
		if ($this->missing_counter > $this->missing) $this->missing = $this->missing_counter;
		$this->missing_counter = 0;
	    } else  if ($v->missing > $this->missing) $this->missing = $v->missing;
	} else {
	    for ($i=0; $i < $items; $i++) {
		$this->min[$i] = $v->min[$i];
		$this->max[$i] = $v->max[$i];
		$this->mean[$i] = $v->mean[$i];
	    }
		
	    if ($this->missing_counter) {
		$this->missing = $this->missing_counter;
		$this->missing_counter = 0;
	    } else $this->missing = $v->missing;
	}
	$this->n += $v->n;
    } 

    
	// Row from CACHE table (non 0)
    function PushRow(&$v, $width) {
	$n = $v[1];
	
	if (!$n) {
	    $this->missing_counter += $width;
	    return;
	}
	
	$missing = $v[2];
	$items = (count($v) - 3) / 3;
	
	if ($items != $this->items)
	    throw new ADEIException(translate("Number of items in the group have changed from %u to %u (Data::PushRow)", $this->items, $items));
	
	if ($this->n) {
	    for ($i = 0, $j = 2; $i < $items; $i++) {
		if (($v[++$j] !== NULL)&&(($v[$j] < $this->min[$i])||($this->min[$i] === NULL))) $this->min[$i] = $v[$j];
		if (($v[++$j] !== NULL)&&(($v[$j] > $this->max[$i])||($this->max[$i] === NULL))) $this->max[$i] = $v[$j];
		if (($v[++$j] !== NULL)&&($this->mean[$i] !== NULL)) $this->mean[$i] = ($this->mean[$i] * $this->n + $n * $v[$j]) / ($this->n + $n);
		else $this->mean[$i] = NULL;
	    }

	    if ($this->missing_counter) {
		if ($this->missing_counter > $this->missing) $this->missing = $this->missing_counter;
		$this->missing_counter = 0;
	    } elseif ($missing > $this->missing) $this->missing = $missing;
	} else {
	    for ($i=0, $j = 2; $i < $items; $i++) {
		$this->min[$i] = $v[++$j];
		$this->max[$i] = $v[++$j];
		$this->mean[$i] = $v[++$j];
	    }
		
	    if ($this->missing_counter) {
		$this->missing = $this->missing_counter;
		$this->missing_counter = 0;
	    } else $this->missing = $missing;
	}
	$this->n += $n;
    }


	// READER->GetData
    function PushValue(&$v, $gap) {
	$items = count($v);
	if (!$items) return;
	
	if ($items != $this->items)
	    throw new ADEIException(translate("Number of items in the group have changed from %u to %u (Data::PushValue)", $this->items, $items));
	
	if ($this->n) {
	    for ($i = 0; $i < $items; $i++) {
		if ($v[$i] !== NULL) {
		    if (($v[$i] < $this->min[$i])||($this->min[$i] === NULL)) $this->min[$i] = $v[$i];
		    if (($v[$i] > $this->max[$i])||($this->max[$i] === NULL)) $this->max[$i] = $v[$i];
		    if ($this->mean[$i] !== NULL) $this->mean[$i] = ($this->mean[$i] * $this->n + $v[$i]) / ($this->n + 1);
		} else {
		    $this->mean[$i] = NULL;
		}
	    }
	} else {
	    for ($i=0; $i < $items; $i++) {
		$this->min[$i] = $v[$i];
		$this->max[$i] = $v[$i];
		$this->mean[$i] = $v[$i];
	    }
	}
	
	if ($gap > $this->missing) $this->missing = $gap;
	
	$this->n++;
    }

    function Finalize($gap = 0) {
	if ($gap > $this->missing) $this->missing = $gap;
	if (is_float($this->missing)) $this->missing = floor($this->missing);

	if ($this->missing_counter > $this->missing) $this->missing = $this->missing_counter;	    
    }
}

?>