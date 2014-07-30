<?php
  
  if (preg_match("/(.*)downloads_clean.php$/", $_SERVER['SCRIPT_FILENAME'], $m)) @chdir($m[1]);
  require("../adei.php");

  global $ADEI;  
  global $TMP_PATH;
  global $DOWNLOAD_DECAY_TIME;

  $ADEI->RequireClass("download");  
  $dm = new DOWNLOADMANAGER(); 
  //$dm->Logit("Clearing old downloads\n");
  $dir = opendir("$TMP_PATH/downloads");

  while ($file = readdir($dir)) {
    $fullname = "$TMP_PATH/downloads/$file";
    if (filetype($fullname) == "dir") continue;
    
    if(time() - fileatime($fullname) > $DOWNLOAD_DECAY_TIME) {	
      $dotind = strrpos($file, '_');	
      $download = substr($file, $dotind + 1, 32);
      $res = $dm->cache->GetDownloads($download);
      if ($res) {
         $row = mysql_fetch_assoc($res);
	 if($row["auto_delete"] == "true") {
	    $dm->RemoveDownload($download, true);
	 }
      } else {
        echo "Removing rogue file: $file\n";
	unlink($file);
      }
    }
  }
?>