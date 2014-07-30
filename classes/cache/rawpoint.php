<?php

/* 
    $limit > 0 - first <limit> elements
    $limit < 0 - last <limit> elements
*/

class RAWPoint implements Iterator {
 var $cache;
 var $ids;
 var $ivl;
 var $limit;
 var $sampling;

 var $res;
 var $key, $row;
 
 var $use_subseconds;
 var $postfix;
    
 function __construct(CACHEDB $cache, MASK $mask, INTERVAL $ivl, $limit, $sampling = 0) {
    $this->cache = &$cache;

    $this->ids = &$mask->ids;
    if (!is_array($this->ids)) throw new ADEIException("Internal application error (MASK should be created using CACHE call)");

    $this->ivl = $ivl;
    $this->limit = $limit;

    $this->sampling = $sampling;

    $this->use_subseconds = $cache->use_subseconds;
    $this->postfix = false;
 }

 function __destruct() {
    $this->Clear();
 }

 function SetOption($option, $value) {
    $this->$option = $value;
 } 

 function SetOptions($options) {
    foreach ($options as $opt => &$value) {
	$this->$opt = $value;
    }
 }
 
 function Clear() {
    if ($this->res) {
	@mysql_free_result($this->res);
	unset($this->res);
    }
 }
 
 function rewind() {
    global $MYSQL_FORCE_INDEXES;
    
    $this->Clear();
    
    $list = "";

    foreach ($this->ids as $id) {
	$list .= "v$id AS v$id, ";
    }	

    $table = $this->cache->GetTableName(0, $this->postfix);
    $sql = $this->ivl->GetSQL($this->cache, $table, $this->limit, $this->use_subseconds, isset($this->sequence)?$this->sequence:false, $this->sampling);

    if ($MYSQL_FORCE_INDEXES) {
	if ($sql['index']) sprintf($idx_fix, "FORCE INDEX (%s)", $sql['index']);
	else $idx_fix = "FORCE INDEX (PRIMARY)";
    } else $idx_fix = "";

    $list .= $sql['list'];
    $cond = &$sql['cond'];
    $sort = &$sql['sort'];
    $limit = &$sql['limit'];
    $join = &$sql['join'];

    $this->res = mysql_unbuffered_query("SELECT $list FROM `$table` $idx_fix $join $cond $sort $limit", $this->cache->dbh);

    if (!$this->res)
	throw new ADEIException(translate("SELECT request '%s'  on CACHE table '%s' is failed. MySQL error: %s", "SELECT $list FROM `$table` $idx_fix $join $cond $sort $limit", $table, mysql_error($this->cache->dbh)));

    $this->next();
 }
 
 function current() {
    return $this->row;
 }
 
 function key() {
    return $this->key;
 }
 
 function next() {
    $this->row = mysql_fetch_row($this->res);
    if ($this->row) {
	$lastkey = sizeof($this->row) - 1;
	if ($this->use_subseconds) {
	    $ns = $this->row[$lastkey];
	    
	    $extra = (9 - strlen($ns));
	    if ($extra) $ns = str_repeat('0', $extra) . $ns;
		
	    $this->key = $this->row[$lastkey-1] . ".$ns";
	    unset($this->row[$lastkey]);
	    unset($this->row[$lastkey-1]);
	} else {
	    $this->key = $this->row[$lastkey];
	    unset($this->row[$lastkey]);
	}
    } else {
	$this->key = false;
	mysql_free_result($this->res);
    }
 }

 function valid() {
    return $this->key?true:false;
 }
}

?>