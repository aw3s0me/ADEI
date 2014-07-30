<?php

class LOCK {
 var $lockf;
 var $rlock;
 
 const BLOCK = 1;
 const ALL = 2;
 const EXCLUSIVE = 2;
 
 function __construct($name) {
    global $TMP_PATH;
    global $SETUP;
    
    $dir = $TMP_PATH . "/locks";

    if (!is_dir($dir)) {
    	if (!@mkdir($dir, 0777 /*0755*/, true))
	    throw new ADEIException(translate("It is not possible to create lock directory \"$dir\""));

# When creating from apache, the 0777 mode is ignored for unknown reason	
	@chmod($dir, 0777);
    }
    
    if ($SETUP) $fname = $dir . "/${SETUP}__${name}.lock";
    else $fname = $dir . "/ADEI__${name}.lock";

    $umask = @umask(0);

    $this->lockf = @fopen($fname, "a+");
    if (!$this->lockf) {
	@umask($umask);
	throw new ADEIException(translate("It is not possible to create lock file \"$fname\""));
    }

    $fname = $dir . "/${name}.lock";
    $this->rlock = @fopen($fname, "a+");
    if (!$this->rlock) {
	fclose($this->lockf);
        @umask($umask);
	throw new ADEIException(translate("It is not possible to create lock file \"$fname\""));
    }

    @umask($umask);
 }

 function __destruct() {
    fclose($this->lockf);
    fclose($this->rlock);
 }

 function Lock($flag = 0, $errmsg = false) {
    if ($flag&LOCK::BLOCK) {
	
	if ($flag&LOCK::ALL)
	    $res = flock($this->rlock, LOCK_EX);
	else
	    $res = flock($this->rlock, LOCK_SH);

	if (!$res) {
	    if ($errmsg) throw new ADEIException($errmsg);
	    else throw new ADEIException(translate("Locking is failed"));
	}
	
	$res = flock($this->lockf, LOCK_EX);
        if (!$res) {
	    flock($this->rlock,  LOCK_UN);
	    
	    if ($errmsg) throw new ADEIException($errmsg);
	    else throw new ADEIException(translate("Locking is failed"));
	}
    } else {
	if ($flag&LOCK::ALL)
	    $res = flock($this->rlock, LOCK_EX|LOCK_NB);
	else
	    $res = flock($this->rlock, LOCK_SH|LOCK_NB);

        if ((!$res)&&($errmsg)) throw new ADEIException($errmsg);

	$res = flock($this->lockf, LOCK_EX|LOCK_NB);
        if (!$res) {
	    flock($this->rlock, LOCK_UN);
	    if ($errmsg) throw new ADEIException($errmsg);
	}
    }
    
    return $res;
 }
 
 function UnLock() {
    flock($this->lockf, LOCK_UN);
    flock($this->rlock, LOCK_UN);
 }
}


?>