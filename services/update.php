<?php

header("Content-type: application/json");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");


function TryPlot(DATARequest $req, &$draw, &$no_data) {
    try {
	$draw = $req->CreatePlotter();
        $draw->Create();
    } catch (ADEIException $ae) {
	$recovered = false;
	switch ($ae->getCode()) {
	    case ADEIException::PLOTTER_WINDOW_TOO_SMALL:
		if (!$req->GetProp('hide_axes')) {
		    $ae->logInfo(NULL, $draw?$draw:$req);
		    
		    $req->SetProp('hide_axes', 'Y');
		    TryPlot($req, $draw, $no_data);
		    return translate("To many Y-axes, hidden");
		}
	    break;
	    case ADEIException::NO_DATA:
	    case ADEIException::NO_CACHE:
	    case ADEIException::INVALID_REQUEST:
		if ($draw) {
		    try {
			$error = $ae->getInfo();
			$draw->CreateMessage("Error", $error);
			$no_data = true;
			return 0;
		    } catch (ADEIException $ex) {
			$ex->logInfo(NULL, $draw);
			throw $ae;
		    }
		}
	    break;
	}

	throw $ae;
    }
    return 0;
}

try {
    $req = new REQUEST();
    switch ($req->props['module']) {
     case "graph":
        $warning = 0;
	if ($req->CheckData()) {
	    $req = $req->CreateDataRequest();

	    $draw = NULL; 
	    $no_data = false;
	    $warning = TryPlot($req, $draw, $no_data);
	    $file = $draw->Save();
	    
	    if (!$no_data) $scale = $draw->GetScaleInfo();
	} else {
	    $draw = $req->CreateImageHelper();
	    $draw->Create();
	    $file = $draw->Save();
	    $no_data = true;
	}
     break;
     default:
        if ($req->props['module']) {
	    try {
		ADEI::RequireServiceClass("update", $req->props['module']);
		$loaded = true;
	    } catch (ADEIException $ae) {
		// modules without updates are perfectly OK
		$loaded = false;
	    }
	    
	    if ($loaded) {		
		if (!function_exists("ADEIServiceGetUpdateInfo")) {
		    throw new ADEIException(translate("Update code for module (%s) does not provide any suitable interface"));
		}
		
		$info = ADEIServiceGetUpdateInfo($req);
	    }
	}
    }
} catch(ADEIException $e) {
    $error = $e->getInfo();
    $e->logInfo(NULL, $req);	
}

if ($error) {
    echo json_encode(array("error" => $error));
} else {
    if ($draw) {
	if ($no_data) {
	    echo json_encode(array(
		"module" => "graph",
		"nodata" => true,
		"draw" => 1,
		"image" => $file
	    ));
	} else {
	    echo json_encode(array_merge(array(
		"error" => 0,
		"warning" => $warning,
		"module" => "graph",
		"draw" => 1,
		"image" => $file
	    ), $scale));
	}
    } else if ($info) {
	if (!$info['module']) $info['module'] = $req->props['module'];
	echo json_encode($info);
    } else {
	echo json_encode(array(
	    "error" => 0
	));
    }
}


?>