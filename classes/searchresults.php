<?php

class SEARCHResults {
 var $results;
 var $current;		// Current module, we are adding results in
 var $engine;		// Current engine

 var $filter;
 var $threshold;
 
 function __construct(SEARCHFilter $filter = NULL, SEARCHEngine $engine = NULL, $module = false, $title = false, $description = false) {
    if ($filter) {
	$this->filter = $filter;
    } else {
	$this->filter = new SEARCHFilter();
    }
    $this->threshold = $this->filter->GetThreshold();

    $this->results = array();
    $this->current = false;
    if ($module) $this->NewModule($engine, $module, $title, $description);
 }

 function AcceptCurrent() {
    if ($this->current) {
	if ($this->results[$this->current]['results']) {
	    $func = $this->engine->GetCmpFunction($this->current);
	    if ($func) {
	        usort($this->results[$this->current]['results'], $func);
	    }
	} else if (!$this->results[$this->current]['content']) {
	    unset($this->results[$this->current]);
	}
	$this->current = false;
	$this->engine = false;
    }
 }

 function HaveResults() {
    $this->AcceptCurrent();
    if (sizeof($this->results)>0) return true;
    return false;
 }
 
 function GetResults() {
    return $this->results;
 }
 
 function NewModule(SEARCHEngine $engine, $module, $title = false, $description = false) {
    $data = array(
	'module' => $module,
	'title' => (($title!==false)?$title:$engine->GetModuleTitle($module)),
	'description' => (($description!==false)?$description:$engine->GetModuleDescription($module)),
    );

    $this->results[$module] = $data;

    $this->AcceptCurrent();
    $this->current = $module;
    $this->engine = $engine;
 }
    
 function Append(&$info, $rating = 1, $key = false) {
    if ($this->current === false)
	throw new ADEIException("Internal Error: Addition of search results is failed, Unknown module");
    
	// Filtering or detailing result
    if (($rating < 1)&&($rating < $this->threshold)) return false;
    if ($this->filter->FilterResult($info, $rating)) return false;
    if (($rating < 1)&&($rating < $this->threshold)) return false;
    
    if (is_array($info)) {
	if (!$this->results[$this->current]['results']) {
	    if ($this->results[$this->current]['content']) {
		throw new ADEIException(translate("The search results for current module are already containing html content, therefore result elements could not be added"));
	    }
	    $this->results[$this->current]['results'] = array();
	}
	
	$info['rating'] = $rating;
        if ($key === false) {
	    $this->results[$this->current]['results'][] = $info;
	} else {
	    $this->results[$this->current]['results'][$key] = $info;
	}
    } else {
	if (!$this->results[$this->current]['content']) {
	    if ($this->results[$this->current]['results']) {
		throw new ADEIException(translate("The search results for current module are already containing some elemnts, therefore html content could not be added"));
	    }
	    $this->results[$this->current]['content'] = "";
	}
	
	$this->results[$this->current]['content'] .= $info;
    }
    
    return true;
 }
 
 function Combine(SEARCHResults $sr) {
    $this->AcceptCurrent();
    $sr->AcceptCurrent();
    
    $this->results = array_merge($this->results, $sr->results);
/*
    foreach ($sr->results as $mod => &$res) {
	$this->results[$mod] = $res;
    }
*/
 }
 
 function Intersect(SEARCHResults $sr) {
    throw new ADEIException("Intersection of search results is not implemented yet");
 }

 
 static function Merge() {
    $res = new SEARCHResults();
    
    for ($i = 0;$i < func_num_args();$i++) {
	$mres = func_get_arg($i);
	if ($mres instanceof SEARCHResults) {
	    $res->Combine($mres);
	} else if ($mres !== false) {
	    throw new ADEIException("Internal error: Only SEARCHResult could be merged");
	}
    }

    if ($res->HaveResults()) return $res;
    return false;
 }
}


?>