<?php

require($ADEI_ROOTDIR . "/classes/timezone.php");
require($ADEI_ROOTDIR . "/classes/readertime.php");

require($ADEI_ROOTDIR . "/classes/profiler.php");
require($ADEI_ROOTDIR . "/classes/logger.php");

require($ADEI_ROOTDIR . "/classes/request.php");
require($ADEI_ROOTDIR . "/classes/options.php");
require($ADEI_ROOTDIR . "/classes/database.php");
require($ADEI_ROOTDIR . "/classes/datahandler.php");

require($ADEI_ROOTDIR . "/classes/common.php");
require($ADEI_ROOTDIR . "/classes/exception.php");
require($ADEI_ROOTDIR . "/classes/lock.php");
require($ADEI_ROOTDIR . "/classes/loggroup.php");
require($ADEI_ROOTDIR . "/classes/interval.php");
require($ADEI_ROOTDIR . "/classes/mask.php");
require($ADEI_ROOTDIR . "/classes/resolution.php");
require($ADEI_ROOTDIR . "/classes/data.php");

require($ADEI_ROOTDIR . "/classes/axis.php");
require($ADEI_ROOTDIR . "/classes/axes.php");

require($ADEI_ROOTDIR . "/classes/cgroup/sourcetree.php");
require($ADEI_ROOTDIR . "/classes/cgroup/cachewrapper.php");
require($ADEI_ROOTDIR . "/classes/cgroup/cacheset.php");

require($ADEI_ROOTDIR . "/classes/adeidb.php");
require($ADEI_ROOTDIR . "/classes/uidlocator.php");

require($ADEI_ROOTDIR . "/classes/datafilter.php");
require($ADEI_ROOTDIR . "/classes/readerfilter.php");
require($ADEI_ROOTDIR . "/classes/filterdata.php");
require($ADEI_ROOTDIR . "/classes/reader.php");
require($ADEI_ROOTDIR . "/classes/cache.php");

require_once('includes/hybridauth-2.1.2/hybridauth/Hybrid/Auth.php');

class ADEI extends ADEICommon {
 var $RESPONSE_ENCODING;
 
 var $item_locator;
 var $control_locator;
 var $hybridauth;
 var $hybrid_config;
 //var $hybridauth = new Hybrid_Auth($hybrid_config);
 //var $ololo;
 
 function __construct() {

    /**/

    parent::__construct();
    $this->RESPONSE_ENCODING = REQUEST::GetResponseEncoding();
    $this->item_locator = NULL;
    $this->control_locator = NULL;
    try {
        $this->hybrid_config = 'includes/hybridauth-2.1.2/hybridauth/config.php';
        //require_once('includes/hybridauth-2.1.2/hybridauth/Hybrid/Auth.php');
        $this->hybridauth = new Hybrid_Auth($this->hybrid_config);
    }
    catch(Exception $e) {
        $message = ""; 
        
        switch( $e->getCode() ){ 
            case 0 : $message = "Unspecified error."; break;
            case 1 : $message = "Hybriauth configuration error."; break;
            case 2 : $message = "Provider not properly configured."; break;
            case 3 : $message = "Unknown or disabled provider."; break;
            case 4 : $message = "Missing provider application credentials."; break;
            case 5 : $message = "Authentication failed. The user has canceled the authentication or the provider refused the connection."; break;

            default: $message = "Unspecified error!";
        }
    }
 }
 
 function __destruct() {
	// Fixing LabVIEW bug (existing at least at Internet Toolkit 6.0.1)
    if ($this->RESPONSE_ENCODING == REQUEST::ENCODING_LABVIEW) {
	echo str_repeat(" ", 1024);
    }
 }
 
 function ResolveUID($uid, $control = false) {
    if ($control) {
	if (!$this->control_locator) $this->control_locator = new UIDLocator(true);
	$locator = $this->control_locator;
    } else {
	if (!$this->item_locator) $this->item_locator = new UIDLocator(false);
	$locator = $this->item_locator;
    }    
    return $locator->GetItem($uid);
 }

    function isConnected() {
        try {
            $connected_adapters_list = $this->hybridauth->getConnectedProviders(); 
            $first_connected = $connected_adapters_list[0];
            return (!empty($first_connected)); 
        }
        catch( Exception $e ) {
            throw new ADEIException("Error when connecting to auth library");
        }
    }

    function doLogout() {
        $connected_adapters_list = $hybridauth->getConnectedProviders(); 
        foreach ($connected_adapters_list as &$adapter_name) {
            $adapter = $hybridauth->getAdapter($adapter_name);
            $adapter->logout();
        }
    }

    function getUserProfile() {
        $connected_adapters_list = $this->hybridauth->getConnectedProviders(); 
        $first_connected = $connected_adapters_list[0];
        if(!$this->hybridauth->isConnectedWith($first_connected)) { 
            // redirect him back to login page
            return NULL;
        }
        // call back the requested provider adapter instance (no need to use authenticate() as we already did on login page)
        try {
            $adapter = $this->hybridauth->getAdapter($first_connected);
            return $adapter->getUserProfile();
        }
        catch(Exception $e) {
            $adapter->logout();
            return NULL;
        }

        
    }

    function getUsername() {
        $userProfile = $this->getUserProfile();
        if (!$userProfile) {
            return NULL;
        }
        return $userProfile->displayName;
    }

    function getHybridAuth() {
        return $this->hybridauth;
    }

}

$ADEI = new ADEI();
/*
$ADEI->RequireClass(
    "timezone", "readertime",
    "profiler", "logger",
    "request", "options", "database", "datahandler",
    "common", "exception", "lock", 
    "loggroup", "interval", "mask", "resolution", "data",
    "datafilter", "readerfilter", "filterdata", "reader",
    "adeidb", "cache", "draw", "drawtext", "welcome", "export"
);
*/
?>