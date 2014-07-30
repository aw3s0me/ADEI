<?php
class INTERVALSearch extends SEARCHEngine {
 function __construct(REQUEST $req = NULL, $opts = false) {
    parent::__construct($req, $opts);
    
    $this->modules = array(
	"interval" => _("Dates")
    );

    $this->engine_search = array("interval");
    
 }

 function DetectModules($string) {
    if (preg_match("/\b(Jan|January|Feb|FebruaryMar|March|Apr|April|May|Jun|June|Jul|July|Aug|August|Sep|September|Oct|October|Nov|November|Dec|December)\b/i", $string)) {
        return array("interval"=>true);
    }
	
    if (preg_match("/^\s*(\d{4})(\s*-\s*\d{4})?\s*$/", $string, $m)) {
	if (($m[2])||(($m[1]>1900)&&($m[1]<2100))) {
	    return array("interval"=>true);
	}
    }
    
    return false;
 } 

 function GetList($search_data, $module, $opts) {
    try {
	$ivl = INTERVAL::ParseInterval($search_data);
    } catch (ADEIException $ae) {
	return false;
    }
    
    list($from, $to) = explode("-", $ivl);

    $title = date("M j, Y H:i:s", $from) . " - " .  date("M j, Y H:i:s", $to);

    return  array(
		array(
		    'title' => $title,
		    'props' => array(
			'window' => $ivl
		    ),
		    'description' => false,
		    'certain' => true
		)
    );	
 }
}
?>