<?php

class LENGTHFilter extends BASEDataFilter implements SIMPLEDataFilter {
 var $actual_items;
 
 var $remove_extra_items;
 var $add_missing_items;
 var $report_invalid_data;
 var $missing_value;
 
 function __construct($expected, &$opts = NULL) {
    $this->actual_items = $expected;

    if (is_array($opts)) {
        $this->remove_extra_items = $opts["remove_extra_items"];
	$this->add_missing_items = $opts["add_missing_items"];
	$this->report_invalid_data = !$opts["ignore_invalid_data"];
	if (isset($opts["missing_value"])) $this->missing_value = $opts["missing_value"];
	else $this->missing_value = NULL;
    } else {
        $this->remove_extra_items = false;
	$this->add_missing_items = false;
	$this->report_invalid_data = true;
    }
 } 
 
 function ProcessVector(&$data, &$time, &$values) {
    $size = sizeof($values);

    if ($size != $this->actual_items) {
	if ($data['masked']) {
	    throw new ADEIException(translate("Configuration error: LENGTHFilter is not supporting pre-masked data"));
	}
    
	if ($size > $this->actual_items) {
	    if ($this->remove_extra_items) {
	        for ($i = $this->actual_items; $i<=$size;$i++) {
		    unset($values[$i]);
		}
	    } elseif ($this->report_invalid_data) {
		throw new ADEIException(translate("Invlid source data is detected: data vector length mistmatch. According to the READER configuration, vector length is %u, but %u is returned by GetData call", $this->actual_items, $size));
	    } else {
		return true;
	    }
	} else {
	    if (($size)&&($this->add_missing_items)) {
		$data['missing_items'] = array();
		for ($i = $size; $i < $this->actual_items; $i++) {
		    $values[$i] = $this->missing_value;
		    $data['missing_items'][$i] = true;
		}
	    } elseif ($this->report_invalid_data) {
		throw new ADEIException(translate("Invlid source data is detected: data vector is cut. According to the READER configuration, vector length is %u, but only %u is returned by GetData call", $this->actual_items, $size));
	    } else {
		return true;
	    }
	}
    }
    
    return false;
 }
}


class READER_LENGTHFilter extends LENGTHFilter implements READER_SIMPLEDataFilter {
 var $skip;
 
 function __construct(READER $rdr, LOGGROUP $grp, DATAFilter $filter, &$opts = NULL) {
    if ($rdr instanceof CACHEReader) $this->skip = true;
    else {
	$this->skip = false;
        parent::__construct($rdr->GetGroupSize($grp), $opts);
    }
 }

 function ProcessVector(&$data, &$time, &$values) {
    if ($this->skip) return false;
    return parent::ProcessVector($data, $time, $values);
 }
}

?>