<script type="text/javascript">
//<![CDATA[

if (!Array.prototype.indexOf) {
  Array.prototype.indexOf = function(elt /*, from*/) {
    var len = this.length >>> 0;

    var from = Number(arguments[1]) || 0;
    from = (from < 0) ? Math.ceil(from) : Math.floor(from);
    if (from < 0) from += len;

    for (; from < len; from++) {
      if (from in this && this[from] === elt) return from;
    }

    return -1;
  };
}

    function doDrop() {
	var node = document.getElementById("action_input");
	if (node) node.value = "drop";
    }

    function doRewidth() {
	var node = document.getElementById("action_input");
	if (node) node.value = "rewidth";
    }

    function Mark(mlist, on) {
        var l = mlist.split(",");
        var frm = document.getElementById("postfix_form");
        if (typeof frm == "undefined") return;

        for (var i = 0; i < l.length; i++) {
            l[i] = "postfix" + l[i];
        }
        
        var j = 0;
        var inputs = frm.getElementsByTagName('input');
        for (var i = 0; i < inputs.length; i++) {
            var name = inputs[i].name;//getAttribute['name'];
            if (l.indexOf(name) >= 0) {
                inputs[i].checked = on;
            }
        }
        
        
        //alert(typeof frm.elements);
        //.getElementById("postfix" + l[0]));
        
    }
//]]>
</script>

<?php 

/*
function data_link(&$req, $grname, $format, $from, $to) {
    global $FORMATS;
    
    $props = array();
    $props["experiment"] = "$from-$to";
    $props["window"] = "0";
    $props["format"] = $format;    

    if ($FORMATS[$format]["title"]) $title = $FORMATS[$format]["title"];
    else $title = $format;
    if ($FORMATS[$format]["extension"]) $ext = $FORMATS[$format]["extension"];
    else $ext = $format;

    
//    $props["filename"] =  preg_replace("/[^\w\d]/", "_", $grname) . "__" . round($from) . "_" . round($to) . "." . $ext;
    
    $query = $req->GetQueryString($props);
    
    echo "<a href=\"services/getdata.php?$query\">" . $title . "</a>";
}

try {
    $req = new SOURCERequest();
} catch (ADEIException $e) {
    $req = new REQUEST();
    $nodata = 1;
}
*/

$flags = REQUEST::NEED_ITEMINFO|REQUEST::NEED_INFO|CACHE::TABLE_INFO|CACHE::NEED_REQUESTS|CACHE::FIND_BROKEN;

$cache = new CACHEDB();
$list = $cache->GetCacheList($flags);
usort($list, create_function('$a,$b', 'return strcasecmp(
    $a["db_server"] . "__" . $a["db_name"] . "__" . $a["db_group"],
    $b["db_server"] . "__" . $b["db_name"] . "__" . $b["db_group"]
);'));

?><br/><form id="postfix_form" action="index.php?page=do.php" method="post">
    <input type="hidden" name="action" id="action_input"/>
    <input type="submit" value="<?echo translate("Drop Selected");?> " onClick="javascript:doDrop()"/>
    <input type="submit" value="<?echo translate("Resize Selected");?> " onClick="javascript:doRewidth()"/>
<?

function sumarize_info(&$sum, &$info) {
    if (is_numeric($info['first'])) {
        if (($sum['first'] === false)||($sum['first'] > $info['first'])) {
	    $sum['first'] = $info['first'];
	}
    }

    if (is_numeric($info['last'])) {
	if (($sum['last'] === false)||($sum['last'] < $info['last'])) {
	    $sum['last'] = $info['last'];
	}
    }

    if (is_numeric($info['dbsize'])) {
        $sum['dbsize'] += $info['dbsize'];
    }
}

if ((isset($_GET['source_info']))||(isset($_GET['filter']))) {
    $sources = array();
    $source_info = array(
        'tables' => array(),
        'groups' => array(),
        'first' => false,
        'last' => false,
        'dbsize' => 0
    );
    $servers = array();
    $server_info = array(
        'tables' => array(),
        'databases' => array(),
        'groups' => array(),
        'first' => false,
        'last' => false,
        'dbsize' => 0
    );
    $unknown = array(
        'tables' => array(),
        'databases' => array(),
        'groups' => array(),
        'first' => false,
        'last' => false,
        'dbsize' => 0
    );
    $groups = array();
    foreach ($list as $id => &$info) {
      if ((!isset($info['db_server']))||(!isset($info['db_name']))||(!isset($info['db_group']))) {
        array_push($unknown['tables'], $info['postfix']);
        sumarize_info($unknown, $info['info']);
      } else {
        $server = $info['db_server'];
        $source = $info['db_server'] . "__" . $info['db_name'];
        $group = $info['db_server'] . "__" . $info['db_name'] . "__" . $info['db_group'];

        if (!isset($sources[$source])) $sources[$source] = $source_info;
        if (!isset($servers[$server])) $servers[$server] = $server_info;
        array_push($servers[$server]['databases'], $source);
        array_push($sources[$source]['tables'], $info['postfix']);
        array_push($sources[$source]['groups'], $group);
        $groups[$group] = $id;
        sumarize_info($sources[$source], $info['info']);
      }
    }
    
    foreach ($servers as $server => &$srv) {
        $srv['databases'] = array_unique($srv['databases']);
        foreach ($srv['databases'] as $db) {
            array_splice($servers[$server]['groups'], -1, 0, $sources[$db]['groups']);
            array_splice($servers[$server]['tables'], -1, 0, $sources[$db]['tables']);
            sumarize_info($servers[$server], $sources[$db]);
        }
    }
    
    if ($unknown['tables']) {
        $servers['_unknown_'] = &$unknown;
    }

    if (isset($_GET['filter'])) {
        $filter = $_GET['filter'];
        $pos = strpos($filter, "__");
        if ($pos === false) {
            $filter_server = $filter;
            $filter_source = false;
            $tables = &$servers[$filter]['tables'];
        } else {
            $filter_server = substr($filter, 0, $pos);
            $filter_source = $filter;
            $tables = &$sources[$filter]['tables'];
        }
    } else {
        $filter_server = false;
        $filter_source = false;
    }

    if (isset($_GET['source_info'])) {
      foreach ($servers as $server => &$srv) {
        if (isset($_GET['group_info'])&&(($filter_server === false)||($filter_server == $server))) $show_mark = true;
        else $show_mark = false;
        do {
            $grp0 = $groups[$srv['groups'][$i++]];
        } while ((is_numeric($grp0))&&(!$list[$grp0]['req']));
        ?><div class="source" <?= ($filter_server == $server)?"style=\"border: 2px solid pink;\"":""?>>
        <h3> Server: <a href="index.php?page=cacheinfo.php&source_info&group_info&table_info&filter=<?=$server?>"><?=$server?></a><?
        if ($show_mark) {
            ?>(<a href="javascript:Mark('<?=implode(",", $srv["tables"])?>', 1)">Mark</a>,<a href="javascript:Mark('<?=implode(",", $srv["tables"])?>', 0)">Unmark</a>)<?
        }?></h3><?
        if ($server != "_unknown_") {
            if (is_numeric($grp0)) echo translate("Server: %s (%s)", $list[$grp0]['server'], $list[$grp0]['reader']);
    	    else echo translate("Server: In-active");
    	    echo "<br/>";
	}
	echo translate("First record: %s", date("r", $srv['first'])) . "<br/>";
	echo translate("Last record: %s", date("r", $srv['last'])) . "<br/>";
	echo translate("Database Size: %s", dsPrintSize($srv['dbsize']));

	if ($srv['databases']) echo "<br/><br/>Databases:<br/>";
	else echo ", " . translate("%u groups", sizeof($srv['tables'])) . "<br/>";
	foreach ($srv['databases'] as $source) {
	    $db = &$sources[$source];
	    echo "<a href=\"index.php?page=cacheinfo.php&source_info&group_info&table_info&filter=$source\"" . (($filter_source == $source)?"style=\"color:red\";":"") . ">" . substr(strstr($source, "__"),2) . "</a>: ";
            echo translate("%u groups", sizeof($db['groups'])) . ", ";
	    echo dsPrintSize($db['dbsize']) . ", ";
	    echo date("r", $db['first']) . " - " . date("r", $db['last']);
	    if (($show_mark)&&(($filter_source === false)||($filter_source == $source))) {
                ?>(<a href="javascript:Mark('<?=implode(",", $db["tables"])?>', 1)">Mark</a>,<a href="javascript:Mark('<?=implode(",", $db["tables"])?>', 0)">Unmark</a>)<?
	    }
	    echo "<br/>";
	}?>
        </div><?
      }
    }
} 

if (isset($_GET['group_info'])) {
  foreach ($list as &$info) {
    if ($filter) {
        if (!in_array($info['postfix'], $tables)) continue;
    }
    ?><div class="group">
	<h3>Tables: cache*<?echo $info["postfix"];?> (<a href="index.php?page=do.php&action=drop&postfix=<?echo urlencode(json_encode(array($info['postfix'])));?>"><?echo translate("Drop");?></a>)
	<input type="checkbox" name="postfix<?echo $info['postfix']?>" value="1"/>
	</h3>
	<?
	echo translate("SourceID: %s", $info['db_server'] . "__" . $info['db_name'] . "__" . $info['db_group']) . "<br/>";

	if ($info['incomplete']) {
	    echo translate("Status") . ": <b>" . translate("Broken") ."</b>" . "<br/>";
	}
	
	if ($info['req']) {
    	    echo translate("Active: yes") . "<br/>";
	    if ($info['disconnected']) {
		echo translate("Mode: disconnected") . "<br/>";
	    }
        } else if ($info['disconnected']) {
	    echo translate("Active: unknown");
	    echo " (" . translate("The data source is disconnected at the moment") . ")";
	    echo "<br/>";
	} else {
	    echo translate("Active: no");
	    if (($info['server'])&&($info['database'])&&($info['group'])) {
		// strange should not be	
	    } else if (($info['server'])&&($info['database'])) {
		echo " (" . translate("The loggroup is not present any more") . ")";
	    } else if ($info['server']) {
		echo " (" . translate("The database is not present any more") . ")";
	    } else {
		echo " (" . translate("The data source is not present in active configuration") . ")";
	    }
	    echo "<br/>";
	}
	
	if ($info['reader']) {
	    echo translate("Reader: %s", $info['reader']) . "<br/>";
	}
	
	if ($info['server']) {
	    echo translate("Server: %s", $info['server']) . "<br/>";
	}
	
	if ($info['database']) {
	    echo translate("Database: %s", $info['database']) . "<br/>";
	}

	if ($info['group']) {
	    echo translate("LogGroup: %s", $info['group']) . "<br/>";
	}
	
	echo "<br/>";

	if ($info['info']['dbsize']) {
	    echo translate("Database Size: %s", dsPrintSize($info['info']['dbsize'])) . "<br/>";
	}

	if ($info['info']['records']) {
	    echo translate("Number of records: %s", $info['info']['records']) . "<br/>";
	}

	if ($info['info']['width']) {
	    echo translate("Number of items: %s", $info['info']['width']);

	    if (is_array($info['info']['items'])) {
		$reader_width = sizeof($info['info']['items']);
		if (($reader_width)&&($reader_width != $info['info']['width'])) {
		    echo " (CACHE), $reader_width (READER)";
		    echo " <a href=\"index.php?page=do.php&action=rewidth&postfix=" . urlencode(json_encode(array($info['postfix']))) . "\">[ " . translate("Resize") . " ]</a>";
		}
	    }
	    
	    echo "<br/>";
	}
	
	if ($info['info']['outdated']) {
	    echo translate("Table version: outdated, needs update") . "<br/>";
	}
	
	if (isset($info['info']['ns'])) {
	    if ($info['info']['ns']) {
		echo translate("Subsecond precision: yes") . "<br/>";
	    } else {
		echo translate("Subsecond precision: no") . "<br/>";
	    }
	}

	if (($info['info']['first'])&&($info['info']['last'])) {
	    echo translate("First record: %s", date("r", $info['info']['first'])) . "<br/>";
	    echo translate("Last record: %s", date("r", $info['info']['last'])) . "<br/>";
	}
	
	if ($info['info']['tables']) {
	    echo translate("Resolutions:");
	    foreach (array_keys($info['info']['tables']) as $res) {
		echo " $res";
	    }
	    echo "<br/>";
	    
	    if (isset($_GET['table_info'])) {
	        echo "<br/>";
		
	        echo translate("Extended Table Info:") . "<br/><table>";
		foreach ($info['info']['tables'] as $res => $tblinfo) {
		    $output = false;
		    echo "<tr><td>&nbsp;" . sprintf("% 5u", $res) . ":</td><td>";
		
		    if ($tblinfo['dbsize']) {
			if ($output) echo ", ";
			else $output = true;

			echo dsPrintSize($tblinfo['dbsize']);
		    }

		    if ($tblinfo['records']) {
			if ($output) echo ", ";
			else $output = true;
		    
			echo translate("%s records", $tblinfo['records']);
		    }

		    if (($tblinfo['first'])&&($tblinfo['last'])) {
			if ($output) echo ", ";
			else $output = true;

			echo date("c", $tblinfo['first']) . ' - ' . date("c", $tblinfo['last']+$res);
			$output = true;
		    }
		    echo "</td></tr>";
	        }
		echo "</table>";
	    }

	    if ((isset($_GET['item_info']))&&(is_array($info['info']['items']))) {
	        echo "<br/>";
		
	        echo translate("Extended Item Info:") . "<br/><table>";
		foreach ($info['info']['items'] as $id => $iinfo) {
		    echo "<tr><td>&nbsp;" . sprintf("% 3u", $id) . ":</td><td>";
		    echo $iinfo['name'];
		    echo "</td></tr>";
		}
		echo "</table>";
	    }
	}
	

//	print_r($info);
	?>
	<br/><br/>
    </div><?
  }
}
echo "</form>";
?>
