<?php

class APPFilter extends IOFilter {
 var $opts;
 var $start_opts;
 var $end_opts;
 
 var $app_block_mode;
 
 function __construct(&$info = NULL, STREAMWriterInterface $output = NULL) {

    $this->writer = new IO();

    $all_opts = $info['opts'] . " " .$info['start_opts'] . " " . $info['end_opts'];
    if (preg_match_all("/@([\w\d]+)__[\w\d_]+@/", $all_opts, $m, PREG_PATTERN_ORDER)) {
	$this->specials = $m[1];
    }

    if ($info['opts'])  {
	if (strpos($info['opts'], "@TMPFILE@") !== false) {
	    parent::__construct($info, $output, NULL, false);
	} else {
	    parent::__construct($info, $output, NULL, NULL);
	}
    } else {
	parent::__construct($info, $output, NULL, NULL);
    }
    
    if (isset($this->info['groupmode']))
	$this->app_block_mode = $this->info['groupmode'];
    else
	$this->app_block_mode = true;
 }
 
 static function DoReplace ($m) {
    $expr = "if (" . $m[1] . ") \$res=\"" . $m[2] . "\"; else \$res=\"" . $m[5] . "\";";
    if (@eval($expr) === false) {
	if (preg_match("/(@[\w\d_]+@)/", $expr, $mm)) 
	    throw new ADEIException(translate("APPFilter. Unknown option (%s) is supplied", $mm[1]));
	throw new ADEIException(translate("APPFilter. Invalid conditional (%s)?(%s):(%s)", $m[1], $m[2], $m[5]));
    }
    return $res;
 }
 
 function ExpandConditionals(&$str) {
    return preg_replace_callback("/\?{([^?]+)\?((\\\\{|\\\\}|\\\\:|[^{}:])+)(:((\\\\{|\\\\}|\\\\:|[^{}:])+))?}/", 'APPFilter::DoReplace', $str);
 }
 
 function Configure(&$args = NULL, $semode = false) {
    if (($this->info['opts'])||(($semode)&&(($this->info['start_opts'])||($this->info['end_opts'])))) {
	if ($this->filewriter) {
	    if ($args['tmpfile']) $tmpfile = $args['tmpfile'];
	    else $tmpfile = $this->tmpfile;

	    $pattern = array("/@TMPFILE@/");
	    $replacement = array($tmpfile);
	} else {
	    $pattern = array();
	    $replacement = array();
	}
	
	if ($args) {
	    foreach($args as $key => &$value) {
		array_push($pattern, "/@" . strtoupper($key) . "@/");
		array_push($replacement, $value);
	    }
//	    array_push($pattern, "/\?1\?([^:}]+)(:([^:}]+))?}/");
//	    array_push($replacement, '${1}');
//	    array_push($pattern, "/\?0\?([^:}]+)(:([^:}]+))?}/");
//	    array_push($replacement, '${3}');
	}
	
	$this->opts = $this->ExpandConditionals(preg_replace($pattern, $replacement, $this->info['opts']));
	if ($semode) {
	    if ($this->info['start_opts'])
		$this->start_opts = $this->ExpandConditionals(preg_replace($pattern, $replacement, $this->info['start_opts']));
	    else
		$this->start_opts = false;	    
	    if ($this->info['end_opts'])
		$this->end_opts = $this->ExpandConditionals(preg_replace($pattern, $replacement, $this->info['end_opts']));
	    else
		$this->end_opts = false;
	}
    } else {
	$this->opts = false;
	if ($semode) {
	    $this->start_opts = false;
	    $this->end_opts = false;
	}
    }
 }
 
 function OpenFilter() {
    $app = adei_app($this->info['app'], $this->opts, true);
//    echo $app . "\n";
    if ($this->filewriter) {
	$this->proc = popen($app, "w");

	$pipes = array($this->proc, NULL);
    } else {
	$spec = array(
	    0 => array("pipe", "r"),
	    1 => array("pipe", "w")
	);

	$this->proc = proc_open($app, $spec, $pipes);
    }

    return $pipes;
 }
 
 
 function CloseFilter(STREAMWriterInterface $h = NULL) {
    if ($this->filewriter) {
	$ret = pclose($this->proc);
    } else {
	$ret = proc_close($this->proc);
    }
    if ($ret) {
	throw new ADEIException(translate("APPFilter (%s) is finished with error %u", $this->info['app'], $ret));
    }
 }
 
 
 
 function Open(&$args = NULL) {
    parent::Open($args);

    if (($this->info['start_app'])||($this->info['start_opts'])||($this->info['end_app'])||($this->info['end_opts'])) {
	$this->Configure($args, true);
    }

    if ($this->info['start_app']) {
	$start_app = adei_app($this->info['start_app'], $this->start_opts, true);
    } else if ($this->start_opts) {
	$start_app = adei_app($this->info['app'], $this->start_opts, true);
    } else {
	$start_app = false;
    }

    if ($start_app) {
	$res = popen($start_app, "r");
	if ($this->filewriter) {
	    while (!feof($res)) fread($fp, STREAM::BUFER_SIZE);
	} else {
	    if ($this->output) {
		while (!feof($res)) {
        	    $this->output->WriteData(fread($fp, STREAM::BUFER_SIZE));
	        }
	    } else {
		$this->extra_data = "";
		while (!feof($res)) {
        	    $this->extra_data .= fread($fp, STREAM::BUFER_SIZE);
	        }
	    }
	} 
	$ret = pclose($res);
	
	if ($ret)
	    throw new ADEIException(translate("APPFilter (%s) [start] is finished with error %u", $this->info['app'], $ret));
    }

    if (!$this->app_block_mode) {
	parent::BlockStart($args, $this->OpenFilter(), $this->filewriter?0:STREAM::GIFT);
    }
 }
 
 function BlockStart(&$args = NULL, $flags = 0) {
    if ($this->app_block_mode) {
	$this->Configure($args);
	parent::BlockStart($args, $this->OpenFilter(), $this->filewriter?0:STREAM::GIFT);
    }
 }

 function BlockEnd(STREAMHandler $h = NULL) {
    if ($this->app_block_mode) {
	$res = parent::BlockEnd($h);
	$this->CloseFilter();
	return $res;
    }    
 }

 function Close(STREAMHandler $h = NULL, $flags = 0) {
    $res = "";
    if (!$this->app_block_mode) {
	$res = parent::BlockEnd($h);
        $this->CloseFilter();
    }

    if ($this->info['end_app']) {
	$end_app = adei_app($this->info['end_app'], $this->end_opts, true);
    } else if ($this->end_opts) {
	$end_app = adei_app($this->info['app'], $this->end_opts, true);
    } else {
	$end_app = false;
    }

    if ($end_app) {
	$res = popen($end_app, "r");
	if ($this->filewriter) {
	    while (!feof($res)) fread($fp, STREAM::BUFER_SIZE);
	} else {
	    if ($this->output) $h = $this->output;
	    
	    if ($h) {
	        while (!feof($res)) {
        	    $h->WriteData(fread($fp, STREAM::BUFER_SIZE));
		}	    
	    } else {
	        while (!feof($res)) {
        	    $res .= fread($fp, STREAM::BUFER_SIZE);
		}	    
	    }
	} 
	$ret = pclose($res);
	
	if ($ret)
	    throw new ADEIException(translate("APPFilter (%s) [end] is finished with error %u", $this->info['app'], $ret));

    }

    if ($res) return $res . parent::Close();
    return parent::Close();
 }
 
}


?>