<?php


header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");



switch($_GET['target']) {
    case "parse_date":
	if ($_GET['date']) $date = $_GET['date'];
	else $error = translate("The date is not specified");
	if ($_GET['timezone']) $tz = $_GET['timezone'];
	else $tz = "UTC";

	try {	
	    $timezone = new DateTimeZone($tz);	
	    
	    if (preg_match("/^(19|20)\d{2}$/", $date)) {
		$date = $date . "-01-01";
	    }
	    
	    $dt = new DateTime($date, $timezone);
	    
	    $subseconds = $dt->format("u");
	    if (($subseconds)&&(!preg_match("/^0+$/", $subseconds))) {
		$result = $dt->format("U.u");
	    } else {
		$result = $dt->format("U");
	    }
	} catch (Exception $ae) {
	    $error = $ae->getMessage();
	}
    break;
    default:
	$error = translate("Unknown tool service (%s) is requested", $_GET['target']);
}


echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
if ($error) echo "<error>" . xml_escape($error) . "</error>";
else echo "<result>" . $result . "</result>";

?>