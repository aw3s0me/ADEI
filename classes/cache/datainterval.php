<?php


/*
 amount - is used for resolution calculation
 limit - limits maximal number of points
*/

class DATAInterval implements Iterator {
 var $cache;
 var $ids;
 var $ivl;
 var $amount, $limit, $resolution;

 var $res;
 var $row, $next, $lastrow;
 var $set_minmax;
 var $combine_ends;
 
 var $used_table;
 
 var $operation_info;
 var $use_subseconds;
 var $postfix;
 
 var $flags;
 
 function __construct(CACHEDB $cache, MASK $mask, INTERVAL $ivl, $amount, $limit, $resolution, $flags = 0) {
    $this->cache = &$cache;
    $this->ids = &$mask->ids;
    $this->ivl = &$ivl;
    
    $this->amount = $amount;
    $this->limit = $limit;
    $this->resolution = $resolution;
    
    $this->use_subseconds = $this->cache->use_subseconds;
    
    $this->operation_info = array();
    $this->postfix = false;

    $this->flags = $flags;
 }

 function SetOption($option, $value) {
    $this->$option = $value;
 } 

 function SetOptions($options) {
    foreach ($options as $opt => &$value) {
	$this->$opt = $value;
    }
 }
 
 function combine(&$row, &$add, $ratio) {
	// We ignoring edges for missing calculation
	
    for ($i=0;isset($row['mean' . $i]);$i++) {
	if ($add['min' . $i] < $row['min' . $i])  
	    $row['min' . $i] = $add['min' . $i];
 	if ($add['max' . $i] > $row['max' . $i])
	    $row['max' . $i] = $add['max' . $i];

//	$row['mean' . $i] = ($row['mean' . $i] + $ratio*$add['mean' . $i]) / (1 + $ratio);
	if ($add['items']) {
	    $row['mean' . $i] = ($row['items']*$row['mean' . $i] + $add['items']*$add['mean' . $i]) / ($row['items'] + $add['items']);
	}
    }
    
    $row['items'] += $add['items'];
    $row['width'] += $add['width'];
 }

 function rewind() {
    global $CACHE_PRECISE_GAPS;

    if ($this->resolution === false) {
	$res = $this->cache->resolution->Get($this->ivl, $this->amount);
    } else {
	$res = $this->resolution;
    }

    $resolution = $this->cache->resolution->GetWindowSize($res);

    $table = $this->cache->GetTableName($resolution, $this->postfix);
    
    $this->operation_info["resolution"] = $resolution;

    $from = $this->ivl->GetWindowStart();
    $to = $this->ivl->GetWindowEnd();
    
    $combine_ends = false;
    
    if ($resolution > 0) {
	$ifrom = ceil($from);
	$rem = $ifrom % $resolution;
	if ($rem) $curfrom = $ifrom + ($resolution - $rem);
	else $curfrom = $ifrom;
		
	$ito = floor($to);
	$rem = $ito % $resolution;
	if ($rem) $curto = $ito - ($rem);
	else $curto = $ito;
	
	if ($this->ivl->flexibility) {
	    if ($this->ivl->flexibility == INTERVAL::FLAG_REDUCIBLE) {
		$from = $curfrom;
		$to = $curto;
	    } else {
		if ($from != $curfrom) {
		    $curfrom -= $resolution;
		    $from = $curfrom;
		}
		if ($to != $curto) {
		    $curto += $resolution;
		    $to = $curto;
		}
	    }
	}
	
	$sqlfrom = $this->cache->SQLTime(floor($curfrom));
	$sqlto = $this->cache->SQLTime(floor($curto)); 

	if (($from != $curfrom)||($to != $curto)) {
	    $list = ""; $i = 0;
	    foreach ($this->ids as $id) {
	        $list = "MIN(v$id) AS min$i, MAX(v$id) AS max$i, AVG(v$id) AS mean$i";
		$i++;
	    }
	    $rawtable = $this->cache->GetTableName(0, $this->postfix);
	}
	
	$points = ($curto - $curfrom) / $resolution;
	$additional_points = 0;
	
	if ($from != $curfrom) {
	    $sqltime = $this->cache->SQLTime($from);

	    $width = $curfrom - $from;

//	    $res = mysql_query("SELECT EXTENDED_UNIX_TIMESTAMP(MIN(time)) AS timestamp $list FROM `$rawtable` WHERE `time` BETWEEN $sqltime AND $sqlfrom", $this->cache->dbh);
	    $res = mysql_query("SELECT EXTENDED_UNIX_TIMESTAMP(MIN(time)) AS timestamp, COUNT(*) AS items, $width AS width $list FROM `$rawtable` WHERE (`time` >= $sqltime) AND (`time` < $sqlfrom)", $this->cache->dbh);
	    if ($res) {
		$firstrow = mysql_fetch_assoc($res);
		if (($firstrow)&&($firstrow['time'])) $additional_points++;
		else $firstrow = false;

		mysql_free_result($res);
	    } else $firstrow = false;
	} else $firstrow = false;
	
	if ($to != $curto) {
	    $sqltime = $this->cache->SQLTime($to);
	    
	    $width = $to - $curto;
	    
//	    $res = mysql_query("SELECT EXTENDED_UNIX_TIMESTAMP(MIN(time)) AS timestamp $list FROM `$rawtable` WHERE `time` BETWEEN $sqlto AND $sqltime", $this->cache->dbh);
	    $res = mysql_query("SELECT EXTENDED_UNIX_TIMESTAMP(MIN(time)) AS timestamp, COUNT(*) AS items, $width AS width $list FROM `$rawtable` WHERE (`time` >= $sqlto) AND (`time` < $sqltime)", $this->cache->dbh);
	    if ($res) {
		$this->lastrow = mysql_fetch_assoc($res);
		if (($this->lastrow)&&($this->lastrow['time'])) $additional_points++;
		else $this->lastrow = false;
		
		mysql_free_result($res);
	    } else $this->lastrow = false;
	} else $this->lastrow = false;

	if (($this->limit)&&(($points + $aditional_points) > $this->limit)) {
	    if ($additional_points) {
		if ($this->limit > 9) {
		    $limit = $this->limit - $aditional_points;
	        } else {
		    $limit = $this->limit;
		    $this->combine_ends = true;
		}
	    } else {
		$limit = $this->limit;
	    }
	    
	    for ($grouping = $resolution; $points > $limit; $points /= 2) {
		$grouping *= 2;
	    }
	} else $grouping = 0;
	
	$i = 0;
        $this->set_minmax = 0;


	if ($grouping) {
	    $grouping = "GROUP BY FLOOR((EXTENDED_UNIX_TIMESTAMP(`time`) - " . (floor($curfrom)) . " )/$grouping)";
	    $list = "EXTENDED_UNIX_TIMESTAMP(MIN(time)) AS timestamp, SUM(n) AS items, COUNT(*)*$resolution AS width";
	    if ($this->flags&CACHE::MISSING_INFO) {
		if ($CACHE_PRECISE_GAPS) {
		    throw new ADEIException(translate("CACHE_PRECISE_GAPS is not supported yet"));
		} else {
	    	    $val = "FLOOR(COUNT(*)  / (1 + COUNT(*) - SUM(n = 0)))*$resolution";
		    $list .= ", IF($val > MAX(missing), $val, MAX(missing)) AS maxgap";
		}
	    }

	    if ($this->flags&CACHE::TRUNCATE_INTERVALS) {
		foreach ($this->ids as $id) {
		    $list .= ", MIN(min$id) AS min$i, MAX(max$id) AS max$i, AVG(mean$id) AS mean$i";
		    $i++;
		}

		if ($this->flags&CACHE::REPORT_EMPTY) {
		    $grouping .= ", n=0";
		    $cond = "WHERE ((`time` >= $sqlfrom) AND (`time` < $sqlto))";
		} else {
		    $cond = "WHERE ((`time` >= $sqlfrom) AND (`time` < $sqlto) AND (`n` > 0))";
		}
	    } else {
		if (!($this->flags&CACHE::REPORT_EMPTY)) $grouping .= " HAVING(`items` > 0)";

		foreach ($this->ids as $id) {
		    $list .= ", MIN(IF(n,min$id,NULL)) AS min$i, MAX(IF(n,max$id,NULL)) AS max$i, AVG(IF(n,mean$id,NULL)) AS mean$i";
		    $i++;
		}

	        $cond = "WHERE ((`time` >= $sqlfrom) AND (`time` < $sqlto))";
	    }
	    
	} else {
	    $list = "EXTENDED_UNIX_TIMESTAMP(time) AS timestamp, n AS items, $resolution AS width";
	    if ($this->flags&CACHE::MISSING_INFO) {
		$list .= ", missing AS maxgap";
	    }
	    foreach ($this->ids as $id) {
		$list .= ", min$id AS min$i, max$id AS max$i, mean$id AS mean$i";
		$i++;
	    }
	    
	    if ($this->flags&CACHE::REPORT_EMPTY)
		$cond = "WHERE ((`time` >= $sqlfrom) AND (`time` < $sqlto))";
	    else
    		$cond = "WHERE ((`time` >= $sqlfrom) AND (`time` < $sqlto) AND (`n` > 0))";
	}
	

//    	$cond = "WHERE `time` BETWEEN $sqlfrom AND $sqlto";
//    	$cond = "WHERE ((`time` >= $sqlfrom) AND (`time` < $sqlto))";
//    	$cond = "WHERE ((`time` >= $sqlfrom) AND (`time` < $sqlto) AND (`n` > 0))";
	$sort = "ORDER BY `time` ASC";
    } else {
	$use_subseconds = false;
	$list = false; $i = 0;
	$this->nextrow = false;

	if ($this->limit) {
	    $limit = $this->limit;
	    $grouping = ($to - $from) / $limit;
	}

        if ($grouping) {
	    $ifrom = floor($from);

    	    if ($grouping > 1) {
		$list = "EXTENDED_UNIX_TIMESTAMP(MIN(time)) AS timestamp";
		$groupval = "FLOOR((EXTENDED_UNIX_TIMESTAMP(`time`) - " . (floor($ifrom)) . " )/$grouping)";
		$grouping = "GROUP BY $groupval";
	    } else {
		if (($grouping < 1)&&($this->use_subseconds)) {
		    $grouping *= 1000000000; /* in nanoseconds */
		    if ($grouping > 1) {
			$use_subseconds = true;
			$rfrom = ($from - $ifrom)*1000000000;
		    
			    /* There is should not be MIN arround ns (otherwise problems
			    when on the edge of the seconds. Should it be on time?*/
			$list = "EXTENDED_UNIX_TIMESTAMP(MIN(time)) AS timestamp, ns";
			$groupval = "FLOOR(((EXTENDED_UNIX_TIMESTAMP(`time`) - $ifrom) * 1000000000 + (`ns` - $rfrom))/$grouping)";
			$grouping = "GROUP BY $groupval";
		    } else $grouping = false;
		} else $grouping = false;
	    }
	} 

	if ($grouping) {
	    $this->set_minmax = 0;
	    
	    if ($this->use_subseconds) {
		$list .= ", COUNT(*) AS items, 1E-9 * (ADEI_TIMESTAMP(EXTENDED_UNIX_TIMESTAMP(MAX(time)),ns) - ADEI_TIMESTAMP(EXTENDED_UNIX_TIMESTAMP(MAX(time)),ns)) AS width";
	    } else {
		$list .= ", COUNT(*) AS items, (EXTENDED_UNIX_TIMESTAMP(MAX(time)) - EXTENDED_UNIX_TIMESTAMP(MIN(time))) AS width";
	    }
	    if ($this->flags&CACHE::MISSING_INFO) {
		if ($CACHE_PRECISE_GAPS) {
		    /* Unfortunately, this is working from mysql client, but not from php. We should implement it with
		    stored functions, actually:
		    // $list .= ", MAX(IF(@tmpvar_pos=$groupval, EXTENDED_UNIX_TIMESTAMP(time)-@tmpvar_width, 0)) AS maxgap, @tmpvar_pos:=$groupval AS tmpcol1, @tmpvar_width:=EXTENDED_UNIX_TIMESTAMP(time) AS tmpcol2";
		    */
		    throw new ADEIException(translate("CACHE_PRECISE_GAPS is not supported yet"));
		} else {
		    $list .= ", IF(COUNT(*)>1, (EXTENDED_UNIX_TIMESTAMP(MAX(time)) - EXTENDED_UNIX_TIMESTAMP(MIN(time))) / (COUNT(*) - 1), 0) AS maxgap";
		}
	    }
	    
	    foreach ($this->ids as $id) {
		$list .= ", MIN(v$id) AS min$i, MAX(v$id) AS max$i, AVG(v$id) AS mean$i";
		$i++;
	    }	

    	    $cond = "WHERE `time` BETWEEN $sqlfrom AND $sqlto";
	} else {
	    $this->set_minmax = 1;
	    
	    if ($this->use_subseconds) {
		$use_subseconds = true;
		$list = "EXTENDED_UNIX_TIMESTAMP(time) AS timestamp, ns";
	    } else {
	        $list = "EXTENDED_UNIX_TIMESTAMP(time) AS timestamp";
	    }
	    
	    $list .= ", 1 AS items, 0 AS width";
	    if ($this->flags&CACHE::MISSING_INFO) $list .= ", 0 AS maxgap";
	    
	    foreach ($this->ids as $id) {
		$list .= ", v$id AS mean$i";
		$i++;
	    }	
	}

	$ifrom = floor($from);
	$ito = floor($to);

//	print_r($this->ivl);
//	echo "$ifrom - $ito ($from - $to)\n";
	if (($use_subseconds)&&(($ifrom != $from)||($ito != $to))) {
	    if ($ifrom == $ito) {
		$sqlfrom = $this->cache->SQLTime($ifrom);
		$nfrom = ($from - $ifrom)*1000000000;
		$nto = ($to - $ito)*1000000000;
//		$cond = "WHERE ((`time` = $sqlfrom) AND (`ns` BETWEEN $nfrom AND $nto))";
		$cond = "WHERE ((`time` = $sqlfrom) AND (`ns` >= $nfrom) AND (`ns` < $nto))";
	    } else {
		$cond = "";
		if ($ifrom != $from) {
		    $nfrom = ($from - $ifrom)*1000000000;
		    $sqlfrom = $this->cache->SQLTime($ifrom);
		    $cond = "((`time` = $sqlfrom) AND (`ns` >= $nfrom))";
		    $ifrom++;
		}

		if ($ifrom != $ito) {
		    if ($cond) $cond .= " OR ";

		    $sqlfrom = $this->cache->SQLTime($ifrom);
		    $sqlto = $this->cache->SQLTime($ito); 
//    		    $cond .= "(`time` BETWEEN $sqlfrom AND $sqlto)";
    		    $cond .= "((`time` >= $sqlfrom) AND (`time` < $sqlto))";
		} else $sqlto = false;

		if ($ito != $to) {
		    if ($cond) $cond .= " OR ";
		    if (!$sqlto) $sqlto = $this->cache->SQLTime($ito);
		
		    $nto = ($to - $ito)*1000000000;
		    $cond .= "((`time` = $sqlto) AND (`ns` < $nto))";
		}
		$cond = "WHERE ($cond)";
	    }
	    $sort = "ORDER BY `time` ASC, `ns` ASC";
	} else {
	    $sqlfrom = $this->cache->SQLTime($ifrom);
	    $sqlto = $this->cache->SQLTime($ito);
//    	    $cond = "WHERE `time` BETWEEN $sqlfrom AND $sqlto";
    	    $cond = "WHERE ((`time`>= $sqlfrom) AND (`time` < $sqlto))";
	    $sort = "ORDER BY `time` ASC";
	    if ($use_subseconds) $sort .= ", `ns` ASC ";
	}
	
	$curfrom = $from;
    }

    if ($grouping) {
//	echo "SELECT $list FROM `$table` $cond $grouping $sort LIMIT $limit\n";
/*	$f=fopen("/tmp/xxx.9", "w");
	fwrite ($f, "SELECT $list FROM `$table` $cond $grouping $sort LIMIT $limit\n");
	fclose($f);*/
//	echo "SELECT $list FROM `$table` $cond $grouping $sort LIMIT $limit\n";
//	echo "SELECT $list FROM `$table` $cond $grouping $sort LIMIT $limit\n";
	$this->res = mysql_unbuffered_query("SELECT $list FROM `$table` $cond $grouping $sort LIMIT $limit", $this->cache->dbh);
    } else {
//	echo("SELECT $list FROM `$table` $cond $sor\nt");
	$this->res = mysql_unbuffered_query("SELECT $list FROM `$table` $cond $sort", $this->cache->dbh);
    }
    
    if (!$this->res) {
	$mysql_error = mysql_error($this->cache->dbh);

	$cache_width = $this->cache->GetTableWidth(false, $resolution);
	if ($cache_width <= max($this->ids)) {
    	    throw new ADEIException(translate("CACHE query is failed due to the mistmatch of READER and CACHE configurations (Number of channels in the loggroup have been changed). Please, fix or regenerate caching tables. Actual error was: %s", $mysql_error));
	} else {
	    throw new ADEIException(translate("CACHE query is failed: %s", $mysql_error));
	}
    }

    if ($this->combine_ends) {
	$this->nextrow = false;
	$this->next();
	
	if ($firstrow) {
	    $this->combine($this->nextrow, $firstrow, ($curfrom - $from)/$grouping);
	    $this->nextrow['time'] = $firstrow['time'];
	}
	
	if ($this->lastrow) {
	    if ($limit > 1) {
		$this->combine_ends = ($to - $curto)/$grouping;
	    } else {
		$this->combine_ends = ($to - $curto)/($grouping + ($curfrom - $from));
	    }
	} else $this->combine_ends = 0;
    } else {
	if ($firstrow) {
	    $this->nextrow = &$firstrow;
	} else {
	    $this->nextrow = false;
	    $this->next();
	}
    }
    
    $this->next();
 }
 
 function current() {
    return $this->row;
 }
 
 function key() {
    if (isset($this->row['ns'])) {
	$l = strlen($this->row['ns']);
	if ($l < 9) return $this->row['timestamp'] . "." . str_repeat('0', 9 - $l) . $this->row['ns'];
	return $this->row['timestamp'] . "." . $this->row['ns'];
    }
    return $this->row['timestamp'];
 }
 
 /* FIXME: provide min-max if only mean, provide interval ends */
 function next() {
    $this->row = $this->nextrow; // FIXME. We can't reference here, howto optimize (php by itself?)
    if (($this->row)&&(!isset($this->row['min0']))) {
	for ($i=0;isset($this->row['mean' . $i]);$i++) {
	    $this->row['min' . $i] = $this->row['mean' . $i];
	    $this->row['max' . $i] = $this->row['mean' . $i];
	}
    }

    if ($this->res) {
	$this->nextrow = mysql_fetch_assoc($this->res);

	if (!$this->nextrow) {
	    mysql_free_result($this->res);
	    $this->res = false;
	    
	    if ($this->combine_ends) {
		$this->combine($this->row, $this->lastrow, $this->combine_ends);
		$this->nextrow = false;
	    } else {
		$this->nextrow = $this->lastrow;
	    }
	}
    } else $this->nextrow = false;
 }
 
 function valid() {
    return $this->row?true:false;
 }
 
 function GetOperationInfo() {
    if ($this->operation_info) {
	return $this->operation_info;
    } else {
	if ($this->resolution === false) {
	    $res = $this->cache->resolution->Get($this->ivl, $this->amount);
	} else {
	    $res = $this->resolution;
	}

	$resolution = $this->cache->resolution->GetWindowSize($res);

    
	return array(
	    "resolution" => $resolution
	);
    }
 }
}

?>