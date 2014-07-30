<?php
function ADEIServiceGetUpdateInfo(REQUEST $req) {
    $query = $req->GetQueryString(); 
    return array(
	"xml" => "services/" . $req->props['module'] . ".php?$query",
	"xslt" => "settings"
    );
}
?>