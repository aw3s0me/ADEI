<?php
global $ADEI_TIMINGS;
global $EXPORT_SAMPLING_RATES;
global $EXPORT_FORMATS;
global $ADEI;

ADEI::RequireClass("export");
ADEI::RequireClass("draw");

header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");


if ($_GET['version'] == 1) {
    $item_tag = "MenuItem";
    $item_title = "name";
    $item_image = "src";
    $separator_tag = "divider";
    $separator_attrs = "";
} else {
    $item_tag = "item";
    $item_title = "text";
    $item_image = "img";
    $separator_tag = "item";
    $separator_attrs = "type=\"separator\"";
}



/*
echo "<?xml version='1.0' ?>";
echo "<menu maxItems=\"1\" $item_title=\"\">";
echo "<$item_tag $item_title=\"\" src=\"menu.png\" id=\"main_file\" width=\"20px\" withoutImages=\"yes\"/>";
echo "</menu>";
return;
*/

function ListExperiments(&$list, &$query) {
    global $item_tag;
    global $item_title;
    
    foreach ($list as $eid => $exp) {
	$exp_name = xml_escape($exp["name"]);
	$equery = $query .  "__" . $exp['start'] . "-" . $exp['stop'];
	$res .= "<$item_tag $item_title=\"$exp_name\" id=\"SetQuery__$equery\"/>";
    }

    return $res;
}

try {
    $req = new REQUEST();
    $list = $req->GetSources(REQUEST::LIST_ALL);
    
    $data = "";
    $source = "";
    foreach ($list as $sreq) {
	$src_name = xml_escape($sreq->props['db_server'] . "." . $sreq->props['db_name']);
	$query = $sreq->props['db_server'] . "__" . $sreq->props['db_name'];

	try {
	    $s_data = "<$item_tag $item_title=\"$src_name\" id=\"SetQuery__$query\" withoutImages=\"yes\">";
	    $s_source = "<$item_tag $item_title=\"$src_name\" id=\"SetSource__$query\" withoutImages=\"yes\">";

	    $reader = $sreq->CreateReader();
	    $groups = $reader->GetGroupList();
	
	    $glist = $sreq->GetGroups();
	    foreach ($glist as $gid => $greq) {
		$group = &$groups[$gid];
	    
		$gr_name = xml_escape($group['name']);
		$gquery = $query .  "__" . $gid;
		$s_data .= "<$item_tag $item_title=\"$gr_name\" id=\"SetQuery__$gquery\" withoutImages=\"yes\">";
		$s_source .= "<$item_tag $item_title=\"$gr_name\" id=\"SetSource__$gquery\" withoutImages=\"yes\">";
	    
		$cache = $greq->CreateCache($reader);
		$explist = $cache->GetExperimentList();


	        $mlist = $cache->GetMaskList(REQUEST::NEED_INFO);
		foreach ($mlist as $mid => $mask) {
		    $mask_name = xml_escape($mask['name']);
		    $mquery = $gquery .  "__" . $mask['mask'];
		    $s_data .= "<$item_tag $item_title=\"$mask_name\" id=\"SetQuery__$mquery\" withoutImages=\"yes\">";
		    $s_source .= "<$item_tag $item_title=\"$mask_name\" id=\"SetSource__$mquery\"/>";
		    $s_data .= ListExperiments($explist, $mquery);
		    $s_data .= "</$item_tag>";
		}

	        if ($MENU_SHOW_ITEMS) {
		    $s_data .= "<$item_tag $item_title=\"" . _("Items") . "\" id=\"folder__$gquery\" withoutImages=\"yes\">";
		    $s_source .= "<$item_tag $item_title=\"" . _("Items") . "\" id=\"folder__$gquery\" withoutImages=\"yes\">";
		}
	    
		$ilist = $cache->GetItemList();
	        unset($aid);
		foreach ($ilist as $iid => &$item) {
		    if (isset($aid)) $aid .= ",$iid";
		    else $aid = $iid;

		    if ($MENU_SHOW_ITEMS) {
			$item_name = xml_escape($item["name"]);
			$iquery = $gquery .  "__" . $iid;
			$s_data .= "<$item_tag $item_title=\"$item_name\" id=\"SetQuery__$iquery\" withoutImages=\"yes\">";
			$s_source .= "<$item_tag $item_title=\"$item_name\" id=\"SetSource__$iquery\" />";
			$s_data .= ListExperiments($explist, $iquery);
			$s_data .= "</$item_tag>";
		    }
		}
	    
		if ($MENU_SHOW_ITEMS) {
    		    $s_data .= "</$item_tag>";
		    $s_source .= "</$item_tag>";
		}
/*
		$aquery = $gquery .  "__" . $aid;
	        $s_data .= "<$item_tag $item_title=\"" . _("All Items") . "\" id=\"SetQuery__$aquery\" withoutImages=\"yes\">";
		$s_source .= "<$item_tag $item_title=\"" . _("All Items") . "\" id=\"SetSource__$aquery\" />";
		$s_data .= ListExperiments($explist, $aquery);
		$s_data .= "</$item_tag>";
*/
		$s_data .= "</$item_tag>";
		$s_source .= "</$item_tag>";
	    }

	    $s_data .= "</$item_tag>";
	    $s_source .= "</$item_tag>";
	} catch (ADEIException $ae) {
	    $ae->logInfo(NULL, $req);
	    $errmsg = xml_escape($ae->getInfo());

	    $s_data = "
		<$item_tag $item_title=\"$src_name\" withoutImages=\"yes\">
		    <$item_tag $item_title=\"" . translate("Data source is failed, error: %s", $errmsg) . "\" withoutImages=\"yes\"/>
		</$item_tag>
	    ";
	
	    $s_source = $s_data;
	}
	
	$data .= $s_data;
	$source .= $s_source;
    }


    $range = "<$item_tag $item_title=\"" . _("All") . "\" id=\"SetWindow__0\"/>";
    foreach ($ADEI_TIMINGS as $opt => $value) {
	$range .= "<$item_tag $item_title=\"$opt\" id=\"SetWindow__$value\"/>";
    }


    $export .= "";
    $export .= "<$item_tag $item_title=\"" . _("Format") . "\" id=\"folder__SetFormat\" withoutImages=\"yes\">";
    foreach ($EXPORT_FORMATS as $id => &$val) {
	if ((($val['title'])||($val['hidden'] === false))&&(!$val['hidden'])) {
	    $name = $val['title'];
	    $export .= "<$item_tag $item_title=\"$name\" id=\"SetFormat__$id\"/>";
	}
    }
    $export .= "</$item_tag>";

    $export .= "<$item_tag $item_title=\"" . _("Sampling") . "\" id=\"folder__SetExportSampling\" withoutImages=\"yes\">";
    $export .= "<$item_tag $item_title=\"" . _("No Resampling") . "\" id=\"SetExportSampling__0\"/>";
    foreach ($EXPORT_SAMPLING_RATES as $name => $id) {
	$export .= "<$item_tag $item_title=\"$name\" id=\"SetExportSampling__$id\"/>";
    }
    $export .= "</$item_tag>";

    $export .= "<$item_tag $item_title=\"" . _("Exported Items") . "\" id=\"folder__SetExportMask\" withoutImages=\"yes\">";
    $export .= "<$item_tag $item_title=\"" . _("Current Mask") . "\" id=\"SetExportMask__" . EXPORT::MASK_STANDARD  . "\"/>";
    $export .= "<$item_tag $item_title=\"" . _("Current Group") . "\" id=\"SetExportMask__" . EXPORT::MASK_GROUP . "\"/>";
    $export .= "<$item_tag $item_title=\"" . _("Current Database") . "\" id=\"SetExportMask__" . EXPORT::MASK_SOURCE . "\"/>";
    $export .= "<$item_tag $item_title=\"" . _("Everything") . "\" id=\"SetExportMask__" . EXPORT::MASK_COMPLETE . "\"/>";
    $export .= "</$item_tag>";

    


} catch(ADEIException $ex) {
    $ex->logInfo(NULL, $req);
    $err = xml_escape($ex->getInfo());
}




if ($err) {
    $menu = "<?xml version='1.0' ?>
<menu maxItems=\"1\" $item_title=\"\">
 <$item_tag $item_title=\"\" src=\"menu.png\" id=\"main_file\" width=\"20px\" withoutImages=\"yes\">
  <$item_tag $item_title=\"" . translate("Menu Generation is failed, error: %s", $err) . "\" id=\"folder__data\" withoutImages=\"yes\"/>
 </$item_tag>
</menu>";
    echo $menu;
    exit(0);
}

$login_elem = "";

if ($ADEI->isConnected()) {
    $username = $ADEI->getUsername();
    if (!$username) {
        $username = "";
        $login_elem = "<$item_tag $item_title=\"" . _("Login") . "\" id=\"Login\"/>";
    }
    else {
        $login_elem = "<$item_tag $item_title=\"" . _("Logout, ".$username) . "\" id=\"Login\" />";
    } 
  
} 
else { 
    $login_elem = "<$item_tag $item_title=\"" . _("Login") . "\" id=\"Login\"/>";
}


$menu = "<?xml version='1.0' encoding='UTF-8'?>
<menu  absolutePosition=\"auto\" mode=\"popup\" maxItems=\"$MENU_SCROLL_LIMIT\" $item_title=\"\">
 <$item_tag $item_title=\"\" $item_image=\"menu.png\" id=\"main_file\" width=\"20px\" withoutImages=\"yes\">
  <$item_tag $item_title=\"" . _("New Query") . "\" id=\"folder__data\" withoutImages=\"yes\">" . $data . "</$item_tag>
  <$item_tag $item_title=\"" . _("Data Source") . "\" id=\"folder__data_source\" withoutImages=\"yes\">" . $source . "</$item_tag>
  <$item_tag $item_title=\"" . _("Time Range") . "\" id=\"folder__data_range\" withoutImages=\"yes\">" . $range . "</$item_tag>
  <$item_tag $item_title=\"" . _("Export Settings") . "\" id=\"folder__data_export\" withoutImages=\"yes\">" . $export . "</$item_tag>
  <$separator_tag $separator_attrs id=\"div_1\"/>	
  <$item_tag $item_title=\"" . _("Save Mask") . "\" id=\"SaveMask\" withoutImages=\"yes\"/>
  <$item_tag $item_title=\"" . _("Save Window") . "\" id=\"SaveWindow\"  withoutImages=\"yes\"/>
  <$separator_tag $separator_attrs id=\"div_2\"/>	
  <$item_tag $item_title=\"" . _("Lock Window") . "\" id=\"LockWindow\" withoutImages=\"yes\"/>
  <$item_tag $item_title=\"" . _("ReDraw") . "\" id=\"ReDraw\" withoutImages=\"yes\"/>"

.$login_elem.
"
  <$item_tag $item_title=\"" . _("Save") . "\" id=\"ExportWindow\"/>
  
 </$item_tag>
</menu>";
/*
*/
echo $menu;
?>