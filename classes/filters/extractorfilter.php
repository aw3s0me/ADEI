<?php

class READER_EXTRACTORFilter extends BASEDataFilter implements READER_SIMPLEDataFilter {
 var $skip;
 var $filters;
 var $mappings;
 var $cur_indexes;
 var $real_indexes;
 var $remove;
 
 
 function __construct(READER $rdr, LOGGROUP $grp, DATAFilter $filter, &$opts = NULL) {
    if ($rdr instanceof CACHEReader) $this->skip = true;
    else {
	$this->skip = false;
	
	$this->filters = array();
	foreach ($opts['extractors'] as $ext => &$items) {
	    $config = &$opts['config'][$ext];
            $filter_class = $config['filter'];
            
            ADEI::RequireClass("extractors/$filter_class");
            
            $this->filters[$ext] = array();
            foreach ($items as $item => $mask) {
	        $this->filters[$ext][$item] = new $filter_class($mask, $config);
	    }
	}

	$this->mappings = $opts['mappings'];

	$mask = $filter->GetItemMask();

        if (($mask)&&(!$mask->IsFull())) {
            $this->check_masked = true;

            $this->cur_indexes = array(); $i = 0;
            foreach ($mask->ids as $id) {
                $this->cur_indexes[$id] = $i++;
            }
        } else {
            $this->check_masked = false;

            $this->cur_indexes = range(0, $rdr->GetGroupSize($grp) - 1);
        }


	if (($opts['mask'])&&(!$opts['mask']->IsFull())) {
	    $this->real_indexes = array(); $i = 0;
            foreach ($opts['mask']->ids as $id) {
                $this->real_indexes[$id] = $i++;
            }
        } else {
            $this->real_indexes = range(0, $rdr->GetGroupSize($grp) - 1);
        }

	$this->remove = sizeof($this->cur_mask) - sizeof($this->real_mask);
	if ($this->remove < 0) $this->remove = 0;
    }
 }

 function Start(&$data) {
    if ($this->skip) return;
    if (($this->check_masked)&&(!$data['masked']))
        throw new ADEIException(translate("EXTRACTORFilter can't be executed on unmasked data..."));
 }

 function ProcessVector(&$data, &$time, &$values) {
    if ($this->skip) return false;

    $gen = array();
    foreach ($this->filters as $ext => &$items) {
        $gen[$ext] = array();
        foreach ($items as $id => $filter) {
            $gen[$ext][$id] = $filter->ExtractItem($data, $time, $id, $values[$this->cur_indexes[$id]]);
        }
    }

    foreach ($this->mappings as $idx => $map) {
        $values[$this->real_indexes[$idx]] = $gen[$map[0]][$map[1]][$map[2]];
    }

    for ($i = 0; $i < $this->remove; $i++) {
        array_pop($values);
    }

/*
        // No dynamic masking
    $data['mask'] = $this->real_mask;
*/

    return false;
 }
}

?>