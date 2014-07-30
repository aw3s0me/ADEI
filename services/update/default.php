<?php

function ADEIServiceGetUpdateInfo(REQUEST $req) {
    global $ADEI;
    
    $xslt_file = $ADEI->GetXSLTFile($req->props['module']);
    if (file_exists($xslt_file)) $xslt = $req->props['module'];
    else $xslt = "null";
    
    $query = $req->GetQueryString();

    return array(
	"xml" => "services/" . $req->props['module'] . ".php?$query",
	"xslt" => $xslt
    );
}


?>