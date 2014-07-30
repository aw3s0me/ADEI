<?php
require("../adei.php");

header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");


/*
$_GET = array(
    "page"=> "cacheinfo.php",
    "table_info" => true,
    "item_info" => true,
);
*/

?>
<html>
<head>
  <link rel="stylesheet" type="text/css" href="admin.css"/> 
  
</head>
<body>
 <div class="header">
    <a href="index.php?page=cacheinfo.php&source_info"><?echo translate("Overview");?></a>
    <a href="index.php?page=cacheinfo.php&source_info&group_info&table_info"><?echo translate("Cache Info");?></a>
    <a href="index.php?page=cacheinfo.php&source_info&group_info&table_info&item_info"><?echo translate("Extended Cache Info");?></a>
    <a href="index.php?page=logview.php&priority=<?echo LOG_WARNING;?>"><?echo translate("Log Viewer");?></a>
    <a href="index.php?page=logview.php"><?echo translate("Debug Viewer");?></a>
     <a href="index.php?page=downloadsview.php"><?echo translate("Downloads");?></a>
 </div>
<?

    $page = $_GET['page'];
    if (($page)&&(preg_match("/^[\w\d_.]+\.php$/", $page))&&(is_file("admin/$page"))) {
	require("admin/$page");
    }
?></body></html>