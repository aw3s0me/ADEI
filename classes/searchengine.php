<?php

interface SEARCHEngineInterface {
/*
 optional:
 public function GetList($search_data, $module, $opts);
 public function GetCmpFunction($module, $opts = false);
 public function CheckPhrase($info, $phrase, $match, $module, $opts);
*/
}


abstract class SEARCHEngine implements SEARCHEngineInterface {
 var $req;
 var $opts;
 var $modules;
 var $engine_search;
 
 function __construct(REQUEST $req = NULL, $opts = false) {
    $this->req = $req;
    $this->opts = $opts;
    $this->modules = NULL;
    $this->engine_search = NULL;
 }
 
 function GetOption($option, $opts = false, $default = false) {
    if (($opts)&&(isset($opts[$option]))) return $opts[$option];
    else if (($this->opts)&&(isset($this->opts[$option]))) return $this->opts[$option];
    else return $default;
 }

 function DetectModules($string) {
    return false;
 }
 
 function GetModules() {
    if ($this->modules) {
	if (isset($this->modules[0])) return $this->modules;
	else return array_keys($this->modules);
    }
    
    $cname = get_class($this);
    
    if (preg_match("/^(.*)Search$/i", $cname, $m)) {
	return array(strtolower($m[1]));
    } else {
	return array(strtolower($cname));
    }
 }
 
 function GetModuleTitle($module) {
    if (($this->modules)&&(isset($this->modules[$module]))) {
	if (is_array($this->modules[$module])) {
	    $title = $this->modules[$module]['title'];
	} else {
	    $title = $this->modules[$module];
	}
	
	if ($title) return $title;
    }
    return $module;
 }

 function GetModuleDescription($module) {
    if (($this->modules)&&(is_array($this->modules[$module]))) {
	return $this->modules[$module]['description'];
    }
    return false;
 }
 
 
 function CheckTitlePhrase($title, $phrase, $match = 0, $opts = false) {
	// exact match?
	// regular expressions to cover random number of spaces/tabs?
#    echo $phrase . " , " . $title . "\n";
    switch ($match) {
	case SEARCH::WORD_MATCH:
	    $res = preg_match("/\b$phrase\b/i", $title)?1:0;
	break;
	case SEARCH::FUZZY_MATCH:
	    $res = (stripos($title, $phrase) === false)?0:1;
	break;
	case SEARCH::REGEX_MATCH:
	    $res = preg_match($phrase, $title)?1:0;
	break;
	default:
	    $res = preg_match("/\b$phrase/i", $title)?1:0;
    }
#    echo $title . " - " . $phrase . " = " . $res . "<$match>\n";
    return $res;
 }
 
 function CheckPhrase($info, $phrase, $match, $module, $opts) {
    if (is_string($info['name']))
	return $this->CheckTitlePhrase($info['name'], $phrase, $match, $opts);
    else
	return 1;
 }
 
 function CheckString(&$info, $string, $module, &$opts) {
    //if (!method_exists($this, "CheckPhrase")) return 1;
    
    if ($this->opts['match'] == SEARCH::EXACT_MATCH) {
	$string = "/^" . preg_replace(
	    array("/^\s*/","/\s*$/","\s+"),
	    array("/\\s*" ,"\\s*"  ,"\s+"),
	    $string
	) . "\$/i";
    }

    $s = new SEARCHOptions($this, $module, $opts, $info);

#    $allowed_characters = "[^\s]";
    $allowed_characters = "[\w\d_\-]";
	// Splitting in phrases and checking them
    $string = preg_replace_callback("/((([\"'])([^\"']+)\\3)|(([=~]\s*)?(\b($allowed_characters+)\b))|(\/([^\/]+)\/))/", 
	array($s, 'CheckStringCallback'),
	$string
    );
    
//   echo $string . "\n";

	// Ripping invalid characters
    $string = preg_replace("/[^\d+!|&()\-\s]/", "", $string);

//   echo $string . "\n";
    
    do {
	/*
	    http://www.fiction.net/tidbits/computer/true_story_unix.html 
	*/
	$string = preg_replace(
	    array(
		"/!\s*((?P<p>\(((?>[^()]+)|(?P>p))+\))|(?>\d+))/",
		"/-\s*((?P<p>\(((?>[^()]+)|(?P>p))+\))|(?>\d+))/",
		"/\+\s*((?P<p>\(((?>[^()]+)|(?P>p))+\))|(?>\d+))/",
		"/(?P<a>(?P<ap>\(((?>[^()]+)|(?P>ap))+\))|(?>\d+))\s*\|\s*(?P<b>(?P<bp>\(((?>[^()]+)|(?P>bp))+\))|(?>\d+))/",
		"/(?P<a>(?P<ap>\(((?>[^()]+)|(?P>ap))+\))|(?>\d+))\s*(?:&|\s)\s*(?P<b>(?P<bp>\(((?>[^()]+)|(?P>bp))+\))|(?>\d+))/",
	    ),
	    array(
		"(1minus$1)",
		"(($1==0)?1:0)",
		"(($1==1)?1:0)",
		"(max($1,$4))",
		"$1*$4"
	    ),
	    $string, -1, $nrep
	);
    } while ($nrep);

    $string = preg_replace("/minus/", "-", $string);

//    echo $string . "\n";
    
    if (@eval("\$res=$string;") === false) $res = 0;
#    echo "final: $res ({$info['name']})\n";
    return $res;
 }
 
 function ParseString($string) {
/*
    if (preg_match_all("/((([\"'])([^\"']+)\\3)|(\b([^\s]+)\b))/", $string, $m, PREG_PATTERN_ORDER)) {
	print_r($m[4]);
	print_r($m[6]);
    }
*/

    return $string;
 }

 function Search($search_string, $module, SEARCHFilter $filter = NULL, $opts = false) {
    $search_data = $this->ParseString($search_string, $module, $opts);

    $res = new SEARCHResults($filter, $this, $module);
    
    // get list, loop
    $list = $this->GetList($search_data, $module, $opts);
    if ($list) {
      foreach ($list as $key => $info) {
	$rating = $this->CheckString($info, $search_data, $module, $opts);
	if ($rating) {
	    $res->Append($info, $rating, $key);
	}
      }
    }
    
    if ($res->HaveResults()) return $res;
    return false;
 }

 function GetCmpFunction($module, $opts = false) {
    return create_function('$a, $b', '
	if ($a["precision"] == $b["precision"]) return (strcmp($a["title"], $b["title"]));
	else if ($a["precision"] < $b["precision"]) return 1;
	else return -1;
    ');
 }
 
// function GetResultCmpFunction($module)
// function Sort($module, 
 
 function EngineSearch($search_string, SEARCHFilter $filter = NULL, $opts = false) {
    $res = false;
    if ($this->engine_search) {
	foreach ($this->engine_search as $module) {
	    $mres = $this->Search($search_string, $module, $filter, $opts);
	    if ($mres) {
		if (!$res) $res = new SEARCHResults();
		$res->Combine($mres);
	    }
	}
    }

    return $res;
 }

}

class SEARCHOptions {
 var $engine;
 var $module;
 var $opts;
 var $info;
    
 function __construct($engine, $module, &$opts, &$info) {
    $this->engine = $engine;
    $this->module = $module;
    $this->opts = &$opts;
    $this->info = &$info;
 }
 
 function CheckStringCallback($m) {
    $match = $this->opts['match'];
    
    if ($m[4]) {
	$phrase = $m[4];
    } else if ($m[8]) {
	$phrase = $m[8];
	$match_string = $m[6];
	if ($match_string) {
	    if (strpos($match_string, "=")!==false) $match = SEARCH::WORD_MATCH;
	    else if (strpos($match_string, "~")!==false) $match = SEARCH::FUZZY_MATCH;
	}
    } else {
	$phrase = $m[10];
	$match = SEARCH::REGEX_MATCH;
    }
    
    $phrase = $m[4]?$m[4]:$m[8];
#    if ($m[4])

#    print_r($m);
#    echo $phrase . "\n";
    return $this->engine->CheckPhrase($this->info, $phrase, $match, $this->module, $this->opts);
 }
}

?>