<?php

/* 
 Provided modules:
    item: searches items with specified uid or name
    channel: search items with specified uid (subset of item)
    mask: search masks
    group: search groups with specified id or name
    control_item: search control items with specified uid or name
    control_group: search control groups
    control: search control items with specified uid
 
 Engine search includes: item, group, mask
    
 the items found in the currently selected group are placed first?

*/

class ITEMSearch extends SEARCHEngine {
 function __construct(REQUEST $req = NULL, $opts = false) {
    parent::__construct($req, $opts);
    $this->modules = array(
	"item" => _("Items"),
	"channel" => _("Channels"),
	"mask" => _("Masks"),
	"group" => _("Groups"),
	"control_item" => _("Control Items"),
	"control_group" => _("Control Groups"),
	"control" => _("Control Channels")
    );

    $this->engine_search = array("item", "group", "mask");
 }

 function CheckPhrase($info, $phrase, $match, $module, $opts) {
    $res = 0;
    
    switch ($module) {
	case "channel":
	case "control":
	    return $this->CheckTitlePhrase($info['uid'], $phrase, $match, $opts);
	break;
	case "item":
	case "control_item":
	    $res = $this->CheckTitlePhrase($info['uid'], $phrase, $match, $opts);
	default:
	    return max($res, $this->CheckTitlePhrase($info['name'], $phrase, $match, $opts));
    } 
    
    return 0;
 }
 
 function GetCmpFunction($module, $opts = false) {
    if ($this->req->props['db_server']) {
        return array($this, "ResultCmp");
    }
    return parent::GetCmpFunction($module, $opts);
 }
 
 function CountResultFit(&$a) {
    if (($a['db_server'])&&(!strcmp($a['db_server'], $this->req->props['db_server']))) {
        if (($a['db_name'])&&(!strcmp($a['db_name'], $this->req->props['db_name']))) {
    	    if (($a['db_group'])&&(!strcmp($a['db_group'], $this->req->props['db_group']))) {
		return 3;
	    }
	    return 2;
	}    
	return 1;
    }
    
    return 0;
 }
 
 function ResultCmp(&$a, &$b) {
    if ($a['precision'] == $b['precision']) {
	$ares = $this->CountResultFit($a['props']);    
	$bres = $this->CountResultFit($b['props']);
	if ($ares == $bres) return (strcmp($a['title'], $b['title']));
	else if ($ares < $bres) return 1;
	else return -1;
    } else if ($a['precision'] < $b['precision']) return 1;
    else return -1;
 }


 function GetList($search_data, $module, $opts) {
    $res  = array();

    $req = new REQUEST($tmp = array());

    if (($module == "control")||($module == "control_group")||($module == "control_item")) {
	$sources = $req->GetSources();
	$flags = REQUEST::CONTROL;
    } else {    
	$sources = $req->GetSources(REQUEST::SKIP_UNCACHED|REQUEST::LIST_ALL);
	$flags = REQUEST::SKIP_UNCACHED;
    }
    
    foreach ($sources as $sreq) {
	$title = $sreq->GetSourceTitle();
	
	$groupinfo = $sreq->GetGroupList();

	$groups = $sreq->GetGroups(NULL, $flags);

	foreach ($groups as $gid => $greq) {
	    $gtitle = $title . " -- " . $groupinfo[$gid]["name"];

	    $props = $greq->GetProps();

	    if (($module == "group")||($module == "control_group")) {
		array_push($res, array(
		    'title' => $gtitle,
		    'props' => $props,
		    'description' => false,
		    'name' => $groupinfo[$gid]["name"]
		));	
	    } elseif ($module == "mask") {
		$list = $greq->GetMaskList(REQUEST::NEED_INFO);
		foreach ($list as $mid => &$mask) {
		    $mtitle = $mask["name"] . " (Group: $gtitle)";
		    $props['db_mask'] = $mask['mask'];
		    
		    array_push($res, array(
			'title' => $mtitle,
			'props' => $props,
			'description' => false,

			'name' => $mask['name'],
		    ));	
		}
	    } else {
		$list = $greq->GetItemList($flags);
		
		foreach ($list as $iid => &$item) {
		    $ititle = $item["name"] . " (Group: $gtitle)";
		    $props['db_mask'] = $item['id'];
		    
		    array_push($res, array(
			'title' => $ititle,
			'props' => $props,
			'description' => false,

			'name' => $item['name'],
			'uid' => $item['uid']
		    ));	
		}
	    }
	}
    }

    return $res;
 }
}


?>