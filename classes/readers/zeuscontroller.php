<?php

require_once($GLOBALS['ADEI_ROOTDIR'] . "/classes/readers/zeus.php");

class ZEUSController extends ZEUS /*implements CONTROLInterface*/ {
 var $control_host;
 var $control_port;
 
 const MAX_MESSAGE_SIZE = 1048576;

 function __construct(&$props) {
    parent::__construct($props);    
    if ($this->server['control_host']) {
	$this->control_host = $this->server['control_host'];
    } else {
	throw new ADEIException(translate("The control_host and control_port must point the location of cFPcom_proxy server"));
    }
    if ($this->server['control_port']) {
	$this->control_port = $this->server['control_port'];
    } else {
	throw new ADEIException(translate("The control_host and control_port must point the location of cFPcom_proxy server"));
    }
 }
/*
 function CreateMask(LOGGROUP $grp = NULL, array &$minfo = NULL, $flags = 0) {
    if (!($flags&REQUEST::CONTROL)) return parent::CreateMask($grp, $minfo, $flags);

    return READER::CreateMask($grp, $minfo, $flags);
 }
*/
 function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
    if (!($flags&REQUEST::CONTROL)) return parent::GetGroupInfo($grp, $flags);
    
    if ($flags&REQUEST::NEED_INFO)
	$req_cols = "bid, name, length, ";
    else
	$req_cols = "bid, name";


    if ($grp)
        $res = $this->db->Query("SELECT $req_cols FROM blocks WHERE bid=" . $grp->gid);
    else
        $res = $this->db->Query("SELECT $req_cols FROM blocks");


    $groups = array();
    foreach ($res as $row) {
        $gid = $row['bid'];

	$groups[$gid] = array(
		'gid' => $gid,
		'name' => $row['name']
	);
	
	if (($flags&REQUEST::NEED_INFO)&&($flags&REQUEST::NEED_ITEMINFO)) {
	    $ginfo = array("db_group" => $gid);
	    $grzeus = $this->CreateGroup($ginfo);
	    $groups[$gid]['items'] = $this->GetItemList($grzeus, NULL, $flags);
	}
    }

    return $grp?$groups[$grp->gid]:$groups;
 }

 public function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
    if (!($flags&REQUEST::CONTROL)) return parent::GetItemList($grp, $mask, $flags);

    $grp = $this->CheckGroup($grp, $flags);
    if (!$mask) $mask = $this->CreateMask($grp, $info = NULL, $flags);

    $uid = $this->opts->Get('control_uids', false);

    $items = array();
    

    $bid = $grp->gid;
    $resp = $this->db->Query("SELECT length, name, itemnames, oid FROM blocks WHERE bid=$bid");
    if ($resp) $res = $resp->fetch();
    else $res = false;
    unset($resp);
    	
    if (!$res) return $items;
    
    $oid = $res['oid'];
    $allow_read = true;
    $allow_write = false;
    $must_update = 0;
    
    $resp = $this->db->Query("SELECT mode, must_update FROM opc WHERE oid=$oid");
    if ($resp) {
	$opcres = $resp->fetch();
	if ($opcres) {
	    switch ($opcres['mode']) {
		case 1:			// write only
		    $allow_read = false;
		case 2:			// read-write
		    $allow_write = true;
	    }
	    $must_update = $opcres['must_update'];
	}
    }
    
    $names = preg_split("/\r?\n/", $res['itemnames']);

    for ($rpos = 0, $pos = 0; $pos < $res['length']; $pos++) {
	if (!$mask->Check($pos)) continue;
	    
	$items[$rpos] = array(
	    "id" => $pos,
	    "group" => $res['name'],
	    "name" =>  $names[$pos],
	    "read" => $allow_read,
	    "write" => $allow_write,
	    "sampling_rate" => $must_update
	);
	    
	
	if (preg_match("/[\w\d]/", $items[$rpos]["name"])) {
	    if ($uid) {
		if (($uid === true)||(preg_match($uid, $items[$rpos]["name"]))) {
		    $items[$rpos]["uid"] = $items[$rpos]["name"];
		}
	    }
	} else {
		// This convention is considered in Get/SetControls functions
	    $items[$rpos]["name"] = "item" . $pos;
	}
	
        $rpos++;
    }

    unset($names);
    
    return $items;
 }


 function GetGroupSize(LOGGROUP $grp = NULL, $flags = 0) {
    if (!($flags&REQUEST::CONTROL)) return parent::GetGroupSize($grp, $flags);

    $grp = $this->CheckGroup($grp, $flags);

    $params = $this->GetGroupParameters($grp);
    if ($params['width']) return $params['width'];

    $size = 0;
    try {
	$resp = $this->db->Query("SELECT length FROM blocks WHERE bid=" . $grp->gid);
	if ($resp) $res = $resp->fetch();
	else $res = false;
	
	if ($res) $size = $res['length'];
    } catch (PDOException $e) {
	throw new Exception($e->getMessage());
    }
    
    $params['width'] = $size;
    
    return $size;
 }


 function GetControls(LOGGROUP $grp = NULL, MASK $mask = NULL) {
    $grp = $this->CheckGroup($grp, REQUEST::CONTROL);

    if (!$mask) $mask = $this->CreateMask($grp, $info = NULL, REQUEST::CONTROL);

    $bid = $grp->gid;
    $resp = $this->db->Query("SELECT name,length,itemnames FROM blocks WHERE bid=$bid");
    if ($resp) $res = $resp->fetch();
    else $res = false;
    unset($resp);
    
    if ($res) {
	$gid = $res['name'];
	$inames = preg_split("/\r?\n/", $res['itemnames']);
	$length = $res['length'];
    } else throw new ADEIException(translate("Unable to resolve group %u in ZEUS database", $bid));

    
    $info = $this->GetItemList($grp, $mask, REQUEST::CONTROL|REQUEST::NEED_INFO);

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    
    if ($this->server['timeout']) {
	socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => floor($this->server['timeout']/1000000), "usec" => $this->server['timeout']%1000000));
    }

    if (@socket_connect($socket, $this->control_host, $this->control_port) === false) {
	socket_close($socket);
	throw new ADEIException(translate("Failed to connect to cFPcom_Proxy running on %s:%u", $this->control_host, $this->control_port));
    }

    $message = "get:" . $gid;
    $md5 = md5($message, true);
    $header = pack("VVVV", -1, 0, strlen($message), 0);
    if (@socket_write($socket, $header . $message . $md5) === false) {
	$err = socket_last_error($socket);
	socket_close($socket);
	throw new ADEIException(translate("Failed to send request to the cFPcom_Proxy running on %s:%u, error: %d (%s)", $this->control_host, $this->control_port, $err, socket_strerror($err)));
    }

    $blob = @socket_read($socket, ZEUSController::MAX_MESSAGE_SIZE, PHP_BINARY_READ);
    if ($blob === false) {
	$err = socket_last_error($socket);
	socket_close($socket);
	throw new ADEIException(translate("Failed to read data from the cFPcom_Proxy running on %s:%u, error: %d (%s)", $this->control_host, $this->control_port, $err, socket_strerror($err)));
    }

    $header = unpack('Vsig/Vver/Vsize/Verr', $blob);
    if (!$header) {
	socket_close($socket);
	throw new ADEIException(translate("Incomplete response from cFPcom_Proxy server"));
    }

    if (($header['sig'] != -1)&&($header['sig'] != 4294967295)) {
	socket_close($socket);
	throw new ADEIException(translate("Invalid message signature (%d) is received from cFPcom_Proxy server", $header['sig']));
    }    

    $size = $header['size'] + 32;
    while (strlen($blob) < $size) {
	$new_blob = @socket_read($socket, ZEUSController::MAX_MESSAGE_SIZE, PHP_BINARY_READ);
	if ($blob === false) {
	    $err = socket_last_error($socket);
	    socket_close($socket);
	    throw new ADEIException(translate("Failed to read data from the cFPcom_Proxy running on %s:%u, error: %d (%s)", $this->control_host, $this->control_port, $err, socket_strerror($err)));
	}
	$blob .= $new_blob;
    }

    socket_close($socket);    

    $data_blob = substr($blob, 16, $size - 32);

    if ($header['err']) {
	throw new ADEIException(translate("The cFPcom_Proxy server returned error %u: %s", $header['err'], $data_blob));
    }
    
    if (strcmp(md5($data_blob, true), substr($blob, -16))) {
	throw new ADEIException(translate("CRC check failed while communicating with cFPcom_Proxy server"));
    }

    $dim = ($size - 32) / 8;
    $data = unpack('d'.$dim.'val', $data_blob);

    
    unset($blob);
    unset($data_blob);

	/* This is done to handle special hack in ZEUS implemented to be 
	backward compatible with old HMI's. If timestamps are included into
	the block (which should not normally be done) they could be actually
	stripped off. We are adding them back.
	The checks should couply with checks done in cFPproxy */
    $block_timestamps = 2; $ro_items = 0;
    if (($dim == $length)&&((!$inames[0])||(preg_match("/(time|zeit)/i", $inames[0])))&&((!$inames[1])||(preg_match("/(time|zeit)/i", $inames[1])))) {
	$from = $this->ImportUnixTime(1104537600.); // 2005
	$to = $this->ImportUnixTime(4070908800.); // 2099
	if (($data["val1"] > $from)&&($data["val2"] > $from)&&($data["val1"] < $to)&&($data["val2"] < $to)) {
	    $block_timestamps = 0;
	    $ro_items = 2;
	}
    }
    
    if (($length + $block_timestamps)  != $dim) {
	throw new ADEIException(translate("The invalid number of items returned, expected: %d, returned: %d", $length, $dim - $block_timestamps));
    } 

    if (($mask)&&($mask->ids)) {
	$size = sizeof($mask->ids);
    } else {
	$size = $dim - $block_timestamps;
    }
    
    $timestamp = $this->ExportUnixTime($data['val2']);
    $verified = $this->ExportUnixTime($data['val1']);
    
    $res = array();
    if (is_array($data)) {
	for ($key = 0; $key < $size; $key++) {
	    $res[$key] = array(
		'value' => $data['val' . ($mask->Get($key) + $block_timestamps + 1)],
		'timestamp' => $timestamp,
		'verified' => $verified,
		'write' => ($mask->Get($key) >= $ro_items)&&($info[$key]['write']),
		'obtained' => gettimeofday(true),
		'db_server' => $this->srvid,
		'db_name' => $this->dbname,
		'db_group' => $grp->gid,
		'id' => $info[$key]['id'],
		'name' => $info[$key]['name']
	    );

	    if ($info[$key]['sampling_rate']) {
		$res[$key]['sampling_rate'] = $info[$key]['sampling_rate'];
	    }
	    if ($info[$key]['uid']) {
		$res[$key]['uid'] = $info[$key]['uid'];
	    }
	}
    }
    unset($data);
    
    return $res;
 }

 
 function SetRawControls(LOGGROUP $grp = NULL, MASK $mask = NULL, array $values) {
    $grp = $this->CheckGroup($grp, REQUEST::CONTROL);
    if (!$mask) $mask = $this->CreateMask($grp, $minfo = NULL, REQUEST::CONTROL);

    $this->DisposeControlMask($grp, $mask, $values);
    
    
    $bid = $grp->gid;
    $resp = $this->db->Query("SELECT name,itemnames FROM blocks WHERE bid=$bid");
    if ($resp) $res = $resp->fetch();
    else $res = false;
    unset($resp);
    
    if ($res) $gid = $res['name'];
    else throw new ADEIException(translate("Unable to resolve group %u in ZEUS database", $bid));

    $inames = preg_split("/\r?\n/", $res['itemnames']);
    

	/* This is done to handle special hack in ZEUS implemented to be 
	backward compatible with old HMI's. If timestamps are included into
	the block (which should not normally be done) they could be actually
	stripped off. We are adding them back.
	The checks should couply with checks done in cFPproxy */
    if ((sizeof($values) >= 2)&&((!$inames[0])||(preg_match("/(time|zeit)/i", $inames[0])))&&((!$inames[1])||(preg_match("/(time|zeit)/i", $inames[1])))) {
	$from = $this->ImportUnixTime(1104537600.); // 2005
	$to = $this->ImportUnixTime(4070908800.); // 2099
	if (($values[0] > $from)&&($values[1] > $from)&&($values[0] < $to)&&($values[1] < $to)) {
		// Would be ignored
	    array_shift($values);
	    array_shift($values);
	}
    }

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($this->server['timeout']) {
	socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => floor($this->server['timeout']/1000000), "usec" => $this->server['timeout']%1000000));
    }

    if (@socket_connect($socket, $this->control_host, $this->control_port) === false) {
	socket_close($socket);
	throw new ADEIException(translate("Failed to connect to cFPcom_Proxy running on %s:%u", $this->control_host, $this->control_port));
    }

    $message = "set:" . $gid;
    
    $data = "";
    foreach ($values as $value) $data .= pack('d', $value);

    $md5 = md5($message . $data, true);
    $header = pack("VVVV", -1, 0, strlen($message), strlen($data));
    
    if (@socket_write($socket, $header . $message . $data . $md5) === false) {
	$err = socket_last_error($socket);
	socket_close($socket);
	throw new ADEIException(translate("Failed to send request to the cFPcom_Proxy running on %s:%u, error: %d (%s)", $this->control_host, $this->control_port, $err, socket_strerror($err)));
    }

    $blob = @socket_read($socket, ZEUSController::MAX_MESSAGE_SIZE, PHP_BINARY_READ);
    if ($blob === false) {
	$err = socket_last_error($socket);
	socket_close($socket);
	throw new ADEIException(translate("Failed to read response from the cFPcom_Proxy running on %s:%u, error: %d (%s)", $this->control_host, $this->control_port, $err, socket_strerror($err)));
    }

    $header = unpack('Vsig/Vver/Vsize/Verr', $blob);
    if (!$header) {
	socket_close($socket);
	throw new ADEIException(translate("Incomplete response from cFPcom_Proxy server"));
    }

    if (($header['sig'] != -1)&&($header['sig'] != 4294967295)) {
	socket_close($socket);
	throw new ADEIException(translate("Invalid message signature (%d) is received from cFPcom_Proxy server", $header['sig']));
    }    

    $size = $header['size'] + 32;
    while (strlen($blob) < $size) {
	$new_blob = @socket_read($socket, ZEUSController::MAX_MESSAGE_SIZE, PHP_BINARY_READ);
	if ($blob === false) {
	    $err = socket_last_error($socket);
	    socket_close($socket);
	    throw new ADEIException(translate("Failed to read data from the cFPcom_Proxy running on %s:%u, error: %d (%s)", $this->control_host, $this->control_port, $err, socket_strerror($err)));
	}
	$blob .= $new_blob;
    }

    socket_close($socket);    
    
    $data_blob = substr($blob, 16, $size - 32);

    if ($header['err']) {
	throw new ADEIException(translate("The cFPcom_Proxy server returned error %u: %s", $header['err'], $data_blob));
    }
    
    if (strcmp(md5($data_blob, true), substr($blob, -16))) {
	throw new ADEIException(translate("CRC check failed while communicating with cFPcom_Proxy server"));
    }

    if ($size != 32) {
	throw new ADEIException(translate("The invalid content is returned by cFPcom_Proxy server, expected: %d bytes, returned: %d", sizeof($info), 32, $size));
    }
    
 } 
}



?>