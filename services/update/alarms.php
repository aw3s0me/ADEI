<?php

function ADEIServiceGetUpdateInfo(REQUEST $req) {
    if ($req->CheckData()) {
    	    $query = $req->GetQueryString($extra = array(
		"target" => "alarms_summary",
		"time_format" => "text"
	    ));
	
    	    return array(
		"xml" => "services/control.php?$query",
		"xslt" => "alarms"
	    );
    }
    return false;
}


?>