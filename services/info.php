<?php

global $ADEI;
global $ADEI_SETUP;
global $fix_time;

if (strtolower($_REQUEST['encoding']=='text')) $text = true;
else $text = false;

$target = $_REQUEST['target'];

try {
    $req = new REQUEST;
    $xslt = $req->GetProp('xslt');
} catch (ADEIException $ae) {
    $xslt = false;
}

switch ($target) {
 case "version":
    if (file_exists("VERSION")) {
	$stat = stat("VERSION");
	$date = date("Ymd", $stat["mtime"]);
			
	$version = file_get_contents("VERSION");
	if (preg_match("/^\s*([\d.]+)\s*(cvs)?/i", $version, $m)) {
    	    $version = $m[1];
	    if ($m[2]) $release = 0;
    	    else $release = 1;
	} else {
	    $release = 1;
	}
    
        if ($release) $title = $version;
	else $title = "$version-$date";
    } else {
	$error = translate("Version information is not available");
    }
 break;
 case "cache":
    try {
	$req = new GROUPRequest();
	$fix_time = $req->GetTimeFormat();
	$multi_mode = false;
    } catch (ADEIException $ae) {
	if (($_GET['db_server'])||($_GET['db_name'])||($_GET['db_group'])) {
	    throw $ae;
	}
	$multi_mode = true;
    }

    try {
        $cache = new CACHEDB();
        $flags = REQUEST::NEED_ITEMINFO|REQUEST::NEED_INFO|CACHE::TABLE_INFO;
	if ($multi_mode) {
	    $info = $cache->GetCacheList($flags);
	} else {
	    $info = array();
	    $postfix = $cache->GetCachePostfix($req->props['db_group'], $req->props['db_name'], $req->props['db_server']);
	    $info[0] = $cache->GetExtendedCacheInfo($postfix, $flags);
	}
    } catch (ADEIException $ex) {
	$ex->logInfo(NULL, $req);
	$error = xml_escape($ex->getInfo());
    }
 break;
 case "log":
    try {
	$cur = time();

	$interval = $_REQUEST['interval'];
	$filter = json_decode(stripslashes($_REQUEST['filter']), true);

	if (isset($_REQUEST['priority'])) {
	    $priority = $_REQUEST['priority'];
	} else {
	    $priority = LOG_ERR;
	}

	if (preg_match("/^(\d+)-(\d+)$/", $interval, $m)) {
	    $from = $m[1];
	    $to = $m[2];
    
	    if ($to > $cur) $to = $cur;
    
	    if ($to < $from) {
		$from = false;
		$to = false;
	    }
	} else if (preg_match("/^(\d+)$/", $interval, $m)) {
	    $to = $cur;
	    $from = $to - $interval;
	}
	
	if ((!$from)||(!$to)) {
	    $to = $cur;
	    $from = $to - $to%86400;
	} 

	$filter = array(
	    "setup" => $ADEI_SETUP
	);
	foreach (array("setup", "session", "source", "pid", "client") as $prop) {
	    if (isset($_REQUEST[$prop])) {
		if ($_REQUEST[$prop]==="") {
		    unset($filter[$prop]);
		} else {
		    $filter[$prop] = $_REQUEST[$prop];
		}
	    }
	}
	$logs = adeiGetLogs($from, $to, $priority, $filter);
    } catch (ADEIException $ex) {
	$ex->logInfo(NULL, $req);
	$error = xml_escape($ex->getInfo());
    }
 break;
 default:
    if (isset($_GET['target'])) $error = translate("Unknown info target (%s) is specified", $_GET['target']);
    else $error = translate("The info target is not specified");
}

function OutputCacheInfo($out, &$item) {
    global $fix_time;

    if (is_array($item['info']['tables'])) {
	$item['info']['resolutions'] = implode(',',array_keys($item['info']['tables']));
    }

    if ($fix_time) {
	if ($item['first']) $info['first'] = date($fix_time, $info['first']);
	if ($item['last']) $info['last'] = date($fix_time, $info['last']);
    }
    
    $properties = array("db_server", "db_name", "db_group", "supported");
    $info_properties = array("first", "last", "records", "dbsize", "resolutions", "width");

    $extra = "";
    foreach ($item as $prop => $value) {
	if (!in_array($prop, $properties)) continue;
        $extra .= " $prop=\"" . xml_escape($value) . "\"";
    }
    foreach ($item['info'] as $prop => $value) {
	if (!in_array($prop, $info_properties)) continue;
        $extra .= " $prop=\"" . xml_escape($value) . "\"";
    }
    fwrite($out, " <Value$extra/>\n");
}

function OutputLog($out, $item) {
    $item['time'] = $item['time']->format("Y-m-d\Th:i:s.uP");
    $extra = "";
    $properties = array("priority", "setup", "source", "session", "client", "message");
    foreach ($item as $prop => $value) {
	if (!in_array($prop, $properties)) continue;
        $extra .= " $prop=\"" . xml_escape($value) . "\"";
    }
    fwrite($out, " <Value$extra/>\n");
}

if ($text) {
    header("Content-type: text/plain");
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

    if ($error) echo translate("Error: %s\n", $error);
    else {
	switch ($target) {
	 case "version":
	    echo $title;
	 break;
	 case "log":
	    foreach($logs as $item) {
	        echo $item['time']->format("Y-m-d\Th:i:s.uP") . " " . html_entity_decode($item['message']) . "\n";
	    }
	 break;
	 default:
	    echo translate("Error: Target %s is not supported text encoding\n", $target);
	}
    }
    exit;
} else {
    header("Content-type: text/xml");
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
}

if ($xslt) {
    $temp_file = tempnam(sys_get_temp_dir(), 'adei_version.');
    $out = @fopen($temp_file, "w");
    if (!$out) $error = translate("I'm not able to create temporary file \"%s\"", $temp_file);
} else {
    $out = fopen("php://output", "w");
}

if (true) {
    fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    if ($error) {
	fwrite($out, "<result><Error>$error</Error></result>");
	$error = false;
    } else {
	fwrite($out, "<result>\n");

	switch ($target) {
	 case "version":
	    fwrite($out, " <version date=\"${date}\" release=\"${release}\" version=\"${version}\">${title}</version>\n");
	    fwrite($out, " <capabilities/>\n");
//	    fwrite($out, " <server>${_SERVER['SERVER_SIGNATURE']}</server>\n");
	    fwrite($out, " <php>" . phpversion() . "</php>\n");
	 break;
	 case "cache":
	    foreach ($info as &$item) {
	        OutputCacheInfo($out, $item);
	    }
	 break;
	 case "log":
	    foreach($logs as $log) {
		OutputLog($out, $log);
	    }
	 break;
	}
	fwrite($out, "</result>");
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

?>