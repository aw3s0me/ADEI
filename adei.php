<?php


$ADEI_RUNDIR = getcwd();
if (!isset($ADEI_ROOTDIR)) {
    if (($_SERVER['SCRIPT_FILENAME'])&&(substr($_SERVER['SCRIPT_FILENAME'],0,1)=="/")) {
	if (preg_match("/^(.*\/)(services|system|admin|test|tmp\\/adminscripts)\/?$/", dirname($_SERVER['SCRIPT_FILENAME']), $m)) $ADEI_ROOTDIR = $m[1];
	else $ADEI_ROOTDIR =  dirname($_SERVER['SCRIPT_FILENAME']) . "/";
    } else {
	if (preg_match("/^(.*\/)(services|system|admin|test|tmp\\/adminscripts)\/?$/", $ADEI_RUNDIR, $m)) $ADEI_ROOTDIR = $m[1];
	else $ADEI_ROOTDIR = $ADEI_RUNDIR . "/";
    }
}

if ($ADEI_ROOTDIR != $ADEI_RUNDIR) chdir($ADEI_ROOTDIR);

if (isset($_GET['adei_session'])) $ADEI_SESSION = $_GET['adei_session'];
else $ADEI_SESSION = "00000000000000000000000000000000";

require($ADEI_ROOTDIR . "/config.php");
require($ADEI_ROOTDIR . "/tools.php");
require($ADEI_ROOTDIR . "/classes/adei.php");

require($ADEI_ROOTDIR . "locale.php");


//require("classes/zeus.php");

function adei_app($name, $opts=false, $throw=false) {
    global $ADEI_APP_PATH;
    
    if (isset($ADEI_APP_PATH[$name])) $appname = $ADEI_APP_PATH[$name];
    else $appname = $ADEI_APP_PATH["default"] . $name;

    if (!file_exists($appname)) {
	if ($throw) throw new ADEIException("Application \"$name\" is not installed");
	else return false;
    }
    
    if (!is_executable($appname)) {
	if ($throw) throw new ADEIException("Application \"$name\" is not executable");
	else return false;
    }
    
    return $appname . ($opts?(" " . $opts):"");
}

?>