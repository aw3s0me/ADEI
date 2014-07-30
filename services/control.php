<?php
header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

global $ADEI;
global $fix_time;

define("DATA_MODE", 0);
define("ALARMS_MODE", 1);
define("STATUS_MODE", 2);
define("ERROR_MODE", 3);

$res = false;
$mode = DATA_MODE;

try {
    $req = $ADEI->CreateControlGroupRequest();
//    $enc = $req->GetResponseEncoding(REQUEST::ENCODING_XML);

    $xslt = $req->GetProp('xslt');
    $target = $req->GetProp('target');
    
    $status = "";

    $reader = $req->CreateReader();
    
    switch ($target) {
     case "get":
	$res = $reader->GetControls(); 
     break;
     case "get_data":
	$res = $reader->GetControlsFromData(); 
     break;
     case "set":
        $data = $reader->SetControls();
	$alarms = $reader->GetCurrentAlarms();
	if (($data)||($alarms)) $res = true;
	$status = "The new control values are set"; 
	$mode = STATUS_MODE;
     break;
     case "send":
        $reader->SetControls(NULL, NULL, $info=NULL, REQUEST::SKIP_CHECKS);
	$status = "The new control values are set"; 
	$mode = ERROR_MODE;
     break;
     case "alarms":
        $res = $reader->GetAlarmsDetailed();
	$mode = ALARMS_MODE;
     break;
     case "alarms_summary":
        $res = $reader->GetAlarms();
	$mode = ALARMS_MODE;
     break;
     case "alarms_current":
        $res = $reader->GetCurrentAlarms();
	$mode = ALARMS_MODE;
     break;
     case "status":
	$data = $reader->GetControls(NULL, NULL); 
	$alarms = $reader->GetCurrentAlarms();
	if (($data)||($alarms)) $res = true;
	$mode = STATUS_MODE;
     break;
     default:
	if (isset($_GET['target'])) $error = translate("Unknown control target (%s) is specified", $_GET['target']);
	else $error = translate("The control target is not specified");
    }

    if (($mode == ALARMS_MODE)||($mode == STATUS_MODE)) {
	$title = $reader->GetSourceTitle();
	$fix_time = $req->GetTimeFormat();
	
	if ($mode != STATUS_MODE) $alarms = $res;
	
	$arr = array();
	foreach ($alarms as $control) {
	    array_push($arr, $control);
	}
	
	if ($mode == STATUS_MODE) $alarms = $arr;
	else $res = $arr;
    }
} catch(ADEIException $ex) {
    $ex->logInfo(NULL, $reader?$reader:$req);
    $error = xml_escape($ex->getInfo());
}

function OutputResults($out, $res, $mode) {
    global $fix_time;

    switch ($mode) {
	case DATA_MODE:
	    $tag = "data";
	break;
	case ALARMS_MODE:
	    $tag = "alarms";
	break;
    }
    
    fwrite($out, "<$tag>");
    foreach ($res as $control) {
	if ($fix_time) {
	    if ($mode == ALARMS_MODE) {
	        if ($control['in']) $control['in'] = date($fix_time, $control['in']);
		if ($control['out']) $control['out'] = date($fix_time, $control['out']);
	    }

	    if ($mode == DATA_MODE) {
	        if ($control['timestamp']) $control['timestamp'] = date($fix_time, $control['timestamp']);
		if ($control['verified']) $control['verified'] = date($fix_time, $control['verified']);
		if ($control['obtained']) $control['obtained'] = date($fix_time, $control['obtained']);
	    }
	}

	$extra = "";
	foreach ($control as $prop => $value) {
	    $extra .= " $prop=\"" . xml_escape($value) . "\"";
	}
	fwrite($out, " <Value$extra/>\n");
    }
    fwrite($out, "</$tag>");
}


if ($xslt) {
    $temp_file = tempnam(sys_get_temp_dir(), 'adei_control.');
    $out = @fopen($temp_file, "w");
    if (!$out) $error = translate("I'm not able to create temporary file \"%s\"", $temp_file);
} else {
    $out = fopen("php://output", "w");
}

if ($out) {
    fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    if ($error) {
	fwrite($out, "<result><Error>$error</Error></result>");
	$error = false;
    } else if ($res !== false) {
	$extra = "";
        if ($title) $extra .= " title=\"" . xml_escape($title) . "\"";
	if ($status) $extra .= " status=\"" . xml_escape($status) . "\"";
    
	fwrite($out, "<result$extra>\n");
	if ($mode == STATUS_MODE) {
	    OutputResults($out, $data, DATA_MODE);
	    OutputResults($out, $alarms, ALARMS_MODE);
	} else {
	    OutputResults($out, $res, $mode);
	}
	fwrite($out, "</result>");
    } else if ($mode == ERROR_MODE) {
	echo "<result>Ok</result>\n";
    }
    fclose($out);
}

if (($xslt)&&(!$error)) {
    try {
	echo $ADEI->TransformXML($xslt, $temp_file);
    } catch (ADEIException $ex) {
	$ex->logInfo(NULL, $reader?$reader:$req);
	$error = $ADEI->EscapeForXML($ex->getInfo());
    }
    @unlink($temp_file);
}

if ($error) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<result><Error>$error</Error></result>";
}


#    $export = $req->CreateExporter();
#    $export->Export();
#} catch(ADEIException $ex) {

?>
