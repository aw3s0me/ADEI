<?php
$res = exec('ps xa | grep "backup.php" | grep -v grep | wc -l');
if ($res > 1) exit;

if (preg_match("/(.*)backup.php$/", $_SERVER['SCRIPT_FILENAME'], $m)) @chdir($m[1]);

require("../adei.php");

/*
$config = array (
    "db_server" => "katrin", 
    "db_name" => "hauptspektrometer"
);
*/


function DoBackup(&$req, &$backup) {
    try {
	$reader = $req->CreateReader(REQUEST::READER_FORBID_CACHEREADER);
	$reader->Backup($backup, READER::BACKUP_FULL);
	unset($reader);
    



	
//	$bzeus  = new ZEUS($srv);

//	$bzeus = new ZEUS($bprops);
    
/*    
	$zeus = new ZEUS($props);
	$groups = $zeus->GetGroups();
	    
	
	
	
	echo $bdb;
	foreach ($groups as $group) {

	}
*/
/*	
	$cprops = $props;
	foreach (array_keys($groups) as $group) {
	    $cprops['db_group'] = $group;
	    $lg = new LOGGROUP($cprops);
	    $zlg = new ZEUSLogGroup($lg);
	    
	    $cache = new CACHE($cprops, $zeus);
	    $cache->Update();
	}*/
    } catch(ADEIException $e) {
	$e->logInfo("Backup is failed", $reader?$reader:$req);
	$error = $e->getInfo();
    }
    return $error?$error:0;
}




$req = new REQUEST($config);
$list = $req->GetSources();

foreach ($list as $sreq) {
    $opts = &$sreq->GetOptions();
    $backup = $opts->Get('backup');
    if ($backup) {
	$err = DoBackup($sreq, $backup);
	if ($err) 
	    echo $sreq->GetLocationString() . ", Error: $err\n";
    }
}


?>