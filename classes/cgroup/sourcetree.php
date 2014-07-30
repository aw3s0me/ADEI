<?php

class SOURCETree {
 var $list;
 var $flags;
 
 var $props_cast;
 
 const IGNORE_UIDS = 0x80000000;
 const IGNORE_BADITEMS = 0x40000000;
 const EXPAND_SERVERS =  0x20000000;

 function __construct($srctree, $optimize = false, $flags = 0) {
    if (is_array($srctree)) $this->list = $srctree;
    else $this->list = $this->Parse($srctree, $optimize, $flags);

    $this->props_cast = false;
    $this->flags = $flags;
 }
 
 
 function GetRequests(MASK $mask = NULL, $props_cast = false, $type = "GROUPRequest") {
    $list = $this->ApplyMask($mask, $this->list);
    
    $plist = array();
    foreach ($list as $key => &$gr) {
	$plist[$key] = $gr['props'];
    }

    if ($props_cast) {
	array_walk($plist, $props_cast);
    }

    return new REQUESTList($props = array(), $plist, $type);
 }
 
 function GetSourceRequests(MASK $mask = NULL) {
     $list = $this->ApplyMask($mask, $this->list);

    $plist = array();
    foreach ($list as &$gr) {
        $key = $gr['props']['db_server'] . "__" . $gr['props']['db_name'];
        if ($plist[$key]) continue;
        
	$plist[$key] = array(
	    'db_server' => $gr['props']['db_server'],
	    'db_name' => $gr['props']['db_name']
	);
    }
    
    return new REQUESTList($props = array(), $plist, "SOURCERequest");
 }
 
 function GetGroupRequest(LOGGROUP $grp, MASK $mask = NULL) {
    if (!isset($this->list[$grp->gid])) {
	throw new ADEIException(translate("Unknown group (%s) is requested in SOURCETree::GetGroupRequest", $grp->gid));
    }

    $info = $this->list[$grp->gid];
    if (($mask)&&(is_array($mask->ids))) {
	if ($info['items'] === false) {
	    $info['props']['db_mask'] = $mask->GetProp();
	} else {
	    $ires = array();
	    foreach ($mask->ids as $id) {
		if (isset($info['items'][$id])) {
		    array_push($ires, $info['items'][$id]);
	        } else {
		    throw new ADEIException(translate("Invalid mask, item (%s) is not present in group", $id));
	        }
	    }
	
	    if ($ires) {
		$info['props']['db_mask'] = implode(",", $ires);
	    } else {
		$info['props']['db_mask'] = false;
	    }
	}
    }

/*
    if ($this->props_cast) {
	call_user_func($this->props_cast, $info['props'], $grp->gid);
    }
*/    
    return new GROUPRequest($info['props']);
 }

 static function ApplyMask(MASK $mask = NULL, $list) {
    if ((!$mask)||(!is_array($mask->ids))) return $list;

    $list_pos = 0;

    $res = array();
        
    $ids = $mask->ids;
    sort($ids);

    if ($ids) $curid = array_shift($ids);
    else return $res;

    foreach($list as $key => &$gr) {
	if (is_array($gr['items'])) {
	    $items = $gr['items'];
	    $size = sizeof($items);
	} else {
	    $req = new GROUPRequest($gr['props']);
	    $rdr = $req->CreateReader();
	    $size = $rdr->GetGroupSize();
	    unset($rdr);
	    unset($req);
	    $items = range(0, $size - 1);
	}
	
	$ires = array(); $ireal = array();
	while ($list_pos + $size > $curid) {
	    array_push($ires, $items[$curid - $list_pos]);
	    array_push($ireal, $curid - $list_pos);
	    
	    if ($ids) $curid = array_shift($ids);
	    else {
		$curid = -1;
		break;
	    }
	}
	
	if ($ires) {
	    $props = $gr['props'];
	    $props['db_mask'] = implode(",", $ires);
	    $props['real_mask'] = implode(",", $ireal);
	    $res[$key] = array(
		'props' => $props,
		'items' => $ires
	    );
	}
	
	$list_pos += $size;
	
	if ($curid < 0) break;
    }

    return $res;
 }

 static function Parse($srctree, $optimize = false, $flags = 0) { 
    global $ADEI;
    global $ADEI_SRCTREE_EXTRA;
    
    $elements = explode(",", 
	preg_replace_callback(
	    "/(\([^)]+,[^)]+\))/", 
	    create_function('$matches', '
		return preg_replace("/,/", ";", $matches[0]);
	    '),
	    $srctree
	)
    );
    
    $curid = false;
    $res = array();
    $idnum = array();
    $extras = array();
    
    if ((!$elements)||(!$elements[0])) $elements = array();

    if ($flags&SOURCETree::EXPAND_SERVERS) {
	$remove_dublicates = false;
	
	$new_elements = array();
	foreach ($elements as $element) {
	    if (preg_match("/^(.*)\(([^)]+)\)$/", $element, $m)) {
		array_push($new_elements, $element);
	    } else {
	        $parts = explode("__", $element, 4);
		if (sizeof($parts) == 1) {
		    $req = new SERVERRequest($props = array(
			"db_server" => $parts[0]
		    ));
		    $list = $req->GetDatabaseList();
		    foreach ($list as $id => $info) {
			array_push($new_elements, $parts[0] . "__" . $id);
		    }

		    $remove_dublicates = true;
		} else {
		    if ((sizeof($parts) > 2)&&($parts[0] == "virtual")) {
		        $extra = $parts[1];
                        if (!$extras[$extra]) {
	                    ADEI::RequireClass("srctree/{$ADEI_SRCTREE_EXTRA[$extra]['class']}");
	                    $extras[$extra] = new $ADEI_SRCTREE_EXTRA[$extra]['class']($req, $ADEI_SRCTREE_EXTRA[$extra]['options']);
		        }
	                $parts = explode("__", $element, 3);
		        $list = $extras[$extra]->Parse($parts[2]);
		        foreach ($list as $item) {
		            array_push($new_elements, $item);
		        }
		        
		        $remove_dublicates = true;
		    } else {
		        array_push($new_elements, $element);
		    }
		}
	    }
	}

	$elements = array();
	foreach ($new_elements as $element) {
	    if (preg_match("/^(.*)\(([^)]+)\)$/", $element, $m)) {
		array_push($elements, $element);
	    } else {
	        $parts = explode("__", $element, 4);
		if (sizeof($parts) == 2) {
		    $req = new SOURCERequest($props = array(
			"db_server" => $parts[0],
			"db_name" => $parts[1]
		    ));
		    $list = $req->GetGroupList();
		    foreach ($list as $gid => $info) {
			array_push($elements, $parts[0] . "__" . $parts[1] . "__" . $gid);
		    }

		    $remove_dublicates = true;
		} else {
		    array_push($elements, $element);
		}
	    }
	    
	}
	
	if ($remove_dublicates) {
	    if ($optimize) $elements = array_unique($elements);
	    else {
		$new_elements = array_unique($elements);
		ksort($new_elements);
		$elements = array_values($new_elements);
	    }
	}

    }
    
    foreach ($elements as $element) {
	if (preg_match("/^(.*)\(([^)]+)\)$/", $element, $m)) {
	    $id = $m[1];

	    $parts = explode("__", $m[1], 3);
	    
	    if (sizeof($parts)<3) {
		throw new ADEIException(translate("Unsupported element (%s) in the source tree", $m[1]));
	    }

	    $items = explode(";", $m[2]);
	} else {
	    $parts = explode("__", $element, 4);

	    if ((sizeof($parts) == 1)&&(($flags&SOURCETree::IGNORE_UIDS)==0)) {
		$item_props = $ADEI->ResolveUID($parts[0]);	// Controls are not supported at the moment
		if ($item_props) $parts = explode("__", SOURCETree::PropsToItem($item_props));
		else if (($flags&SOURCETree::IGNORE_BADITEMS)==0) throw new ADEIException(translate("UID (%s) is not available", $parts[0]));
		else continue;
	    }

	    if (sizeof($parts)<3) {
		if (($flags&SOURCETree::IGNORE_BADITEMS)==0) throw new ADEIException(translate("Unsupported element (%s) of source tree", $element));
		else continue;
		
	    }
	    $id = $parts[0] . "__" . $parts[1] . "__" . $parts[2];

	    if (sizeof($parts) == 4) {
		$items = array($parts[3]);
	    } else {
		$items = false;
	    }
	}
    
	if ($optimize) {
	    $realid = $id;
	} else {
	    if (strcmp($id, $curid)) {
		if ($idnum[$id]) {
		    $realid = $id . "__" . (++$idnum[$id]);
		} else {
		    $realid = $id;
		    $idnum[$id] = 1;
		}
		$curid = $id;
	    }
	}
	
	if (!isset($res[$realid])) {
	    $res[$realid] = array(
		'props' => array(
		    'db_server' => $parts[0],
		    'db_name' => $parts[1],
		    'db_group' => $parts[2]
		),
		'items' => array()
	    );
	}
	
	if ($items === false) {
	    $res[$realid]['items'] = false;
	    unset($res[$realid]['props']['db_mask']);
	} else if (is_array($res[$realid]['items'])) {
	    $res[$realid]['items'] = array_merge($res[$realid]['items'], $items);
	    $res[$realid]['props']['db_mask'] = implode(",", $res[$realid]['items']);
	}

    }
    
    return $res;
 }

 static function Create($list, $flags = 0) {
    $res = array();
    foreach ($list as $gr) {
	$item = "{$gr['props']['db_server']}__{$gr['props']['db_name']}__{$gr['props']['db_group']}";
	if ($gr['items']) {
	    $item .= "(" . implode(";", $gr['items']) . ")";
	}
	array_push($res, $item);
    }
    return implode(",", $res);
 }

 static function Optimize($srctree, $flags = 0) {
    return SOURCETree::Create(SOURCETree::Parse($srctree, true, $flags), $flags);
 }
 
 static function GetGroupID($props) {
    return "{$props['db_server']}__{$props['db_name']}__{$props['db_group']}"; 
 }
 
 static function ItemToProps($item) {
    $arr = explode("__", $item, 4);
    if (sizeof($arr) < 4) 
	throw new ADEIException(translate("Invalid item specification (%s) is supplied", $item));
    
    $props = array(
        'id' => $item,
	'db_server' => $arr[0],
	'db_name' => $arr[1],
	'db_group' => $arr[2],
	'db_mask' => $arr[3]
    );
    return $props;    
 }

 static function PropsToItem(array &$props) {
    if (is_numeric($props['db_mask'])) {
	return "{$props['db_server']}__{$props['db_name']}__{$props['db_group']}__{$props['db_mask']}"; 
    } else {
	return "{$props['db_server']}__{$props['db_name']}__{$props['db_group']}({$props['db_mask']})"; 
    }
 }
 
 static function PropsCmp($p1, $p2) {
    if (sizeof($p1) != sizeof($p2)) return 1;
    foreach ($p1 as $key => $value) {
	if ((!isset($p2[$key]))||(strcmp($p2[$key], $value))) return 1;
    }
    return 0;
 }
}


?>