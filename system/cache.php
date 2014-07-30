<?php
/*
$config = array (
    "db_server" => "katrin", 
    "db_name" => "hauptspektrometer"
);
*/

/*
function signal_handler($signal) {
    profilerDisplay();
    exit;
}
declare(ticks = 1);
pcntl_signal(SIGINT, "signal_handler");
*/

$curdir = getcwd();

if (preg_match("/(.*)cache.php$/", $_SERVER['SCRIPT_FILENAME'], $m)) @chdir($m[1]);

require("../adei.php");
#adeiLogOutput();

$verbose = 0;
$user_req = new REQUEST($config);
$source_param = "";

$params = sizeof($_SERVER['argv']);
if ($params>1) {
    $pos = array_search("-verbose", $_SERVER['argv']);
    if ($pos) $verbose = 1;

    $pos = array_search("-source", $_SERVER['argv']);

    if ($pos) {
	if (($pos + 1) < $params) {
	    $source_param = $_SERVER['argv'][$pos + 1];
	    $user_req = new REQUEST($source_param);
	} else {
	    throw new ADEIException(translate("The source is not specified"));
	}
    }

    $pos = array_search("-parallel", $_SERVER['argv']);
    if ($pos) {
	if ($source_param)
	    throw new ADEIException(translate("The source can't be specified in the parallel mode"));
	
	unset($_SERVER['argv'][$pos]);
	
	$partype = "sources";
	if ((($pos + 1) < $params)&&(substr($_SERVER['argv'][$pos + 1], 0, 1) != "-")) {
	    $partype = $_SERVER['argv'][$pos + 1];
	    unset($_SERVER['argv'][$pos + 1]);
	}
	
	$req = new REQUEST();
	
        switch($partype) {
	 case "servers":
	    $list = $req->GetServerList();
	    break;
	 case "sources":
	    $list = $req->GetSourceList();
	    break;
	 case "groups":
	    $list = $req->GetGroupList();
	    break;
	 default:
	    throw new ADEIException(translate("Unsupported type of parallelization (%s)", $partype));
	}
	
	$params = implode(" ", $_SERVER['argv']);
	chdir($curdir);

	$procs = array();	
	foreach ($list as $item) {
	    $source = "db_server={$item['db_server']}";
	    if (isset($item['db_name'])) $source .= "&db_name={$item['db_name']}";
	    if (isset($item['db_group'])) $source .= "&db_group={$item['db_group']}";
	    $run = "php $params -source \"$source\"";
	    array_push($procs, popen($run, "r"));
	}
	
	foreach ($procs as $proc) {
	    while (!feof($proc)) {
		echo fgets($proc);
	    }
	    pclose($proc);
	}
	exit;
    }
} else $SETUP_MULTI_MODE = 0;

if ($source_param) {
#    sleep(100);
    $res = exec('ps xa | grep "cache.php" | grep -v "grep cache.php" | grep -E "' . $source_param . '\s*([\'\\-\\\\\\"]|$)" | wc -l');
    if ($res > 1) {
	if ($verbose) echo "Cache script is already running for source \"$source_param\"\n";
	exit;
    }
} else {
    $res = exec('ps xa | grep "cache.php" | grep -v "grep cache.php" | grep -v "\-source" | wc -l');
    if ($res > 1) {
	if ($verbose) echo "Cache script is already running\n";
	exit;
    }
}
#$res = exec('ps xa | grep "cache.php" | grep -v grep | wc -l');
#if ($res > 1) exit;

$syslock = new LOCK("system-cache");

function ROOTCache(&$cache, $rootdb, $app, $inq_app, $rootdb_name) {
    try {

	$desc = array(
	    0 => array("pipe", "r"),
	    1 => array("pipe", "w"),
	    2 => array("pipe", "w")
	);

	$cmd = proc_open($inq_app, $desc, $pipes);
	if ($cmd) {
	    if ($rootdb) {
	        fwrite($pipes[0], $rootdb['user'] . "\n");
		fwrite($pipes[0], $rootdb['password'] . "\n");
	    }
	    fclose($pipes[0]);

	    $start = stream_get_contents($pipes[1]);
	    fclose($pipes[1]);
	    
	    $errors = stream_get_contents($pipes[2]);
	    $ret = proc_close($cmd);
	    if ($ret) throw new ADEIException("Execution of csv2root is finished with error code $ret");

	} else throw new ADEIException("Execution of csv2root is failed");
	
	if ($start < 0) $start = 0;

	$ivl = $cache->CreateInterval();
	$ivl->Limit($start);

	$cmd = popen($app, "w");
	if ($rootdb) {
	    fwrite($cmd, $rootdb['user'] . "\n");
	    fwrite($cmd, $rootdb['password'] . "\n");
	}
	$cache->ExportCSV(new STREAMOutput($cmd), $mask = NULL, $ivl);
	if ($err = pclose($cmd)) throw new ADEIException("csv2root is finished with error: $err");
    } catch(ADEIException $e) {
	$e->logInfo(translate("Problem processing ROOT database \"%s\"", $rootdb_name), $cache);
	echo "Problem processing ROOT database \"" . $rootdb_name . "\": " .  $e->getInfo() . "\n";
    }
}

function DoCache(&$req) {
    global $user_req;
    global $verbose;
    global $ROOT_DB;
    global $syslock;

    try {
	$reader = $req->CreateReader(REQUEST::READER_FORBID_CACHEREADER);
    } catch(ADEIException $e) {
	if ($e->getCode() == ADEIException::DISCONNECTED) return;

	$e->logInfo(translate("READER constructor is failed"), $req);
        return $req->GetLocationString() . ", Error: " . $e->getInfo();
    }

    try {
        $opts = &$req->GetOptions();
	$rootdb = $opts->Get('root_database');

	$list = $req->GetGroups($reader);
    } catch(ADEIException $e) {
	$e->logInfo(translate("Problem processing options"), $reader);
        return $req->GetLocationString() . ", Error: " . $e->getInfo();
    }

    if ($rootdb) {
	if (strchr($rootdb, "/")) {
	    $db_info = false;
	    $inq_app = adei_app("csv2root", "--file " . $rootdb . " --inquiry-latest-data", true);
	    $app = adei_app("csv2root", "--file " . $rootdb, true);
	} else {
	    try {
		$db_info = $ROOT_DB;
		$db_info['database'] = $rootdb;
		$database = new DATABASE($db_info);
	    } catch (ADEIException $e) {
		unset($db_info['database']);
		try {
		    $database = new DATABASE($ROOT_DB);
		    $database->CreateDatabase($rootdb);
		} catch(ADEIException $e) {
		    $e->logInfo(translate("Problem accessing ROOT database \"%s\"", $rootdb), $req);
		    echo translate("Problem accessing ROOT database \"%s\": %s\n", $rootdb, $e->getInfo());
		    $rootdb = false;
		}
	    }

	    if ($rootdb) {
		$conprm = $db_info['driver'] . "://" . $db_info['host'] . ($db_info['port']?(":" . $db_info['port']):"") . "/" . $rootdb;
		$inq_app = adei_app("csv2root", "--db " . $conprm . " --inquiry-latest-data", true);
		$app = adei_app("csv2root", "--db " . $conprm, true);
	    }
	}
	if ($rootdb) {
	    if ((!$app)||(!$inq_app)) {
		echo("csv2root is not present");
		$rootdb = false;
	    } else {
	        $groups = $reader->GetGroupList(REQUEST::NEED_INFO);
	    }
	}
    }

    foreach ($list as $gid => $greq) {
        if (isset($user_req->props['db_group'])&&($user_req->props['db_group'] != $gid)) continue;

	if ($verbose) {
	    echo "{$greq->props['db_server']} -- {$greq->props['db_name']} -- $gid:";
	}

        $syslocked = false;
        $prelocked = false;

	try {
	    
	    $cache = $greq->CreateCacheUpdater($reader);

	    $syslock->Lock(LOCK::BLOCK);
	    $syslocked = true;
	    if (!$cache->PreLock()) {
		if ($verbose) echo " busy\n";
		continue;
	    }
	    $prelocked = true;
	    $syslocked = false;
	    $syslock->UnLock();
	    
	    $cache->Update($ivl=NULL, CACHE::DIM_AUTO, verbose?CACHE::VERBOSE:0);
	    
	    $prelocked = false;
	    $cache->UnLock();
	    
	    if ($rootdb) {
		$grname = " --group \"" . $groups[$gid]["name"] . "\"";
		ROOTCache($cache, $db_info, $app . $grname, $inq_app . $grname, $conprm?$conprm:$rootdb);
	    }
	    unset($cache);
	} catch(ADEIException $e) {
	    if ($prelocked) $cache->UnLock();
	    if ($syslocked) $syslock->UnLock();
	    
	    if ($e->getCode() !=  ADEIException::DISABLED) {
		$e->logInfo(translate("Failed to update CACHE"), $cache?$cache:$greq);
		if ($verbose) echo " failed\n";
		return $greq->GetLocationString() . ", Error: " . $e->getInfo();
	    }
	}
	if ($verbose) echo " done\n";
    }
    
    return 0;
}

$list = $user_req->GetSources();
foreach ($list as $sreq) {
    if (isset($user_req->props['db_server'])&&($user_req->props['db_server'] != $sreq->props['db_server'])) continue;
    if (isset($user_req->props['db_name'])&&($user_req->props['db_name'] != $sreq->props['db_name'])) continue;
    $err = DoCache($sreq);
    if ($err) echo "$err\n\n\n";
}
?>