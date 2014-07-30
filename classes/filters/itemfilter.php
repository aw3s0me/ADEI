<?php
interface SIMPLEItemFilter {
 public function ProcessItem(&$data, $time, $id, &$value);
}

class READER_ITEMFilter extends BASEDataFilter implements READER_SIMPLEDataFilter {
 var $skip;
 var $filter;
 var $indexes;
 
 var $check_masked;
 
 function __construct(READER $rdr, LOGGROUP $grp, DATAFilter $filter, &$opts = NULL) {
    if ($rdr instanceof CACHEReader) $this->skip = true;
    else {
	$this->skip = false;

        $filter_class = $opts['filter'];
        
	if (!include_once("item/" . strtolower($filter_class) . ".php")) {
	    if (!isset($opts['filter']))
	        throw new ADEIException(translate("No item filter is configured"));
	    else
	        throw new ADEIException(translate("Unsupported item filter is configured: \"%s\"", $filter_class));
	}
	    
	$this->filter = new $filter_class($opts);
	
        $mask = $filter->GetItemMask();
        
        if (($mask)&&(!$mask->IsFull())) $this->check_masked = true;
        else $this->check_masked = false;
        
	if (isset($opts['item_mask'])) {

            $this->indexes = array();
            $key = array(); $re = array();
	    if (is_array($opts['item_mask'])) {
	        if (is_array($opts['item_mask'][0])) {
	            $checks = $opts['item_mask'];
	        } else {
	            $checks = array($opts['item_mask']);
	        }
	    } else {
	        $re = $opts['item_mask'];
	        if ($mask) {
	            $checks = false; 
	            $i = 0;
	            foreach ($mask->ids as $id) {
	                if (preg_match($re, $id)) $this->indexes[$i] = $id;
	                $i++;
	            }
	        } else {
	            $checks = array(array(
	                "key" => id,
	                "items" => $re
	            ));
	        }
	    }

	    if ($checks) {
	        $items = $rdr->GetItemList($grp, ($mask?$mask:new MASK()));
	        $i = 0;
	        foreach ($items as &$item) {
	            $matched = true;
	            foreach ($checks as $check) {
	                if (!preg_match($check['items'], $item[$check['key']])) {
	                    $matched = false;
	                    break;
	                }
	            }
	            if ($matched) $this->indexes[$i] = $item["id"];
	            $i++;
	        }
	    }
	} else if (($mask)&&(!$mask->IsFull())) {
	    $this->indexes = $mask->ids;
	} else {
	    $this->indexes = range(0, $rdr->GetGroupSize($grp) - 1);
	}
    }
 }

 function Start(&$data) {
    if ($this->skip) return;

    if (($this->check_masked)&&(!$data['masked']))
        throw new ADEIException(translate("ITEMFilter can't be executed on unmasked data..."));
 }

 function ProcessVector(&$data, &$time, &$values) {
    if ($this->skip) return false;

    foreach ($this->indexes as $idx => $id) {
        $res = $this->filter->ProcessItem($data, $time, $id, $values[$idx]);
        if ($res) return true;
    }
//    print_r($data);
//    print_r($values);
    return false;
 }
}

?>