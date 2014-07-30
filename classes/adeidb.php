<?php

class ADEIDB {
 var $dbh;

 const MYSQL_ER_BAD_DB_ERROR = 1049;
 const MYSQL_ER_NO_SUCH_TABLE = 1146;
 const MYSQL_ER_DUP_ENTRY = 1062;
 const MYSQL_ER_WRONG_VALUE_COUNT_ON_ROW = 1136;
 const MYSQL_ER_SP_ALREADY_EXISTS = 1304;
 const MYSQL_ER_BAD_FIELD_ERROR = 1054;

 function __construct() {
    global $ADEI_DB;

    $this->dbh = $this->ConnectDB();
 }

 static function ConnectDB() {
    global $ADEI_DB;

    $dbh = mysql_connect($ADEI_DB['host'] . ($ADEI_DB['port']?(":" . $ADEI_DB['port']):""), $ADEI_DB['user'], $ADEI_DB['password']);
    if (!$dbh) throw new ADEIException(translate("Connection to the Caching MySQL Server is failed"));
    
    mysql_query("SET time_zone = '+0:00'", $dbh);

    if (!@mysql_select_db($ADEI_DB['database'], $dbh)) {
		// ER_NO_DB_ERROR
	if (mysql_errno($dbh) == CACHEDB::MYSQL_ER_BAD_DB_ERROR) { 
	    if (mysql_query("CREATE DATABASE " . $ADEI_DB['database'], $dbh)) {
		if (!mysql_select_db($ADEI_DB['database'], $dbh))
		    throw new ADEIException(translate("Connection to the Caching MySQL Database is failed") . " (" . mysql_error($dbh) . ")");

		self::CreateStoredProcedures($dbh);
	    } else 
		throw new ADEIException(translate("Creation of the caching MySQL database is failed") . " (" . mysql_error($dbh) . ")");
	    
	    
	} else 
	    throw new ADEIException(translate("Connection to the Caching MySQL Database is failed") . " (" . mysql_error($dbh) . ")");
    }
    
    return $dbh;
 }

 function CreateStoredProcedures($dbh = false) {
    if (!$dbh) $dbh = $this->dbh;
    
	/* The starting points of timestamps should be floating and should not
	be referenced in other places of app */
    $query = "CREATE FUNCTION ADEI_TIMESTAMP(unix_timestamp INT, ns INT) RETURNS BIGINT DETERMINISTIC RETURN unix_timestamp*1000000000+ns";
    if ((!mysql_query($query, $dbh))&&(mysql_errno($dbh) != CACHEDB::MYSQL_ER_SP_ALREADY_EXISTS)) {
	throw new ADEIException(translate("CACHE is not able to create stored procedure (%s), error: %s", "ADEI_TIMESTAMP", mysql_error($dbh)));
    }

    $query = "CREATE FUNCTION EXTENDED_UNIX_TIMESTAMP(dt DATETIME) RETURNS BIGINT DETERMINISTIC RETURN TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', dt)";
    if ((!mysql_query($query, $dbh))&&(mysql_errno($dbh) != CACHEDB::MYSQL_ER_SP_ALREADY_EXISTS)) {
	throw new ADEIException(translate("CACHE is not able to create stored procedure (%s), error: %s", "EXTENDED_UNIX_TIMESTAMP", mysql_error($dbh)));
    }
 }

 function CreateTable($name, $spec) {
    if (!@mysql_query("CREATE TABLE `$name` ($spec)", $this->dbh)) {
	throw new ADEIException(translate("Creation of system table (%s) within CACHE database is failed", $name) . " (" . mysql_error($this->dbh) . ")");
    }
 }
 
 function AppendRecord($table, $values, $spec = false, $update = true) {
    if (is_array($update)) {
	$arr = array();
	foreach ($update as $key => $col) {
	    array_push($arr, "`$col` = '{$values[$key]}'");
	}
	if ($arr) $update = " ON DUPLICATE KEY UPDATE " . implode(",", $arr) . "";
	else $update = "";
    } else $update = "";
    
    $query = "INSERT INTO `$table` VALUES('" . implode("','", $values) . "')$update";
#    echo $query . "\n";
    if (!@mysql_query($query, $this->dbh)) {
	switch (mysql_errno($this->dbh)) {
	    case CACHE::MYSQL_ER_NO_SUCH_TABLE:
		if ($spec) {
		    $this->CreateTable($table, $spec);
		    if (@mysql_query($query, $this->dbh)) return;
		} else {
		    throw new ADEIException(translate("Table (%s) is not found within CACHE database", $table));
		}
	    break;
	    case CACHE::MYSQL_ER_DUP_ENTRY:
		if ($update === false) return;
		else if ($update === true) {
		    throw new ADEIException(translate("Record is already existing in the caching table (%s)", $table));
		}
	    break;
	}
	throw new ADEIException(translate("Can not append entry into the caching table (%s), error: %s", $table, mysql_error($this->dbh)));
    }
 }
 
 function DeleteRecord($table, $cond) {
    $res = "DELETE FROM $table";
    if (is_array($cond)) {
	$conds = array();
	foreach ($cond as $col => $value) {
	    if ($value === false) $value = 0;
	    array_push($conds, "`$col` = '$value'");
	}
	
	if ($conds) {
	    $res .= " WHERE (" . implode(") AND (", $conds) . ")";
	}
    } else if ($cond) $res .= " WHERE $cond";
    
#    echo $res . "\n";
    if (!@mysql_query($res, $this->dbh)) {
	throw new ADEIException(translate("Can not delete entry from the caching table (%s), error: %s", $table, mysql_error($this->dbh)));
    }
 }
 
 function Select($request) {
    $res = @mysql_query($request, $this->dbh);
    if (!$res) {
	switch (mysql_errno($this->dbh)) {
	    case CACHE::MYSQL_ER_NO_SUCH_TABLE:
	    break;
	    default:
		throw new ADEIException(translate("Can not query CACHE table, error") . ": " . mysql_error($this->dbh));
	}
	return array();
    }

    $list = array();
    while ($row = mysql_fetch_row($res)) {
	array_push($list, $row);
    }
    
    return $list;
 }
 
 function SelectRequest($table, $columns="*", array $req = NULL) {
    $res = "SELECT ";

    if (is_array($columns)) {
	if (!$columns) 
	    throw new ADEIException(translate("Select request is failed: column list is empty"));

	$res .= "`" . implode("`,`", $columns) . "`";
    } else $res .= $columns;

    $res .= " FROM " . $table;

    if ($req['condition']) $res = " WHERE " . $req['condition'];
    else if (is_array($req['columns_equal'])) {
	$conds = array();
	foreach ($req['columns_equal'] as $col => $value) {
	    if ($value === false) $value = 0;
	    array_push($conds, "`$col` = '$value'");
	}
	
	if ($conds) {
	    $res .= " WHERE (" . implode(") AND (", $conds) . ")";
	}
    }
    
    if ($req['group']) $res .= " GROUP BY " . $req['group'];
    if ($req['order']) $res .= " ORDER BY " . $req['order'];

    if ($req['limit']) $res .= " LIMIT " . $req['limit'];

    return $this->Select($res);
 }    
}

?>