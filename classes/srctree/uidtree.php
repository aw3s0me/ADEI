<?php
include_once($GLOBALS['ADEI_ROOTDIR'] . "classes/uidlocator.php");

class UIDTree {
 var $locator;
 var $uids;
 var $delim;
 
 function __construct(REQUEST $req = NULL, $opts) {
    $this->locator = new UIDLocator();
    $this->uids = $this->locator->GetUIDs();
    
    $this->delim = '/';
    if (is_array($opts)) {
        if (isset($opts['delimiter'])) $this->delim = $opts['delimiter'];
    }
 }
 
 function GetBranches($pos) {
    $uids = &$this->uids;
    
    if ($pos) {
        $add = $pos;
        $pos = preg_replace("/__/", $this->delim, $pos);
    }
    $len = strlen($pos);
    
    $res = array();
    $childs = array();
    foreach ($uids as $uid => &$info) {
        if (($len)&&(strncmp($uid, $pos, $len))) continue;
        if ($len) $prefix = explode($this->delim, substr($uid, $len + 1), 2);
        else $prefix = explode($this->delim, $uid, 2);
        array_push($res, $prefix[0]);
        if ($prefix[1]) $childs[$prefix[0]] = 1;
    }
    
    $list = array();
    foreach (array_unique($res) as $prefix) {
        if ($pos) $id = "{$add}__{$prefix}";
        else $id = $prefix;
        $list[$id] = array(
            'name' => $prefix,
            'child' => $childs[$prefix]
        );
    }

    return $list;
 }
 
 function Parse($pos) {
    $uids = &$this->uids;

    if ($pos) $pos = preg_replace("/__/", $this->delim, $pos);
    $len = strlen($pos);

    $res = array();
    foreach ($uids as $uid => &$info) {
        if (($len)&&(strncmp($uid, $pos, $len))) continue;
        array_push($res, $info['id']);
    }
    return $res;
 }

}
?>