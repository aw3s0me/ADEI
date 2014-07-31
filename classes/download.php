<?php
class DOWNLOADMANAGER {  
  var $props;
  var $req;
  var $cache; 
 
  var $downloads_path;
  var $images_path;
  
  function __construct() {      
      global $TMP_PATH;
      global $ADEI;

      if($_GET["target"] == "dlmanager_add"){
	$this->req = new DATARequest();
	$this->props = $this->req->GetProps();
	if($this->props['db_server'] != 'virtual') $this->props['srctree'] = ""; //<- This because if user selects virtual server and some channels and then
      }	      									//switches to single server the srctree= attribute doesnt clear		 				
      $this->cache = new CACHEDB();    						//and it messes download details channel listing.		
      $this->downloads_path = $TMP_PATH."/downloads";
      $this->images_path = $TMP_PATH."/downloads/images";
      $this->CheckAndCreateDirectories();            
  }
  
  function CheckAndCreateDirectories(){
    if (!is_dir($this->downloads_path)) {
	if (@mkdir($this->downloads_path, 0777, true)){
	    @chmod($this->downloads_path, 0777);
	}   
    }
    if (!is_dir($this->images_path)) {
	if (@mkdir($this->images_path, 0777, true)){
	    @chmod($this->images_path, 0777);
	}   
    }
  }
    
  public function DlmanagerUpdate($target, $progress = NULL, $download = NULL) {   
    switch ($target) {
      case "progress":	
	$this->UpdateProgress($progress, $download);	
	if(mysql_fetch_array($this->cache->GetDownloads($download)) == "") $ret = false;
	else $ret = true;    
	return $ret; 
      break;
      case "start":
	$download = $this->GetDownload();
	$this->UpdateProgress(1, $download);  
	return $download;
      break;      
      case "finish":	
	$filesremaining = $this->GetFilesRemaining($download);
	if($filesremaining == 0 || $filesremaining == 1) {
	  if($filesremaining == 1) $this->UpdateFilesRemaining($download, 0);
	  $this->UpdateProgress(100, $download);
	  $this->ChangeStatus($download, "Finalizing");
	}     
	else {
	  $this->UpdateFilesRemaining($download, $filesremaining - 1);
	  $this->UpdateProgress(1, $download);
	}
      break;      
    }
  }
  
  function GetDownload() {
    $res = $this->cache->GetDownloads("","ASC");
    while($row = mysql_fetch_array($res)) {
      if($row['status'] == "Preparing") {
	  return $row['dl_id'];
	  exit;
	} 
      }
  }

  function AddDownload() {      
    global $ADEI_SETUP;
    $user_info = $this->GetUserInfo();   
    if(!is_numeric($this->props['db_group'])) $db_group = " - " .ucfirst($this->props['db_group']);
    else $db_group = "";
    $name = ucfirst("{$this->props['db_server']} - ").ucfirst("{$this->props['db_name']}") .$db_group;    
    $download = md5(uniqid (rand(), true));	
    $reader = $this->req->CreateReader(); 
    //$this->Logit(var_export($reader,true));
    $iv = $reader->CreateInterval();
    $spec['from'] = $iv->GetWindowStart();
    $spec['to'] = $iv->GetWindowEnd();
    $detwin = $this->ParseTitleDate($spec);
   
    $download_props = 
      array("dl_id" => 		 $download,
	    "dl_name" => 	 $name,
	    "db_server" => 	 $this->props['db_server'],
	    "db_name" => 	 $this->props['db_name'],
	    "db_group" => 	 $this->props['db_group'],
	    "db_mask" => 	 $this->props['db_mask'],
	    "control_group" =>   $this->props['control_group'],
	    "resample" => 	 $this->props['resample'],
	    "experiment" => 	 $this->props['experiment'],
	    "window" =>  	 $this->props['window'],
	    "status" => 	 "Queue",
	    "startdate" => 	 time(),
	    "enddate" => 	 "",
	    "format" =>  	 $this->props['format'],
	    "virtual" => 	 $this->props['virtual'],
	    "srctree" => 	 $this->props['srctree'],
	    "progress" => 	 0,
      "user" =>      $user_info,
	    "filesize" =>  	 0,
	    "ctype" =>  	 "",
	    "filesremaining" =>  1,
	    "readablewindow" =>  "",
	    "error" => 		 "",
	    "detwindow" => 	 $detwin,	    
	    "axis_range" =>      $this->props['axis_range'],
	    "temperature_axis_range" => $this->props['temperature_axis_range'],
	    "voltage_axis_range" => $this->props['voltage_axis_range'],
	    "aggregation" =>     $this->props['aggregation'],
	    "interpolate" =>     $this->props['interpolate'],
	    "show_gaps" =>       $this->props['show_gaps'],
	    "mask_mode" =>       $this->props['mask_mode'],
	    "auto_delete" =>	 "true",
      "isshared" => 0,
      "setup" => $ADEI_SETUP
	    );      
    //$this->Logit(var_export($download_props,true));
    $this->cache->AddDownload($download_props); 
    $this->GetCacheImg($download);  
    $this->Response();  
    $this->RunBackground();      
  }

  function GetIp() {
    /*if(getenv("HTTP_CLIENT_IP"))   $ip = getenv("HTTP_CLIENT_IP");
    else if(getenv("REMOTE_ADDR")) $ip = getenv("REMOTE_ADDR");   
    else $ip = "Unknown IP";
    if($ip == "::1") $ip = "127.0.0.1";   
    return $ip; */
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR')) //headers, can be spoofed
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR')) //returns proxy sometimes
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'Unknown IP';
 
    return $ipaddress;
  } 

  function GetUserInfo() {
    global $ADEI;
    $username = $ADEI->getUsername();
    if (!$username) {
      return $this->GetIp();
    }
    return $username;
  }

  function SetDownloadShared($download) {
    global $ADEI;
    if (!$ADEI->isConnected()) {
      throw new ADEIException(translate("User is not logged in"));
    }
    $res = $this->cache->GetDownloads($download);
    $row = mysql_fetch_assoc($res); 
    $is_shared = $row["isshared"]? 0 : 1;
    $this->cache->UpdateDownloadCol($download, "isshared", $is_shared);
    $this->Response();
  }

  function IsUser($input) {
    return filter_var($input, FILTER_VALIDATE_IP) ? 'false' : 'true';
  }

  
  function RemoveDownload($download = NULL, $noresp = false) {
    if(!$download) $download = $_GET['dl_id'];   
    $file = $this->downloads_path."/".$this->Getfilename($download);
    $img = $this->downloads_path."/images/$download.png";
    $this->cache->RemoveDownload($download);
    unlink("$file");
    unlink("$img");
    if(!$noresp)$this->Response();     
  }
  
  function GetDownloads($isadmin = false) {
    
    session_start();
    $sqlres = $this->cache->GetDownloads();    
    $user = $this->GetUserInfo();
    $ip = $this->GetIp();
    $download_list = array();
    global $ADEI_SETUP;
    
    while($row = mysql_fetch_array($sqlres)) {   
      //$ADEI_SETUP is in config.actual.php
      if (empty($row['setup']))
          throw new ADEIException(translate("Entry doesnt have setup value column %s", $row['dl_id']));    
      if (($ADEI_SETUP != $row['setup']) && !$isadmin) 
          continue;
      $download_props = array();
      $download_props['is_shared'] = $row['isshared'] ? "true" : "false";
      $download_props['is_user'] = $this->IsUser($row['user']); // if user
      if(is_array($_SESSION['sortby'])) {
      	foreach($_SESSION['sortby'] as $sortby) {      
      	  if($row['user'] == $sortby && $user != $sortby) {	   
      	    $download_props['sort'] = "true"; 
      	    $download_props['owner'] = "false";
      	    foreach ($row as $key => $value) {	  
      	      if($key == 'startdate') $value = $this->ParseReadableWindow($value, 1);
      	      if(!is_numeric($key)) $download_props[$key] = $value;
      	    }		   
      	    $handled = 1;
      	  }
      	}
      }
      if($row['user'] == $user) {	
      	$download_props['sort'] = "true";
      	$download_props['owner'] = "true";
        
      	foreach ($row as $key => $value) {
      	  if($key == 'startdate') $value = $this->ParseReadableWindow($value, 1);
      	  if(!is_numeric($key)) $download_props[$key] = $value;
      	}	
      } 
      else if(!$handled) {	 
        if ($row['user'] == $ip) {
          $download_props['sort'] = "true";
          $download_props['owner'] = "true";
        }
        else {
          $download_props['sort'] = "rest";
          $download_props['owner'] = "false";
        }
    	  foreach ($row as $key => $value) {
    	    if($key == 'startdate') $value = $this->ParseReadableWindow($value, 1);
    	    if(!is_numeric($key))  $download_props[$key] = $value;
    	  }	 
      }
      

	// if prepared file not found from server -> remove download.
      $file = $this->downloads_path."/".$this->Getfilename($download_props['dl_id']);

      if($download_props['status'] != "Queue" && !file_exists($file)) $this->RemoveDownload($download_props['dl_id'], 1);
      else {
          array_push($download_list, $download_props);
      }
    }
    return $download_list;    	
  }
  
  function GetDOwnloadProps($download) {   
    $filename = $this->Getfilename($download);
    $content = $this->GetContentType($download);     
    $file = $this->downloads_path."/$filename";    
    $props = array('filename' => $filename,
		  'content_type' => $content,
		  'file' => $file);
    return $props;
  }
  
  function UpdateFilesRemaining($download, $val) {
    $this->cache->UpdateDownloadCol($download, "filesremaining", $val);    
  }
  
  function GetFilesRemaining($download) {
    $res = $this->cache->GetDownloads($download);
    $row = mysql_fetch_assoc($res);    
    return $row['filesremaining'];      
  }
  
  function DlManagerRun() {           
    $res = $this->cache->GetDownloads("","ASC");
    $starting = 0;
    $finalizing = 0;
    while($row = mysql_fetch_array($res)) {
      $status = $row['status'];
      if($status == "Finalizing") {
	$download = $row['dl_id'];
	$name = $this->Getfilename($download); 
	$file = $this->downloads_path."/".$name;
	$fsize = filesize($file);	
	$lastmodified = time() - filemtime($file);
	if($fsize > 1 && $lastmodified > 1) $this->ChangeStatus($row['dl_id'], 'Ready');
	$finalizing = 1;
	$busy = 1;
      }
      else if($status == "Preparing" && $finalizing == 0) {
	$download = $row['dl_id'];
	$progress = $row['progress'];
	$frem = $row['filesremaining'];
	$busy = 1;
      }
      if($nextdownload == "" && $status == "Queue"){
	$nextdownload = $row['dl_id'];
	$frem = $row['filesremaining'];
      }
    }
    if($nextdownload != "" && $busy != 1) {       
      if($this->RunBackground() == 0) $st = "Done";      
    }        
    if($progress != "") {     
	$extra = "dl_id=\"$download\" progress=\"$progress\" frem=\"$frem\" finalizing=\"$finalizing\"";
	$st = "Showprogress";
    } else if(!$st) {
	$st = "Idle";
	if ($finalizing) $extra = "finalizing=\"$finalizing\"";
    } else {
	if ($finalizing) $extra = "finalizing=\"$finalizing\"";
    }
    
    $this->Response($st, $extra);	    
  }
  
  function RunBackground() {
   global $PHP_BINARY;   
   global $ADEI_ROOTDIR;
   $res = exec('ps xa | grep "downloads_check.php" | grep -v grep | wc -l');    
   if($res == 0) exec("$PHP_BINARY $ADEI_ROOTDIR"."system/downloads_check.php &");
//   if($res == 0) exec("$PHP_BINARY $ADEI_ROOTDIR"."system/downloads_check.php >/tmp/xxx &");
   return $res;
  }

  function DataRequest($opts){
    global $ADEI;
    $ADEI->RequireClass("export");    
    $req = new DATARequest($opts);      
    $export = new EXPORT($req, $h, $format, $this);
    $export->Export();	    
  }

  function GetFile() {
    if($download = $_GET["dl_id"]) {            
      $props = $this->GetDownloadProps($download);	
      $content_type = $props['content_type'];
      $filename = $props['filename'];
      $file = $props['file'];    
      $fsize = filesize($file);
      header("Cache-Control: no-cache, must-revalidate");
      header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
      header("Content-type: $content_type");
      header("Content-length: $fsize");
      header("Content-Disposition: attachment; filename=\"$filename\"");
      readfile($file);
    }
    else throw new ADEIException(translate("Error while starting file download"));
  }

  function GetDmOutput($multimode, $ext, $req) {
    $props = $req->GetProps();
    $download = $props["dl_id"];
    $dl_name = "{$props['db_server']}_{$props['db_name']}_$download"; 
    if($multimode)$ext = "zip";	
    $astring = $this->downloads_path."/$dl_name.$ext";
    $output = new IO($astring);	
    $this->ChangeStatus($download, "Preparing");
    return $output;	    
  }
  
  function CreateDataRequestOpts($download) {
    $res = $this->cache->GetDownloads($download);
    $row = mysql_fetch_assoc($res);   
    $opts = array('dl_id' => '');
    foreach ($row as $key => $value) {      
      $opts[$key] = $value;	
    }    
    return $opts;  
  } 
  
  function UpdateProgress($prog, $download) {
    if($prog == 100) {
      $dl_name = $this->Getfilename($download); 
      $fsize = $this->GetFilesize($dl_name);
      $this->cache->UpdateDownloadCol($download, "status", "Ready", $fsize);           
    }
    else $this->cache->UpdateDownloadCol($download, "progress", $prog); 
  }  
  
  function ChangeStatus($download = NULL, $status = NULL) {     
    if($status == "Ready") {	
      $fsize = $this->GetFilesize($this->Getfilename($download));
    }        
    $this->cache->UpdateDownloadCol($download, "status", $status, $fsize);
  }
    
  function SetError($download, $error) {
   $this->cache->UpdateDownloadCol($download, "error", $error);       
  }
    
  function CheckStatus($download) {
    if(mysql_fetch_array($this->cache->GetDownloads($download)) == "") $ret = 0;
    else $ret = 1;    
    return $ret;
  }

  function Response($st = NULL, $extra = NULL) {	
    if(!$st) $st = "Done";    
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<result job=\"".$st."\" $extra></result>";
  }
    
  function Getfilename($download) {
    $res = $this->cache->GetDownloads($download);
    while($row = mysql_fetch_array($res)) {    
      $dl_name = "{$row['db_server']}_{$row['db_name']}_$download";     
      $ext = $this->GetFileExtension($dl_name);
      $dl_name .= ".$ext";
    }
    return $dl_name;
  }
  
  function GetFileExtension($dl_name) {	  
    $dir = opendir($this->downloads_path);
    while ($file = readdir($dir)) { 
      if (preg_match("/".$dl_name."./i", $file)) {
	return pathinfo($file, PATHINFO_EXTENSION);	
      }
    }
  }
  
  function GetFilesize($dl_name) {
    $file = $this->downloads_path."/".$dl_name;      
    $fsize = round((filesize($file) / 1048576), 2);
    if($fsize >= 1)$fsize = round($fsize, 1);
    else if($fsize >= 2)$fsize = round($fsize, -1);
    if($fsize < 1) $fsize = 0.1;    
    return $fsize;
  }      
    
  function Toggleautodelete($download) {
    $res = $this->cache->GetDownloads($download);
    $row = mysql_fetch_assoc($res);     
    if($row["auto_delete"] == "true") $status = "no";
    else $status = "true";
    $this->cache->UpdateDownloadCol($download,"auto_delete", $status);	
    $this->Response();
  }

  function SortBy() {
    session_start();   
    if(isset($_SESSION['sortby']) && is_array($_SESSION['sortby'])) {     
      foreach($_SESSION['sortby'] as $key=>$value) {
	if($value == $_GET['sortby']) {
	  unset($_SESSION['sortby'][$key]);
	  $push = true;
	}
      }     
      if(!$push) {
	array_push($_SESSION['sortby'], $_GET['sortby']);	
      }
    }
    else {
      $arr = array($_GET['sortby']);
      $_SESSION['sortby'] = $arr;
    }
    $this->Response();   
  }
  
  function GetDownloadDetails() {
    $i = 0;
    $download = $_GET['dl_id'];          
    $res = $this->cache->GetDownloads($download);
    $row = mysql_fetch_assoc($res);   
    $opts = array('dl_id' => '');
    foreach ($row as $key => $value) {      
      $opts[$key] = $value;	
    }
    
    if(!$size = $opts['filesize']) $size = "n/a";
    else if($size < 1) $size = "&#60;1 MB";
    else $size .= " MB";
    if($opts['ctype'] == "application/x-zip-compressed") $format = "zip/{$opts['format']}";
    else $format = $opts['format'];
         
    if($row['srctree'] != "") {
      $res = SOURCETree::Parse($row['srctree']);
      foreach($res as $key => $grp) {		
	$req = new DATARequest($grp['props']);
	$reader = $req->CreateReader();	
	$itemlist[$key] = $reader->GetItemList();	  
      }
    } 	
    else {
      $req = new DATARequest($opts);      
      $reader = $req->CreateReader(); 
      $grps = $reader->GetGroups();
      $glist = $reader->GetGroupList();      
      $mask = new MASK($opts);
     
      foreach($glist as $grp => $det) { 	
	$itemlist[$det['gid']] = $reader->GetItemList($grps[$i], $mask);	 
	$i = $i+1;
      }
    }
    $window = $this->ParseReadableWindow($opts['window']);
    if($opts['status'] == "ERROR") $download_props['error'] = "Something went wrong while preparing data.";
    if($error = $opts['error']) $download_props['error'] = $error;    
    $download_props['window'] = $window;     
    $download_props['format'] = $format;
    $download_props['size'] = $size;
    $download_details['props'] = $download_props;
    foreach($itemlist as $gid => $items) {
      if(!is_numeric($gid)) {		
	foreach($items as $key => $item ) {	  
	  $download_details['groups'][$gid][$key] = array("id" =>"{$item['id']}", "name" => "{$item['name']}");	 
	}		
      }
    }
    
    return $download_details;
  }  

  function ParseReadableWindow($window, $single = NULL) {
    if($single) {
	$ret = @date("F j, Y, g:i:s a", $window);
	if (!$ret) $ret = $window;
    } else {
      global $ADEI_TIMINGS;
      if($window == 0) $start = $end = "All";
      else if(!$midpos = stripos($window,'-',2)) {
	foreach($ADEI_TIMINGS as $time => $secs) {
	  if($window == $secs) $start = "$time";
	}
	$end = "All";
      }
      else {
	$to = substr($window, $midpos + 1);
	$from = substr($window, 0, $midpos);    
	if($midpos < 3) {
	  $start = date("F j, Y, g:i:s a", $to);
	  $end = "All";
	}
	else {
	  $start = date("F j, Y, g:i:s a", $from);
	  $end = date("F j, Y, g:i:s a", $to);
	}
      }  
      $ret = array('from' => $start, 'to' => $end); 
    }
    return $ret;
  }
  
  function GetCacheImg($download) {    
    $filename = $this->downloads_path."/images/$download.png";    
    try {
        $req = new DATARequest();
	$req->SetProp("width", "512");
	$req->SetProp("height", "384");
        $draw = $req->CreatePlotter();
        $draw->Create();	
        $draw->Save($filename);	
    }
    catch(ADEIException $ex) {
        $ex->logInfo(NULL, $draw);            
    }   
  }
  
  function SetContentType($type, $download) {
    $this->cache->UpdateDownloadCol($download, "ctype", $type);  
  }
  
  function GetContentType($download) {
    $res = $this->cache->GetDownloads($download);
    $row = mysql_fetch_assoc($res);       
    return $row['ctype']; 
  }

  

  function Logit($msg) {  
    $time = date("F j, Y, g:i:s a");
    $logfile = fopen($this->downloads_path ."/logit.txt", 'a') or die("can't open file");
    if(is_array($msg)) {
      ob_start();
      print_r($msg); 
      $input = ob_get_contents();
    }
    else $input = $msg;
    fwrite($logfile, "$time :: MSG:  $input\n");
    fclose($logfile);
  }

  function IsVirtual($download) {
    $virtual = false;
    $res = $this->cache->GetDownloads($download);
    $row = mysql_fetch_assoc($res);    
    if($row['db_server'] == 'virtual') $virtual = true;
    return $virtual;
  }

  function ParseTitleDate($spec) {
    global $GRAPH_SUBSECOND_THRESHOLD;

    $spec['length'] = dsMathPreciseSubstract($spec['to'], $spec['from']);

    $from = $spec['from'];
    $to = $spec['to'];
    $length = $spec['length'];

    $afrom = getdate($from);
    $ato = getdate($to);

   if ($length > 315360000) { // 10 years 
        $date_format = 'Y';
        $label_interval = 1;
	$date_title = $afrom['year'] . " - " . $ato['year'];
    } elseif ($length > 31104000) { // 1 year	
	if ($afrom['year'] == $ato['year']) {
	    $date_format = 'M';
	    $label_interval = 2;
	    $date_title = $afrom['year'];
	} else {
	    $date_format = 'M, Y';
	    $label_interval = 2;	    
	    $date_title = $afrom['year'] . " - " . $ato['year'];
	}
    } elseif ($length > 1036800) { // 12 days
	$date_format = 'M d';
	$label_interval = 3;
	
	if ($afrom['year'] == $ato['year']) {
	    if ($afrom['mon'] == $ato['mon']) {
		$date_title = $afrom['month'] . ", " . $afrom['year'];	   
	    } else {
		$date_title = $afrom['year'];
	    }
	} else {
	    $date_title = $afrom['year'] . " - " . $ato['year'];
	}
    } elseif ($length > 86400) { // 1 day
	$date_format = 'M d, H:i';
	$label_interval = 4;

	if ($afrom['year'] == $ato['year']) {
	    if ($afrom['mon'] == $ato['mon']) {
		$date_title = $afrom['month'] . ", " . $afrom['year'];
	    } else {
		$date_title = $afrom['year'];
	    }
	} else {
	    $date_title = $afrom['year'] . " - " . $ato['year'];
	}
    } elseif ($length > 14400) { // 4 hours
	$date_format = 'H:i';
	$label_interval = 2;

	if ($afrom['year'] == $ato['year']) {
	    if ($afrom['mon'] == $ato['mon']) {
		if ($afrom['mday'] == $ato['mday']) {
		    $date_title = $afrom['month'] . " " . $afrom['mday'] . ", " . $afrom['year'];
		} else {
		    $date_title = $afrom['month'] . " " . $afrom['mday'] . " - " . $ato['mday'] . ", " . $afrom['year'];
		}
	    } else {
		$date_title = date("M", $from) . " " . $afrom['mday'] . " - " . date("M", $to) . " " . $ato['mday'] . ", " . $afrom['year'];
	    }
	} else {
	    $date_title = date("M j, Y", $from) . " - " . date("M j, Y", $to);
	}
    } else if ($length > $GRAPH_SUBSECOND_THRESHOLD) {
	$date_format = 'H:i:s';
	$label_interval = 4;

	if ($afrom['year'] == $ato['year']) {
	    if ($afrom['mon'] == $ato['mon']) {
		if ($afrom['mday'] == $ato['mday']) {
		    $date_title = $afrom['month'] . " " . $afrom['mday'] . ", " . $afrom['year'];
		} else {
		    $date_title = $afrom['month'] . " " . $afrom['mday'] . " - " . $ato['mday'] . ", " . $afrom['year'];
		}
	    } else {
		$date_title = date("M", $from) . " " . $afrom['mday'] . " - " . date("M", $to) . " " . $ato['mday'] . ", " . $afrom['year'];
	    }
	} else {
	    $date_title = date("M j, Y", $from) . " - " . date("M j, Y", $to);
	}
    } else {
	$ifrom = floor($from);
	if (is_float($from)) $rfrom = substr(printf("%.9F", $from - $ifrom),2);
	else {
	    $pos = strpos($from, ".");
	    if ($pos === false) $rfrom = 0;
	    else $rfrom = substr($from, $pos + 1);
	}

	$date_title = date("M j, Y H:i:s", $ifrom);
	if ($rfrom) {
	    $date_title .= "." . $rfrom;
	    $rfrom = "0.$rfrom";
	}	
    }
    
  if($date_title == "") $date_title = "All data";
  
  return $date_title;   
  } 
}
?>