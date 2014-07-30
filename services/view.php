<?php

global $ADEI_TIMINGS;
global $EXPORT_SAMPLING_RATES;
global $EXPORT_FORMATS;

header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

global $ADEI_INFO_MODULES;

define("OUTPUT_LIST_MODE", 1);
define("OUTPUT_XML_MODE", 2);
define("OUTPUT_UPDATE_MODE", 3);


try {
    $req = new REQUEST($_GET);
    switch($req->props['type']) {
        case "":
        case "info":
            $modules = &$ADEI_INFO_MODULES;
            $argument = "infomod";
        break;
        default:
	    throw new ADEIException(translate("Unknown view type (%s) is specified", $_GET['target']));
    }

    switch($req->props['target']) {
        case 'list':
            $mode = OUTPUT_LIST_MODE;
        break;
        case 'update_options':
        case 'update':
            $mode = OUTPUT_UPDATE_MODE;
        break;
        default:
            $mode = OUTPUT_XML_MODE;
    }

    if ($mode != OUTPUT_LIST_MODE) {
	if (!isset($req->props[$argument])) 
	    throw new ADEIException(translate("The view should be specified"));
	
	$view_name = $req->props[$argument];
	if (!isset($modules[$view_name]))
	    throw new ADEIException(translate("Invalid view (%s) is specified", $view_name));
    }

    switch($req->props['target']) {
	case 'list':
	    $list = array();
	    foreach ($modules as $v => $info) {
	        $view = $req->CreateView($info['handler'], $info['opts']);
	        if ($view->IsApplicable()) {
	            array_push($list, array(
	                "value" => $v,
	                "name" => (isset($info['title'])?$info['title']:$view->title)
	            ));
	        }
	    }
	break;
/*	case 'update_options':
	    $xslt = strtolower($view_name) . "_form";
            $xslt_file = $ADEI->GetXSLTFile($xslt);
            if (!file_exists($xslt_file)) $xslt = "forms";
    
            $query = $req->GetQueryString(array("target"=>"get_options"));
            $json = array(
	        "xml" => "services/view.php?$query",
	        "xslt" => $xslt
            );
	break;
	case 'update':
	    $xslt = strtolower($view_name) . "_side";
            $xslt_file = $ADEI->GetXSLTFile($xslt);
            if (!file_exists($xslt_file)) $xslt = "null";
    
            $query = $req->GetQueryString(array("target"=>"get"));
            $json = array(
	        "xml" => "services/view.php?$query",
	        "xslt" => $xslt
            );
	break;*/
	case 'get_options':
	    $view = $req->CreateView($modules[$view_name]['handler'], $modules[$view_name]['opts']);
	    $list = $view->GetOptions();
	break;
	case 'get':
	    $view = $req->CreateView($modules[$view_name]['handler'], $modules[$view_name]['opts']);
	    $list = $view->GetView();
	break;
	default:
	    if (isset($_GET['target'])) $error = translate("Unknown view target (%s) is specified", $_GET['target']);
	    else $error = translate("The view target is not specified");
    }	    
    
    if ($list) {
        $obj = $req->GetProp("view_object", false);
        if ($obj) $list["object"] = $obj;
    }
} catch (ADEIException $ex) {
    $ex->logInfo(NULL, $reader?$reader:$req);
    $error = xml_escape($ex->getInfo());
}

$req->CreateResponse($list, $error);
/*
if ($mode != OUTPUT_UPDATE_MODE) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    if ($error) {
        echo "<result><Error>$error</Error></result>";
        return true;
    }
} else if ($error) {
    echo json_encode(array("error" => $error));
    return true;
}

echo "<result>";
switch($mode) {
    case OUTPUT_LIST_MODE:
	foreach ($list as $id => &$info) {
	        $extra = "";
		echo "<Value value=\"" . $id . "\"$extra name=\"" . xml_escape($info["name"]) . "\"/>";
	}
    break;
    case OUTPUT_UPDATE_MODE:
	echo json_encode($info);
    break;
    default:
}
echo "</result>";
*/
/*
else {
    echo "<result>";

    switch($mode) {
	case INFO_MODE:
	    foreach ($list as $id => &$info) {
	        $extra = "";
		if ($info['uid']) $extra .= " uid=\"{$info['uid']}\"";
		echo "<Value value=\"" . ($value_arg?$info[$value_arg]:$id) . "\"$extra name=\"" . xml_escape($info[$name_arg]) . "\"/>";
	    }
	break;
	case SOURCE_MODE:
	    foreach ($list as $id => &$info) {
		$extra = "";
		if (isset($info['db_server'])) $extra .= " db_server=\"{$info['db_server']}\"";
		if (isset($info['db_name'])) $extra .= " db_name=\"{$info['db_name']}\"";
		if (isset($info['db_group'])) $extra .= " db_group=\"{$info['db_group']}\"";
		if ($info['uid']) $extra .= " uid=\"{$info['uid']}\"";
		if ($info['virtual']) $extra .= " virtual=\"1\"";

		if ($flags&REQUEST::CONTROL) {
		    if ($info['read']) $extra .= " read=\"1\"";
		    if ($info['write']) $extra .= " write=\"1\"";
		    if ($info['sampling_rate']) $extra .= " sampling_rate=\"1\"";
		}

		echo "<Value value=\"$id\"$extra name=\"" . xml_escape($info['name']) . "\"/>";
	    }
	break;
	case PASSTHROUGH_MODE:
	    foreach ($list as $id => &$info) {
		$add_value = 1;
		
		$extra = "";
		foreach ($info as $prop => $value) {
		    if (($add_value)&&($prop == "value")) $add_value = 0;
		    $extra .= " $prop=\"" . xml_escape($value) . "\"";
		}
		
		if ($add_value) {
		    echo "<Value value=\"" . ($value_arg?$info[$value_arg]:$id) . "\"$extra/>";
		} else {
		    echo " <Value$extra/>\n";
		}
	    }
	break;
	default:
	    foreach($list as $id => &$info) {
	        $extra = "";
		if ($info['uid']) $extra .= " uid=\"{$info['uid']}\"";
		echo "<Value value=\"$id\"$extra name=\"" . xml_escape($info) . "\"/>";
	    }
    }
    
    echo "</result>";
}
*/
?>