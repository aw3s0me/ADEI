<?php

$res = exec('ps xa | grep "cache_uid.php" | grep -v grep | wc -l');
if ($res > 1) exit;

$curdir = getcwd();
if (preg_match("/(.*)cache_uid.php$/", $_SERVER['SCRIPT_FILENAME'], $m)) @chdir($m[1]);

require("../adei.php");

function Update($control) {
    $locator = new UIDLocator($control);
    $locator->UpdateUIDs();
}


try {
    Update(false);
    Update(true);
} catch(ADEIException $e) {
    $e->logInfo(translate("Problem processing uids"));
    echo translate("Error: %s", $e->getInfo());
}
?>