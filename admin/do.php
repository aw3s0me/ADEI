<?php

    function PlaceJS() {
	global $action;
	global $postfix;
?>
<script type="text/javascript">
//<![CDATA[
    function Confirm() {
	window.location = "index.php?page=do.php&confirm&action=<?echo $action;?>&postfix=<?echo urlencode(json_encode($postfix));?>";
    }
//]]>
</script>
<?
    }

    function RequestConfirmation($msg, $list) {
	echo "<b>$msg</b>";
	echo "<div class=\"list\">";
	foreach ($list as $postfix) {
	    echo "cache*$postfix<br/>";
	}
	echo "</div>";
	echo "<br/>";
	echo "<button onclick=\"Confirm();\">" . translate("Confirm") . "</button>";
    }


    function GetAdminFile($prefix = false) {
	global $TMP_PATH;
    
	$dir = "adminscripts/";

	if (!is_dir($TMP_PATH . "/" .  $dir)) {
	    if (!@mkdir($TMP_PATH . "/" . $dir, 0755, true)) 
		throw new ADEIException(translate("Access to the temporary directory is denied"));
	}

	$fn = tempnam($TMP_PATH . "/" .  $dir, $prefix);

	if ($fn) unlink($fn);

	return $fn . ".php";
    }



    $action = $_REQUEST['action'];
    $confirm = isset($_REQUEST['confirm']);

    echo "<br/><br/>";

    if (isset($_GET['postfix']))
	$postfix = json_decode(stripslashes($_GET['postfix']), true);
    else {
	$postfix = array();
	foreach ($_POST as $key => $value) {
	    if (($value == 1)&&(preg_match("/^postfix(.*)$/", $key, $m))) {
		array_push($postfix, $m[1]);
	    }
	}
    }
    
    if (!$postfix) {
	?><span class="error"><?echo translate("Error: The list of items is not supplied");?></span><?
	exit;
    }
    
    if (!is_array($postfix)) $postfix = array($postfix);

    if (!$confirm) PlaceJS();
    
    switch ($action) {
	case "drop":
	    if ($confirm) {
		try {
		    $cache = new CACHEDB();
		    foreach ($postfix as $p) {
			$cache->Drop($p);
		    }
		    echo translate("Done. All CACHES are droped.");
		} catch (ADEIException $ae) {
		    ?><span class="error"><?echo translate("Error: %s", $ae->getInfo());?></span><?
		}
	    } else RequestConfirmation(
		translate("Do you really want to drop following tables: "),
		$postfix);
	    break;
	case "rewidth":
	    if ($confirm) {
		try {
		    $name = GetAdminFile("resize-");
		    $f = fopen($name, "w");
		    if (!$f)
			throw new ADEIException(translate('Error creating temporary file: %s', $name));
		    
		    fwrite ($f, '<?php
			$ADEI_ROOTDIR = "' . dirname($_SERVER['SCRIPT_FILENAME']) . '/../";
			require("$ADEI_ROOTDIR/adei.php");
			try {
			    $cache = new CACHEDB();
		    ');
		    foreach ($postfix as $p) {
			fwrite($f, '$cache->Rewidth("' . $p . '", true);' . "\n");
		    }
		    fwrite($f, '
			} catch (ADEIException $ae) {
			    echo translate("Error: %s", $ae->getInfo()) . "\n";
			    exit;
			}
			echo translate("done...") . "\n";?>');
		    fclose($f);
		    
		    if ($SETUP) {
			echo "Please, login to the server and run: php $name -setup $SETUP";
		    } else {
			echo "Please, login to the server and run: php $name";
		    }

/*
		    $cache = new CACHEDB();
		    foreach ($postfix as $p) {
			$cache->Rewidth($p);
		    }
*/
		} catch (ADEIException $ae) {
		    ?><span class="error"><?echo translate("Error: %s", $ae->getInfo());?></span><?
		}
	    } else RequestConfirmation(
		translate("Do you really want to resize following tables: "),
		$postfix);
	    break;
	default:
	?><span class="error"><?
	    if ($action)
		echo translate("Error: Invalid action \"%s\" is specified", $action);
	    else
		echo translate("Error: Action is not specified");
	?></span><?
    }	
?>