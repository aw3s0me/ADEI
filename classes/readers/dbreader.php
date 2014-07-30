<?php

class DBLogGroup extends LOGGROUP {
 var $table;
 
 function __construct(array &$info, DBReader &$rdr, $flags = 0) {
    parent::__construct($info);

    if (is_array($rdr->groups)) {
	foreach($rdr->groups as $re => &$table) {
	    if (preg_match($re, $this->gid)) {
		$this->table = preg_replace($re, $table, $this->gid);
		return;
	    }
	}
    }	
    $this->table = &$this->gid;
 }
}

class DBAxes extends GRAPHAxes {
 function __construct(REQUEST $req = NULL, DATABASE $db, &$info, $flags = 0) {
    parent::__construct($props, $flags);
    
    if ($flags&GRAPHAxes::PRIVATE_AXES)
        $prefix = "{$req->props['db_server']}__{$req->props['db_name']}__";
    else
        $prefix = "";
    
    if (!$info['table']) 
	throw new ADEIException("The axes table is not specified in the reader configuration");

    if (!$info['id']) 
	throw new ADEIException("The id column for axes table is not specified in the reader configuration");

    $query = "";
    if (is_array($info['properties'])) {
	foreach ($info['properties'] as $prop => $col) {
	    $query.= ", $col AS ${prop}";
	}
    }

    $axes = $db->Query("SELECT {$info['id']} AS axis_id{$query} FROM {$this->col_quote}{$info['table']}$this->col_quote");
    foreach ($axes as $axis) {
	$this->axis_info[$prefix . $axis['axis_id']] = $axis;
    }
 }
}


class DBReader extends READER {
 var $db;
 
 var $groups;
 var $tables;
 var $columns;
 
 var $data_request;	// string request with no MASK (pre group)
 var $data_columns;	// array of data columns for each group
 var $custom_columns;   // array of custom columns for each group
 var $time_column;	// time column request for each group
 var $item_info;	// item informations
 var $axes;		// AXES object
 var $private_axes;

 var $time_sort;
 var $inverse_sort;
 
 var $monitor_timings;
 var $emit_delays;
 

 function __construct(&$props) {
    $this->no_default_time_module = true;

    parent::__construct($props);

    $this->db = new DATABASE($this->server);
    
    $this->groups = $this->opts->Get('groups');
    $this->group_class = "DBLogGroup";
    
    $this->tables = $this->opts->Get('tables');
    $this->columns = $this->opts->Get('columns');
    $this->data_request = false;
    $this->data_columns = array();
    $this->custom_columns = array();
    $this->time_column = false;
    $this->axes = false;
    $this->item_info = false;

    if (!$this->time_module) {
	$this->time_format = $this->db->GetTimeFormat();
	$this->time_module = new READERTime($this, array(
	    'format' => $this->time_format,
	    'timezone' => $this->time_zone
	));
    }

    $time_sort = $this->opts->Get("timesort", 1);
    if ($time_sort) {
	if (is_string($time_sort)) {
	    $this->time_sort = $time_sort;
	    $this->inverse_sort = $this->opts->Get("inverse_timesort");
	} else if ($time_sort>0) {
	    $this->time_sort = $this->db->col_quote . $this->columns['time'] . $this->db->col_quote . " ASC";
	    $this->inverse_sort = $this->db->col_quote . $this->columns['time'] . $this->db->col_quote . " DESC";
	} else {
	    $this->time_sort = $this->db->col_quote . $this->columns['time'] . $this->db->col_quote . " DESC";
	    $this->inverse_sort = $this->db->col_quote . $this->columns['time'] . $this->db->col_quote . " ASC";
	}
    } else {
	$this->time_sort = false;
	$this->inverse_sort = false;
    }

    $this->private_axes = $this->opts->Get("private_axes");

    /* per db only, no per group handling here */    
    $this->emit_delays = $this->opts->Get("emit_delays");
    $this->monitor_timings = $this->opts->Get("monitor_timings");
 }

 function GetDatabaseList($flags = 0) {
    $filter = $this->GetDatabaseFilter($flags);
    $res = $this->SortDatabaseList($this->db->GetDatabaseList($filter));

    foreach ($res as &$item) {
       $item['name'] = gettext($item['name']);
    }

    return $res;
 }

 function GetFilteredMaskList(LOGGROUP $grp = NULL, $maskid = false, $flags = 0) {
    $grp = $this->CheckGroup($grp, $flags);

    $info = $this->req->GetGroupOption("mask_table", $grp);
//    $info = $this->opts->Get("mask_table");
    if (!$info) return parent::GetMaskList($grp, $flags);
    
    if (!is_array($info))
	throw new ADEIException(translate("Invalid mask table specified in the reader configuration, the array with information should be provided"));

    if (!$info['table']) 
	throw new ADEIException(translate("The mask table is not specified in the reader configuration"));

    if (!$info['id']) 
	throw new ADEIException(translate("The id column for mask table is not specified in the reader configuration"));


    $query = "";
    if (is_array($info['properties'])) {
	foreach ($info['properties'] as $prop => $col) {
	    $query.= ", {$this->db->col_quote}$col{$this->db->col_quote} AS ${prop}";
	}
    }
    
    if ($maskid) {
	$cond = " WHERE {$this->db->col_quote}{$info['id']}{$this->db->col_quote} = {$this->db->text_quote}{$maskid}{$this->db->text_quote}";
    } elseif ($info['gid']) {
	$table = $this->db->FixTableName($grp->table);
	$cond = " WHERE {$this->db->col_quote}{$info['gid']}{$this->db->col_quote} = {$this->db->text_quote}{$table}{$this->db->text_quote}";
    } else {
	$cond = "";
    }
    $query = "SELECT {$this->db->col_quote}{$info['id']}{$this->db->col_quote} AS id{$query} FROM {$this->db->tbl_quote}{$info['table']}{$this->db->tbl_quote}" . $cond;

    $masks = $this->db->Query($query);

    if ($masks->rowCount()) {
	$items = $this->GetItemList($grp, $mask = new MASK(), $flags);
	$hash = array();
	foreach ($items as $item) {
	    $hash[$item['name']] = $item;
	    if ($item['column']) {
		//$hash[$item['id']] = $item;
		$hash[$item['column']] = $item;
	    }
	}
    } else if ($maskid) {
	throw new ADEIException(translate("Mask \"%s\" is not found for group \"%s\"", $maskid, $grp->gid));
    }
    
    $list = array();
    foreach ($masks as $mask) {
	$items = explode(",", $mask['mask']);
	foreach ($items as &$item) {
	    if (!is_numeric($item)) {
		if (isset($hash[$item])) {
		    $item = $hash[$item]['id'];
		} else {
		    throw new ADEIException(translate("Item \"%s\" specified in the mask \"%s\" is not available in the group \"%s\"", $item, $mask['id'], $grp->gid));
		}
	    }
	}

	$mask['mask'] = implode(",", $items);
	$mask['id'] = 	"maskid" . $mask['id'];

	$list[$mask['id']] = $mask;
    }

    return array_merge(
	parent::GetMaskList($grp, $flags),
	$list
    );
 }

 function GetMaskList(LOGGROUP $grp = NULL, $flags = 0) {
    return $this->GetFilteredMaskList($grp, false, $flags);
 }

 function CreateMask(LOGGROUP $grp = NULL, array &$minfo = NULL, $flags = 0) {
    if ($minfo === NULL) {
	if ($this->req instanceof ITEMGroupRequest)
	    $minfo = $this->req->GetMaskInfo($flags);
    }

    if (preg_match("/^maskid(\d+)$/", $minfo["db_mask"], $m)) {
	$res = new MASK(array(), $this, $grp, $flags);
    
	$masks = $this->GetFilteredMaskList($grp, $m[1], $flags);
	$mask = $masks->current();

	if ($mask) {
	    $res->SetIDs($mask['mask'], $mask['id']);
	} 
	return $res;
    } else {
	return new MASK($minfo, $this, $grp, $flags);
    }
 }


 function CreateAxes($flags = 0) {
    if ($this->axes) return $this->axes;
    
    $axes_table = $this->opts->Get("axes_table");
    if ($axes_table) {
	$this->axes = new DBAxes($this->req, $this->db, $axes_table, $flags|($this->private_axes?GRAPHAxes::PRIVATE_AXES:0));
	return $this->axes;
    } else {
	return parent::CreateAxes($flags);
    }
 }

 function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
    $groups = array();

    $res = $this->db->ShowTables();
    foreach ($res as $row) {
	$gid = $row[0];
	
        $members = array();
	$names = array();
	if (is_array($this->tables)) {
	    $found = false;
	    foreach ($this->tables as $re => &$info) {
		if (preg_match($re, $gid)) {
		    if (is_array($info)) {
			if (is_array($info[0])) {
			    $minfo = &$info;
			} else {
			    $tmp_info = array (&$info);
			    $minfo = &$tmp_info;
			}
			foreach ($minfo as $sinfo) {
			    if (isset($sinfo['title'])) {
				$name = preg_replace($re, $sinfo['title'], $gid);
				$real_gid = preg_replace($re, $sinfo['gid'], $gid);
			    } else {
				$real_gid = preg_replace($re, $sinfo['gid'], $gid);
				$name = $real_gid;
			    }
			    array_push($members, $real_gid);
			    $names[$real_gid] = $name;
			}
		    } else {
			$gid = preg_replace($re, $info, $gid);
			//$name = $gid;
			array_push($members, $gid);
			$names[$gid] = $gid;
		    }
		    $found = true;
	    	    break;
		}
	    }
	    if (!$found) continue;
//	    if (($grp)&&($grp->gid != $gid)) continue;
	} else if ($tables) {
//	    if (($grp)&&($grp->gid != $gid)) continue;
	    if (!preg_match($this->tables, $gid)) continue;
	    //$name = $gid;
	    array_push($members, $gid);
	    $names[$gid] = $name;
	} else {
	    throw new ADEIException(translate("DBReader has no tables are configured in"));
	}

	foreach ($members as $gid) {
	  if (($grp)&&($grp->gid != $gid)) continue;
	  $name = $names[$gid];
	    
	  $groups[$gid] = $row;
	  $groups[$gid]['gid'] = $gid;
	  $groups[$gid]['name'] = $name;

	  if ($flags&REQUEST::NEED_INFO) {
	    if ($grp) {
		$grzeus = $grp;
	    } else {
		$ginfo = array("db_group" => $gid);
		$grzeus = $this->CreateGroup($ginfo);
	    }
	    
	    $tc = $this->columns['time'];
	    $req = "MIN($tc), MAX($tc)";
	    if ($flags&REQUEST::NEED_COUNT) 
		$req .= ", COUNT($tc)";

	    if ($this->monitor_timings) {
		$tt = $this->req->GetGroupOption("monitor_timings", $grp);
		if (is_array($tt)) {
		    if (isset($tt['query_limit'])) $tt_limit = $tt['query_limit'];
		    else $tt_limit = 1000000;
		    $tt_exception = $tt['raise_exception'];
		} else {
		    $tt_limit = 1000000;
		    $tt_exception = false;
		}
		$tt_limit += 1000000*microtime(true);
/*
	        $tod = gettimeofday();
		$tod['usec'] += $tt_limit%1000000;
		if ($tod['usec'] > 999999) {
		    $tod['usec'] -= 1000000;
		    $tod['sec']++;
		}
		$tod['sec'] += $tt_limit / 1000000;
*/
	    }
	    
	    if ($this->emit_delays) {
		log_message("Sleeping");
		usleep($this->emit_delays);
	    }
	    
	    $valres = $this->db->Query("SELECT $req FROM " . $this->db->tbl_quote . $grzeus->table . $this->db->tbl_quote, DATABASE::FETCH_NUM);
	    $vals = $valres->fetch(PDO::FETCH_NUM);
	    $valres = NULL;
	    
	    if ($this->monitor_timings) {
		if (1000000 * microtime(true) > $tt_limit) {
		    $msg = translate("The query on group '%s' is exceeded allowed execution time (exceeding %d msec). This normally indicates inappropriate indexing of the source database. You can overcome the problem by setting '%s' and '%s' options", $grp->gid, ceil(1000*microtime(true) - $tt_limit/1000), "use_cache_reader", "fill_raw_first");
		    if ($tt_exception)
			throw new ADEIException($msg);
		    else
			log_message($this->req->GetLocationString() . ": " . $msg);
		}
	    }

	    if (($vals)&&(($vals[0])||($vals[1]))) {
	        $opts = $this->req->GetGroupOptions($grzeus);
		$limit = $opts->GetDateLimit();
	    
		$groups[$gid]['first'] = $this->ExportUnixTime($vals[0]);
	        if ((is_int($limit[0]))&&($limit[0] > $groups[$gid]['first'])) {
	    	    $groups[$gid]['first'] = $limit[0];
		} else if ($groups[$gid]['first'] < 0) {
		    $groups[$gid]['first'] = 0;
		}
	    
		$groups[$gid]['last'] = $this->ExportUnixTime($vals[1]);
		if ((is_int($limit[1]))&&($limit[1] < $groups[$gid]['last'])) {
	    	    $groups[$gid]['last'] = $limit[1];
		} else if ($groups[$gid]['last'] < 0) {
	    	    $groups[$gid]['last'] = 0;
		}

		if ($flags&REQUEST::NEED_COUNT) 
		    $groups[$gid]['records'] = $vals[2];
	    } else {
		unset($groups[$gid]['first']);
		unset($groups[$gid]['last']);
	    }

	    if ($flags&REQUEST::NEED_ITEMINFO) {
		$groups[$gid]['items'] = $this->GetItemList($grzeus);
	    }
	  }
	}
    }

    return $grp?$groups[$grp->gid]:$groups;
 }

 function FindItemInfo() {
    if ($this->item_info) return;
    
    $info = $this->opts->Get("item_table");
    if (!$info) return;
    
    if (!$info['table']) 
	throw new ADEIException("The item table is not specified in the reader configuration");

    if (!$info['id']) 
	throw new ADEIException("The id column for item table is not specified in the reader configuration");

    if ($info['gid']) $query = ", {$info['gid']} AS gid";
    else $query = "";
    if (is_array($info['properties'])) {
        if (isset($info['properties']['axis'])) {
            if ($this->private_axes) {
                $info['properties']['axis'] = "CONCAT('{$this->req->props['db_server']}__{$this->req->props['db_name']}__', {$info['properties']['axis']})";
            }
        }
	foreach ($info['properties'] as $prop => $col) {
	    $query.= ", $col AS ${prop}";
	}
    }

    $items = $this->db->Query("SELECT {$info['id']} AS id{$query} FROM {$this->col_quote}{$info['table']}$this->col_quote");
    if (!$items) return;
    
    $this->item_info = array();
    if ($info['gid']) {
	$this->item_info['__group_mode__'] = true;
	foreach ($items as $item) {
	    if ($item["name"])
		$item['name'] = $this->db->RecodeMessage($item['name']);

	    if (!is_array($this->item_info[$info['gid']])) {
		$this->item_info[$info['gid']] = array();
	    }
    	    $this->item_info[$info['gid']][$item['id']] = $item;
	}
    } else {
	$this->item_info['__group_mode__'] = false;
	foreach ($items as $item) {
	    if ($item["name"])
		$item['name'] = $this->db->RecodeMessage($item['name']);

    	    $this->item_info[$item['id']] = $item;
	}
    }
 }
 
 function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    $this->FindItemInfo();

    if ($flags&REQUEST::ONLY_AXISINFO) {
	if ((!$this->item_info)&&(!$this->req->GetGroupOptions($grp, "axis"))) return array();
    }

    $grp = $this->CheckGroup($grp, $flags);
    if (!$mask) $mask = $this->CreateMask($grp, $info = NULL, $flags);

    if (!$mask->IsFull()) $flags |= REQUEST::LIST_CUSTOM;

    $uid = $this->opts->Get('channel_uids', false);
    
    $data_columns = $this->req->GetGroupOption('columns', $grp);

    $items = array();

    $resp = $this->db->ShowColumns($grp->table);

    $pos = 0; $rpos = 0; $cpos = 0;
    foreach ($resp as $row) {
	$name = $row[0];
	
//	if (!preg_match($this->columns['data'], $name)) continue;
	if (!preg_match($data_columns['data'], $name)) {
	    if (($flags&REQUEST::LIST_CUSTOM)&&(isset($data_columns['custom']))&&(preg_match($data_columns['custom'], $name))) $custom = 1;
	    else continue;
	} else $custom = 0;

        if ($custom) $id = "c" . ($cpos++);
        else $id = $pos++;

	if (!$mask->Check($id)) continue;

        $items[$rpos] = array(
	    "id" => $id,
	    "name" =>  $this->db->RecodeMessage($name),
	    "column" => $name
	);
	
	if ($custom) $items[$rpos]['custom'] = $custom;

	if ($this->item_info) {
	    if ($this->item_info['__group_mode__']) {
		$info = $this->item_info[$grp->gid][$name];
	    } else {
		$info = $this->item_info[$name];
	    }
	    if (is_array($info)) {
	        unset($info['id']);
		unset($info['column']);
		
		$items[$rpos] = array_merge($items[$rpos], $info);
	    }
	}
	
	if (($uid)&&(!isset($items[$rpos]["uid"]))) {
	    if (($uid === true)||(preg_match($uid, $name))) {
		$items[$rpos]["uid"] = $name;
	    }
	}


	$rpos++;
    }

    $this->AppendExtractedItems($grp, $mask, $items, $flags);

    if (!$items) {    
        if (!$name)
	    throw new ADEIException(translate("DBReader can't find any column in table (%s)", $grp->table));

        if (!$pos)
	    throw new ADEIException(translate("DBReader is not able to find any column matching filter (%s) in table (%s)", $this->columns['data'], $grp->table));
    }

    if ($flags&REQUEST::NEED_AXISINFO) {
	$this->AddAxisInfo($grp, $items);
    }
    
    return $items;
 }

 function ApplySqlFilters($col, &$filters) {
    foreach ($filters as $flt) {
        $col = "$flt($col)";
    }
    return $col;
 }
 
 function FindColumns(LOGGROUP $grp, MASK $mask = NULL) {
    $sql_filters = false;

    if ((!$this->time_column)||(!$this->time_column[$grp->gid])) {
	if ($this->time_module)
	    $time_column = $this->columns['time'];
	else
	    $time_column = $this->db->GetTimeRequest($this->columns['time']);

        $sql_filters = $this->req->GetGroupOption("sql_filters", $grp, array());

	$data_columns = array();

        $msk = $this->CreateMask($grp, $info = array());
//        if ($msk->IsFull()) $flags = 0;
//        else $flags = REQUEST::LIST_CUSTOM;
	$items = $this->GetItemList($grp, $msk);
	foreach ($items as $item) {
	    $col = $this->db->col_quote . $item['column'] . $this->db->col_quote;
	    foreach ($sql_filters as $re => $sqlflt) {
	        if (preg_match($re, $item['column'])) {
	            $col = $this->ApplySqlFilters($col, $sqlflt);
	            break;
	        }
	    }
	    array_push($data_columns, $col);
	}

	$this->data_columns[$grp->gid] = $data_columns;
	$this->time_column[$grp->gid] = $time_column;
    } 

    if (($mask)&&(is_array($ids = $mask->GetIDs()))) {
	if (!isset($time_column)) {
	    $data_columns = &$this->data_columns[$grp->gid];
	    $time_column = &$this->time_column[$grp->gid];
	}
	$data_request = "";
	$custom_columns = false;
	foreach ($ids as $id) {
	    if (isset($data_columns[$id])) {
	        $data_request .=  "{$data_columns[$id]}, ";
            } else {
                if ($custom_columns === false) {
                    if ($sql_filters === false) {
                        $sql_filters = $this->req->GetGroupOption("sql_filters", $grp, array());
                    }

                    if (isset($this->custom_columns[$grp->gid])) {
                        $custom_columns = &$this->custom_columns[$grp->gid];
                    } else {
                        $items = $this->GetItemList($grp, $msk = $this->CreateMask($grp, $minfo = array()), REQUEST::LIST_CUSTOM);
                        $custom_columns = array(); $i = 0;
                        foreach ($items as $item) {
                            if ($item['custom']) {
                                $col = $this->db->col_quote . $item['column'] . $this->db->col_quote; 
	                        foreach ($sql_filters as $re => $sqlflt) {
	                            if (preg_match($re, $item['column'])) {
	                                $col = $this->ApplySqlFilters($col, $sqlflt);
	                                break;
	                            }
	                        }
                                $custom_columns["c" . ($i++)] = $col;
                            }
                        }
                        $this->custom_columns[$grp->gid] = $custom_columns;
                    }
                }
	        if (isset($custom_columns[$id])) {
	            $data_request .=  "{$custom_columns[$id]}, ";
                } else {
	            throw new ADEIException(translate("Invalid item mask is supplied. The ID:%s refers non-existing item.", $id));
	        }
	    }

	}

        return "$data_request{$this->db->col_quote}$time_column{$this->db->col_quote}";
    } elseif ($this->data_request[$grp->gid]) {
	return $this->data_request[$grp->gid];
    } else {
	$this->data_request[$grp->gid] = 
	    implode(", ", $this->data_columns[$grp->gid]) . ", {$this->db->col_quote}" . $this->time_column[$grp->gid] . $this->db->col_quote;
	return $this->data_request[$grp->gid];
    }
 }    

 function GetRawData(LOGGROUP $grp = NULL, $from = 0, $to = 0, DATAFilter $filter = NULL, &$filter_data = NULL) {
    $grp = $this->CheckGroup($grp);

    if ((!$from)||(!$to)) {
        $ivl = $this->CreateInterval($grp);
	$ivl->Limit($from, $to);
	
	$from = $ivl->GetWindowStart();
	$to = $ivl->GetWindowEnd();
    }


    if ($filter) {
	$mask = $filter->GetItemMask();
	$resample = $filter->GetSamplingRate();
	$limit = $filter->GetVectorsLimit();

	if (isset($filter_data)) {
	    if ($mask) $filter_data['masked'] = true;
	    if ($resample) $filter_data['resampled'] = true;
	    if ($limit) $filter_data['limited'] = true;
	}
    } else {
	$mask = NULL;
	$resample = 0;
	$limit = 0;
    }

    $data_columns = $this->FindColumns($grp, $mask);

    if ($this->emit_delays) {
	log_message("Sleeping");
	usleep($this->emit_delays);
    }


    $selopts = array(
	"condition" => $this->db->col_quote . $this->columns['time'] . "{$this->db->col_quote} BETWEEN " . $this->ImportUnixTime($from) . " and " . $this->ImportUnixTime($to)
    );
    
    if ($resample) {
	$selopts['sampling'] = array(
	    "slicer" => $this->time_module->GetTimeSlicingFunction("{$this->db->col_quote}{$this->columns['time']}{$this->db->col_quote}", $resample/*, $from*/),
	    "selector" => "{$this->db->col_quote}{$this->columns['time']}{$this->db->col_quote}"
	);
    }
    
    if ($limit) {
	$selopts['limit'] = abs($limit);
	if ($limit > 0) {
	    $selopts['order'] = $this->time_sort;
	} else {
	    if (!$this->inverse_sort)
		throw new ADEIException(translate("The inverse_timesort should be specified in order to select items from the end"));
		
	    $selopts['order'] = $this->inverse_sort;
	    if ($limit < -1)
		throw new ADEIException(translate("Current version supports only selecting a single item from the end"));
	}
    } else {
	$selopts['order'] = $this->time_sort;
    }
    
    $query = $this->db->SelectRequest($grp->table, $data_columns, $selopts);
#    echo $query . "\n";

    $stmt = $this->db->Prepare($query);
    return new DATABASEData($this, $stmt);
 }

 function HaveData(LOGGROUP $grp = NULL, $from = 0, $to = 0) {
    $grp = $this->CheckGroup($grp);
    if (!$to) $to = time();

    if ($this->emit_delays) {
	log_message("Sleeping");
	usleep($this->emit_delays);
    }
    $res = $this->db->Query($this->db->SelectRequest($grp->table, array($this->columns['time']), array(
	"condition" => "{$this->db->col_quote}" . $this->columns['time'] . "{$this->db->col_quote} BETWEEN " . $this->ImportUnixTime($from) . " and " . $this->ImportUnixTime($to),
	"limit" => 1)), DATABASE::FETCH_NUM);
    if ($res->fetch(PDO::FETCH_NUM)) return true;
    return false;
 }
 
}

?>