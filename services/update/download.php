<?php

function ADEIServiceGetUpdateInfo(REQUEST $req) {
    return array(
	  "xml" => "services/download.php?target=dlmanager_list",
	  "xslt" => "download"
    );
}

?>
