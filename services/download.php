<?php

  header("Content-type: text/xml");
  header("Cache-Control: no-cache, must-revalidate");
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
  global $ADEI;
  $ADEI->RequireClass("download");


try {  
  $target = $_GET["target"]; 
  $isadmin = $_GET["isadmin"] ? true : false;   
  $dm = new DOWNLOADMANAGER();    
 
  switch ($target) {
    case "dlmanager_add":	  
      $dm->AddDownload();	    
    break;
    case "dlmanager_remove":
      $dm->RemoveDownload();      
    break;
    case "dlmanager_list":      
      CreateDownloadXml($dm->GetDownloads(), $target, $isadmin);
    break;          
    case "dlmanager_run":
      $dm->DlManagerRun();
    break;  
    case "dlmanager_sort":
      $dm->SortBy();
    break;
    case "dlmanager_details":
      CreateDownloadXml($dm->GetDownloadDetails(), $target);
    break;
    case "dlmanager_download":
      $dm->GetFile();
    break;
    case "dlmanager_toggleautodelete";
      $download = $_GET["dl_id"];		 
      $dm->ToggleAutodelete($download);
    break;
    case "dlmanager_setshared":
      $download = $_GET["dl_id"];
      //$is_checked = $_GET["is_checked"];
      $dm->SetDownloadShared($download);
    break;
    case "dlmanager_multi_remove":
      if (isset($_POST['param1'])) {
        $response_data = $_POST['param1'];
      }
      else {
        $response_data = implode(',', $_POST);
        //$response_data = $_POST;
      }
      
      echo $response_data;
      return;
    break;
      //$downloads = json_decode($_POST[''])
    default:
      throw new ADEIException(translate("Error with download service: Target ( $target ) not valid"));
    break;
  }
} catch(ADEIException $ex) {  
    throw new ADEIException(translate("Error with download service. Target: $target \n Error: $ex"));   
}

  function AttachParameterXml($parameter, $value) {
    return " ".$parameter."=\"".$value."\" ";
  }


  function CreateDownloadXml($props, $mode, $isadmin=false) {
    $XMLoutput = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    switch ($mode) {
      case 'dlmanager_list':

    //<<ALEX KOROVIN ADDITION >>
    global $ADEI;
    $bisLoggedIn = $ADEI->isConnected();	
    $isLoggedIn = $bisLoggedIn ? "true": "false";

    $isAdmin = $isadmin? "true": "false";
  	$XMLoutput .= "<result "; 
    $XMLoutput .= AttachParameterXml("islogged", $isLoggedIn);
    $XMLoutput .= AttachParameterXml("isadmin", $isAdmin);

    if ($bisLoggedIn) {
      $userName = $ADEI->getUsername();
      $XMLoutput .= AttachParameterXml("username", $userName);
    }



  
  $XMLoutput .= ">\n";
  //$XMLoutput .= "islogged=\"".$isLoggedIn."\" isadmin=\"".$isAdmin."\">\n";
	foreach($props as $download) {   
	$XMLoutput .= "<download";
	  foreach($download as $key => $value) {
	    $XMLoutput .= " $key=\"" . htmlentities($value) . "\"";
	  }	
	$XMLoutput .= "></download>\n";
	}	
	$XMLoutput .= "</result>";
      break;
      case 'dlmanager_details':
	$XMLoutput .= "<groups>";
	if(!empty($props['props']['error'])) $XMLoutput .= "<error>" . htmlentities($props['props']['error']) . "</error>";
	$XMLoutput .= "<window><from>{$props['props']['window']['from']}</from><to>{$props['props']['window']['to']}</to></window>"; 	//
	$XMLoutput .= "<data><format>{$props['props']['format']}</format><size>{$props['props']['size']}</size></data>";
	
	foreach($props['groups'] as $gid => $itemlist) {
	  $XMLoutput .= "<group>\n<gname>$gid</gname>";
	  foreach($itemlist as $item => $info){
	    $XMLoutput .= "<item>\n<itemid>{$info['id']}</itemid>\n<itemname>{$info['name']}</itemname>\n</item>\n";	    
	  }
	  $XMLoutput .= "</group>";
	}
	$XMLoutput .= "</groups>";   
      break;
    }
    echo $XMLoutput;        
  }

?>
