<?php

$DEFAULT_PROPS = array (
	"db_mask" => 0,
	"experiment" => "0-0",
	"window" => "0"
);


class REQUESTList implements Iterator {
 var $props;
 var $list;
 var $cl;
 
 var $cur;
 
 function __construct(&$props, &$list, $cl = NULL) {
    $this->props = &$props;

    $this->list = &$list;

    $this->cur = false;
    
    if ($cl) $this->cl = &$cl;
    else $this->cl = "REQUEST";
 }
 
 function IsEmpty() {
    if ($this->list) return false;
    return true;
 }

    // false - is not known
 function GetSize() {
    return sizeof($this->list);
 }
  
 function CreateDataRequest() {
    return new DATARequest($this->props);
 }
 
 function rewind() {
    reset($this->list);
    $this->cur = current($this->list);
 }
 
 function current() {
    $props = $this->props;

	// To avoid confusing
    if (isset($this->cur['db_group'])) {
	unset($props['db_mask']);
    } elseif (isset($this->cur['db_name'])) {
	unset($props['db_mask']);
	unset($props['db_group']);
    } elseif (isset($this->cur['db_server'])) {
	unset($props['db_mask']);
	unset($props['db_group']);
	unset($props['db_name']);
    }
    
    foreach ($this->cur as $key => $value) {
	$props[$key] = $value;
    }

    return new $this->cl($props);
 }
 
 function key() {
    return key($this->list);
 }
 
 function next() {
    $this->cur = next($this->list);
 }

 function valid() {
    return $this->cur?true:false;
 }
}

class REQUESTListConstructor implements Iterator {
 var $list;
 var $func;
 
 function __construct(REQUESTList $list, $func) {
    $this->list = $list;
    $this->func = $func;
 }
  
 function rewind() {
    $this->list->rewind();
 }
 
 function current() {
    $req = $this->list->current();
    return call_user_func(array($req, $this->func));
 }
 
 function key() {
    return $this->list->key();
 }
 
 function next() {
    $this->list->next();
 }

 function valid() {
    return $this->list->valid();
 }
}



class BASICRequest {
 var $props;

 const ENCODING_DEFAULT = 0;
 const ENCODING_XML = 1;
 const ENCODING_JSON = 2;
 const ENCODING_LABVIEW = 3;
 const ENCODING_TEXT = 4;

 function __construct(&$props = NULL) {
/*    $f = fopen("/tmp/xxx.props", "a+");
    fwrite($f, print_r($_GET, true));
    fwrite($f, print_r($_POST, true));
    fclose($f);*/
    
    if ($props === NULL) {
	if (isset($_GET["db_server"])) $this->props = $_GET;
	else if (isset($_POST["db_server"])) $this->props = $_POST;
	else if (isset($_POST["props"])) return $this->props = json_decode(stripslashes($_POST['props']), true);
	else if (isset($_GET["module"])||isset($_GET["setup"])) $this->props = $_GET;
	else if (isset($_POST["module"])||isset($_POST["setup"])) $this->props = $_POST;
	else if ($_GET) $this->props = $_GET;
	else if ($_POST) $this->props = $_POST;
	else $this->props = array();
    } else {
	if (is_array($props)) $this->props = $props;
	else { 
	    $this->props = array();

	    if ($props) {
		$prop_array = preg_split("/&/", $props);
		foreach ($prop_array as $prop_eq) {
		    $prop = preg_split("/=/", $prop_eq);
		    $this->props[$prop[0]] = $prop[1];
		}
	    }
	}
    }

/*		
    foreach ($DEFAULT_PROPS as $prop => $value) {
	if (!isset($this->props[$prop]))
	    $this->props[$prop] = $value;
    }	
*/
 }
 
 static function GetResponseEncoding($default = false) {
    switch (strtolower($_REQUEST['encoding'])) {
	case "labview":
	    return BASICRequest::ENCODING_LABVIEW;
	case "xml":
	    return BASICRequest::ENCODING_XML;
	case "json":
	    return BASICRequest::ENCODING_JSON;
	case "text":
	    return BASICRequest::ENCODING_TEXT;
	default:
	    if ($default === false) 
	        return BASICRequest::ENCODING_DEFAULT;
	    return $default;
    }
 }

 static function GetGlobalOption($opt, $default = false) {
//    $opt = strtoupper($opt);
    if (isset($GLOBALS[$opt])) return $GLOBALS[$opt];
    return $default;
 }
  
 function GetProp($prop, $default = false) {
    if (isset($this->props[$prop])) return $this->props[$prop];
    return $default;
 }
 
 function SetProp($prop, $value = false) {
    $this->props[$prop] = $value;
 }
 
 function ParseProps($props) {
    if ($props) {
      if (!is_array($props)) {
	$props = preg_split("/(&amp;|\s|&)/", $props);
	$override = array();
	foreach ($props as $prop) {
	    if (preg_match("/^([^=]+)=(.*)$/", $prop, $m)) {
	        $override[$m[1]] = $m[2];
	    }
	}
	return $override;
      } else {
	return $props;
      }
    } 
    
    return array();
 }

 function CombineProps($props, $override = NULL, $override2 = NULL) {
    if ($override) {
	$override = $this->ParseProps($override);
	$override2 = $this->ParseProps($override2);

	return array_merge($props, $override, $override2);
    }
    return $props;
 }

 function GenerateQueryString($props) {
    global $ADEI_SETUP;
    
    $props['setup'] = $ADEI_SETUP;
    $first = 1;
    $query = "";

    foreach ($props as $name => $value) {
	if ($first) $first = 0;
	else $query .= "&";
	
	$query .= $name . "=" . urlencode($value);
    }
    
    return $query;
 }
 
 function GetFilteredProps(array $filter, $exclude = false) {
    if ($exclude) {
	$props = $this->props;
	foreach ($filter as $i) {
	    unset($props[$i]);
	}
    } else {
	$props = array();
	foreach ($filter as $i) {
	    if (isset($this->props[$i])) {
		$props[$i] = $this->props[$i];
	    }
	}
    }

    return $props;
 }

 
 function GetProps($override = NULL, $override2 = NULL) {
    return $this->CombineProps($this->props, $override, $override2);
 }
 
 function GetGroupProps($override = NULL, $override2 = NULL) {
    return $this->CombineProps(
	$this->GetFilteredProps(array("db_server", "db_name", "db_group")),
	$override, $override2
    );
 }
 
 function GetQueryString($ext = NULL, $ext2 = NULL) {
    $props = $this->GetProps($ext, $ext2);
    return $this->GenerateQueryString($props);
 }

 function GetGroupQueryString($ext = NULL, $ext2 = NULL) {
    $props = $this->GetGroupProps($ext, $ext2);
    return $this->GenerateQueryString($props);
 }
 
 function GetTimeFormat() {
    $format = $this->GetProp("time_format", "unix");
    switch ($format) {
	case "unix":
	    return false;
	break;
	case "iso":
	    return "c";
	break;
	case "text":
	    return "d M Y, H:i:s";
	break;
    }
    return $format;
 }
 
 function CreateImageHelper() {
    global $ADEI;
    $ADEI->RequireClass("welcome", true);
    return new WELCOME($this);
 }
 
 function CreateTextPlotter() {
    global $ADEI;
    $ADEI->RequireClass("drawtext");
    return new DRAWText($this);
 }
 
 function CreateSearcher() {
    global $ADEI;
    $ADEI->RequireClass("search");
    return new SEARCH($this);
 }
 
 function CreateView($view, $opts) {
    global $ADEI;
    $ADEI->RequireClass("view");
    $ADEI->RequireClass("views/$view");
    return new $view($this, $opts);
 }

 function CreateServerRequest() {
    return new SERVERRequest();
 }
 
 function CreateSourceRequest() {
    return new SOURCERequest();
 }
 
 function CreateGroupRequest() {
    return new GROUPRequest();
 }

 function CreateControlGroupRequest() {
    return new CONTROLGroupRequest();
 }
 
 function CreateDataRequest() {
    return new DATARequest();
 } 

 function CreateResponse(array &$result = NULL, $error = false) {
    $encoding = $this->GetResponseEncoding(REQUEST::ENCODING_XML);
    
    switch ($encoding) {
     case REQUEST::ENCODING_JSON:
	if ($error) {
	    echo json_encode(array("error" => $error));
	} else {
	    echo json_encode(array_merge(array("error" => 0), $result));
	}
     break;
     default:
        $xslt = $this->GetProp('xslt');
	$time_format = $this->GetTimeFormat();
	return ADEI::EncodeToStandardXML($error, $result, $xslt, array(
	    'time_format' => $time_format
	));
    }
    
    return 0;
 }
}

class REQUEST extends BASICRequest {
 var $opts = false;
 var $opts_custom = false;
 
 const LIST_ALL = 0x0001;
 const LIST_WILDCARDED = 0x0002;
 const NEED_INFO = 0x0004;
 const NEED_COUNT = 0x0008;
 const NEED_ITEMINFO = 0x0010;
 const NEED_AXISINFO = 0x0020;
 const ONLY_AXISINFO = 0x0040;
 const LIST_VIRTUAL = 0x0100;           // Include Virtual Groups into the List
 const LIST_COMPLEX = 0x0200;           // Include Complex Groups into the List
 const LIST_CUSTOM = 0x0400;            // Include Custom Items into the Full List (always listed if in the mask)
 const SKIP_UNCACHED = 0x0800;
 const SKIP_CHECKS = 0x1000;
 const SKIP_GENERATED = 0x2000;
 
 const CONTROL = 0x4000;
 
 const FLAG_MASK = 0xFFFF;

 const READER_FORBID_CACHEREADER = 1;
 
 function __construct(&$props = NULL) {
    global $DEFAULT_PROPS;

    parent::__construct($props);
 }

 function GetServerConfig() {
    global $READER_DB;
    global $ADEI_VIRTUAL_READERS;

    if (isset($this->props['db_server'])) {
	$srvid = $this->props['db_server'];
	if (isset($READER_DB[$srvid]))
	    return $READER_DB[$srvid];
	elseif (isset($ADEI_VIRTUAL_READERS[$srvid]))
	    return $ADEI_VIRTUAL_READERS[$srvid];
	    throw new ADEIException(translate("Invalid server name is specified: \"%s\"", $srvid));    
    } else
	throw new ADEIException(translate("The data source server is not specified"));
 }

 function GetSources($flags = 0) {
    global $READER_DB;

    $srvlist = $this->GetServerList($flags);
    
    $list = array();
    foreach ($srvlist as $srvid => &$srv) {
	if ((($flags&REQUEST::LIST_ALL)==0)&&(isset($this->props['db_name']))) {
		// Check database existence (DS)
	    $db = $this->props['db_name'];
	    $list[$srvid . "__" . $db] = array(
		'db_server' => $srvid,
		'db_name' => $db
	    );
	} else {
	    try {
		$req = new SERVERRequest($srv);
		$dblist = $req->GetDatabaseList($flags);
//		$reader = $req->CreateReader();
//		$dblist = $reader->GetDatabaseList($flags/*&REQUEST::LIST_WILDCARDED*/);

		foreach (array_keys($dblist) as $db) {
		    $list[$srvid . "__" . $db] = array(
			'db_server' => $srvid,
			'db_name' => $db
		    );
		}

//		unset($reader);
	    } catch (ADEIException $ae) {
		// Just skipping source
#		throw $ae;
	    }
	}
    }

    return new REQUESTList($this->props, $list, "SOURCERequest");
 }
 
 function GetGroups($flags = 0) {
    $list = array();

    if ((($flags&REQUEST::LIST_ALL)==0)&&(isset($this->props['db_group'])))
	$filter = $this->props['db_group'];
    else
	$filter = false;

    $slist = $this->GetSources($flags);
    foreach ($slist as $sid => $sreq) {

	$glist = $sreq->GetGroups(NULL, $flags&~REQUEST::LIST_ALL);
	foreach ($glist as $gid => $greq) {
	    if (($filter)&&($gid != $filter)) continue;
	    
	    $list[$sid . "__" . $gid] = $greq->props;
	}
    }

    return new REQUESTList($this->props, $list, "GROUPRequest");
 }

 function ComposeSourceName($srvname, $dbname) {
    return "$srvname -- $dbname";
 }
 
 function ComposeGroupName($srvname, $dbname, $grname) {
    return "$srvname -- $dbname -- $grname";
 }

 function GetServerList($flags = 0) {
    global $READER_DB;
    global $ADEI_VIRTUAL_READERS;
    
    if (!is_array($READER_DB))
	throw new ADEIException(translate("No data sources is configured"));
    
    $list = array();

    foreach ($READER_DB as $db_id => &$db) {
	if ($flags&REQUEST::NEED_INFO) {
    	    $list[$db_id] = $db;
	} else {
    	    $list[$db_id] = array();
	}
        $list[$db_id]["name"] = $db["title"];
        $list[$db_id]["db_server_name"] = $db["title"];
        $list[$db_id]["db_server"] = $db_id;
    }

	/* Accepting all virtuals for now */
    if ($flags&REQUEST::SKIP_UNCACHED) {
	$cache = new CACHEDB();
	$cached_servers = $cache->ListCachedServers();

        foreach (array_keys($list) as $key) {
	    if (!in_array($list[$key]['db_server'], $cached_servers)) {
		unset($list[$key]);
	    }
	}
    } 

    
    if (($flags&REQUEST::LIST_VIRTUAL)&&(is_array($ADEI_VIRTUAL_READERS))) {
	foreach ($ADEI_VIRTUAL_READERS as $db_id => &$db) {
	    if ($flags&REQUEST::NEED_INFO) {
    		$list[$db_id] = $db;
	    } else {
    		$list[$db_id] = array();
	    }
	    
	    $list[$db_id]["name"] = $db["title"];
    	    $list[$db_id]["db_server_name"] = $db["title"];
    	    $list[$db_id]["db_server"] = $db_id;
	    $list[$db_id]["virtual"] = true;
	}
    }
    
    return $list;
 }

 function GetSourceList($flags = 0) {
    $list = array();

    $slist = $this->GetSources($flags);
    foreach ($slist as $sid => $sreq) {
	$sinfo = $sreq->GetSourceInfo();
        $list[$sid] = array_merge($sinfo, array(
		'name' => $this->ComposeSourceName(
		    $sinfo['db_server_name'],
		    $sinfo['db_name_name']
		)
	    ));
    }
    
    return $list;
 }
 
 function GetGroupList($flags = 0) {
    $list = array();

    if ((($flags&REQUEST::LIST_ALL)==0)&&(isset($this->props['db_group'])))
	$filter = $this->props['db_group'];
    else
	$filter = false;

    $slist = $this->GetSources($flags);
    foreach ($slist as $sid => $sreq) {
	$sinfo = $sreq->GetSourceInfo();
    
	$glist = $sreq->GetGroupList($flags&~REQUEST::LIST_ALL);
	
	foreach ($glist as $gid => $ginfo) {
	    if (($filter)&&($gid != $filter)) continue;
	    
	    $list[$sid . "__" . $gid] = array_merge($sinfo, $ginfo, array(
		'name' => $this->ComposeGroupName(
		    $sinfo['db_server_name'],
		    $sinfo['db_name_name'],
		    $ginfo['db_group_name']
		)
	    ));
	}
    }
    
    return $list;	
 }
 
 function GetOptions() {
    if ($this->opts) return $this->opts;
    else {
	$this->opts = new OPTIONS($this);
	return $this->opts;
    }
 } 

 function GetGroupOptions(LOGGROUP &$grp = NULL) {
    if (($grp)&&($grp->gid != $this->props['db_group'])) {
	return $this->GetCustomOptions($this->props['db_server'], $this->props['db_name'], $grp->gid);
    }
    
    if (!$this->opts) {
	$this->opts = new OPTIONS($this);
    }
    
    return $this->opts;
 }

 function GetCustomOptions($server = false, $db = false, $group = false) {
    $id = $server . "__" . $db . "__" . $group;
    
    if (!$this->opts_custom) $this->opts_custom = array();
    else if (isset($this->opts_custom[$id])) return $this->opts_custom[$id];

	
    $req = array();
    if ($server !== false) $req["db_server"] = $server;
    if ($db !== false) $req["db_name"] = $db;
    if ($group !== false) $req["db_group"] = $group;
    
    $this->opts_custom[$id] = new OPTIONS($req);
    return $this->opts_custom[$id];
 }

 function GetOption($prop, $default = NULL) {
    $opts = $this->GetOptions();
    return $opts->Get($prop, $default);
 }
 
 function GetGroupOption($prop, LOGGROUP $grp = NULL, $default = NULL) {
    $opts = $this->GetGroupOptions($grp);
    return $opts->Get($prop, $default);
 }

 function GetCustomOption($prop, $server = false, $db = false, $group = false, $default = NULL) {
    $opts = $this->GetCustomOptions($server, $db, $group);
    return $opts->Get($prop, $default);
 }
 
 function LimitInterval(INTERVAL $ivl, LOGGROUP $grp = NULL) {
    $opts = $this->GetGroupOptions($grp);
    $limits = $opts->GetDateLimit();
    $ivl->Limit($limits[0], $limits[1]);
 }
 
 
 function CheckServer() {
    if (!isset($this->props['db_server'])) return false;
    return true;
 }
 function CheckSource() {
    if (!$this->CheckServer()) return false;
    if (!isset($this->props['db_name'])) return false;
    return true;
 }
 function CheckGroup() {
    if (!$this->CheckSource()) return false;
    if (!isset($this->props['db_group'])) return false;
    return true;
 }
 function CheckData() {
    if (!$this->CheckGroup()) return false;
//    if (!isset($this->props['db_mask'])) return false;
    return true;
 }
 
 function CreateServerRequest() {
    return new SERVERRequest($this->props);
 }
 function CreateSourceRequest() {
    return new SOURCERequest($this->props);
 }
 function CreateControlGroupRequest() {
    return new CONTROLGroupRequest($this->props);
 }
 function CreateGroupRequest() {
    return new GROUPRequest($this->props);
 }
 function CreateDataRequest() {
    return new DATARequest($this->props);
 }

 function GetLocationString() {
    if ($this->props['db_server']) {
	$res = "Server: \"" . $this->props['db_server'] . "\"";
	if ($this->props['db_name']) {
	    $res .= ", Database: \"" . $this->props['db_name'] . "\"";
	    if ($this->props['db_group']) {
		$res .= ", Group: \"" . $this->props['db_group'] . "\"";
	    }
	}
	return $res;
    }
    
    return "";
 }
 
 function GetAxisInfo($aid = false) {
    return $this->props;
 }
 
 function GetAxis($aid) {
    global $ADEI_AXES;
    
    if ($aid) {
	$aprop = $aid . "_axis";
	if (isset($this->props[$aprop. "_name"])) {
	    $info = $this->GetAxisInfo($aid);
	    return new DRAWAxis($info, $aid);
	}
	
	// UI Axes resolver should go here (only if UI already included)
	
	if (isset($ADEI_AXES[$aid])) {
	    return new DRAWAxis($ADEI_AXES[$aid]);
	}
    } else {
	$info = $this->GetAxisInfo();
	return new DRAWAxis($info);
    }
 }
 
 
 function GetAxes($aids) {
    $res = array();
    foreach ($aids as $aid) {
	$res[$aid] = $this->GetAxis($aid);
    }
    return $res;
 }

}

class SERVERRequest extends REQUEST {
 var $srv;

 function __construct(&$props = NULL) {
    global $READER_DB;
    global $ADEI_VIRTUAL_READERS;

    parent::__construct($props);
    
    if (isset($this->props["db_server"]))
	$srvid = $this->props["db_server"];
    else
	throw new ADEIException(translate("The data source server should be specified"));

    if (is_array($READER_DB[$srvid])) {
	$this->srv = $READER_DB[$srvid];
    } else if (is_array($ADEI_VIRTUAL_READERS[$srvid])) {
	$this->srv = $ADEI_VIRTUAL_READERS[$srvid];
	$this->srv['virtual'] = 1;
    } else {
	throw new ADEIException(translate("Invalid server identificator is supplied: \"%s\"", $srvid));
    }
 }
 
 function IsVirtual() {
    if ($this->srv['virtual']) return true;
    return false;
 }

 function GetServerInfo($flags = 0) {
    if ($flags&REQUEST::NEED_INFO) {
	$server = $this->srv;
	if (isset($this->props['db_name'])) $server['database'] = $this->props['db_name'];
	else unset($server['database']);
    } else {
	$server = array();
    }

    $server['db_server'] = $this->props['db_server'];
    $server['db_server_name'] = $this->srv['title'];
    if ($this->srv['virtual']) $server['virtual'] = true;
    
    return $server;
 }
 
 function GetServerTitle($flags = 0) {
    return $this->srv['title'];
 }
 
 function GetDatabaseList($flags = 0) {
    $reader = $this->CreateReader();
    $dblist = $reader->GetDatabaseList($flags);
    
    foreach ($dblist as $did => &$dinfo) {
	$dinfo['db_name'] = $did;
	$dinfo['db_name_name'] = $dinfo['name'];
    }

    if (($flags&REQUEST::SKIP_UNCACHED)&&(!$this->srv['virtual'])) {
	$cache = new CACHEDB();
	$cached_databases = $cache->ListCachedDatabases($this->props['db_server']);

        foreach (array_keys($dblist) as $key) {
	    if (!in_array($dblist[$key]['db_name'], $cached_databases)) {
		unset($dblist[$key]);
	    }
	}
    } 
    
    return $dblist;
 }
 
 function CreateReader($flags = 0) {
    global $ADEI;
    
    if ($this->srv['disconnected']) {
	if ($flags&REQUEST::READER_FORBID_CACHEREADER)
	    throw new ADEIException(translate("The data reader(%s) is disconnected", $this->srv['reader']), ADEIException::DISCONNECTED);

	$rdr = $this->CreateCacheReader();
	$rdr->DisableReaderAccess();
	return $rdr;
    } else if (($opts = $this->GetOptions())&&($opts->Get('use_cache_reader'))&&(!($flags&REQUEST::READER_FORBID_CACHEREADER))) {
	return $this->CreateCacheReader();
    } else {
	$reader = $this->srv['reader'];
    
	try {
	    $ADEI->RequireClass("readers/$reader", true);
	} catch (ADEIException $ae) {
	    if ($this->srv['reader'])
		throw new ADEIException(translate("Unsupported data reader is configured: \"%s\"", $this->srv['reader']));
	    else
		throw new ADEIException(translate("The data reader is not configured"));
	}
    }

    try {
	$rdr = new $reader($this);
    } catch (ADEIException $ae) {
	if ($flags&REQUEST::READER_FORBID_CACHEREADER) throw $ae;

	if ($opts->Get('overcome_reader_faults')) {
	    $rdr = $this->CreateCacheReader();
	    $rdr->DisableReaderAccess();
	} else throw $ae;    
    }
    
    return $rdr;
 }
 
 function CreateCacheReader(CACHEDB &$cache = NULL) {
    if ($this->srv['virtual']) {
	$reader = $this->srv['reader'];
    
	try {
	    ADEI::RequireClass("readers/$reader", true);
	} catch (ADEIException $ae) {
	    if ($this->srv['reader'])
		throw new ADEIException(translate("Unsupported data reader is configured: \"%s\"", $this->srv['reader']));
	    else
		throw new ADEIException(translate("The data reader is not configured"));
	}
	if (method_exists($reader, "ConvertToCacheReader")) {
	    $rdr = new $reader($this);
	    $rdr->ConvertToCacheReader($cache);
	    return $rdr;
	}
	if (method_exists($reader, "CreateCacheReader")) {
	    $rdr = new $reader($this);
	    return $rdr->CreateCacheReader($cache);
	}
    }
	
    return new CACHEReader($this, $cache);
 }

 function GetLocationString() {
    return "Server: \"" . $this->props['db_server'] . "\"";
 }
}


class SOURCERequest extends SERVERRequest {
 function __construct(&$props = NULL) {
    parent::__construct($props);

    if (!isset($this->props["db_name"]))
	throw new ADEIException(translate("The database should be specified"));
 }


 function GetDatabaseInfo($flags = 0) {
    return array(
	'db_name' => $this->props['db_name'],
	'db_name_name' => $this->props['db_name']
    );
 }

 function GetDatabaseTitle($flags = 0) {
    return $this->props['db_name'];
 }
 
 function GetSourceTitle($flags = 0) {
    return $this->GetServerTitle($flags) . " -- " . $this->GetDatabaseTitle($flags);
 }

 function GetSourceInfo($flags = 0) {
    return array_merge($this->GetServerInfo($flags), $this->GetDatabaseInfo($flags));
 }
 
 function GetGroups(READER $rdr = NULL, $flags = 0) {
    if ($flags&REQUEST::LIST_ALL) return REQUEST::GetGroups($flags);

    $list = array();
    if (isset($this->props['db_group'])) {
	$gid = $this->props['db_group'];
	$list[$gid] = array(
	    'db_group' => $gid,
	);
    } else {
	if ($rdr) $reader = $rdr;
	else $reader = $this->CreateReader();
	$list = $reader->GetGroupList($flags);
	foreach (array_keys($list) as $gid) {
	    $list[$gid] = array(
		'db_group' => $gid,
	    );
	}
    }
    return new REQUESTList($this->props, $list, "GROUPRequest");
 }

 function GetGroupList($flags = 0) {
    if ($flags&REQUEST::LIST_ALL) return REQUEST::GetGroupList($flags);

    $list = array();
    
    if (isset($this->props['db_group'])) {
	if ($rdr) $reader = &$rdr;
	else $reader = $this->CreateReader();

	$grp = $reader->CreateGroup($req = array(
	    'db_group' => $this->props['db_group']
	));
	
	$list = $reader->GetGroupInfo($grp, $flags);
    } else {
	if ($rdr) $reader = &$rdr;
	else $reader = $this->CreateReader();
	
	$list = $reader->GetGroupList($flags);
    }
    
    if (($flags&REQUEST::SKIP_UNCACHED)&&(!$this->srv['virtual'])) {
	$cache = new CACHEDB();
	$cached_groups = $cache->ListCachedGroups($this->props['db_name'], $this->props['db_server']);

#	print_r($list);
#	print_r($cached_groups);
        foreach (array_keys($list) as $key) {
	    if (!in_array($list[$key]['gid'], $cached_groups)) {
		unset($list[$key]);
	    }
	}
    } 

    if ($list) {
	foreach ($list as &$gr) {
	    $gr['db_group'] = $gr['gid'];
	    $gr['db_group_name'] = $gr['name'];
	}
    }
    
    
    return $list;
 }

 function GetAlarmList($flags = 0) {
    $reader = $this->CreateReader();
    $list = $reader->GetAlarmList(NULL, NULL, $flags);
    return $list;
 }
 
 function GetLocationString() {
    return "Server: \"" . $this->props['db_server'] . "\" Database: \"" . $this->props['db_name'] . "\"";
 }

 function CreateControlGroupRequest(LOGGROUP $grp = NULL) {
    if ($grp) {
	$props = $this->props;
	$props['conntrol_group'] = $grp->gid;
        return new CONTROLGroupRequest($props);
    }

    return parent::CreateControlGroupRequest();
 }

 function CreateGroupRequest(LOGGROUP $grp = NULL) {
    if ($grp) {
	$props = $this->props;
	$props['db_group'] = $grp->gid;
        return new GROUPRequest($props);
    }

    return parent::CreateGroupRequest();
 }
}

abstract class ITEMGroupRequest extends SOURCERequest {
 function __construct(&$props = NULL) {
    parent::__construct($props);
 }

 function GetGroupInfo($flags = 0) {
    if ($flags&REQUEST::CONTROL) {
	if (isset($this->props["control_group"])) {
	    return array(
		'control_group' => $this->props['control_group'],
		'control_group_name' => $this->props['control_group']
	    );
	} else if (isset($this->props["db_group"])) {
	    return array(
		'control_group' => $this->props['db_group'],
		'control_group_name' => $this->props['db_group']
	    );
	} else {
	    throw new ADEIException(translate("The Control Group is not specified"));
	}
    } else {
	if (!isset($this->props["db_group"]))
	    throw new ADEIException(translate("The Logging Group is not specified"));

	return array(
	    'db_group' => $this->props['db_group'],
	    'db_group_name' => $this->props['db_group']
	);
    }
 }

 function GetItemList($flags = 0) {
    $reader = $this->CreateReader();
    $list = $reader->GetItemList(NULL, NULL, $flags);
    return $list;
 }
 
 function GetMaskList($flags = 0) {
    $reader = $this->CreateReader();
    $list = $reader->GetMaskList($gr=NULL, $flags);
    return $list;
 }
 
 function GetMaskInfo($flags = 0) {
    return $this->props;
 }
 
 function CreateGroup(READER $rdr = NULL, $flags = 0) {
    if (!$rdr) $rdr = $this->CreateReader();

    $ginfo = $this->GetGroupInfo($flags);
    return $rdr->CreateGroup($ginfo, $flags);
 }

 function GetLocationString($flags = 0) {
    if ($flags&REQUEST::CONTROL) {
	return "Server: \"" . $this->props['db_server'] . "\" Database: " . $this->props['db_name'] . "\" Group: \"" . $this->props['control_group'] . "\"";
    } else {
	return "Server: \"" . $this->props['db_server'] . "\" Database: " . $this->props['db_name'] . "\" Group: \"" . $this->props['db_group'] . "\"";
    }
 }
}

class CONTROLGroupRequest extends ITEMGroupRequest {
 function __construct(&$props = NULL) {
    parent::__construct($props);

    if (!isset($this->props["control_group"]))
	throw new ADEIException(translate("The Control Group should be specified"));
 }
}

class GROUPRequest extends ITEMGroupRequest {
 function __construct(&$props = NULL) {
    parent::__construct($props);

    if (!isset($this->props["db_group"]))
	throw new ADEIException(translate("The Logging Group should be specified"));
 }


 function CreateCache(READER $rdr = NULL) {
    if ($this->srv['virtual']) {
	if (!$rdr) $rdr = $this->CreateReader();
	return $rdr->CreateCache();
    }

    return new CACHE($this, $rdr);
 }
 
 function CreateSimpleCacheSet(MASK $mask = NULL, CACHE $cache = NULL) {
    if (!$cache) $cache = $this->CreateCache();
    return new SIMPLECacheSet($cache, $mask);
 }
 
 function CreateCacheSet(REQUESTList $list) {
    return new REQUESTListCacheSet($list);
 }
 
 function CreateSimpleRequestSet(MASK $mask = NULL, LOGGROUP $grp = NULL, $type = "GROUPRequest") {
    $props = array();
    if ($grp) $props['db_group'] = $grp->GetProp();
    if ($mask) $props['db_mask'] = $mask->GetProp();
    return new REQUESTList($this->props, $list = array($props), $type);
 }
 
 function CreateRequestSet(REQUESTList $list) {
    return $list;
 }
 
 function CreateCacheUpdater(READER $rdr = NULL) {
    if ($this->srv['virtual']) {
	if (!$rdr) $rdr = $this->CreateReader();
	return $rdr->CreateCache(NULL, CACHE::CREATE_UPDATER);
    }

    return new CACHE($this, $rdr, CACHE::CREATE_UPDATER);
 }

 function GetCachePostfix() {
    $cache = new CACHE($this);
    return $cache->GetCachePostfix();
 }
}

class DATARequest extends GROUPRequest {
 function __construct(&$props = NULL) {
    parent::__construct($props);
 }

 function GetIntervalInfo() {
    return $this->props;
 }
 
 function CreateInterval(READER &$rdr = NULL, LOGGROUP &$grp = NULL, $flags = 0) {
    $iinfo = $this->GetIntervalInfo();
    return new INTERVAL($iinfo, $rdr, $grp, $flags);
 }
 
 function CreatePlotter() {
    global $ADEI;
    $ADEI->RequireClass("draw");

    return new DRAW($this);
 }
 
 function GetFormatInfo() {
    global $ADEI_SYSTEM_FORMATS;
    global $EXPORT_FORMATS;
    global $EXPORT_DEFAULT_FORMAT;
    
    $format = $this->props['format'];
    if ($format) {
	if ($EXPORT_FORMATS[$format])
	    return $EXPORT_FORMATS[$format];
	else if ($ADEI_SYSTEM_FORMATS[$format])
	    return $ADEI_SYSTEM_FORMATS[$format];
	else
	    throw new ADEIException(translate("Unsupported export fromat (%s) is specified", $format));
    } else if ($EXPORT_FORMATS[$EXPORT_DEFAULT_FORMAT]) 
	return $EXPORT_FORMATS[$EXPORT_DEFAULT_FORMAT];
    else 
	return array(
    	    title => "CSV",
	    extension => "csv"
	);
 }
 
 function CreateExporter(STREAMObjectInterface &$h = NULL, &$format = NULL) {
    global $ADEI;
    $ADEI->RequireClass("export");

    return new EXPORT($this, $h, $format);
 }
}


function adeiCreateExporter(STREAMHandler &$h = NULL) {
    $req = new DATARequest();
    return $req->CreateExporter($h);
}

?>