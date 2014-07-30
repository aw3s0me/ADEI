<?php


interface DataInterface {
    public function GetData($from, $to);
}


function CopyObjectProperties(&$dst, &$src) {
    foreach ($src as $key => $value) {
	$dst->$key = $value;
    }
}


abstract class ADEICommon  extends BASICRequest {
 static function ParseDate($date) {
    $datetime = new DateTime($date);
    if ($datetime->format("u")>0) {
	return $datetime->format("U.u");
    } else {
	return $datetime->format("U");
    }

	// This is broken in some systems (Gentoo 32bit, PhP 5.2.6)
    // return strtotime($date);
 }

 //function GetLoginObject() {
    //return 25;
//}

 static function GetRootDir() {
    global $ADEI_ROOTDIR;

    return $ADEI_ROOTDIR;
 }
 
 static function GetSetupDir() {
    global $ADEI_SETUP;
    
    return self::GetRootDir() . "/setups/$ADEI_SETUP";
 }

 function GetTmpFile($prefix = "00000000000000000000000000000000", $suffix = "png") {
    global $ADEI_SESSION;
    global $TMP_PATH;

    $dir = "clients/" . $ADEI_SESSION . "/$prefix/";

    if (!is_dir($TMP_PATH . "/" .  $dir)) {
	if (!@mkdir($TMP_PATH . "/" . $dir, 0755, true)) 
	    throw new ADEIException(translate("Unable to create the temporary directory"));
    }

    return $dir . time() . "_" . rand() . "." . $suffix;
 }
 
 static function GetXSLTFile($xslt) {
    $root = self::GetRootDir();
    $setup = self::GetSetupDir();

    if (file_exists("$setup/xslt/$xslt.xsl")) return "$setup/xslt/$xslt.xsl";
    else if (file_exists("$root/xslt/$xslt.xsl")) return "$root/xslt/$xslt.xsl";
    else if (preg_match("/^(.*\\/)?([^\\/]+)_([\d\w]+)$/", $xslt, $m)) {
        $xslt = $m[1] . $m[3];
        if (file_exists("$setup/xslt/$xslt.xsl")) return "$setup/xslt/$xslt.xsl";
        else if (file_exists("$root/xslt/$xslt.xsl")) return "$root/xslt/$xslt.xsl";
        else throw new ADEIException(translate("Neither (%s.xsl) nor (%s.xsl) is found", $m[0], $xslt));
    }
    else throw new ADEIException(translate("XSL template (%s.xsl) is not found", $xslt));
 }
 
 static function RequireClass($names, $override = false) {
    global $ADEI_ROOTDIR;
    global $JPGRAPH_PATH;

    $root = self::GetRootDir();
    if ($override) {
	$setup = self::GetSetupDir();
    }
    
    if (!is_array($names)) {
	$names = array($names);
    }
    
    foreach ($names as &$name) {
	if (!preg_match("/[\w\d_\/]+/", $name)) {
	    throw new ADEIException(translate("Invalid class (%s) is requested", $name));
	}

	$inc = strtolower($name);
	
	if (($override)&&(file_exists("$setup/classes/$inc.php"))) $inc = "$setup/classes/$inc.php";
	else $inc = "$root/classes/$inc.php";

	if (!@include_once($inc)) {
	    throw new ADEIException(translate("Required file (%s) is not found", $inc));
	}
    }
 }

 static function RequireModule($inc, $override = true) {
    global $ADEI_ROOTDIR;
    global $JPGRAPH_PATH;

    if (!preg_match("/[\w\d_\/]+/", $inc)) {
	throw new ADEIException(translate("Invalid service (%s) is requested", $inc));
    }

    $root = self::GetRootDir();
    if ($override) {
	$setup = self::GetSetupDir();
    }
    
    if (($override)&&(file_exists("$setup/modules/$inc.php"))) $inc = "$setup/modules/$inc.php";
    else $inc = "$root/modules/$inc.php";

    if (!include_once($inc)) {
	throw new ADEIException(translate("Required file (%s) is not found", $inc));
    }
 }

 static function RequireService($inc, $override = true) {
    global $ADEI_ROOTDIR;
    global $JPGRAPH_PATH;

    if (!preg_match("/[\w\d_\/]+/", $inc)) {
	throw new ADEIException(translate("Invalid service (%s) is requested", $inc));
    }

    $root = self::GetRootDir();
    if ($override) {
	$setup = self::GetSetupDir();
    }
    
    $inc = strtolower($inc);
    
    if (($override)&&(file_exists("$setup/services/$inc.php"))) $inc = "$setup/services/$inc.php";
    else $inc = "$root/services/$inc.php";

    if (!include_once($inc)) {
	throw new ADEIException(translate("Required file (%s) is not found", $inc));
    }
 }

 static function RequireServiceClass($service, $module, $override = true) {
    global $ADEI_ROOTDIR;
    global $JPGRAPH_PATH;
    
    if (!preg_match("/[\w\d_\/]+/", $service)) {
	throw new ADEIException(translate("Invalid service (%s) is requested", $service));
    }

    if (!preg_match("/[\w\d_\/]+/", $module)) {
	throw new ADEIException(translate("Invalid service module (%s) is requested", $service));
    }

    $root = self::GetRootDir();
    if ($override) {
	$setup = self::GetSetupDir();
    }
    
    if (($override)&&(file_exists("$setup/services/$service/$module.php"))) $inc = "$setup/services/$service/$module.php";
    else $inc = "$root/services/$service/$module.php";

    if (!include_once($inc)) {
	throw new ADEIException(translate("Required file (%s) is not found", $inc));
    }
 }


 static function GetBaseURL() {
    global $ADEI_URL;
    global $ADEI_ROOTDIR;

    if ($ADEI_URL) {
	return $ADEI_URL;
    } else if ($_SERVER['SERVER_NAME']) {
//	list($url, $query) = preg_split("/\?/", $_SERVER['REQUEST_URI'], 2);

	$ssl = $_SERVER['HTTPS'];
	if (($ssl)&&(preg_match("/^(off|false|no)$/i", $ssl))) $ssl = false;
	
	if ($ssl) {
	    $url = "https://";
	    $default_port = 443;
	} else {
	    $url = "http://";
	    $default_port = 80;
	}

	$url .= $_SERVER['SERVER_NAME'];
	
	$port = $_SERVER['SERVER_PORT'];
	if (($port)&&($port != $default_port)) {
	    $url .= ":$port";
	}
    	
	if ($_SERVER['SCRIPT_NAME']) {
	    if (preg_match("/(.*\/)(services|admin)(\/[^\/]+)?$/", $_SERVER['SCRIPT_NAME'], $m)) {
		$url .= $m[1];
	    } else if (preg_match("/(.*\/)(\/[^\/]+)?$/", $_SERVER['SCRIPT_NAME'], $m)) {
		$url .= $m[1];
	    } else {
	    	$url .= $_SERVER['SCRIPT_NAME'];
	    }
	} else {
	    $url .= "/";
	}

	return $url;
    } else {
	return "http://localhost/" .  basename($ADEI_ROOTDIR) . "/";
    }
 }

 static function TransformXML($xslt, $xml, $need_xslt_loading=false) {
    $xsldoc = new DOMDocument();
    $xmldoc = new DOMDocument();

    if (!$need_xslt_loading) {
       if (($xsldoc)&&($xmldoc)) {
        if (!@$xsldoc->load(self::GetXSLTFile($xslt))) {
            throw new ADEIException(translate("Failed to load xslt stylesheet \"%s\"", $xslt));
        }
        
        if (!@$xmldoc->load($xml)) {
            throw new ADEIException(translate("Failed to load xml file \"%s\"", $xml));
        }
        } else {
        throw new ADEIException(translate("Failed to create DOM document"));
        } 
    }
    else {
        $xsldoc->loadXML($xslt);
        $xmldoc->loadXML($xml);
        if (!$xmldoc) {
            throw new ADEIException(translate("Failed to load xml file \"%s\"", $xml));
        }
        if (!$xsldoc) {
            throw new ADEIException(translate("Failed to load xslt stylesheet \"%s\"", $xslt));
        }
    }
    
    
    // HTML output is evil, it will change <br/> to <br>. And XHTML is not supported yet.
    $outputs = $xsldoc->getElementsByTagNameNS("http://www.w3.org/1999/XSL/Transform", "output");
    if ($outputs->length) {
	$node = $outputs->item(0);
	if (!strcasecmp($node->getAttribute("method"), "html")) {
	    $node->setAttribute("method", "xml");
/*	
    <xsl:output method="xml" version="1.0" encoding="utf-8" 
	media-type="text/html" omit-xml-declaration="yes"
	doctype-public="-//W3C//DTD HTML 4.0 Transitional//EN"
	doctype-system="http://www.w3.org/TR/REC-html40/loose.dtd"/>
*/
	}	
    }
    
    $xh = new XSLTProcessor();
    if ($xh) {
	if (!@$xh->importStylesheet($xsldoc)) {
	    throw new ADEIException(translate("Failed to process xslt stylesheet \"%s\"", $xslt));
	}
    } else {
	throw new ADEIException(translate("Failed to create XSLT processor"));
    }
	
    $res = @$xh->transformToXML($xmldoc);
    if (!$res) throw new ADEIException(translate("XSL Transformation is failed"));
    
    return $res;
 }

 static function EscapeForXML($message) {
	/* Note: htmlspecialchars will return empty string if non-unicode m
	essage is passed. */
    $msg = htmlspecialchars($message, ENT_COMPAT, "UTF-8");
    if ($msg) return $msg;
    return htmlspecialchars($message, ENT_COMPAT);
 }
 
 static function EncodeObjectToXML($out, $name, array &$object, array $opts, $toplevel = false) {
    $have_children = false;
    $enumerated = true;
    fwrite($out, "<$name");
    $xml = false;
    foreach ($object as $key => &$value) {
	if (!strcmp($key, "xml")) {
	    $xml = $value;
	} else if (!is_array($value)) {
	    if (preg_match("/^(from|to)$/", $key)) {
		if ($opts['time_format']) {
		    $keyval = self::EscapeForXML(date($opts['time_format'], $value));
		} else {
		    $keyval = self::EscapeForXML($value);
		}
	    } else {
		$keyval = self::EscapeForXML($value);
	    } 
	    fwrite($out, " $key=\"$keyval\"");
	} else {
	    $have_children = true;
	    if (!is_int($key)) $enumerated = false;
	}
    }
    if ((!$have_children)&&(!$xml)) {
	fwrite($out, "/>");
	return;
    } else if ($xml) {
	fwrite($out, "><span>$xml</span>");
//	if (!$haev_children) fwrite($out, "</$name>");
    } else {
	fwrite($out, ">");
    }
    
    
    $level = $toplevel?false:0;
        
    foreach ($object as $key => &$value) {
	if (is_array($value)) {
	    if (is_numeric(substr($key, 0, 1))) {
		if ($enumerated) {
		    if ($level === false) {
			$level = 0;
			foreach ($value as &$sub) {
			    if (is_array($sub)) {
				$level = 1;
				break;
			    }
			}
		    }
		    if ($level) $subname = "Group";
		    else $subname = "Value";
		} else {
		    throw new ADEIException(translate("Encoding to XML failed, invalid object element \"%s\"", $key));
		}
	    } else {
		$subname = $key;
	    }
	    self::EncodeObjectToXML($out, $subname, $value, $opts);
	}
    }
    fwrite($out, "</$name>");
 }
 
 static function EncodeToXML($out, $name, array &$result, $opts = false) {
    fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    self::EncodeObjectToXML($out, $name, $result, $opts?$opts:array(), true); 
 }

 static function EncodeToStandardXML($error, array &$result = NULL, $xslt = false, $opts = false) {
    if ($xslt) {
	$temp_file = tempnam(sys_get_temp_dir(), 'adei_xml.');
	$out = @fopen($temp_file, "w");
	if (!$out) $error = translate("I'm not able to create temporary file \"%s\"", $temp_file);
    } else {
	$out = fopen("php://output", "w");
    }

    if ($out) {
	if ($error) {
	    fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
	    fwrite($out, "<result><Error>" . self::EscapeForXML($error) . "</Error></result>");
	    $error = false;
	} else if ($result) {
	    self::EncodeToXML($out, "result", $result, $opts);
	} else {
	    fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
	    fwrite($out, "<result></result>");
	}

	fclose($out);
    }

    if (($xslt)&&(!$error)) {
	try {
	    echo self::TransformXML($xslt, $temp_file);
	} catch (ADEIException $ex) {
	    $ex->logInfo();
	    $error = self::EscapeForXML($ex->getInfo());
	}
	@unlink($temp_file);
    }
    
    if ($error) {
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<result><Error>$error</Error></result>";
    }

    return $error;
 }
}



/*
    $this->gid = $props["db_group"];
    $this->group_table = "log" . $this->gid;

	// Handle names
    $this->mask = $props["db_mask"];

    
    $this->interval_initialized = false;
    if (isset($props['interval_start'])) $this->interval_start = $props['interval_start'];
    else $this->interval_start = 0;
    if (isset($props['interval_end'])) $this->interval_end = $props['interval_end'];
    else $this->interval_end = 0;

    $this->window_initialized = false;
    if (isset($props['window_start'])) $this->window_start = $props['window_start'];
    else $this->window_start = 0;
    if (isset($props['window_size'])) $this->window_size = $props['window_size'];
    else $this->window_size = 0;


    if (!$this->connected) throw new ADEIException(_("The ZEUS database is not specified"));


 function GetWindowSize() {	
    if (!$this->window_initialized) $this->SetupWindow();
    return $this->window_size;
 }

*/    

?>