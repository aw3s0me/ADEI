<?php

function ADEIServiceGetUpdateInfo(REQUEST $req) {
    $query = $req->GetQueryString($extra = array(
	"target" => "get"
    ));
	    
    $mod = $req->GetProp("infomod");
	
    if ($mod) {
    	return array(
	    "xml" => "services/view.php?$query",
	    "xslt" => "views/$mod"
	);
    }
    return false;
}


?>