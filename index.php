<?php 

require("config.php");
require("tools.php");
require("locale.php");

header("Content-Type: application/xhtml+xml; charset=UTF-8");
header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");



if (!isset($_GET["minimal"])) {
	if (strpos($_SERVER['HTTP_USER_AGENT'],"iPhone") || strpos($_SERVER['HTTP_USER_AGENT'],"iPod") || strpos($_SERVER['HTTP_USER_AGENT'],"iPad") || preg_match('/iPhone/',$_SERVER['HTTP_USER_AGENT']) || preg_match('/iPod/',$_SERVER['HTTP_USER_AGENT'])) {
		$host  = $_SERVER['HTTP_HOST'];
		$uri   = rtrim(dirname($_SERVER['PHP_SELF']), "/\\");
		$extra = 'index.php?minimal=iAdei';
		header("Location: http://$host$uri/$extra");	
	} 	
}
//$config = dirname(__FILE__) . '/includes/hybridauth-2.1.2/hybridauth/config.php';
//require_once('includes/hybridauth-2.1.2/hybridauth/Hybrid/Auth.php');

/*try {
    $hybridauth = new Hybrid_Auth($config);
    $connected_adapters_list = $hybridauth->getConnectedProviders(); 
    $first_connected = $connected_adapters_list[0];
}
catch( Exception $e ){
    echo "Ooophs, we got an error: " . $e->getMessage();
    echo " Error code: " . $e->getCode();
    echo "<hr /><h3>Trace</h3> <pre>" . $e->getTraceAsString() . "</pre>"; 
} */


if (isset($_GET["minimal"])) {
    if ($_GET["minimal"]) $minimal = $_GET["minimal"];
    else $minimal = true;
    unset($_GET["minimal"]);
} else $minimal = false;

$ADEI_MODE = $minimal;

if ($minimal) {
    switch ($minimal) {
     case "nosource":
	$allowed_modules = false;
	$displayed_popups = array("controls");
	$CONTROLS = array("export", "aggregator", "searchtab");
        $allowed_popups = array("source");
	
	$no_header = false;
	$no_search = false;
     break;
     case "nomenu":
	$allowed_modules = false;
	$displayed_popups = array();
        $allowed_popups = array("source", "export");
	$no_header = false;
	$no_search = true;
	$no_menu = true;
     break;
     case "search":
	$config_module = "graph";
	$allowed_modules = array("graph");
	//$displayed_popups = array("searchtab");
	$displayed_popups = array("controls");
	$allowed_popups = array("source", "export");
	$CONTROLS = array("searchtab");
	
	$no_header = false;
	$no_search = false;
     break;
     case "wiki":
	$config_module = "wiki";
	$allowed_modules = array("graph", "wiki");
	$displayed_popups = array();
        $allowed_popups = array("source", "export");
	$no_header = false;
	$no_search = true;
     break;
     case "iAdei":
        if (!in_array("settings", $MODULES)) array_push($MODULES, "settings");
        $config_module = "wiki";
	$allowed_modules = array("slowcontrol", "alarms", "graph", "wiki", "settings");
        $allowed_popups = array("source", "export");
        $displayed_popups = array();        
        $no_header = true;
        $iHeader = true;
     break;
     default:
	$config_module = "graph";
	$allowed_modules = array($config_module);
        $allowed_popups = array("source", "export");
	$displayed_popups = array();
	$no_header = true;
    }

    if ($no_header) {
	$no_menu = true;
	$no_search = true;
    }
} else {
    $allowed_popups = false;
    $allowed_modules = false;
    $no_header = false;
    $no_menu = false;
    $no_search = false;

    if (isset($_GET["module"])) $config_module = $_GET["module"];
    else $config_module = $DEFAULT_MODULE;

}

$config_options = &$_GET;
if (sizeof($config_options)) $config_options["apply"] = 1;

session_start();
if (isset($_SESSION['setup'])) {
    if ($_SESSION['setup'] != $ADEI_SETUP) {
        session_regenerate_id();
        $_SESSION['setup'] = $ADEI_SETUP;
    }
} else {
    $_SESSION['setup'] = $ADEI_SETUP;
}



//login lib needs config.php that contains info about

require("module.php");
?>




<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:xf="http://www.w3.org/2002/xforms" xmlns:ev="http://www.w3.org/2001/xml-events" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
<head>
    <title>ADEI (Advanced Data Extraction Infrastructure) </title>
<?  if($iHeader){?>
	<meta name = "viewport" content = "user-scalable=no, initial-scale = 1.0, maximum-scale = 1.0, minimum-scale = 1.0" />
	<meta name="apple-touch-fullscreen" content="YES" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta names="apple-mobile-web-app-status-bar-style" content="black-translucent" />
	<link rel="stylesheet" type="text/css" href="iadei.css"/> <?/* Should be first, otherwise cssSetProperty would not work */?>
	<link rel="stylesheet" type="text/css" href="includes/sw/spinningwheel.css"/>
<?  } else {?>    
	<link rel="stylesheet" type="text/css" href="adei.css"/> <?/* Should be first, otherwise cssSetProperty would not work */?>
<?  }  

    if (file_exists($SETUP_CSS)) {?>
	<link rel="stylesheet" type="text/css" href="<?echo $SETUP_CSS;?>"/>
<?  }?>
    <link rel="stylesheet" type="text/css" href="includes/dhtmlx/skins/dhtmlxmenu_<?=$DHTMLX_SKIN;?>.css"/>
    <link rel="stylesheet" type="text/css" href="includes/dhtmlx/dhtmlxtree.css"/>

    
    <script type="text/javascript" src="includes/date.format.js"></script>
    <script type="text/javascript" src="includes/datetimepicker.js"></script>
    <script type="text/javascript" src="includes/rsh.js"></script>

    <script type="text/javascript" src="includes/prototype.js"></script>
    <script type="text/javascript" src="includes/scriptaculous/scriptaculous.js?load=builder,effects,dragdrop"></script>
    <script type="text/javascript" src="includes/cropper/cropper.js"></script>
<!-- Alexander Korovin css dep -->
    <link rel="stylesheet" type="text/css" href="includes/logindep/colorbox.css"/>

	
<?/*
    This causes Ajax.Request (prototype) to return on 'onSuccess' handler
    status=0 (should be 200) and undefined responseText. 
    This happens ocassionally (not always). Could be stimulated by openning
    current ADEI page in the new tab. Occurce much rare if reloaded in the 
    same tab and even more rare than just pressing Apply button. However, still
    happens sometimes.
    
    <script type="text/javascript" src="includes/ext/adapter/prototype/ext-prototype-adapter.js"></script>
    <script type="text/javascript" src="includes/ext/ext-all.js"></script>
*/?>

    <script type="text/javascript" src="includes/dhtmlx/dhtmlxcommon.js"></script>
    <script type="text/javascript" src="includes/dhtmlx/dhtmlxmenu.js"></script>
    <script type="text/javascript" src="includes/dhtmlx/dhtmlxtree.js"></script>

    <!-- Login dep !-->
    <script type="text/javascript" src="includes/logindep/jquery.min.js"></script>
    <script type="text/javascript" src="includes/logindep/jquery.colorbox.js"></script>
    <!-- Alexander Korovin Login HTML appending !-->
    <script>
    jQuery.noConflict();
    </script>

<?php
    //require('login.php')
?> 

<?
    if ($ADEI_RELEASE) {
	echo "<script type=\"text/javascript\" src=\"adei.js\"></script>\n";
	if (file_exists("setups/$ADEI_SETUP/$ADEI_SETUP.js")) {
	    echo "<script type=\"text/javascript\" src=\"setups/$ADEI_SETUP/$ADEI_SETUP.js\"></script>\n";
	}
    } else {
	$dir = opendir("js");
	while ($file = readdir($dir)) {
	    if (preg_match("/\.js$/", $file)) {
		echo "<script type=\"text/javascript\" src=\"js/$file\"></script>\n";
	    }
	}
	closedir($dir);

	$dir = opendir("js/xmlmodule");
	while ($file = readdir($dir)) {
	    if (preg_match("/\.js$/", $file)) {
		echo "<script type=\"text/javascript\" src=\"js/xmlmodule/$file\"></script>\n";
	    }
	}
	closedir($dir);

	$dir = @opendir("setups/$ADEI_SETUP/js");
	if ($dir) {
	    while ($file = readdir($dir)) {
		if (preg_match("/\.js$/", $file)) {
		    echo "<script type=\"text/javascript\" src=\"setups/$ADEI_SETUP/js/$file\"></script>\n";
	        }
	    }
	    closedir($dir);

	    $dir = @opendir("setups/$ADEI_SETUP/js/xmlmodule");
	    if ($dir) {
		while ($file = readdir($dir)) {
		    if (preg_match("/\.js$/", $file)) {
			echo "<script type=\"text/javascript\" src=\"setups/$ADEI_SETUP/js/xmlmodule/$file\"></script>\n";
		    }
		}
		closedir($dir);
	    }
	}
    }

?>    
    <script type="text/javascript" ev:event="onload">
//<![CDATA[
	function Geometry() {
	    /* DS: Do we need this? */
	    if (typeof adei == "undefined") return;

	    var new_width = windowGetWidth();
	    var new_height = windowGetHeight();
	    <?moduleAdjustGeometry("new_width", "new_height");?>
	}
	
	function UpdatePopupGeometry(source, dragger) {
	    adei.popup.popups_width = 0;        // to allow reduction
      	    adei.popup.UpdateGeometry(source);
      	    adei.popup.RunCallbacks();
	}

	function Startup() {
	    var adei_options = new Object();
	    <?foreach ($config_options as $key => $value) {
		echo "adei_options." . $key . "=\"" . $value . "\";\n";
	    }?>
	    
	    <?if ($minimal) {
		if ($no_menu) $menuid = "false";
		else $menuid="\"menu_zone\"";
		
		if ($displayed_popups) $sidebarid="\"main_sidebar\"";
		else $sidebarid = "false";
	    ?>
		adei = new ADEI("main_div", <?=$sidebarid?>, "main_statusbar", <?=$menuid?>, "<?echo session_id();?>");
	    <?} else {?>
		adei = new ADEI("main_div", "main_sidebar", "main_statusbar", "menu_zone", "<?echo session_id();?>");
	    <?}?>

	    adei.SetOptions(adei_options);
    
	    adei.SetProperty('window_border', <?echo $AJAX_WINDOW_BORDER;?>);
	    adei.SetProperty('parse_delay', <?echo $AJAX_PARSE_DELAY;?>);
	    adei.SetProperty('subsecond_threshold', <?echo $GRAPH_SUBSECOND_THRESHOLD;?>);
	    adei.SetProperty('zoom_ratio', <?echo $GRAPH_ZOOM_RATIO;?>);
	    adei.SetProperty('step_ratio', <?echo $GRAPH_STEP_RATIO;?>);
	    adei.SetProperty('deepzoom_area', <?echo $GRAPH_DEEPZOOM_AREA;?>);
	    adei.SetProperty('edge_ratio', <?echo $GRAPH_EDGE_RATIO;?>);
	    adei.SetProperty('default_status_duration', <?echo $STATUS_DEFAULT_DURATION;?>);
	    adei.SetProperty('menu_scroll_limit', <?= $MENU_SCROLL_LIMIT?>);
	    adei.SetProperty('export_formats',<?= json_encode($EXPORT_FORMATS)?>);
	    adei.SetProperty('dhtmlx_iconset', '<?="includes/dhtmlx/imgs/" . ((file_exists("includes/dhtmlx/imgs/$DHTMLX_ICONSET"))?"$DHTMLX_ICONSET/":"")?>');

	    <?if ($SETUP_MULTI_MODE) {?>
	    adei.AddToQuery('setup=<?echo $ADEI_SETUP?>');
	    <?}?>
	    
	    <?modulePlaceJS(($allowed_popups===false)?false:array_merge($allowed_popups, $displayed_popups), $allowed_modules);?>

	    Geometry();
	    <?moduleSetupDragger();?>

	    adei.Start('<?echo $config_module;?>', <?echo $AJAX_UPDATE_RATE;?>);
	}
	
	function Navigate(btn) {
  	    if(btn != "settings") {
		adei.OpenModule(btn);
	    } else {
    		adei.SetConfiguration("p_id=main");
    		adei.OpenModule(btn);
    		adei.updater.Update();
  	    } 
	}
//]]>
    </script>
</head>

<body onload="javascript:Startup()" onresize="Geometry()" onorientationchange="Geometry()">
<div class="all ales">

<?if (!$no_menu) {?>
    <div class="menu_button"><div id="menu_zone"></div></div>
<?}?>

<?if (!$no_header) {?>
    <div id="header_div" class="header">
	<table width="100%" cellspacing="0" cellpadding="0"><tr>
	    <td class="title">
	        <b><?echo $TITLE;?></b>
	    </td><td class="right">
	    <? if (!$no_search) {?>
		<div class="search"><form action="javascript:nope()" onsubmit="javascript:adei.Search(this.search.value);"><input name="search" id="search"/></form></div>
	    <? }?>
	        <div class="links"><table width="100%" height="100%" cellspacing="0" cellpadding="0"><tr><td>
		    <?moduleLinkModules($allowed_modules);?>
		</td></tr></table></div>
	    </td>
	</tr></table>
    </div>
<?}?>

<?if ($iHeader){?>
    <div id="header_div" class="iheader">
	<table><tr><td><button class="settingsbtn" id="settingsbutton" onclick="javascript:Navigate('settings');"></button></td><td><h1 class="ADEIhead">ADEI</h1></td></tr></table>
        <select class="moduleSelector" onChange='javascript:Navigate(this.options[this.selectedIndex].value);' name="modsel" id="modsel">
<? 
     if($allowed_modules){
     	for($i = 0; $i<sizeof($allowed_modules);$i++){
   			echo"<option value='$allowed_modules[$i]'>";
     		if (isset($GLOBALS[$allowed_modules[$i] . "_title"])) echo  $GLOBALS[$allowed_modules[$i] . "_title"];
			else echo"$allowed_modules[$i]";
			echo"</option>";
     		
     	}
     }
 ?>
	</select>     
    </div>
<?}?>

    <div><table align="center" cellspacing="0" cellpadding="0">
	<?/*<tr><td colspan="2"></td></tr>*/?>
	<tr><td id="main_sidebar"><div>
	    <?if ($minimal) {
		foreach ($allowed_popups as $popup) {
		    ?><div class="popup" id="popup_<?echo $popup;?>" style="display: none;"><?
			if (function_exists($popup . "Page")) call_user_func($popup . "Page");
		    ?></div><?
		}
		moduleLinkPopups($displayed_popups);
	    } else {?>
		<?moduleLinkPopups();?>
	    <?}?>
	</div></td><td width="100%"><div id="main_div">
		<?modulePlacePages($allowed_modules);?>
	</div></td></tr>
    </table></div>

    <div id="main_statusbar" class="statusbar">
	<div>I'm a status bar</div>
    </div>

<?
    var_dump($_SESSION);
?>
    
</div>
</body>
</html>
