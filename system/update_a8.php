<?php

require("../adei.php");

$c = new CACHEDB();
$c->CreateStoredProcedures();
$list = $c->GetCacheList(REQUEST::NEED_ITEMINFO);

foreach ($list as &$info) {
    $table = "cache0{$info['postfix']}";
    $subseconds = $info['info']['ns'];
    echo $table . " - ";

    try {
	$query = "ALTER TABLE `$table` ADD `id` BIGINT NOT NULL FIRST";
	$c->Query($query);
	if ($subseconds) {
	    $query = "UPDATE `$table` SET id = UNIX_TIMESTAMP(`time`)*1000000000+`ns`";
	} else {
	    $query = "UPDATE `$table` SET id = UNIX_TIMESTAMP(`time`)*1000000000";
	}
	$c->Query($query);
	$query = "ALTER TABLE `$table` ADD UNIQUE KEY (id)";
	$c->Query($query);
#	if ($subseconds) {
#	    $query = "CREATE TRIGGER cache0_id BEFORE INSERT ON `$table` FOR EACH ROW SET NEW.id = UNIX_TIMESTAMP(NEW.time)*1000000000+NEW.ns";
#	} else {
#	    $query = "CREATE TRIGGER cache0_id BEFORE INSERT ON `$table` FOR EACH ROW SET NEW.id = UNIX_TIMESTAMP(NEW.time)*1000000000";
#	}
#	$c->Query($query);
	
	$done = true;
    } catch (ADEIException $ae) {
	$done = false;
	$error = $ae->getMessage();
    }

    if ($done) echo "OK\n";
    else echo "Failed: $error\n";
}





?>