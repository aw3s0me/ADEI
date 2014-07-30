<?php


function adeiSetSystemTimezone() {
    global $adei_system_timezone;
    global $adei_timezone_history;

    array_push($adei_timezone_history, date_default_timezone_get());
    date_default_timezone_set($adei_system_timezone); 
}

function adeiSetClientTimezone() {
    throw new ADEIException(translate("Client timezone is not supported yet"));
}

function adeiSetUTCTimezone() {
    global $adei_timezone_history;

    array_push($adei_timezone_history, date_default_timezone_get());
    date_default_timezone_set("GMT"); 
}

function adeiRestoreTimezone()  {
    global $adei_timezone_history;

    if ($adei_timezone_history) {
	date_default_timezone_set(array_pop($adei_timezone_history));
    }
}

$adei_timezone_history = array();
$adei_system_timezone = @date_default_timezone_get();
date_default_timezone_set("GMT"); 


?>