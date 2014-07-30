<?php

class OPTIONS {
 var $props;		// const, modification is not allowed
 var $options;
    
 function __construct(&$req = NULL, &$override = NULL) {
    global $OPTIONS;

    if ($req instanceof REQUEST) $props = &$req->props;
    else $props = &$req;
    $this->props = &$props;
    
    $this->options = array();
    if ($override) $this->options[] = &$override;
    
    if (($req)&&(isset($props['db_server']))) {
	$sname = $props['db_server']; $slen = strlen($sname);
	
	if (isset($props['db_name'])) {
	    $dname = "__" . $props['db_name']; $dlen = strlen($dname);

	    if (isset($props['db_group'])) {
	        $gname = $props['db_group'];
	    }
	}

	foreach (array_keys($OPTIONS) as $regex) {
	    if (!strncmp($regex, $sname, $slen)) {
		$len = strlen($regex);
		if ($len == $slen) $sopts = &$OPTIONS[$regex];
		elseif (($dname)&&(!strcmp(substr($regex, $slen, $dlen), $dname))) {
		    $pos = strpos($regex, "__", $slen + $dlen);
		    
		    if (($pos === false)&&($len == ($slen + $dlen))) $dopts = &$OPTIONS[$regex];
		    else if (($gname)&&(!strcmp(substr($regex, $pos + 2), $gname))&&($pos == ($slen+$dlen))) {
			$gopts = &$OPTIONS[$regex];
			if (($sopts)&&($dopts))	break;
		    }
		}
	    }
	}
	
	if ($gopts) $this->options[] = &$gopts;
        if ($dopts) $this->options[] = &$dopts;
	if ($sopts) $this->options[] = &$sopts;
    }
    if (isset($OPTIONS['default'])) $this->options[] = &$OPTIONS['default'];
 }
 
 function Get($prop, $default=NULL) {
    foreach ($this->options as &$opts) {
	if (isset($opts[$prop])) return $opts[$prop];
    }
    return $default;
 }


 function GetDateLimit($default_from = false, $default_to = false) {
    $date_limit = $this->Get('date_limit');
    if ($date_limit) {
	if (is_array($date_limit)) {
	    if (is_int($date_limit[0])) $start_date = $date_limit[0];
	    else $start_date = ADEI::ParseDate($date_limit[0]);
	    if (is_int($date_limit[1])) $end_date = $date_limit[1];
	    else $end_date = ADEI::ParseDate($date_limit[1]);
	} else {
	    if (is_int($date_limit)) $start_date = $date_limit;
	    else $start_date = ADEI::ParseDate($date_limit);
	    $end_date = $default_to;
	}
    } else {
	if (is_string($default_from))
	    $start_date = ADEI::ParseDate($default_from);
	else 
	    $start_date = $default_from;
	$end_date = $default_to;
    }

    return array($start_date, $end_date);
 }

 static function GetSpecific($prop, $default=NULL, $db_server = false, $db_name = false, $db_group = false) {
    global $OPTIONS;
    
    if ($db_server !== false) {
	$path = $db_server;
	if ($db_name !== false) {
	    $path .= "__$db_name";
	    if ($db_group !== false) {
		$path .= "__$db_group";
	    }
	}
    } else {
	$path = "default";
    }
    
    if (isset($OPTIONS[$path][$prop])) return $OPTIONS[$path][$prop];

    return $default;
 }
 
 function ListConfiguredDatabases() {
    global $OPTIONS;

    if ((!$this->props)||(!isset($this->props['db_server']))) {
	throw new ADEIException(translate("The server should be specified in order to list configured databases"));
    }
 
    $list = array();

    $name = $this->props['db_server'] . "__";
    $namelen = strlen($name);

    foreach (array_keys($OPTIONS) as $regex) {
	if (!strncmp($regex, $name, $namelen)) {
	    array_push($list, substr($regex, $namelen));
	}
    }

    return array_unique($list);
 }

 function ListConfiguredGroups() {
    global $OPTIONS;

    if ((!$this->props)||(!isset($this->props['db_server']))||(!isset($this->props['db_server']))) {
	throw new ADEIException(translate("The server and database should be specified in order to list configured groups"));
    }
 
    $list = array();

    $name = $this->props['db_server'] . "__" . $this->props['db_name'] . "__";
    $namelen = strlen($name);

    foreach (array_keys($OPTIONS) as $regex) {
	if (!strncmp($regex, $name, $namelen)) {
	    $dbgr = substr($regex, $namelen);
	    list($db) = preg_split("/__/", $dbgr, 2);
	    array_push($list, $db);
	}
    }

    return $list;
 }
}


?>