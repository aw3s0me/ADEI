<?php
class CACHEDB extends ADEIDB {
 var $req;

 var $default_db_server, $default_db_name, $default_db_group;
 var $default_postfix;

 var $md5_postfix;
 var $use_subseconds;

 var $base_mask;	// Limits available channels, used in conjuction with VIRTUALReader
 
 const TYPE_AUTO = 0;
 const TYPE_MINMAX = 1;
 const TYPE_MEAN = 2;
 const TYPE_ALL = 3;

 const TABLE_INFO = 0x00010000;		// Information about cache tables
 const NEED_REQUESTS = 0x00020000;	// Create appropriate requests on listing
 const FIND_BROKEN = 0x00040000;	// Find incomplete caches

 function __construct(SOURCERequest $props = NULL) {
    parent::__construct();
    
    if ($props) $this->req = $props;
    else $this->req = new REQUEST();

    if (isset($this->req->props['db_server']))
	$this->default_db_server = $this->req->props['db_server'];
    else 
	$this->default_db_server = false;
    if (isset($this->req->props['db_name']))
	$this->default_db_name =  $this->req->props['db_name'];
    else 
	$this->default_db_name = false;
    if (isset($this->req->props['db_group']))
	$this->default_db_group = $this->req->props['db_group'];
    else 
	$this->default_db_group = false;

    if (($this->default_db_server !== false)&&($this->default_db_name !== false)&&($this->default_db_group !== false)) {
	$this->default_postfix = $this->GetCachePostfix();	
    } else {
	$this->default_postfix = false;
    }

    if (isset($this->req->props['base_mask'])) {
	$this->base_mask = new MASK($this->req->props['base_mask']);
	if ($this->base_mask->IsFull()) $this->base_mask = NULL;
    } else {
	$this->base_mask = NULL;
    }
 }
 

/*
 function __destruct() {

    This could cause problems in the following scenario:
	$cache = new CACHE();
	$cache = new CACHE(); 
    It looks like destructor is called after constructor of new class. To 
    correct problem use:
	$cache = new CACHE();
	unset($cache);
	$cache = new CACHE(); 

    if ($this->dbh) {
	@mysql_close($this->dbh);
	unset($this->dbh);
    }
 }
*/

 static function FindAggregator($aggregator) {
    $name = "CACHEDB::TYPE_" . strtoupper($aggregator);
    if (defined($name)) return constant($name);
    else if ((!$aggregator)||(($aggregator>0)&&($aggregator<=CACHEDB::TYPE_ALL))) return $aggregator;
    
    throw new ADEIException(translate("Unknown aggregation mode (%s) is specified", $aggregator));
 }
 
 function ParseSimplePostfix($postfix, &$m, $prefix="") {
    $pos = strlen($prefix);

    $m[1] = substr($postfix, $pos);

    $pos += 2;
    if (strncmp($postfix, "{$prefix}__",  $pos)) return false;

    $next = strpos($postfix, "__", $pos);
    if ($next === false) return false;
    
    $m[2] = substr($postfix, $pos, $next - $pos);

    $pos =  $next + 2;
    $next = strpos($postfix, "__", $pos);
    if ($next === false) return false;

    $m[3] = substr($postfix, $pos, $next - $pos);
    $m[4] = substr($postfix, $next + 2);
    $m[0] = $postfix;
    return true;
 }
 
 function ParsePostfix($postfix, $prefix="", $allow_incomplete = false) {
    if (preg_match("/^$prefix(__md5_(.*))$/", $postfix, $m)) {
	$supported = true;
	    
	$postfix = $m[1];
	$md5 = $m[2];
	    
	$mres = @mysql_query("SELECT `postfix` FROM md5 WHERE hash = '" . $postfix . '\'', $this->dbh);
	if ($mres) $mrow = mysql_fetch_row($mres);
	else $mrow = false;
	
	if (($mrow)&&($this->ParseSimplePostfix($mrow[0], $m))) {
	    $srv = $m[2];
	    $db = $m[3];
	    $group = $m[4];
	} else if ($allow_incomplete) {
	    $srv = false;
	    $db = false;
	    $group = false;
	} else {
	    throw new ADEIException(translate("Supplied MD5 postfix (%s) is not listed in md5 table", $postfix));
	}

	unset($mres);
    } else if ($this->ParseSimplePostfix($postfix, $m, $prefix)) {
	$supported = true;
	$md5 = false;

	$postfix = $m[1];
	$srv = $m[2];
	$db = $m[3];
	$group = $m[4];
    } else if (preg_match("/^$prefix(__.*)$/", $postfix, $m)) {
	if ($allow_incomplete) {
	    $supported = false;
	    $postfix = $m[1];
	    $md5 = false;
	    $srv = false;
	    $db = false;
	    $group = false;
	} else {
	    throw new ADEIException(translate("Unsupported postfix (%s) is supplied", $postfix));
	}
    } else {
	if ($postfix) {
	    throw new ADEIException(translate("Unsupported postfix (%s) is supplied", $postfix));
	} else {
	    throw new ADEIException(translate("Postfix should be specified"));
	}
    }
    
    return array(
	'postfix' => $postfix,
	'md5' => $md5,
	'db_server' => $srv,
	'db_name' => $db,
	'db_group' => $group,
	'supported' => $supported
    );
 }
 
 function CreateServerRequest(&$postfix_or_info) {
    if (is_array($postfix_or_info))
	$info = &$postfix_or_info;
    else if ($postfix_or_info)
	$info = $this->ParsePostfix($postfix_or_info);
    else
	$info = $this->ParsePostfix($this->default_postfix);
	
    return new SERVERRequest($req = array(
	    'db_server' => $info['db_server']
    ));
	
 }
 
 function CreateGroupRequest(&$postfix_or_info) {
    if (is_array($postfix_or_info))
	$info = &$postfix_or_info;
    else if ($postfix_or_info)
	$info = $this->ParsePostfix($postfix_or_info);
    else
	$info = $this->ParsePostfix($this->default_postfix);

    return new GROUPRequest($req = array(
	'db_server' => $info['db_server'],
	'db_name' => $info['db_name'],
	'db_group' => $info['db_group']
    ));
 }
 
 function GetExtendedCacheInfo(&$postfix_or_row, $flags = 0) {
    global $READER_DB;
    
    if (is_array($postfix_or_row)) {
	$prefix = "cache0";
	$string = $postfix_or_row[0];
    } else if ($postfix_or_row) {
	$prefix = "";
	$string = $postfix_or_row;
    } else {
	$prefix = "";
	$string = $this->default_postfix;
    }

    $info = $this->ParsePostfix($string, $prefix, true);
    $postfix = $info['postfix'];
    $srv = $info['db_server'];
    $db = $info['db_name'];
    $group = $info['db_group'];
    $supported = $info['supported'];
    
    if ($flags&CACHE::NEED_REQUESTS) {
	    if ($supported) {
		try {
		    $req = $this->CreateGroupRequest($info);

		    $info['server'] = $READER_DB[$srv]['title'];
		    
		    try {
			$reader = $req->CreateReader();
		    } catch (ADEIException $ae) {
			$sreq = $this->CreateServerRequest($info);
			$reader = $sreq->CreateReader();
		    }
		    
		    $info['reader'] = get_class($reader);
		    
		    if ($reader->server['disconnected'])
			$info['disconnected'] = true;

		    $db_list = $reader->GetDatabaseList();
		    if (isset($db_list[$db])) {
			$info['database'] = $db_list[$db]['name'];
			
			if ($req) {
			    $group_list = $reader->GetGroupList();
			    if (isset($group_list[$group])) {
				$info['group'] = $group_list[$group]['name'];
			    } else {
				$req = false;
			    }
			}
		    } else {
			$req = false;
		    }
		    
		} catch (ADEIException $ae) {
		    if ($info['server']) $info['disconnected'] = true;
		    $req = false;
		}
	    } else $req = false;
	    
	    $info['req'] = $req;
    }

    if (($flags&CACHEDB::TABLE_INFO)&&($supported)&&($cache_info=$this->GetCacheInfo($postfix))) {
	    $info['info'] = $cache_info;
	    
	    if (is_array($postfix_or_row)) {
	        $info['info']['records'] = $postfix_or_row[4];
		$info['info']['dbsize'] = $postfix_or_row[6] + $postfix_or_row[8];
	    }
	    
	    $tables = array();

	    $mres = @mysql_query("SHOW TABLE STATUS LIKE 'cache%$postfix'", $this->dbh);
	    if ($mres) {
		$size = 0;
		while ($mrow = mysql_fetch_row($mres)) {
		    $size += $mrow[6] + $mrow[8];
		    if (preg_match("/^cache(\d+)$postfix$/", $mrow[0], $m)) {
			$id = $m[1];
			
			if (!$id) $info['info']['records'] = $mrow[4];

			$tables[$id] = $this->GetTableInfo($id, $postfix);
			if (!is_array($tables[$id])) $tables[$id] = array();

			$tables[$id]['resolution'] = $id;
			$tables[$id]['records'] = $mrow[4];
			$tables[$id]['dbsize'] = $mrow[6] + $mrow[8];
		    }
		}
		unset($mres);
		
		if ($size) {
		    $info['info']['dbsize'] = $size;
		}
		
		if ($tables) {
		    ksort($tables);
		    $info['info']['tables'] = $tables;
		} else {
		    $info['info']['tables'] = array(
			0 => $info['info']
		    );
		}
	    }
    }

    if (($flags&REQUEST::NEED_ITEMINFO)&&($supported)) {
	    $mres = mysql_query("SHOW COLUMNS FROM `cache0$postfix`", $this->dbh);
	    if ($mres) {
		$mrow = mysql_fetch_row($mres);
		if ($mrow[0] == 'id') {
		    $mrow = mysql_fetch_row($mres);
		} else {
		    $info['info']['outdated'] = true;
		}

		if ($mrow[0] == 'time') {
		    $mrow = mysql_fetch_row($mres);
		    if ($mrow[0] == 'ns') {
			$info['info']['ns'] = true;
			$lastrow = "";
//			$item_number = 0;
		    } else {
			$info['info']['ns'] = false;
			$lastrow = $mrow[0];
//			$item_number = 1;
		    }

		    while ($mrow = mysql_fetch_array($mres)) $lastrow = $mrow[0];
		    if (preg_match("/(\d+)/", $lastrow, $m)) {
			$info['info']['width'] = $m[1] + 1;
		    } else {
			$info['info']['width'] = 0;
		    }
		    
/*			
		    $mrow = mysql_fetch_row($mres);
		    if ($mrow[0] != 'subcache') $item_number++;
		    
		    while ($mrow = mysql_fetch_row($mres)) {
			if ($mrow[0]) $item_number++;
		    }
		    $info['info']['width'] = $item_number;
*/
		}
	    }	   
#	    echo $postfix . " - " . $info['info']['width'] . "<br/>";


	    if ($req) {
		$info['info']['items'] = $reader->GetItemList();
	    }
    }
	
    return $info;
 }
 
 function GetCacheList($flags = 0) {
    $list = array();

    if ($flags&CACHEDB::TABLE_INFO)
	$res = @mysql_query("SHOW TABLE STATUS LIKE 'cache0\\_\\_%'", $this->dbh);
    else
	$res = @mysql_query("SHOW TABLES LIKE 'cache0\\_\\_%'", $this->dbh);
    
    $postfixes = array();
    
    while ($row = mysql_fetch_row($res)) {
	$info = $this->GetExtendedCacheInfo($row, $flags);
	array_push($list, $info);

	array_push($postfixes, $info['postfix']);
    }

    if ($flags&CACHE::FIND_BROKEN) {
	$res = @mysql_query("SHOW TABLES LIKE 'cache%\\_\\_%'", $this->dbh);
	if ($res) {
	    $all_postfixes = array();
    	    while ($row = mysql_fetch_row($res)) {
		if (preg_match("/^cache\d+(__.*)$/", $row[0], $m)) {
		    array_push($all_postfixes, $m[1]);
		}
	    }

	    $incomplete = array_diff(array_unique($all_postfixes), $postfixes);
	    
	    foreach ($incomplete as $postfix) {
		$info = $this->GetExtendedCacheInfo($postfix, $flags);
		$info['incomplete'] = true;
		array_push($list, $info);
	    }
	}
    }
        
    return $list;
 }

 function CreateReader($postfix = false) {
    if (!$postfix) $postfix = $this->default_postfix;
    
    $req = $this->CreateGroupRequest($postfix);
    return $req->CreateReader();
 }
 
 function Drop($postfix) {
    $lock = new LOCK("cache" . $postfix);
    $lock->Lock(LOCK::BLOCK|LOCK::ALL);

    try {	
	$resolutions = $this->ListResolutions($postfix);
	sort($resolutions);
    
	foreach ($resolutions as $res) {
	    $table = $this->GetTableName($res, $postfix);
	    $query = "DROP TABLE `$table`;";
	    if (!mysql_query($query, $this->dbh)) {
    		throw new ADEIException(translate("Failed to drop CACHE (%s). Request '%s' is failed with error: %s", "cache*_$postfix", $query,  mysql_error($this->dbh)));
	    }
	}
    } catch (ADEIException $ae) {
	$lock->UnLock();    
	throw $ae;
    }
    
    $lock->UnLock();    
 }
 
 function Rewidth($postfix, $report = false) {
    $lock = new LOCK("cache" . $postfix);
    $lock->Lock(LOCK::BLOCK|LOCK::ALL);

    try {
	$current = $this->GetCacheWidth($postfix);

	$reader = $this->CreateReader($postfix);
	$actual = $reader->GetGroupSize();
    
	if ($current == $actual) {
	    $lock->UnLock();    
	    return;
	}

	if ($report) {
	    echo translate("Resizing %s from %d to %d", "cache*_$postfix", $current, $actual);
	    if ($report === "html") echo "<br/>";
	    echo "\n";
	}
	
	if ($current < $actual) {
	    $spec0 = "";
	    $spec = "";
	    
	    for ($i = $current; $i < $actual; $i++) {
		if ($i > $current) {
		    $spec0 .= ", ";
		    $spec .= ", ";
		}
		$spec0 .= "ADD `v$i` DOUBLE";
		$spec .= "ADD `min" . $i . "` DOUBLE, ADD `max" . $i . "` DOUBLE, ADD `mean" . $i . "` DOUBLE";
	    }
	} else {
	    $spec0 = "";
	    $spec = "";

	    for ($i = $actual; $i < $current; $i++) {
		if ($i > $actual) {
		    $spec0 .= ", ";
		    $spec .= ", ";
		}
		$spec0 .= "DROP `v$i`";
		$spec .= "DROP `min" . $i . "`, DROP `max" . $i . "`, DROP `mean" . $i . "`";
	    }
	}

	$resolutions = $this->ListResolutions($postfix);
	rsort($resolutions);
    
	foreach ($resolutions as $res) {
	    if ($res) {
		$width = $this->GetTableWidth($postfix, $res);
		if ($width != $current) {
		    if ($width == $actual) {
			if ($report) {
    			    echo translate("Resolution %lu is already converted", $res);
			    if ($report === "html") echo "<br/>";
			    echo "\n";
			}
			continue;
		    }
		    throw new ADEIException(translate("CACHE table (%s) have unexpected size: %d (converting from %d to %d)", "cache${res}${postfix}", $width, $current, $actual));
		}

		$query = "ALTER TABLE `cache${res}${postfix}` $spec";
	    } else {
		$query = "ALTER TABLE `cache0$postfix` $spec0";
	    }
	
	    if ($report) {
		echo translate("Converting resolution %lu", $res);
		if ($report === "html") echo "<br/>";
		echo "\n";
	    }

#	    echo $query . "<br/>\n";
	    if (!mysql_query($query, $this->dbh)) {
		throw new ADEIException(translate("The CACHE (%s) resizing request is not complete. Request '%s' is failed with error: %s", "cache*_$postfix", $query,  mysql_error($this->dbh)));
	    }
	
	}
    } catch (ADEIException $ae) {
	$lock->UnLock();    
	throw $ae;
    }
    
    $lock->UnLock();    
 }

 function ListCachedServers() {
    $groups = array();
    $md5 = array();
    
    $res = @mysql_query("SHOW TABLES LIKE 'cache0__%'", $this->dbh);
    if (!$res)
	throw new ADEIException(translate("SHOW TABLES request is failed on CACHE database, error: %s", mysql_error($this->dbh)));
	
    while ($row = mysql_fetch_row($res)) {
	if (strncmp($row[0], "cache0__md5_", 12)) {
	    $parts = explode("__", $row[0], 3);
	    array_push($groups, $parts[1]);
	} else {
	    array_push($md5, substr($row[0], 6));
	}
    }
    
    if (sizeof($md5) > 0) {
	$res = mysql_query("SELECT `hash`, `postfix` FROM md5", $this->dbh);
	if (!$res) 
	    throw new ADEIException(translate("SELECT request on MD5 table is failed, error: %s", mysql_error($this->dbh)));

	while ($row = mysql_fetch_row($res)) {
	    if (in_array($row[0], $md5)) {
		$parts = explode("__", $row[1], 3);
		array_push($groups, $parts[1]);
	    }
	}
    }

    return array_unique($groups);    
 }


 function ListCachedDatabases($db_server = false) {
    if (!$db_server) {
	if (!$db_server) $db_server = $this->default_db_server;

	if (!$db_server)
	    throw new ADEIException(translate("Server name and database should be specified to list available groups"));
    }

    $groups = array();
    
    $res = @mysql_query("SHOW TABLES LIKE 'cache0__${db_server}__%'", $this->dbh);
    if (!$res)
	throw new ADEIException(translate("SHOW TABLES request is failed on CACHE database, error: %s", mysql_error($this->dbh)));
	
    while ($row = mysql_fetch_row($res)) {
	$parts = explode("__", $row[0], 4);
	array_push($groups, $parts[2]);
    }
    
    $md5 = array();
    $res = mysql_query("SHOW TABLES LIKE 'cache0__md5_%'", $this->dbh);
    while ($row = mysql_fetch_row($res))
	array_push($md5, substr($row[0], 6));
    
    if (sizeof($md5) > 0) {
	$res = mysql_query("SELECT `hash`, `postfix` FROM md5 WHERE postfix LIKE '__${db_server}__%'", $this->dbh);
	if (!$res) 
	    throw new ADEIException(translate("SELECT request on MD5 table is failed, error: %s", mysql_error($this->dbh)));

	while ($row = mysql_fetch_row($res)) {
	    if (in_array($row[0], $md5)) {
		$parts = explode("__", $row[1], 4);
		array_push($groups, $parts[2]);
	    }
	}
    }

    return array_unique($groups);    
 }
 
 function ListCachedGroups($db_name = false, $db_server = false) {
	// DS, only listing databases having level0 CACHE
    if ((!$db_server)||(!$db_name)) {
	if (!$db_server) $db_server = $this->default_db_server;
	if (!$db_name) $db_name = $this->default_db_name;

	if ((!$db_server)||(!$db_name))
	    throw new ADEIException(translate("Server name and database should be specified to list available groups"));
    }

    $groups = array();
    
    $prefix = "cache0__" . $db_server . "__" . $db_name . "__";
    $prefix_length = strlen($prefix);
    
    $res = @mysql_query("SHOW TABLES LIKE 'cache0__${db_server}__${db_name}%'", $this->dbh);
    if (!$res)
	throw new ADEIException(translate("SHOW TABLES request is failed on CACHE database, error: %s", mysql_error($this->dbh)));
	
    while ($row = mysql_fetch_row($res)) {
	array_push($groups, substr($row[0], $prefix_length));
    }
    
    $md5 = array();
    $res = mysql_query("SHOW TABLES LIKE 'cache0__md5_%'", $this->dbh);
    while ($row = mysql_fetch_row($res))
	array_push($md5, substr($row[0], 6));
    
    if (sizeof($md5) > 0) {
	$prefix = "__" . $db_server . "__" . $db_name . "__";
	$prefix_length = strlen($prefix);

	$res = mysql_query("SELECT `hash`, `postfix` FROM md5 WHERE postfix LIKE '__${db_server}__${db_name}__%'", $this->dbh);
	if (!$res) 
	    throw new ADEIException(translate("SELECT request on MD5 table is failed, error: %s", mysql_error($this->dbh)));

	while ($row = mysql_fetch_row($res)) {
	    if (in_array($row[0], $md5))
		array_push($groups, substr($row[1], $prefix_length));
	}

	return array_unique($groups);
    }
    
    return $groups;    
 }

 function ListResolutions($postfix) {
    if (!$postfix) $postfix = $this->default_postfix;
    
    $tables = array();
    
    $mres = @mysql_query("SHOW TABLES LIKE 'cache%$postfix'", $this->dbh);
    if ($mres) {
	while ($mrow = mysql_fetch_row($mres)) {
	    if (preg_match("/^cache(\d+)$postfix$/", $mrow[0], $m)) {
		array_push($tables, $m[1]);
	    }
	}
    }
    
    return $tables;
 }

 function GetGroupPostfix($db_group = false, $db_name = false, $db_server = false) {
    if ((!$db_server)||(!$db_name)||(!$db_group)) {
	if ($db_server === false) $db_server = $this->default_db_server;
	if ($db_name === false) $db_name = $this->default_db_name;
	if ($db_group === false) $db_group = $this->default_db_group;
	if (($db_server === false)||($db_name === false)||($db_group === false))
	    throw new ADEIException(translate("Server name, database and group is required to construct CACHE postfix"));
    }

    if (!$this->GetPostfixType($db_group, $db_name, $db_server)) {
	$db_group = preg_replace("/[^\w\d_]/","",$db_group);
    }

    return "__${db_server}__${db_name}__${db_group}";
 }    

 function MD5CreateTable() {
    if (!@mysql_query("CREATE TABLE `md5` (`hash` CHAR(64) PRIMARY KEY,  `postfix` VARCHAR(4096) NOT NULL, UNIQUE INDEX(postfix(64)))", $this->dbh)) {
	throw new ADEIException(translate("Creation of system tables (md5) within CACHE database is failed") . " (" . mysql_error($this->dbh) . ")");
    }
 }

 function MD5CreateEntry($postfix) {
    $query = "INSERT INTO `md5` VALUES('" . $this->default_postfix . "' , '" . mysql_real_escape_string($postfix) . "')";
    if (!@mysql_query($query, $this->dbh)) 
	throw new ADEIException(translate("Can not insert entry into the MD5 caching table, error") . ": " . mysql_error($this->dbh));
 }

 function MD5Check() {
    $res = @mysql_query("SELECT `postfix` from `md5` WHERE `hash` = '" . $this->default_postfix . '\'', $this->dbh);
    if (!$res) {
	switch (mysql_errno($this->dbh)) {
	    case CACHE::MYSQL_ER_NO_SUCH_TABLE:
		$this->MD5CreateTable();
		$row = false;
	    break;
	    default:
		throw new ADEIException(translate("Can not query CACHE md5 table, error") . ": " . mysql_error($this->dbh));
	}
	
    } else {
	$row = mysql_fetch_row($res);
    }
    
    $postfix = $this->GetGroupPostfix();

    if ($row === false) 
	$this->MD5CreateEntry($postfix);
    elseif (strcmp($row[0], $postfix))
	throw new ADEIException(translate("Unlucky, the MD5 checksums of two groups (\"%s\" and \"%s\") are coinciding. This is not handled automaticaly yet. Please, add/alter md5 suffix to the newly added group.", $row[0], $postfix));
 }

 function GetPostfixType($db_group = false, $db_name = false, $db_server = false) {
	if ((!$db_server)||(!$db_name)||(!$db_group)) {
	    if ($db_server === false) $db_server = $this->default_db_server;
	    if ($db_name === false) $db_name = $this->default_db_name;
	    if ($db_group === false) $db_group = $this->default_db_group;
	    
	    if (($db_server === false)||($db_name === false)||($db_group === false))
		throw new ADEIException(translate("Server name, database and group is required to construct CACHE postfix"));
	}

	return $this->req->GetCustomOption('use_md5_postfix', $db_server, $db_name, $db_group);
 }
 
 function GetCachePostfix($db_group = false, $db_name = false, $db_server = false) {
    $postfix = $this->GetGroupPostfix($db_group, $db_name, $db_server);

    if (isset($this->md5_postfix)) $md5 = $this->md5_postfix;
    else $md5 = $this->GetPostfixType($db_group, $db_name, $db_server);
    
    if ($md5) {
	return "__md5_" . (is_string($md5)?$md5:"") .  md5($postfix);
    } else return $postfix;
 }
 
 function SetDefaultPostfix($postfix = false) {
    if ($postfix) {
	if ($postfix instanceof REQUEST) {
	    $postfix = $postfix->GetProps();
	}
	
	if (is_array($postfix)) {
	    if (isset($postfix['db_server'])) $this->default_db_server = $postfix['db_server'];
	    if (isset($postfix['db_name'])) $this->default_db_name = $postfix['db_name'];
	    if (isset($postfix['db_group'])) $this->default_db_group = $postfix['db_group'];
	    $this->md5_postfix = $this->GetPostfixType($this->default_db_group, $this->default_db_name, $this->default_db_server);
	    $this->default_postfix = $this->GetCachePostfix();
	} else {
	    $this->default_postfix = $postfix;
	}
    } else {
	$this->default_postfix = $this->GetCachePostfix();
    }
 }
 
 function GetTableName($resolution = 0, $postfix = false) {
    return "cache" . $resolution . ($postfix?$postfix:$this->default_postfix);
 }

 function DetectSubseconds($postfix = false) {
    $table = $this->GetTableName(0, $postfix);
    $res = mysql_query("SHOW COLUMNS FROM `$table`", $this->dbh);
    if (!$res) {
	switch (mysql_errno($this->dbh)) {
	    case CACHEDB::MYSQL_ER_NO_SUCH_TABLE:
    		throw new ADEIException(translate("The CACHE table '%s' is empty", $table), ADEIException::NO_CACHE);
	    default:
    		throw new ADEIException(translate("There is problem executing 'SHOW COLUMNS' request on CACHE table '%s'", $table));
	}
    }
    
    mysql_fetch_array($res); 		// skipping id column
    mysql_fetch_array($res); 		// skipping time column
    $row = mysql_fetch_array($res);	// possibly ns column
    if (!strcmp($row[0],"ns")) {
	if (!$postfix) $this->use_subseconds = true;
	return true;
    }

    if (!$postfix) $this->use_subseconds = false;
    return false;
 }
 
 function GetTableWidth($postfix = false, $resolution = 0) {
    $table = $this->GetTableName($resolution, $postfix);

    $res = mysql_query("SHOW COLUMNS FROM `$table`", $this->dbh);
    if (!$res) {
	switch (mysql_errno($this->dbh)) {
	    case CACHEDB::MYSQL_ER_NO_SUCH_TABLE:
    		throw new ADEIException(translate("The CACHE table '%s' is empty", $table), ADEIException::NO_CACHE);
	    default:
    		throw new ADEIException(translate("There is problem executing 'SHOW COLUMNS' request on CACHE table '%s'", $table));
	}
    }
    
    while ($col = mysql_fetch_array($res)) $lastcol = &$col['Field'];

    if (!preg_match("/(\d+)/", $lastcol, $m)) 
        throw new ADEIException("There is problem finding out the number of channels. Last cache column is '$lastcol'");
	
    return ($m[1] + 1);
 }
 
 function GetCacheWidth($postfix = false) {
    return $this->GetTableWidth($postfix, 0);
 }
 
 function GetCacheIDs($postfix = false) {
	// DS, check if we do not have a level0 CACHE
	$size = $this->GetCacheWidth($postfix);
	
	if ($this->base_mask) {
	    if ($this->base_mask->ids) return range(0, sizeof($this->base_mask->ids) - 1);
	    else $mask->ids = array();
	} else {
	    if ($size>0) return range(0, $size - 1);
	    else return array();
	}
 }

 function CreateCacheInterval($postfix = false, array &$iinfo = NULL, $flags = 0) {
    if (!is_array($iinfo)) {
	if ($this->req instanceof DATARequest)
	    $iinfo = $this->req->GetIntervalInfo();
    }

    $ivl = new INTERVAL($iinfo, NULL, NULL, $flags);
    $ivl->ApplyCache($this);

    return $ivl;
 }
 
 function CreateInterval(array &$iinfo = NULL, $flags = 0) {
    return $this->CreateCacheInterval(false, $iinfo, $flags);
 }

 function CreateCacheMask($postfix = false, array &$minfo = NULL, $flags = 0) {
    if (!$minfo) {
	if ($this->req instanceof GROUPRequest)
	    $minfo = $this->req->GetMaskInfo();
    }
    
    $mask = new MASK($minfo);
    if ($mask->ids === false) {
/*
	if ($this->base_mask) {
	    if ($this->base_mask->ids) $mask->ids = range(0, sizeof($this->base_mask->ids) - 1);
	    else $mask->ids = array();
	} else 
*/
	$mask->ids = $this->GetCacheIDs($postfix);
    }
    return $mask;
 }

 function CreateMask(array &$minfo = NULL, $flags = 0) {
    return $this->CreateCacheMask(false, $minfo, $flags);
 }

 function GetTableInfo($resolution = 0, $postfix = false, $flags = 0) {
    $table = $this->GetTableName($resolution, $postfix);
    
    $req = "EXTENDED_UNIX_TIMESTAMP(MIN(time)) AS first, EXTENDED_UNIX_TIMESTAMP(MAX(time)) AS last";
    if ($flags&REQUEST::NEED_COUNT) $req .= ", COUNT(time) AS records";

    $res = mysql_query("SELECT $req FROM `$table`", $this->dbh);
    if (!$res) {
	switch (mysql_errno($this->dbh)) {
	    case CACHE::MYSQL_ER_NO_SUCH_TABLE:
		return false;
	    break;
	    default:
		throw new ADEIException(translate("SELECT request '%s' on CACHE table '%s' is failed. MySQL error: %s", $req, $table, mysql_error($this->dbh)));
	}
    }
    return mysql_fetch_assoc($res);
 }
 
 function GetCacheInfo($postfix = false, $flags = 0) {
	// DS, check if we do not have a level0 CACHE
    return $this->GetTableInfo(0, $postfix, $flags);
 }

 function GetInfo($flags = 0) {
    return $this->GetCacheInfo(false, $flags);
 } 

 function GetCacheItemList($postfix = false, MASK $mask = NULL, $flags = 0) {
    $items = array();
    
    if ($this->base_mask) {
	$mask = $this->base_mask->Superpose($mask);
    }
    
    if (($mask)&&($mask->ids)) {
	foreach ($mask->ids as $i => $id) {
	    $items[$i] = array(
		"id" => $id,
		"name" =>  "Channel" . ($id+1),
	    );
	}
    } else {
        $size = $this->GetCacheWidth($postfix);

	for ($i=0;$i<$size;$i++) {
	    $items[$i] = array(
		"id" => $i,
		"name" =>  "Channel" . ($i+1),
	    );
	}
    }
    
    return $items;
 }

 function GetItemList(MASK &$mask = NULL, $flags = 0) {
    return $this->GetCacheItemList(false, $mask, $flags);
 }
 
 function SQLTime($time) {
    return strftime("%Y%m%d%H%M%S", floor($time));

/* 
    Subseconds are not supported by MySQL Datetime type
    if (is_int($time))
	return strftime("%Y%m%d%H%M%S", $time);
    else {
	return strftime("%Y%m%d%H%M%S", floor($time)) . substr(sprintf("%.6F", $time - floor($time)), 1);
    }
*/
 }
 
 function CreateTable($resolution, $items, $postfix = false) {
    if (($this instanceof CACHE) == false)
	throw new ADEIException(translate("CreateTable calls are only allowed on CACHE object"));

    $name = $this->GetTableName($resolution, $postfix);
    
    if ($resolution > 0) {
	for ($i = 0; $i < $items; $i++)
	    $query .= ", `min" . $i . "` DOUBLE, `max" . $i . "` DOUBLE, `mean" . $i . "` DOUBLE";

	/*, `duration` INT, `complete` BOOL,*/
	if (!mysql_query("CREATE TABLE `$name` (`time` DATETIME PRIMARY KEY, `n` BIGINT, `missing` INT" .  $query . ") ENGINE = MYISAM", $this->dbh)) {
	    throw new ADEIException(translate("Unable to create cache table, error") . ": " . mysql_error($this->dbh));
	}
    } else {	
	$this->CreateStoredProcedures();

	for ($i = 0; $i < $items; $i++)
	    $query .= ", `v" . $i . "` DOUBLE";

	$id = "`id` BIGINT NOT NULL UNIQUE KEY";
	if ($this->use_subseconds) {
	    $time = "`time` DATETIME, `ns` INT, `subcache` INT";
	    $index = ", PRIMARY KEY (`time`, `ns`, `subcache`)";
	} else {
	    $time = "`time` DATETIME PRIMARY KEY";
	    $index = "";
	}
	
	if (!mysql_query("CREATE TABLE `$name` ($id, $time"  .  $query . "$index) ENGINE = MYISAM", $this->dbh)) {
	    throw new ADEIException(translate("Unable to create cache table, error: %s [%s]", mysql_error($this->dbh), "CREATE TABLE `$name` ($time"  .  $query . ",$index)"));
	}
    }
 }
 
 function Query($query) {
    if (!mysql_query($query, $this->dbh)) {
	throw new ADEIException(translate("Query is failed, error: %s [%s]", mysql_error($this->dbh), $query));
    }
 }

 function CreateQuery($table, $time, &$d) {
    if (($this instanceof CACHE) == false)
	throw new ADEIException(translate("CreateTable calls are only allowed on CACHE object"));

    if (is_object($d)) {
	$sqltime = $this->SQLTime(floor($time));
#	echo "Time: $time -> $sqltime\n";
        
	$query = "INSERT INTO `$table` VALUES ($sqltime, " . $d->n . ", " . $d->missing;
	if ($d->n > 0) {
	    for ($j = 0; $j < $this->items; $j++) {
		$query .= ", " . (($d->min[$j]!==NULL)?$d->min[$j]:"NULL") . ", " . (($d->max[$j]!==NULL)?$d->max[$j]:"NULL") . ", " . (($d->mean[$j]!==NULL)?$d->mean[$j]:"NULL");
	    }
	} else {
	    for ($j = 0; $j < $this->items; $j++) {
		$query .= ", 0, 0, 0";
	    }
	}
	$query .= ")";
    } else {
	$sqltime = $this->SQLTime($time);

	if ($this->use_subseconds) {
	    $point = strstr($time, ".");
	    if ($point) {
		$subs = substr($point, 1);
		$chars = strlen($subs);
		if ($chars<9) $subs .= str_repeat("0", 9 - $chars);
	    } else $subs= "0";
	    
	    $ns .= ", $subs, 0";
	    $id = "ADEI_TIMESTAMP(" . (floor($time)) . ", $subs)";
	} else {
	    $ns="";
	    $id = "ADEI_TIMESTAMP(" . (floor($time)) . ", 0)";
	}
	    
	$query = "INSERT INTO `$table` VALUES ($id, $sqltime" . $ns;
	foreach ($d as $val) {
	    if ($val === NULL)
		$query .= ", NULL";
	    else
		$query .= ", $val";
	}
	$query .= ")";
    }

    return $query;
 }

 function Insert($resolution, &$query) {
    if (($this instanceof CACHE) == false)
	throw new ADEIException(translate("CreateTable calls are only allowed on CACHE object"));

    if (!@mysql_query($query, $this->dbh)) {
	$handled = false;
	switch (mysql_errno($this->dbh)) {
	     case CACHE::MYSQL_ER_NO_SUCH_TABLE:
		$this->CreateTable($resolution, $this->items);
		if (@mysql_query($query, $this->dbh)) $handled = true;
	     break;
	     case CACHE::MYSQL_ER_DUP_ENTRY:
	        if (!strstr($query, "cache0"))
		    throw new ADEIException(translate("Internal error. Trying to insert the block wich is already in the cache (Resolution: $resolution)."));
	        return CACHE::MYSQL_ER_DUP_ENTRY;
	     break;
	     case CACHE::MYSQL_ER_WRONG_VALUE_COUNT_ON_ROW:
		throw new ADEIException(translate("The number of items in caching table and database doesn't match. Please, fix your configuration."));
	     break;
	     case CACHE::MYSQL_ER_BAD_FIELD_ERROR:
	        // May be we have INF in the column list
	        $fixed_query = preg_replace("/,\s+-?(INF|NAN)/", ", NULL", $query, -1, $reps);
	        if (($fixed_query)&&($reps > 0)) {
		    $query = $fixed_query;
		    if (@mysql_query($query, $this->dbh)) $handled = true;
		}
	     break;
	}
	
	if (!$handled) {
	    throw new ADEIException(translate("Can not insert into the caching table (Resolution: %u), error %u: %s [query %s]", $resolution, mysql_errno($this->dbh), mysql_error($this->dbh), $query));
	}
    }
 
    return 0;
 }

 function GetReady($table, $from, $to) {
    return new CACHEData($this, $table, $from, $to);
 }

 
 function GetCachePoints($postfix = false, MASK &$mask = NULL, INTERVAL &$ivl = NULL, $type = CACHEDB::TYPE_AUTO, $limit = 0, $amount_or_sampling = 0, $resolution = false) {
    if (!$mask) $mask = $this->CreateCacheMask();
    if (!$ivl) $ivl = $this->CreateCacheInterval();
    
    if ($type == CACHEDB::TYPE_ALL) {
	$res = new RAWPoint($this, $mask, $ivl, $limit, $amount_or_sampling);
    } else {
	$res = new DATAPoint($this, $mask, $ivl, $type, $amount_or_sampling, $limit, $resolution);
    }

    if (($postfix)||(!isset($this->use_subseconds))) {
	// we should find subsecond precision
	$use_subseconds = $this->DetectSubseconds($postfix);
	$res->SetOption("use_subseconds", $use_subseconds);

	if ($postfix) $res->SetOption("postfix", $postfix);
    }
    return $res;    
  }

  function GetDownloads($download = NULL, $sort = NULL) {
    if($sort != "ASC") $sort = "DESC";
    if(!$download) $query = "SELECT * FROM downloads ORDER BY startdate $sort";
    else $query = "SELECT * FROM downloads WHERE dl_id='".$download."' ORDER BY startdate $sort;";
    
    $res = @mysql_query($query, $this->dbh);   

    if (!$res) {
      switch (mysql_errno($this->dbh)) {
	case CACHE::MYSQL_ER_NO_SUCH_TABLE:
	    $this->CreateDownloadsTable();
	    $res = @mysql_query($query, $this->dbh);
	break;	     
      }
      if (!$res) {
        throw new ADEIException(translate("Can't get downloads from database, error") . ": " . mysql_error($this->dbh));
      }
    }
    return $res;
  }
  
  function UpdateDownloadCol($download, $col, $value, $fsize = NULL) {
    if($col == "status" && $value == "Ready" && !@mysql_query("UPDATE downloads SET filesize='".$fsize."', progress='100' ,enddate='".time()."' ,status='".$value."' WHERE dl_id ='".$download."';" , $this->dbh)) {
      throw new ADEIException(translate("Can't change download status, error") . ": " . mysql_error($this->dbh));
    }
    else if(!@mysql_query("UPDATE downloads SET $col='".$value."' WHERE dl_id ='".$download."';" , $this->dbh)) {
      throw new ADEIException(translate("Error while updating downloads table. Column: $col. Error") . ": " . mysql_error($this->dbh));
    }  
  } 
 
  function RemoveDownload($download) {
    if(!@mysql_query("DELETE FROM downloads WHERE dl_id ='".$download."';" , $this->dbh)) {
	throw new ADEIException(translate("Can not delete entry from the downloads table, error") . ": " . mysql_error($this->dbh));
    }
  }

  function AddDownload($props) {
    $query = "INSERT into downloads VALUES(";

    foreach($props as $item => $value) {
      if ($value === "") $query .= "NULL";
      else $query .= "'$value'";
      
      //if($item != "auto_delete") $query .= ",";
      if($item != "isshared") $query .= ",";
      else $query .= ")";
    }
    
    if (!@mysql_query($query, $this->dbh)) {
      switch (mysql_errno($this->dbh)) {
	case CACHE::MYSQL_ER_NO_SUCH_TABLE:
	    $this->CreateDownloadsTable();
	    if(!@mysql_query($query,$this->dbh)) {
	      throw new ADEIException(translate("Error adding download. ERROR: ") . ": " . mysql_error($this->dbh));
	    } 	
	break;	     
	default:
	    throw new ADEIException(translate("Error adding download. ERROR: ") . ": " . mysql_error($this->dbh));
	break;
      }
    }
  }

  function CreateDownloadsTable() {
    $query = "CREATE TABLE IF NOT EXISTS downloads (
	      dl_id varchar(32) NOT NULL,
	      dl_name text COLLATE utf8_unicode_ci,
	      db_server text COLLATE utf8_unicode_ci NOT NULL,
	      db_name text COLLATE utf8_unicode_ci NOT NULL,
	      db_group text COLLATE utf8_unicode_ci NOT NULL,
	      db_mask text COLLATE utf8_unicode_ci,
	      control_group text COLLATE utf8_unicode_ci,
	      resample int(11),
	      experiment text COLLATE utf8_unicode_ci,
	      window text COLLATE utf8_unicode_ci,
	      status text COLLATE utf8_unicode_ci,
	      startdate text COLLATE utf8_unicode_ci,
	      enddate text COLLATE utf8_unicode_ci,
	      format text COLLATE utf8_unicode_ci,
	      virtual text COLLATE utf8_unicode_ci,
	      srctree text COLLATE utf8_unicode_ci,
	      progress int(11),	    
	      user text COLLATE utf8_unicode_ci,
	      filesize float,
	      ctype text COLLATE utf8_unicode_ci,
	      filesremaining int(11),
	      readablewindow text COLLATE utf8_unicode_ci,
	      error text COLLATE utf8_unicode_ci,
	      detwindow text COLLATE utf8_unicode_ci,	      
	      axis_range text COLLATE utf8_unicode_ci,
	      temperature_axis_range text COLLATE utf8_unicode_ci,
	      voltage_axis_range text COLLATE utf8_unicode_ci,
	      aggregation text COLLATE utf8_unicode_ci,
	      interpolate text COLLATE utf8_unicode_ci,
	      show_gaps text COLLATE utf8_unicode_ci,
	      mask_mode text COLLATE utf8_unicode_ci,
	      auto_delete text COLLATE utf8_unicode_ci,
	      UNIQUE KEY dl_ID (dl_id) ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
    
    if(!@mysql_query($query,$this->dbh)) {
      throw new ADEIException(translate("Can't create downloads table, error") . ": " . mysql_error($this->dbh));
    } 
  }

}

?>