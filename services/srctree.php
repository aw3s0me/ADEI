<?php
global $ADEI_ID_DELIMITER;
global $ADEI_SRCTREE_EXTRA;

header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");


$id = $_GET['id'];
$child = 1;
if (isset($id)) {
    $tmp = explode($ADEI_ID_DELIMITER, $id, 3);
    $prefix = $id . $ADEI_ID_DELIMITER;
    $parent = $id;

    if ($tmp[0] == "virtual") {
        $props = array();
        $extra_mod = $tmp[1];
        $prefix = $tmp[2];
        $type = 4;
    } else {
        $props = array(
	    "db_server" => $tmp[0]
        );
    
        if (isset($tmp[1])) {
	    $props["db_name"] = $tmp[1];
	    if (isset($tmp[2])) {
	        $props["db_group"] = $tmp[2];
	        $type = 3;
	        $child = 0;
	    } else {
	        $type = 2;
	    }
        } else {
	    $type = 1;
        }
    }
} else {
    $prefix = "";
    $props = array();
    $type = 0;
    $parent = "0";
    unset($extra_mod);
}


$tree = "";
try {
    switch ($type) {
     case 0:
	$req = new REQUEST($props);
	$list = $req->GetServerList();
	break;
     case 1:
	$req = new SERVERRequest($props);
	$list = $req->GetDatabaseList(REQUEST::LIST_WILDCARDED);
	break;
     case 2:
	$req = new SOURCERequest($props);
	$list = $req->GetGroupList();
	break;
     case 3:
	$req = new GROUPRequest($props);
	$list = $req->GetItemList();
	break;
     default:
	$req = new REQUEST($props);
    }

    $branches = array();
    
    if (($type == 0)||($type == 4)) {
        if ($ADEI_SRCTREE_EXTRA) {
	    foreach ($ADEI_SRCTREE_EXTRA as $extra => $cfg) {
	        if (isset($extra_mode)&&($extra != $extra_mod)) continue;
	        
	        ADEI::RequireClass("srctree/{$cfg['class']}");
	        $cl = new $extra($req, $cfg['options']);
	        $res = $cl->GetBranches($prefix);
	        $extra_prefix = "virtual__" . strtolower($extra) . "__";
	        foreach ($res as $id => $info) {
	            $branches[$extra_prefix . $id ] = $info;
	        }
    	    }
        }
    }

    if ($list) {
        foreach ($list as $id => $info) {
            $branches["{$prefix}{$id}"] = array_merge($info, array("child" => $child));
        }
    }
} catch (ADEIException $ex) {
    $ex->logInfo(NULL, $req);
    $err = xml_escape($ex->getInfo());
}


if ($err) {
    echo "<?xml version='1.0' encoding='UTF-8'?>
<tree id=\"$parent\">
  <item id=\"{$prefix}___error\" text=\"" . translate("Source tree generation is failed, error: %s", $err) . "\"/>
</tree>";
    exit(0);

}

/*
foreach ($list as $id => $info) {
    $tree.="<item id=\"{$prefix}{$id}\" text=\"" . xml_escape($info['name']) . "\" child=\"$child\" select=\"yes\"/>";
}
*/

foreach ($branches as $id => $info) {
    $tree.="<item id=\"{$id}\" text=\"" . xml_escape($info['name']) . "\" child=\"" . ($info['child']?1:0) . "\" select=\"yes\"/>";
}


echo "<?xml version='1.0' encoding='UTF-8'?>
<tree id=\"$parent\">
 $tree
</tree>";

?>

