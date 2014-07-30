<?php
function GetTmpFile($prefix, $ext = false) {
    return tempnam(sys_get_temp_dir(), $prefix) . ($ext?".$ext":"");
}

function dsPrintSelectOptions($config, $selected=false) {
    foreach ($config as $opt => $value) {
	if (($selected)&&(strcmp($value,$selected))) $selected = " selected=\"1\"";
	else $selected = "";
	
	print "<option value=\"$value\"$selected>$opt</option>";
    }
}

function dsMathPreciseSubstract($a, $b) {
    if ($a < $b) return -dsMathPreciseSubstract($b, $a);
    
    $pos = strpos($a, ".");
    if ($pos === false) {
	$ra = 0;
	$ia = (int)$a;
    } else {
        $ia = (int)floor($a);

	if (is_float($a)) $ra = $a - $ia;
	else if ($ia < 0) $ra = 1 - ("0." . substr($a, $pos + 1));
	else $ra = "0." . substr($a, $pos + 1);
    }
    
    $pos = strpos($b, ".");
    if ($pos === false) {
	$rb = 0;
	$ib = (int)$b;
    } else {
        $ib = (int)floor($b);

	if (is_float($b)) $rb = $b - $ib;
	else if ($ib < 0) $rb = 1 - ("0." . substr($b, $pos + 1));
	else $rb = "0." . substr($b, $pos + 1);
    }

    if (($ra)||($rb)) {
	$r = $ra - $rb;

#	if ($a < 0) echo "$a,$b   $ia,$ib,    $ra,$rb   = $r\n";

	if ($r < 0) {
	    if ($ia > $ib) return ($ia - $ib - 1) . strstr(sprintf("%.24F", ($r+1)), ".");
	    else return 0;
	} else if ($r > 0) {
	    if ($ia < $ib) return 0;
	    else return ($ia - $ib) . strstr(sprintf("%.24F", $r), ".");
	} else {
	    if ($ia > $ib) return ($ia - $ib);
	    else return 0;
	}
    } else return $ia - $ib;
}

function dsMathPreciseAdd($a, $b) {
    $pos = strpos($a, ".");
    if ($pos === false) {
	$ra = 0;
	$ia = (int)$a;
    } else {
        $ia = (int)floor($a);

	if (is_float($a)) $ra = $a - $ia;
	else if ($ia < 0) $ra = 1 - ("0." . substr($a, $pos + 1));
	else $ra = "0." . substr($a, $pos + 1);
    }

    $pos = strpos($b, ".");
    if ($pos === false) {
	$rb = 0;
	$ib = (int)$b;
    } else {
        $ib = (int)floor($b);

	if (is_float($b)) $rb = $b - $ib;
	else if ($ib < 0) $rb = 1 - ("0." . substr($b, $pos + 1));
	else $rb = "0." . substr($b, $pos + 1);
    }

    if (($ra)||($rb)) {
	$r = $ra + $rb;
	if ($r > 1) return ($ia + $ib + 1) . strstr(sprintf("%.24F", $r), ".");
	else if ($r < 1) return ($ia + $ib) . strstr(sprintf("%.24F", $r), ".");
	else  return ($ia + $ib + 1);
    } else return $ia + $ib;
}

function dsMathPreciseCompare($a, $b) {
    $pos = strpos($a, ".");
    if ($pos === false) {
	$ra = 0;
	$ia = (int)$a;
    } else {
        $ia = (int)floor($a);

	if (is_float($a)) $ra = $a - $ia;
	else $ra = "0." . substr($a, $pos + 1);
    }

    $pos = strpos($b, ".");
    if ($pos === false) {
	$rb = 0;
	$ib = (int)$b;
    } else {
        $ib = (int)floor($b);

	if (is_float($b)) $rb = $b - $ib;
	else $rb = "0." . substr($b, $pos + 1);
    }

    if (($ra)||($rb)) {
	if ($ia > $ib) return 1;
	if ($ia < $ib) return -1;
	return ($ra == $rb)?0:(($ra<$rb)?-1:1);
    } 
    return ($ia == $ib)?0:(($ia<$ib)?-1:1);
}


function dsPrintSize($size) {
    $lvl = 0;

    while (($lvl<4)&&($size > 5119)) {
	$lvl++;
	$size = (int)ceil($size / 1024.);
    }
    
    switch ($lvl) {
	case 0:
	    $size .= " bytes";
	    break;
	case 1:
	    $size .= " KB";
	    break;
	case 2:
	    $size .= " MB";
	    break;
	case 3:
	    $size .= " GB";
	    break;
	case 4:
	    $size .= " TB";
	    break;
    }
    
    return $size;
}


function xml_escape($message) {
	/* HTMLSpecialChars will return empty string if non-unicode message
	is passed. */
    $msg = htmlspecialchars($message, ENT_COMPAT, "UTF-8");
    if ($msg) return $msg;
    return htmlspecialchars($message, ENT_COMPAT);
}

function translate($string) {
    $arg = array();
    for($i = 1 ; $i < func_num_args(); $i++)
	$arg[] = func_get_arg($i);

    return vsprintf(gettext($string), $arg);
}

function log_message($message) {
    echo "$message\n";
}

?>