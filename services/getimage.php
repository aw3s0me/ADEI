<?php

ADEI::RequireClass("draw");

header("Content-type: image/png");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

if (isset($_GET["id"])) {
    $id = $_GET["id"];
} else {
    try {
	$req = new DATARequest();
	$draw = $req->CreatePlotter();
	$draw->Create();
	$id = $draw->Save();
    } catch(ADEIException $e) {
	$error = $e->getInfo();
	
        try {
	    if (!$draw) {
		if (!$req) $req = new REQUEST();
		$draw = $req->CreateTextPlotter();
	    }
//	    $draw->CreateMessage("Error", "No Data");
	    $draw->CreateMessage("Error", $error);
	    $id = $draw->Save();
	    $error = false;
	} catch (ADEIException $ex) {
		$ex->logInfo(NULL, $draw);
	}
    }
}

if (!$error) {
    $res = DRAW::Display($id);
    if (!$res) {
    }
}


?>