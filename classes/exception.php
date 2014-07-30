<?php

class ADEIException extends Exception {
    const NO_DATA = 1;
    const DISCONNECTED = 2;
    const DISABLED = 3;
    const NO_CACHE = 4;
    const BUSY = 5;
    const INVALID_REQUEST = 5;
    const PLOTTER_EXCEPTION = 10;
    const PLOTTER_WINDOW_TOO_SMALL = 11;

    function Clarify($message = false, $code = false) {
	return new ADEIException($message?$message:$this->message, ($code===false)?$this->code:$code);
    }
    
    function logInfo($msg = NULL, $req = NULL, $extra = NULL, $priority = LOG_CRIT, $src = NULL) {
	adeiLogException($this, $msg, $req, $extra, $priority, $src);
    }
    
    function getInfo($flags = 0) {
	$msg = $this->getMessage();
	return $msg;
    }
    
    function getFullInfo($flags = 0) {
	return getInfo($flags);
    }
    
    function MergeRevoreyException(ADEIException &$e) {
    }
    
    function MergeRecoveryError($msg) {
    }
}

?>