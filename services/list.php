<?php

global $ADEI_TIMINGS;
global $EXPORT_SAMPLING_RATES;
global $EXPORT_FORMATS;

ADEI::RequireClass("export");
ADEI::RequireClass("draw");


header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");


define("INFO_MODE", 1);
define("SOURCE_MODE", 2);
define("PASSTHROUGH_MODE", 3);

$mode = 0;

    // For INFO_MODE
$value_arg = false;
$name_arg = "name";

if (isset($_GET['menu'])) $menu = true;
else $menu = false; 

$flags = 0;
if ($_GET['list_virtual']) $flags |= REQUEST::LIST_VIRTUAL;
if ($_GET['list_complex']) $flags |= REQUEST::LIST_COMPLEX;
if ($_GET['list_custom']) $flags |= REQUEST::LIST_CUSTOM;
if ($_GET['skip_uncached']) $flags |= REQUEST::SKIP_UNCACHED;
if ($_GET['info']) $flags |= REQUEST::NEED_INFO|REQUEST::NEED_AXISINFO;

try {


    switch($_GET['target']) {
	case 'servers':
	    $req = new REQUEST($_GET);
	    $list = $req->GetServerList($flags);

	    if (!count($list)) $error = translate("No server is configured");
	    
/*
	    if ($menu) {
		$list["-1"] = array(
		    "id" => "-1",
		    "name" => translate("Custom")
		);
	    }
*/
	    $mode = SOURCE_MODE;
	break;
	case 'databases':
	    $req = new SERVERRequest($_GET);
	    $list = $req->GetDatabaseList($flags|REQUEST::LIST_WILDCARDED);

	    if (!count($list)) $error = translate("No database is found");

	    $mode = SOURCE_MODE;
	break;
	case 'sources':
	    $req = new REQUEST($_GET);
	    $list = $req->GetSourceList($flags);

	    if (!count($list)) $error = translate("No sources is found");

	    $mode = SOURCE_MODE;
	break;
	case 'current_databases':
	    $req = new SERVERRequest($_GET);
	    $list = $req->GetDatabaseList($flags);

	    if (!count($list)) $error = translate("No database is found");

	    $mode = SOURCE_MODE;
	break;
	case 'cgroups':
	    $flags|=REQUEST::CONTROL;
	case 'groups':
	    try {
	        $req = new SOURCERequest($_GET);
	    } catch (ADEIException $aer) {
		$req = new REQUEST($_GET);
		$menu = false;
	    }
	    
	    $list = $req->GetGroupList($flags);
/*
	    if (($flags&REQUEST::CONTROL)==0) {
		$dbgroup = array(
		    'id' => "-1",
		    'name' => _("Combined Group")
		);
		
		if ($req->srv['virtual']) {
		    array_unshift($list, $dbgroup);
		} else {
		    array_push($list, $dbgroup);
		}
	    }
*/
	    if (!count($list)) $error = translate("No LogGroups are found");

	    $mode = SOURCE_MODE;
	break;
/*	case 'group_sources':
	    $req = new REQUEST();
	    $list = $req->GetGroupList($flags);

	    if (!count($list)) $error = translate("No LogGroups is defined");

	    $mode = SOURCE_MODE;
	break;*/
	case 'masks':
	    $req = new GROUPRequest($_GET);
	    $list = $req->GetMaskList(REQUEST::NEED_INFO);

	    if ($menu) {
		$list["standalone_item"] = array(
		    "mask" => "-1",
		    "name" => translate("Standalone Item")
		);
	    }
	    
	    $value_arg = "mask";
	    $mode = INFO_MODE;
	break;
	case 'alarms':
	    $req = new SOURCERequest($_GET);
	    $list = $req->GetAlarmList($flags);
	    $mode = PASSTHROUGH_MODE;
	break;
	case 'controls':
	    $flags|=REQUEST::CONTROL;
	    $req = new CONTROLGroupRequest($_GET);
	    $list = $req->GetItemList($flags);

	    $mode = SOURCE_MODE;
	break;
	case 'items':
	    $req = new GROUPRequest($_GET);
	    $list = $req->GetItemList($flags);

	    $mode = SOURCE_MODE;
	break;
	case 'experiments':
	    $req = new SOURCERequest($_GET);

	    $reader = $req->CreateReader();
	    $elist = $reader->GetExperimentList();

	    $list = array(
		"-" => _("All Measurements"),
		"*-*" => _("Everything")
	    );

	    foreach ($elist as $db) {
		$list[$db['start'] . '-' . $db['stop']] = $db['name'];
	    }

	    if ($menu) {
		$list["0"] = _("Custom");
	    }
	break;
	case 'axes':
	    try {
		$req = new SERVERRequest($_GET);
		$rdr = $req->CreateReader();
		$axes = $rdr->CreateAxes();
	    } catch (ADEIException $ae) {
		$req = new REQUEST($_GET);
		$axes = new GRAPHAxes($req, $flags);
	    }
	    $list = $axes->GetAxesInfo();
	    $mode = PASSTHROUGH_MODE;
	break;
	case 'window_modes':
	    $list = array(
		"0" => _("All")
	    );

	    foreach ($ADEI_TIMINGS as $opt => $value) {
		$list["$value"]=$opt;
	    }
	    
	    if ($menu) {
		$list["-1"] = _("Custom");
	    }
	break;
	case 'aggregation_modes':
	    $list = array(
		CACHE::TYPE_AUTO => _("Auto"),
		CACHE::TYPE_MINMAX => _("MIN-MAX"),
		CACHE::TYPE_MEAN => _("MEAN")
	    );
	break;
	case 'plot_modes':
	    $list = array(
		DRAW::PLOT_STANDARD => _("Standard"),
		DRAW::PLOT_CUSTOM => _("Custom")
	    );
	break;
	case 'marks_modes':
	    $list = array(
	        DRAW::MARKS_DEFAULT => _("Auto"),
		DRAW::MARKS_ALWAYS => _("Always"),
	        DRAW::MARKS_GAPS => _("On Missing Data"),
		DRAW::MARKS_NEVER => _("Disabled")
	    );
	break;
	case 'gaps_modes':
	    $list = array(
		DRAW::SHOW_NONE => _("Disabled"),
		DRAW::SHOW_EMPTY => _("On Missing Data"),
		DRAW::SHOW_POINTS => _("Show Data Points"),
		DRAW::SHOW_GAPS => _("Show Missing Data")
	    );
	break;
	case 'interpolation_modes':
	    $list = array(
		_("Off"),
		_("On")
	    );
	break;
	case 'formats':
	    $list = array();
	    foreach ($EXPORT_FORMATS as $id => $val) {
		if ((($val['title'])||($val['hidden'] === false))&&(!$val['hidden'])) {
		    $list[$id] = $val['title'];
		}
	    }
	break;
	case 'sampling_rates':
	    $list = array(
		0 => _("No Resampling")
	    );
	    
	    foreach ($EXPORT_SAMPLING_RATES as $id => $val) {
		$list["$val"] = $id;
	    }
	break;
	case 'export_mask_modes':
	    $list = array(
		EXPORT::MASK_STANDARD => _("Current Mask"),
		EXPORT::MASK_GROUP =>  _("Log Group"),
		EXPORT::MASK_SOURCE => _("Database"),
		EXPORT::MASK_COMPLETE => _("Complete")
	    );
	break;
	case 'export_window_modes':
	    $list = array(
		0 => _("Current Window"),
		1 =>  _("Whole Experiment"),
		-1 => _("Selection"),
	    );
	break;
	default:
	    if (isset($_GET['target'])) $error = translate("Unknown list target (%s) is specified", $_GET['target']);
	    else $error = translate("The list target is not specified");
    }
	    
} catch (ADEIException $ex) {
    $ex->logInfo(NULL, $reader?$reader:$req);
    $error = xml_escape($ex->getInfo());
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
if ($error) echo "<result><Error>$error</Error></result>";
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
	    if ((is_array($list))&&($list)) {
		reset($list); $first = current($list);
		if (!is_array($first)) $list = array($list);
	    }
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

		if ($flags&REQUEST::NEED_INFO) {
		    $used_keys = array("db_server", "db_name", "db_group", "uid", "virtual", "name", "id");
		    if ($flags&REQUEST::CONTROL) array_push($used_keys, "read", "write", "sampling_rate");
		    foreach ($info as $key=>$value) {
			if (in_array($key, $used_keys)||(is_array($value))) continue;
			$extra .= " $key=\"$value\"";
		    }
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

?>