<?php

function ADEIServiceGetUpdateInfo(REQUEST $req) {
    if ($req->CheckData()) {
	if ($req->GetProp("target", false)) {
    	    $query = $req->GetQueryString($extra = array(
		"time_format" => "text"
	    ));
	} else {
    	    $query = $req->GetQueryString($extra = array(
		"target" => "status",
		"time_format" => "text"
	    ));
	}
	
    	return array(
	    "xml" => "services/control.php?$query",
	    "xslt" => "controlinfo"
	);
    }
    return false;
}


?>