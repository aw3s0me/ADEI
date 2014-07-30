<?php 
require("adei.php");

header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

?>
<html>
<head>
 <style type="text/css">
    .header {
    }
    .header a {
	padding: 5px;
	margin-bottom: 2px;
	color: #000055;
	background: #F0F0F0;
	font-weight: bold;
	border: 1px solid #C0C0C0;
	text-decoration: none;
    }
    .header a:hover {
	border: 1px #007000 solid; 
	background: #FDD;
    }
 </style>
</head>
<body>
<?


function GetQueryString(REQUEST $sreq) {
    global $ADEI_SETUP;
    
    $req = $sreq->GetQueryString();
    
    if ($req) $req .= "&";
    else $req = "";
	
    $req .= "setup=" . $ADEI_SETUP;

    return $req;
}

function data_link(&$req, $grname, $format, $from, $to) {
    global $FORMATS;
    
    $props = array();
    $props["experiment"] = "$from-$to";
    $props["window"] = "0";
    $props["format"] = $format;    

    if ($FORMATS[$format]["title"]) $title = $FORMATS[$format]["title"];
    else $title = $format;
    if ($FORMATS[$format]["extension"]) $ext = $FORMATS[$format]["extension"];
    else $ext = $format;

    
//    $props["filename"] =  preg_replace("/[^\w\d]/", "_", $grname) . "__" . round($from) . "_" . round($to) . "." . $ext;
    
#    if (isset($_GET['setup'])) $props["setup"] = $_GET['setup'];
    $query = $req->GetQueryString($props);
    
    echo "<a href=\"services/getdata.php?$query\">" . $title . "</a>";
}

if (isset($_GET['period'])) {
    $period = $_GET['period'] * 3600;
} else {
    $period = 86400;
}


try {
    $req = new SOURCERequest();
} catch (ADEIException $e) {
    $req = new REQUEST();
    $nodata = 1;
}


$list = $req->GetSources(REQUEST::LIST_ALL);
?><div class="header"><?
foreach ($list as $sreq) {
    ?><a href="download.php?<?echo GetQueryString($sreq);?>"><?echo $sreq->props['db_server'];?>.<?echo $sreq->props['db_name'];?></a> <?
}
?><a href="csvmerge.php">Merge Groups</a></div><?
if ($nodata) exit;


?><h3>Server: <?echo $req->props['db_server'];?>, DataBase: <?echo $req->props['db_name'];?></h3><?


//$props = array("db_server"=>"katrin", "db_name"=>"HS");
$reader = $req->CreateReader();
$groups = $reader->GetGroupList(REQUEST::NEED_INFO);

$list = $req->GetGroups();
foreach ($list as $gid => $greq) {
    $group = $groups[$gid];
    ?><div style="background-color: grey">
	<h3><?echo $group["name"];?></h3>
	<?echo $group["comment"];?>
	<br/><br/>
	<?
	$first = $group["first"];
	$last = $group["last"];
	
	$first = floor($first);
	$last = ceil($last);

	$first_day = $first - ($first % $period);
	if ($last%$period) $last_day = $last + $period - ($last % $period);
	else $last_day = $last - ($last % $period);


	for ($i=$first_day; $i<$last_day; $i += $period) {
	    echo date("d.m.Y", $i) . "(";

	    $first_flag = 1;
	    
	    foreach ($EXPORT_FORMATS as $format => $fattr) {
		if ($first_flag) $first_flag = 0;
		else  echo ", ";
		
		data_link($greq, $group["name"], $format, ($i>$first)?$i:$first, (($i + $period)>$last)?$last:($i + $period));
	    }
	    echo ") ";
	}
	

	echo "Everything (";
        $first_flag = 1;

	foreach ($EXPORT_FORMATS as $format => $fname) {
	    if ($first_flag) $first_flag = 0;
	    else  echo ", ";
		
	    data_link($greq, $group["name"], $format, $first, $last);
	}
	echo ") ";
    ?></div><br/><br/><?
}


?>
(*) the GMT days are used
</body></html>