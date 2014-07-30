<?php

 
//   $res = exec('ps xa | grep "downloads_check.php" | grep -v grep | wc -l');
//   if ($res > 1) exit;

  if (preg_match("/(.*)downloads_check.php$/", $_SERVER['SCRIPT_FILENAME'], $m)) @chdir($m[1]);
  require("../adei.php"); 

  $lock = new LOCK("downloads_check");
  $lock->Lock(LOCK::BLOCK);

  global $ADEI;
  global $ADEI_ROOTDIR;
  $ADEI->RequireClass("download");	

  $cache = new CACHEDB();
  $dm = new DOWNLOADMANAGER(); 
  $res = $cache->GetDownloads("","ASC");	

  while($row = mysql_fetch_array($res)) {
    $download = $row['dl_id'];
    $status = $row['status'];
    $name = $dm->Getfilename($download); 
    $file = $ADEI_ROOTDIR."tmp/downloads/".$name;
    if (file_exists($file)) {
	$fsize = filesize($file);	
	$lastmodified = time() - filemtime($file);
    } else {
	$fsize = 0;
	$lastmodified = 0;
    }
    if($fsize > 1 && $lastmodified > 1 && $status != 'Ready') {
	    // We should check if it's really complete (I think - not), and remove failed downloads
	    // We also should check that it is not in progress (old mtime?)
	//$dm->ChangeStatus($row['dl_id'], 'Ready');
    }
    if ($status == "Preparing" || $status == "Finalizing") {
	// We should check if something just failed
      $busy = 1;
    }
    if($nextdownload == "" && $status == "Queue"){
      $nextdownload = $row['dl_id'];	
    }
  }    
  
  
  if($nextdownload != "" && $busy != 1) {
    try {
      $opts = $dm->CreateDataRequestOpts($nextdownload); 
      $dm->DataRequest($opts);
    } catch(ADEIException $ex) {   
      //$dm->Logit($ex->getMessage());      
      $dm->ChangeStatus($nextdownload, "ERROR");
      $dm->SetError($nextdownload, $ex->getMessage());        	      
    }
  }

  $lock->UnLock();
  unset($lock);

?>