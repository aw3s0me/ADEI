<?php
require("../adei.php");

function format2mime($fmt) {
    return "mime/" . strtolower($fmt);
}


$logfile = $_REQUEST['logfile'];
$logpos = $_REQUEST['logpos'];

$info = adeiGetLogInfo($logfile, $logpos);

if ($info['result']) {
    if (strstr($info['source'], "getimage")) {
        $data = pack("H*", $info['result']);

	$image = new Imagick();
	if ($image) {
	    $image->readImageBlob($data);
	    $format = $image->getImageFormat();
	    $mime = format2mime($format);
	} else {
	    $mime = "image/png";
	}

	header("Content-type: $mime");
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

	echo $data;	
    }
}
?>