<?php
class UIDLocator extends ADEIDB {
 var $control;		// flag indicating if we are looking for control uids
 var $uid_array;

 const UIDS_TABLE_SPEC = "`setup` CHAR(64), `control` BOOL, `uid` CHAR(128) NOT NULL, `item` VARCHAR(4096) NOT NULL, UNIQUE INDEX(`setup`, `control`, `uid`)";

 function __construct($control = false) {
    parent::__construct();
    
    $this->control = $control;
    $this->uid_array = false;
 
 }

 function FindUIDs() {
    $res = array();
    
    $flags = 0;
    if ($this->control) $flags |= REQUEST::CONTROL;

    $req = new REQUEST($tmp = array());
    $groups = $req->GetGroups($flags);
    
    foreach ($groups as $greq) {
	$list = $greq->GetItemList($flags);
	foreach ($list as $id => &$item) {
	    if (isset($item["uid"])) {
		$uid = $item["uid"];
		$res[$uid] = $greq->GetProps();
		$res[$uid]['db_mask'] = $id;
	    }
	}
    }
    
    return $res;
 }

 function UIDCreateTable() {
    $this->CreateTable('uids', UIDLocator::UIDS_TABLE_SPEC);
 }
 
 function UIDAppend($uid, array &$item) {
    global $ADEI_SETUP;
    
    $this->AppendRecord('uids', array(
	    $ADEI_SETUP,
	    $this->control?1:0,
	    $uid,
	    SOURCETree::PropsToItem($item),
	),
	UIDLocator::UIDS_TABLE_SPEC,
	array(3 => "item")
    );
 }
 
 function UIDDelete($uid) {
    global $ADEI_SETUP;
    
    $this->DeleteRecord('uids', array(
	'setup' => $ADEI_SETUP,
	'control' => $this->control?1:0,
	'uid' => $uid
    ));
 }
 
 function GetUIDs() {
    global $ADEI_SETUP;
    
    $list = $this->SelectRequest('uids', array('uid', 'item'), array("columns_equal" => array(
	'setup' => $ADEI_SETUP,
	'control' => $this->control?1:0
    )));
    
    $res = array();
    foreach ($list as $row) {
	$res[$row[0]] = SOURCETree::ItemToProps($row[1]);
    }
    
    return $res;
 }
 
 function GetItem($uid) {
    global $ADEI_SETUP;
    
    $list = $this->SelectRequest('uids', array('item'), array("columns_equal" => array(
	'setup' => $ADEI_SETUP,
	'control' => $this->control?1:0,
	'uid' => $uid
    )));
    
    if ($list) return SOURCETree::ItemToProps($list[0][0]);
    else {
	if (!$this->uid_array) $this->uid_array = $this->FindUIDs();
	if (isset($this->uid_array[$uid])) return $this->uid_array[$uid];
	return false;
    }
 } 
 
 function UpdateUIDs() {
    $uids = $this->FindUIDs();
    $db = $this->GetUIDs();
    
    foreach ($uids as $uid => $prop) {
	if (isset($db[$uid])) {
	    if (SOURCETree::PropsCmp($prop, $db[$uid])) {
	        $this->UIDAppend($uid, $prop);
	    }
	    unset($db[$uid]);
	} else {
	    $this->UIDAppend($uid, $prop);
	}
    }
    
    foreach ($db as $uid => &$value) {
	$this->UIDDelete($uid);
    }
 }
 
}



?>