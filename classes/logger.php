<?php

class LOGData implements Iterator {
 var $from;
 var $to; 
 var $prio;	// maximal priority

 var $cur;
 
 var $file;	// currently openned log file
 var $min;	// $from in current file
 var $max;	// $to in current file

 var $fname;	// current log file name 
 var $info;	// current data
 
 var $key;
 
 var $filter;

 function __construct($from, $to, $prio = false, $filter = false) {
    if ($to < $from)
	throw new ADEIException(translate("Invalid time slice is specified: '%d'-'%d'", $from,$to));

    $this->from = $from;
    $this->to = $to;
    $this->prio = $prio;
    $this->filter = $filter;
    
    $this->file = false;

 }

 function OpenFile($date, $min = false, $max = false) {
    global $TMP_PATH;

    $this->fname = LOGGER::GetLogFile($date);
    
    if ($this->file) {
	$this->CloseFile();
    }
    
    $this->file = @fopen($this->fname, "r");
    if ($this->file) {
	$this->min = $min;
	$this->max = $max;
    }

    return $this->file;
 }
 
 function CloseFile() {
    if ($this->file) {
	fclose($this->file);
	$this->file = false;
    }
 }
 
 function rewind() {
    $this->CloseFile();
 
    $mod = $this->from%86400;
    if ($mod) {
	$this->OpenFile($this->from, $this->from, false);
	$this->cur = $this->from + 86400 - $mod;
    } else {
	$this->cur = $this->from;
    }
    
    $this->key = -1;
    $this->next();
 }

 function current() {
    return $this->info;
 }

 function key() {
    return $this->key;
 }

 function next() {
    while (true) {
	while (!$this->file) {
	    $next = $this->cur + 86400;
	    if ($next > $this->to) {
		$this->OpenFile($this->cur, false, $this->to);
		$this->cur = $next;
		break;
	    } else {
		$this->OpenFile($this->cur);
		$this->cur = $next;
	    }
	}
    
	if (!$this->file) return;
    
	$min = $this->min;
	$max = $this->max;
	
	while (!feof($this->file)) {
	    $pos = ftell($this->file);
	    if ($pos === false) break;
	    
	    $hdrlen = fread($this->file, 5);
	    if ((feof($this->file))||(strlen($hdrlen) != 5)||($hdrlen<=0)) break;
	    
	    
	    $header = fread($this->file, $hdrlen);
	    if ((feof($this->file))||(strlen($header) != $hdrlen)) break;

	    if (sscanf($header, "%s %d %s %s %s %s %s %d %d", $iso_time, $prio, $setup, $src, $session, $pid, $client, $len, $msglen) != 9) break;
#	    echo $header . "<br/>\n";
	    
	    if (($this->prio !== false)&&($prio > $this->prio)) {
		if (fseek($this->file, $len, SEEK_CUR) < 0)
		    break;
		else 
		    continue;
	    }

	    $time = new DateTime($iso_time);
	    
	    if (($min)||($max)) {
		$unix_time = $time->format("U");
		
		if ($min) {
		    if ($unix_time < $min) {
			if (fseek($this->file, $len, SEEK_CUR) < 0)
			    break;
			else 
			    continue;
		    }
		    else $min = false;
		}
		
		if ($max) {
		    if ($unix_time > $max) {
			break;
		    }
		}
	    }
	    
	    if ($this->filter) {
		$skip = false;
		if (($this->filter['setup'])&&($setup != $this->filter['setup'])) $skip = true;
		if (($this->filter['session'])&&($session != $this->filter['session'])) $skip = true;
		if (($this->filter['source'])&&($src != $this->filter['source'])) $skip = true;
		if (($this->filter['pid'])&&($pid != $this->filter['pid'])) $skip = true;
		if (($this->filter['client'])&&($client != $this->filter['client'])) $skip = true;
		
		if ($skip) {
		    if (fseek($this->file, $len, SEEK_CUR) < 0)
			break;
		    else 
			continue;
		}
	    }

	    if ($msglen) {
	        $msg = fread($this->file, $msglen);
		if (feof($this->file)) break;
	    } else {
		$msg = "";
	    }
	    
#	    echo $msg . "<br/>\n";
	    
	    if (fseek($this->file, $pos + LOGGER::SIZE_RECORD_LENGTH + $hdrlen + $len, SEEK_SET) < 0) break;
	    
	    $this->key++;	    
	    $this->info = array(
		'time'=> $time,
		'priority'=> $prio,
		'setup' => $setup,
		'source' => $src,
		'session' => $session,
		'pid' => $pid,
		'client' => $client,
		'message' => $msg,
		'logfile' => basename($this->fname),
		'filepos' => $pos
	    );
	    
	    return;
	}
	
	$this->CloseFile();
    }
 }


 function valid() {
    return $this->file?true:false;
 }
}



class LOGGER {
 var $console;
 var $catch;
 var $catch_console;
 
 var $log_output;

 const SIZE_RECORD_LENGTH = 5;

 function __construct() {
    global $LOGGER_LOG_REQUESTS;
    global $LOGGER_LOG_OUTPUT;
    global $ADEI_RUNDIR;
    
    $this->console = false;
    $this->catch = false;
    $this->catch_console = false;

    if ($LOGGER_LOG_REQUESTS) {
	$this->LogMessage("New request is received", false, NULL, LOG_DEBUG);
        if ($LOGGER_LOG_OUTPUT) {
	    $source = basename($_SERVER['SCRIPT_NAME'], ".php");
	    if ((preg_match("/(services)\\/$source\.php$/", $_SERVER['SCRIPT_NAME'], $m))||(preg_match("/(services)$/", $ADEI_RUNDIR, $m))) {
		if (!preg_match("/(getdata)/", $source)) {
		    $this->CatchOutput();
		    $this->log_output = true;
		} else {
		    $this->log_output = true;
		}
	    } 
	}
    }
 } 

 function __destruct() {
    global $LOGGER_LOG_REQUESTS;
    global $LOGGER_LOG_OUTPUT;

    if ($this->catch) {
	if ($this->log_output) {
	    $out = ob_get_contents();
	    ob_end_flush();
	    
	    $extra = array(
		'result' => bin2hex($out)
	    );
	    $this->LogMessage("Request processing is finished", false, $extra, LOG_DEBUG);
	} else {
	    $this->LogOutput();
	}
    } else if (($LOGGER_LOG_REQUESTS)&&($LOGGER_LOG_OUTPUT)) {
	$this->LogMessage("Request processing is finished", false, NULL, LOG_DEBUG);
    }
 }

 static function GetLogDir() {
    global $TMP_PATH;
    return "$TMP_PATH/log/";
 }
 
 static function GetLogFile($date) {
    return sprintf("%s/%s.log", LOGGER::GetLogDir(), date("Ymd", $date));
 }

 function GetLogs ($from, $to, $prio = false, $filter = false) {
    return new LOGData($from, $to, $prio, $filter);    
 }
 
 function ParseLogRecord($f) {
    $hdrlen = @fread($f, 5);
    if ((feof($f))||(strlen($hdrlen) != 5)||($hdrlen<=0)) return false;

    $header = @fread($f, $hdrlen);
    if ((feof($f))||(strlen($header) != $hdrlen)) return false;

    if (sscanf($header, "%s %d %s %s %s %s %s %d %d", $iso_time, $prio, $setup, $src, $session, $pid, $client, $len, $msglen) != 9) return false;
	    
    $time = new DateTime($iso_time);

    if ($msglen) {
	$msg = @fread($f, $msglen);
	if (feof($f)) return false;
    } else {
	$msg = "";
    }

	// skip space in the beggining and eol in the end?    
    $text_info = @fread($f, $len - $msglen);
    if (!$text_info) return false;
    
    $info = json_decode(urldecode($text_info), true);
#    $info = json_decode(pack('H*', substr($text_info,1, strlen($text_info)-2)), true);
    $info['time'] = $time;
    $info['priority'] = $prio;
    $info['setup'] = $setup;
    $info['source'] = $src;
    $info['session'] = $session;
    $info['pid'] = $pid;
    $info['message'] = $msg;
    $info['client'] = $client;
    
    return $info;
 }
 
 function GetLogInfo($file, $pos) {
    $fname = sprintf("%s/%s", $this->GetLogDir(), basename($file));
    $f = @fopen($fname, "r");

    if (!$f) 
	throw new ADEIException(translate("It is not possible to open the specified log file (%s)", basename($file)));

    if (($pos < 0)||(fseek($f, $pos, SEEK_SET) === false)) {
	fclose($f);
	throw new ADEIException(translate("Could not set specified position (%d) within specified log file (%s)", $pos, basename($file)));
    } 

    $rec = $this->ParseLogRecord($f);

    fclose($f);	    
    
    if (!$rec)
	throw new ADEIException(translate("Can't parse log record at specified position (%d) within log file (%s)", $pos, basename($file)));
#    else if (is_string($rec))
#	throw new ADEIException(translate("%s log record at specified position (%d) within log file (%s)", $rec, $pos, basename($file));
    
    return $rec;
    
 }

 function EnableConsoleLog($priority = true) {
    $this->console = $priority;
 }
 
 
 function CatchOutput($console = true) {
    $this->catch = ob_start();
    $this->catch_console = $console;
    $this->log_output = false;
 }
 
 function LogOutput() {
    $out = ob_get_contents();

    if ($this->catch_console) {
	ob_end_flush();
    } else {
	ob_end_clean();
    }

    $console = $this->console;
    $this->console = false;
    
    if ($out) {
	$lines = preg_split("/(\\n|<br\/>)+/", $out, -1, PREG_SPLIT_NO_EMPTY);
	if ($lines) {
	    foreach ($lines as $line) {
		$this->LogMessage($line);
	    }
	}
    }
    
    $this->console = true;
    
    ob_start();
 }
 
 function SkipOutput() {
    if ($this->catch_console) {
	ob_flush();
    } else {
	ob_clean();
    }
 }
 
 static function EncodeObject($obj) {
    return urlencode(serialize($obj));
 }
 
 static function DecodeObject($str) {
    return unserialize(urldecode($str));
 }
 
 function LogMessage($msg, $req = NULL, $extra = NULL, $priority = LOG_WARNING, $source = NULL) {
    global $TMP_PATH;
    global $ADEI_SETUP;
    global $ADEI_SESSION;
    global $ADEI_RUNDIR;
    global $LOGGER_STORE_OBJECTS;

    $tod = gettimeofday();
    
    if ($req instanceof REQUEST) {
	$location = $req->GetLocationString();
    } else if ($req instanceof READER) {
	$location = $req->req->GetLocationString();
    } else if ($req instanceof CACHE) {
	$location = $req->req->GetLocationString();
    } else if ($req instanceof EXPORT) {
	$location = $req->req->GetLocationString();
    } else if ($req instanceof DRAW) {
	$location = $req->req->GetLocationString();
    } else if ($req === NULL) {
	$req = new REQUEST();
	$location = $req->GetLocationString();
    } else {
	$location = false;
    }

    if (!$source) {
	$source = basename($_SERVER['SCRIPT_NAME'], ".php");
	if (preg_match("/services\\/(service).php$/", $_SERVER['SCRIPT_NAME'], $m)) {
	    $source = strtoupper($m[1]) . "(" . $_GET["service"] . ")";
	} else if ((preg_match("/(system|service|admin)\\/$source\.php$/", $_SERVER['SCRIPT_NAME'], $m))||(preg_match("/(system|services|admin)$/", $ADEI_RUNDIR, $m))) {
	    $source = strtoupper($m[1]) . "($source)";
	} else {
	    $source = "ADEI($source)";
	}
    }
    
    
    if (($extra)&&(is_array($extra)))
	$info = $extra;
    else
	$info = array();
    
    if ($_SERVER['REQUEST_URI']) {
	$info['request'] = $_SERVER['REQUEST_URI'];
    } 
    
    if ($_GET) {
	$info['GET'] = &$_GET;
    }
    
    if ($_POST) {
	$info['POST'] = &$_POST;
    } 

    if ($_SERVER) {
	$info['REQUEST_DETAILS'] = &$_SERVER;
    } 

    if ($HTTP_RAW_POST_DATA) {
	$info['RAW_DATA'] = &$HTTP_RAW_POST_DATA;
    }

    
    if (($LOGGER_STORE_OBJECTS)&&($req)&&(is_object($req))) {
	try {
	    /* DSToDo: somehow handle PDO exception (not-serializable) and serialize
	    everything besides it */
	    $info['object'] = $this->EncodeObject($req);
	} catch (Exception $ex) {
	    if (is_object($req->req)) {
		try {
		    $info['object'] = $this->DecodeObject($req->req);
		} catch (Exception $ex2) {}
	    }
	}
    }
    
    $pid = getmypid();
    
    if ($ADEI_SETUP) {
	$setup = $ADEI_SETUP;
    } else {
	$setup = "-";
    }

    if ($ADEI_SESSION) {
	$session = $ADEI_SESSION;
    } else {
	$session = "-";
    }

    if ($_SERVER['HTTP_X_FORWARDED_FOR']) {
        $client = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif ($_SERVER['REMOTE_ADDR']) {
	$client = $_SERVER['REMOTE_ADDR'];
    } else {
	$client = "-";
    }

    if ($location)
	$msg = sprintf("%s. %s", $location, $msg);
    
    if (($this->console === true)||($priority <= $this->console)) {
	if ($this->catch) {
	    $this->LogOutput();
	    echo "$msg\n";
	    $this->SkipOutput();
	}
    }

    if (!is_dir("$TMP_PATH/log")) {
	if (!@mkdir("$TMP_PATH/log", 0777, true)) return;
	@chmod("$TMP_PATH/log", 0777);
    }
    
    $usec = str_pad($tod['usec'], 6, "0", STR_PAD_LEFT);

    $msg = preg_replace("/\n/", "[[BR]]", $msg);

    $info_str = urlencode(json_encode($info));
#    $info_str = bin2hex(json_encode($info));
    $infolen = strlen($info_str);

    $msglen = strlen($msg);
    $msglen_str = sprintf("%d", $msglen);
    
    $fulllen = $msglen + $infolen + strlen(" \n");

    adeiSetSystemTimezone();
    $header = sprintf("%s %d %s %s %s %s %s %d %d", date("Y-m-d\TH:i:s." . $usec . "0P", $tod['sec']), $priority, $setup, $source, $session, $pid, $client, $fulllen, $msglen);
    adeiRestoreTimezone();
    
    $header_size = strlen($header) + strlen("  ");
    
    $fname = sprintf("$TMP_PATH/log/%s.log", date("Ymd"));
    
    $umask = @umask(0);

    $f = @fopen($fname, "a+");
    if ($f) {
    	if (flock($f, LOCK_EX)) {
	    fprintf($f, "%" . LOGGER::SIZE_RECORD_LENGTH . "d %s %s %s\n", $header_size, $header, $msg, $info_str);
	    flock($f, LOCK_UN);
	}

	fclose($f);
    }    

    @umask($umask);
 }
 
 function LogException(ADEIException $ae, $msg = NULL, $req = NULL, $extra = NULL, $priority = LOG_CRIT, $source = NULL) {
    global $LOGGER_STORE_OBJECTS;
    
    $einfo = $ae->getInfo();
    
    if ($msg) {
	if (strstr($msg, "%s")) $info = sprintf($msg, $einfo);
	else $info = "$msg: $einfo";
    } else {
	$info = $einfo;
    }
    
    if ($LOGGER_STORE_OBJECTS) {
	try {
	    if (($extra)&&(is_array($extra))) {
		$extra['exception'] = $this->EncodeObject($ae);
	    } else {
		$extra = array(
		    'exception' => $this->EncodeObject($ae)
		);
	    }
	} catch (Exception $e) {
	}
    }
    
    $this->LogMessage($info, $req, $extra, $priority, $source);
 }
}

$adei_logger = new LOGGER();

function adeiGetLogs ($from, $to, $prio = false, $filter = false) {
    global $adei_logger;
    return $adei_logger->GetLogs($from, $to, $prio, $filter);
}

function adeiGetLogInfo($file, $pos) {
    global $adei_logger;
    return $adei_logger->GetLogInfo($file, $pos);
}

function adeiGetLogger() {
    global $adei_logger;
    return $adei_logger;
}

function adeiEnableConsoleLog($priority = true) {
    global $adei_logger;
    $adei_logger->EnableConsoleLog($priority);
}

function adeiLogException(ADEIException $ae, $msg = NULL, $req = NULL, $extra = NULL, $priority = LOG_CRIT, $source = NULL) {
    global $adei_logger;
    $adei_logger->LogException($ae, $msg, $req, $extra, $priority, $source);
}

function adeiLogMessage($msg, $req = NULL, $extra = NULL, $priority = LOG_WARNING, $source = NULL) {
    global $adei_logger;
    $adei_logger->LogMessage($msg, $req, $extra, $priority, $source);
}

function adeiLogOutput() {
    global $adei_logger;
    $adei_logger->CatchOutput();
}

?>