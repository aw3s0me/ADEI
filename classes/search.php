<?php

require($ADEI_ROOTDIR . "/classes/searchengine.php");
require($ADEI_ROOTDIR . "/classes/searchfilter.php");
require($ADEI_ROOTDIR . "/classes/searchresults.php");

class SEARCH {
 var $req;
 var $engines;
 var $modules;

 const EXACT_MATCH = 1;
 const WORD_MATCH = 2;
 const FUZZY_MATCH = 3;
 const REGEX_MATCH = 4;
 
 function __construct(REQUEST $req = NULL) {
    global $ADEI;
    global $SEARCH_ENGINES;

    if ($req) $this->req = $req;
    else $this->req = new REQUEST();

    $this->modules = array();
    $this->engines = array();
    
    foreach ($SEARCH_ENGINES as $cl => &$opts) {
	try {
	    $ADEI->RequireClass("search/$cl", true);
	} catch (ADEIException $ae) {
	    throw new ADEIException(translate("Unsupported search engine is configured: \"%s\"", $cl));
	}
    
    	$engine = new $cl($req, $opts);
	$modules = $engine->GetModules();
	
	foreach ($modules as $module) {
	    if (isset($this->modules[$module])) {
		throw new ADEIException(translate("SEARCH engines are conflicting. Module (%s) is provided by two different engines: \"" . get_class($this->modules[$module]) . "\" and \"$cl\""));
	    }
	    $this->modules[$module] = $engine;
	}
	
	$this->engines[$cl] = $engine;
    }
 }

 function DetectModules($string) {

    foreach ($this->engines as $engine) {
	$res = $engine->DetectModules($string);
	if ($res !== false) return $res;
    }

    return false;
 } 

 function Search($string = false, $modules = false, $threshold = false, $limits = false, $opts = false) {
    if ($string === false) $string = $this->req->props['search'];
    
    if (preg_match("/^\s*{([^}]*)}\s*(.*)$/", $string, $m)) {
	$modules = $m[1];
	$string = $m[2];
    }

    if (!$opts) $opts = array();
    
    if (preg_match("/^\s*\\[([^\\]]*)\\]\s*(.*)$/", $string, $m)) {
	$opts_string = $m[1];
	if (stripos($opts_string, "=") !== false) {
	    $opts['match'] = SEARCH::EXACT_MATCH;
	} else if (stripos($opts_string, "w") !== false) {
	    $opts['match'] = SEARCH::WORD_MATCH;
	} else if (stripos($opts_string, "~") !== false) {
	    $opts['match'] = SEARCH::FUZZY_MATCH;
	}
    }

    if (($threshold === false)&&(isset($this->req->props['search_threshold']))) {
	$threshold = $this->req->props['search_threshold'];
    }

    if (($modules === false)&&($this->req->props['search_modules'])) {
	$modules = $this->req->props['search_modules'];
    } else {
	// try detection
    }


	// Extracting limits part    
    if (!is_array($limits)) $limits = array();

    $parts = preg_split("/:/", $string);
    if (sizeof($parts) > 1) {
	if (!preg_match("/^(.*)\s*(\b[\w\d_]+)$/", $parts[0], $matches)) {
	    throw new ADEIException(translate("Invalid search string %s", $string));
	}
    
	$key = $matches[2];

	for ($i = 1; $i < sizeof($parts) - 1; $i++) {
	    if (!preg_match("/^(.*)\s*(\b[\w\d_]+)$/", $parts[$i], $m)) {
	        throw new ADEIException(translate("Invalid search string %s", $string));
	    }

	    $limits[$key] = $m[1];	    
	    
	    $key = $m[2];
	}
	
	$limits[$key] = $parts[$i];

	$string = $matches[1];

    }

    $filter = new SEARCHFilter($threshold, $limits);

	// extracting module information    
    if (($modules)&&(is_string($modules))) {
	if (strpos($modules, "{") === false) {
	    $mstr = explode(",", $modules);
	    $modules = array();
	    foreach ($mstr as $mod) {
		if (preg_match("/^([\w\d_]+)(\((.*)\))?$/", $mod, $m)) {
		    $module = $m[1];
		    $modules[$module] = array();
		    
		    if ($m[2]) {
			$params = explode(";", $m[3]);
			foreach ($params as $param) {
			    if (preg_match("/^([^=]+)=(.*)$/", $param, $m)) {
				$modules[$module][$m[1]] = $m[2];
			    } else {
				$modules[$module][$param] = true;
			    }
			}
		    }
		}
	    }
	} else {
	    $modules = json_decode($modules);
	}
    }
    
    if (!$modules) $modules = $this->DetectModules($string);
    
    $result = new SEARCHResults();
    
    if ($modules) {
	foreach ($modules as $module => $opts) {
	    if (isset($this->modules[$module])) {
	        $modres  = $this->modules[$module]->Search($string, $module, $filter, $opts);
		if ($modres) $result->Combine($modres);
		
	    } else {
		throw new ADEIException(translate("The requested search module (%s) is not provided by any of search engines", $module));
	    }
	}
    } else {
	    // Detect what is needed
	    
	foreach ($this->engines as $engine) {
	    $modres = $engine->EngineSearch($string, $filter, $opts);
	    if ($modres) $result->Combine($modres);
	}
    }
    
    if ($result->HaveResults()) {
	return $result;
    }
    
    return false;
 }
 
}

?>