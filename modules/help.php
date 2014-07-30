<?php

$help_title = _("Help");

function helpPage() {
    echo "<h1>Advanced Data Extraction Inftanstracture</h1>";
    if (file_exists("VERSION")) {
	$stat = stat("VERSION");
	$date = date("r", $stat['mtime']);
	echo "<h2>Version: " . file_get_contents("VERSION") .  "</h2>";
	echo "<h3>Date: $date</h3>";
    }
?>
<?}?>