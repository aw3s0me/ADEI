<?php

class DBRes implements Iterator {
 var $arr;
  
 public function __construct() {
    $this->arr = array();
 }
 
 public function push(&$obj) {
    array_push($this->arr, $obj);
 }
 
 public function fetch($flags = 0) {
    return array_shift($this->arr);
 }

 function rewind() {
    reset($this->arr);
 }
 function current() {
    return current($this->arr);
 }
 function key() {
    return key($this->arr);
 }
 function next() {
    each($this->arr);    
 }

 function valid() {
	// We don't expect 'false' elements in array (all are arrays)
    return current($this->arr)?true:false;
 }
 
 function rowCount() {
    return sizeof($this->arr);
 }
}

class DATABASE {
 var $dbh, $connected;
 var $driver;
 var $odbc;
 
 var $server;
 var $dbname;
 
 var $text_quote = '\'';
 var $col_quote = "\"";
 var $tbl_quote = "";

 const GLOBAL_QUERY = 0x0001;
 const SINGLE_RESULT = 0x0002;
 const FETCH_NUM = 0x0100;

 
 function __construct(&$server) {
    $this->server = $server;

    if (($server['charset'])&&(preg_match("/UTF.*8/i", $server['charset']))) {
	unset($this->server['charset']);
    }

    $this->ReConnect();
 }
 
 function ReConnect() {
    $server = &$this->server;

    if (($this->connected)||($this->dbh)) {
	$this->connected = false;
	$this->dbh = false;
    }
    
    $pdo_opts = array();
    if ($server['persistent']) {
	$pdo_opts[PDO::ATTR_PERSISTENT] = true;
    }

     try {
        $this->odbc = false;
	
	switch($server['driver']) {
	  case "odbc":
	    if ($server['source']) {
		$this->dbh = new PDO ("odbc:" . $server['source'], $server['user'], $server['password'], $pdo_opts);
		
		$this->dbname = $server['source'];
		$this->connected = true;
	    } else {
		if ($server['database']) {
		    $dbtext = ";DATABASE=" . $server['database'];
		    $this->dbname = $server['database'];
		    $this->connected = true;
		} else $dbtext = "";
		
		if ($server['timeout']) {
		    if ($server['ping']) {
			if ((!is_array($server['ping']))&&((!$server['host'])||(!$server['port'])))
			    throw new ADEIException(translate("The ping-before-connection feature is requiring the database's host and port to be excplicitly specified."));
			    
			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			if ($socket) {
			    socket_set_nonblock($socket);
			
			    if (is_array($ping))
				$res = @socket_connect($socket, $server['ping']['host'], $server['ping']['port']);
			    else
				$res = @socket_connect($socket, $server['host'], $server['port']);
			
			    if (!$res) {
				$res = socket_select($r = array($socket), $w = array($socket), $e = array($socket), floor($server['timeout']/1000000), $server['timeout']%1000000);
				if ($res == 1) $res = true;
				else $res = false;
			    }
			    socket_close($socket);
			    
			    if (!$res) {
				throw new ADEIException(translate("Error connecting to the database (timeout expired)"));
			    }
			}
		    }
		
		    $timeout = floor($server['timeout'] / 1000000);
		    if (!$timeout) $timeout = 1;
		    
		    $pdo_opts[PDO::ATTR_TIMEOUT] = $timeout;
		    $this->dbh = @new PDO ("odbc:DRIVER=" . $server['subdrv'] . ";SERVER=" . $server['host'] . ";PORT=" . ($server['port']?$server['port']:"1433") . $dbtext . ";PROTOCOL=TCPIP;UID=" . $server['user'] . ";PWD=" . $server['password'], NULL, NULL, $pdo_opts);
		} else {
		    $this->dbh = new PDO ("odbc:DRIVER=" . $server['subdrv'] . ";SERVER=" . $server['host'] . ";PORT=" . ($server['port']?$server['port']:"1433") . $dbtext . ";PROTOCOL=TCPIP;UID=" . $server['user'] . ";PWD=" . $server['password'], NULL, NULL, $pdo_opts);
		}
	    }

	    $this->odbc = true;
	  break;
	  default:	
#	    echo "connecting\n";
#	    print_r($server);
	    if ($server['database']) {
		$this->dbh = new PDO (
		    $server['driver'] . ":host=" . $server['host'] . ($server['port']?(";port=" . $server['port']):"") . ";dbname=" . $server['database'], 
		    $server['user'], 
		    $server['password'],
		    $pdo_opts
		);
		$this->dbname = $server['database'];	    
		$this->connected = true;
	    } else {
    		$this->dbh = new PDO (
		    $server['driver'] . ":host=" . $server['host'] . ($server['port']?(";port=" . $server['port']):""), 
		    $server['user'], 
		    $server['password'],
		    $pdo_opts
		);
		$this->connected = false;
	    }
#	    echo "connected\n";
#	    exit;
	}
        
	if ($server['sqldrv']) $this->driver = $server['sqldrv'];
	else $this->driver = $server['driver'];
	
	    /* Single check for single scheme */
	switch ($this->driver) {
	    case "dblib":
		$this->driver="mssql";
	    case "mssql":
		$this->dbh->query("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
	    break;
	    case "mysql":
		$this->col_quote = "`";
		$this->tbl_quote = "`";
	    break;
	}
	
	 $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    } catch(PDOException $e) {
	$this->dbh = NULL;
	$this->connected = false;

	$errmsg = $this->RecodeMessage($e->getMessage());
	if ($this->dbname)
	    throw new ADEIException(translate("Error connecting to the server \"%s\" database \"%s\": %s", $server['title'], $this->dbname, $errmsg), $e->getCode());
	else
	    throw new ADEIException(translate("Error connecting to the server \"%s\": %s", $server['title'], $errmsg), $e->getCode());
    }
 }
 
 function RecodeMessage($msg) {
    if (($msg)&&($this->server['charset'])) {
	$rec = iconv($this->server['charset'], "UTF-8", $msg);
	if (!$rec) {
	    throw new ADEIException(translate("The message received from server (%s) could not be translated from source charset (%s) to UTF-8. Please, check the server charset in configuration.", $this->server['title'], $this->server['charset']));
	}

	return $rec;
    }

    return $msg;    
 }

/*
 function RecodeData($data) {
 }
*/ 

 static function GetConnectionString(array &$server, array &$options = NULL, &$application = NULL) {
    if (strtolower($server['driver']) == 'mysql') {
	if ((!$application)||($application == 'mysqldump')) {
	    return "-h" . $server['host'] . ($server['port']?(":" . $server['port']):"") . " -u" . $server['user'] . " -p" . $server['password'] . " " . $server['database'];
	} else throw new ADEIException(translate("The required application (\"%s\") is not supported at the moment", $application));
    } else throw new ADEIException(translate("The supplied connection (\"%s\") is not supported at the moment", $server['driver']));
 }
 

 function Query($sql, $flags = 0) {
//    echo $sql . "\n\n";

    if ((!$this->connected)&&(($flags&DATABASE::GLOBAL_QUERY)==0))
	 throw new ADEIException(translate("The database is not specified"));

    try {
	if ($this->odbc) {
		//	This is to prevent spurious odbc errors:
	    $stmt = $this->Prepare($sql, $flags);
	    $stmt->execute();
	
	    $resp = new DBRes();
	    if ($flags&DATABASE::SINGLE_RESULT) {
		$row = $stmt->fetch(($flags&DATABASE::FETCH_NUM)?PDO::FETCH_NUM:PDO::FETCH_ASSOC);
		if ($row) $resp->push($row);
	    } else {
	    	while ($row = $stmt->fetch(($flags&DATABASE::FETCH_NUM)?PDO::FETCH_NUM:PDO::FETCH_ASSOC)) {
		    $resp->push($row);
		}
	    }
	} else {
	    //echo $sql . "\n\n";
	    $resp = $this->dbh->query($sql);
	    $resp->setFetchMode(($flags&DATABASE::FETCH_NUM)?PDO::FETCH_NUM:PDO::FETCH_ASSOC);
	}
    } catch (PDOException $e) {
    	$errmsg = $this->RecodeMessage($e->getMessage());
	throw new ADEIException(translate("SQL Query is failed with error: %s", $errmsg));
    }
//    echo "done\n";
    
    if (!$resp) {
	$e = $this->dbh->errorInfo();
    	$errmsg = $this->RecodeMessage($e[2]);
	throw new ADEIException(translate("SQL Query is failed. SQL Error: %u, Driver Error: %u, Message: %s ", $e[0], $e[1], $errmsg) . "[$sql]");
    }

    return $resp;
 }

 function Prepare($sql, $flags = 0) {
    if ((!$this->connected)&&(($flags&DATABASE::GLOBAL_QUERY)==0))
	 throw new ADEIException(translate("The database is not specified"));

    try {
	$stmt = $this->dbh->prepare($sql);
    } catch (PDOException $e) {
	$throw = true;
	
	    // Link fault, retrying
	if ($e->getCode() == "08S01") {
	    try {
//		echo "Reconnecting\n";
		$this->Reconnect();
//		echo "Preparing New STMT\n";
		$stmt = $this->dbh->prepare($sql);
//		echo "Done\n";
		$throw = false;
	    } catch (PDOException $e2) {
	    }
	}
	if ($throw) {
	    $errmsg = $this->RecodeMessage($e->getMessage());
	    throw new ADEIException(translate("Can not prepare SQL query for execution, Server: %s, Database: %s, Error: %s [%s]", $this->server['title'], $this->dbname, $errmsg, $sql), $e->getCode());
	}
    }

    if (!$stmt) {
	$e = $this->dbh->errorInfo();
	$errmsg = $this->RecodeMessage($e[2]);
	throw new ADEIException(translate("Preparation of the SQL Query is failed. Server: %s, Database: %s, SQL Error: %u, Driver Error: %u, Message: %s [%s]", $this->server['title'], $this->dbname, $e[0], $e[1], $errmsg, $sql));
    }

    return $stmt;
 }

/*
 function statementBindColumn($stmt, $column, &$var, $type = PDO::PARAM_LOB) {
    $stmt->bindColumn($column, $var, $type);
 }

 function statementBindParam($stmt, $param, &$var, $type = PDO::PARAM_LOB) {
    $stmt->bindColumn($param, $var, $type);
 }
*/

 function SelectRequest($table, $columns="*", array $req = NULL) {
    $res = "SELECT ";
    if ($req['limit']) {
	switch ($this->driver) {
	    case "mssql":
		$res .= "TOP " . $req['limit'] . " ";
	    break;
	    case "mysql":
		$suffix = " LIMIT " . $req['limit'];
	    break;
	    default:
		throw new ADEIException(translate("Don't know how to handle LIMIT for '%s'", $this->driver));
	}
    }
    
    if (is_array($columns)) {
	$last = array_pop($columns);
	if (!$last) 
	    throw new ADEIException(translate("Columns list is empty"));
	
	foreach ($columns as $item) {
	    $res .=  "\"" . $item/*['column']*/ . "\", ";
	}
	$res .= "\"" . $last . "\"";
    
    } else $res .= $columns;
    
    if (preg_match("/^[(\s]*SELECT/i", $table)) {
	$res .= " FROM " . $table;
    } else {
	$res .= " FROM " . $this->tbl_quote . $table . $this->tbl_quote;
    }

    if ($req['condition']) $cond = " WHERE " . $req['condition'];
    else $cond = "";
    
    if ($req['sampling']) {
	if (is_array($req['sampling'])) {
	    $slicer = $req['sampling']['slicer'];
	    $selector = $req['sampling']['selector'];
	} else {
	    $slicer = $req['sampling']['slicer'];
	    $selector = $req['sampling']['slicer'];
	}
	
	switch ($this->driver) {
	    case "mysql":
		$res .= ", (SELECT MAX($selector) AS tmptbl_sel FROM {$this->tbl_quote}$table{$this->tbl_quote} $cond GROUP BY FLOOR($slicer)) AS tmptbl";
		$res .= " WHERE  tmptbl.tmptbl_sel = $selector";
	    break;
	    default:
		$res .= " WHERE $selector IN (SELECT MAX($selector) FROM {$this->tbl_quote}$table{$this->tbl_quote} $cond GROUP BY FLOOR($slicer))";
	}
    } else {
	$res .= $cond;
    }
    if ($req['group']) $res .= " GROUP BY " . $req['group'];
    if ($req['order']) $res .= " ORDER BY " . $req['order'];
    
    return $res . $suffix;
 }

 function GetTimeFormat() {
//    if ($this->time_format) return $this->time_format;
    
    switch ($this->driver) {
	case "mysql":
	    return "\'YmdHis\'";
	case "mssql":
	    return "Ymd H:i:s";
	    /* Hm. Didn't work from Linux
	    return "Y-d-m H:i:s";*/
	    /* We can't use '.u' since it is formated with 6 digits after the 
	    decimal point, while the MSSQL complains if there are more than 3
	    return "Y-d-m H:i:s.u";*/
	default:
	    throw new ADEIException(translate("The date format for \"%s\" is not known", $this->driver));
    }
 }
 
 function GetTimeRequest($column_name) {
    switch ($this->driver) {
	case "mssql":
	    return "CONVERT(CHAR(24), {$this->col_quote}$column_name{$this->col_quote}, 21)";
	default:
	    return "{$this->col_quote}column_name{$this->col_quote}";
    }
 }
 
 function ShowDatabases() {
    switch ($this->driver) {
	case "mysql":
	    return $this->Query("SHOW DATABASES", DATABASE::GLOBAL_QUERY|DATABASE::FETCH_NUM);
	case "mssql":
	    return $this->Query("SELECT name FROM master..sysdatabases", DATABASE::GLOBAL_QUERY|DATABASE::FETCH_NUM);
	default:
	    throw new ADEIException(translate("The ShowDatabases for \"%s\" is not implemented", $this->driver));
    }
 } 
 
 function ShowTables() {
    switch ($this->driver) {
	case "mysql":
	    return $this->Query("SHOW TABLES", DATABASE::FETCH_NUM);
	case "mssql":
		/* Bugs in ODBC/MySQL (with segm. in some cases,
		possibly http://bugs.php.net/bug.php?id=33533&edit=1
		*/
/*
	    $db = new DATABASE($this->server);
	    return $db->Query(" SELECT name AS gid FROM sysobjects WHERE type = 'U'");
*/

	    return $this->Query("SELECT name AS gid FROM sysobjects WHERE (type = 'U' OR type = 'V')", DATABASE::FETCH_NUM);
	default:
	    throw new ADEIException(translate("The ShowTables for \"%s\" is not implemented", $this->driver));
    }
 }

 function FixTableName($table) {
    switch ($this->driver) {
	case "mssql":
	    $table = preg_replace("/^\[?mda\]?\./", "", $table);
	    $table = preg_replace("/(^\[|\]$)/", "", $table);
	break;
    }
    return $table;
 }

 function ShowColumns($table) {
    switch ($this->driver) {
	case "mysql":
	    return $this->Query("SHOW COLUMNS FROM `$table`", DATABASE::FETCH_NUM);
	case "mssql":
		/* we could get here problems if several non-equal tables with 
		different prefixes (mda,dbo) are present */
	    $table = $this->FixTableName($table);
/*	    $table = preg_replace("/^\[?mda\]?\./", "", $table);
	    $table = preg_replace("/(^\[|\]$)/", "", $table);*/
	    return $this->Query("SELECT name FROM (SELECT DISTINCT TOP 65535 name=syscolumns.name, type=systypes.name, length=syscolumns.length, objname=sysobjects.name, colid=syscolumns.colid
		FROM sysobjects 
		JOIN syscolumns ON sysobjects.id = syscolumns.id 
		JOIN systypes ON syscolumns.xtype=systypes.xtype
		WHERE (sysobjects.xtype='U' OR sysobjects.xtype='V')  AND sysobjects.name='$table'
		ORDER BY sysobjects.name,syscolumns.colid) AS tmptable", DATABASE::FETCH_NUM);
	default:
	    throw new ADEIException(translate("SHOW COLUMNS not implemented for %s", $this->driver));
    }
 }
 
 function CreateDatabase($dbname) {
    $this->Query("CREATE DATABASE `$dbname`", DATABASE::GLOBAL_QUERY);
 }
 
 
 function GetDatabaseList($filter = false) {
    $resp = $this->ShowDatabases();

    $dblist = array();

    foreach ($resp as $row) {
	$name = $row[0];
	if ((!$filter)||(preg_match($filter, $name))) {
	    $dblist[$name] = array(
		'name' => $name
	    );
	}
    }
    
    return $dblist;
 }

 function SQLTime($time) {
/*
    if (is_int($time))
	return strftime("%Y%m%d%H%M%S", $time);
    else {
	return strftime("%Y%m%d%H%M%S", floor($time)) . substr(sprintf("%.6F", $time - floor($time)), 1);
    }
*/
    return strftime("%Y%m%d%H%M%S", floor($time));

 }
 
 function __sleep() {
    return array('server', 'dbname', 'driver', 'odbc', 'connected');
 }
 
 function __wakeup() {
    $this->dbh = NULL;
    $this->connected = false;
 }
}


/* expecting rows in a form: chan1,chan2,...,chan#,time  */
class DATABASEData implements Iterator {
 var $reader;
 var $stmt;
 var $row;
 var $time;
 var $flags;
 
 const EMPTY_ON_ERROR = 1;
 
 function __construct(READER &$reader, PDOStatement &$stmt, $flags = 0) {
    $this->reader = &$reader;
    $this->stmt = &$stmt;
    $this->flags = $flags;
 }
 
 function rewind() {
    try {
	$this->stmt->execute();
	$this->next();
    } catch(PDOException $e) {
	if ($this->flags&DATABASEData::EMPTY_ON_ERROR) {
	    unset($this->row);
	} else {
	    throw new ADEIException(translate("SQL request is failed with error") . ": " . $e->getMessage(), $e->getCode());
	}
    }
 }
 
    // current element
 function current() {
    return $this->row;
 }
 
    // current key (PHP rounding to integer :()
 function key() {
/*    echo $this->time;
    echo "  -  ";
    echo date('Y-m-d', $this->reader->ExportUnixTime($this->time));
    echo "\n";*/
    return $this->reader->ExportUnixTime($this->time);
 }
 
    // advvance to next (and returns it or false)
 function next() {
    try {
	$this->row = $this->stmt->fetch(PDO::FETCH_NUM);
	if ($this->row) {
	    $last = sizeof($this->row) - 1;
	    $this->time = $this->row[$last];
	    unset($this->row[$last]);
	}
    } catch(PDOException $e) {
//	$err = $this->RecodeMessage($e->getMessage());
	$err = $e->getMessage();
	throw new ADEIException(translate("SQL error: %s", $err));
    }
 }
 
    // checks if there is current element
 function valid() {
    return $this->row?true:false;
 }
}

?>