<?php

$default_precision = 120;
$i_multiplyer = 5;

function do_exit() {
    global $f1, $f2;

    if ($f1) fclose($f1);
    if ($f2) fclose($f2);
    exit;
}

function get_string($f, $exit = true) {
    $t = false;
    
    while ((!$t)&&(!feof($f)))
	$t = preg_replace("/\s+$/", "", fgets($f)); 

    if ((!$t)&&($exit)) do_exit();
    return $t;
}

function just_t1($t1) {
    global $size2;
    
    echo $t1;
    for ($i=0;$i<$size2;$i++) echo ", 0";
    echo "\r\n";
}	

if ((isset($_FILES))&&(isset($_FILES['file1']))&&(isset($_FILES['file2']))) {
    header("Content-type: application/binary"); 
    header("Content-Disposition: attachment; filename=\"merged.csv\"");
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

    if (isset($_POST['precision'])) {
	$precision = intval($_POST['precision']);
	if ($precision <= 0) $precision = $default_precision;
    } else $precision = $default_precision;
    $iprecision = $i_multiplyer * $precision;

    $f1 = fopen($_FILES['file1']['tmp_name'], "r");
    $f2 = fopen($_FILES['file2']['tmp_name'], "r");
    if ((!$f1)||(!$f2)) do_exit();

    $t1 = get_string($f1);    
    $t2 = get_string($f2);    

    $size1 = sizeof(split(",",$t1)) - 1;
    $size2 = sizeof(split(",",$t2)) - 1;

    $t2 = substr($t2, strpos(",", $t2) + 1);
    echo $t1 . $t2 . "\r\n";


    $t1 = get_string($f1);
    $d1 = split (",", $t1, 2); $time1 = strtotime($d1[0]);
	
		
    $t2 = get_string($f2);    
    $d2 = split (",", $t2, 2); $time2 = strtotime($d2[0]);
    $tpre = $time2; $pre = $d2[1]; 
    
    
    $cprecision = $precision;
    while (($t1)&&(($time2-$cprecision)>$time1)) {
	just_t1($t1); $cprecision = 0;
    
	$t1 = get_string($f1, false);
	if (!$t1) break;
	
	$d1 = split (",", $t1, 2); $time1 = strtotime($d1[0]);
    }
    
    
    while ($t1) {
	while (($f2)&&($time2 < $time1)) {
	    $tpre = $time2; $pre = $d2[1];
	
	    $t2 = preg_replace("/\s+$/", "", fgets($f2));
	    if (feof($f2)) {
		fclose($f2);
		$f2 = false;
	    }
	    if (!$t2) continue;
	    
	    $d2 = split (",", $t2, 2); $time2 = strtotime($d2[0]);
	}
    
	if ($time2 > $time1) {
	    if (($tpre + $iprecision) > $time1) 
		echo $t1 . ", " . $pre . "\r\n";
	    elseif (($time2 - $precision) < $time1)
		echo $t1 . ", " . $d2[1] . "\r\n"; /* = */
	    else
		just_t1($t1);
	}
	elseif (($t2)||(($time2 + $precision) > $time1))
	    echo $t1 . ", " . $d2[1] . "\r\n";
	else
	    just_t1($t1);
    
	$t1 = get_string($f1);
	if (!$t1) break;

	$d1 = split (",", $t1, 2); $time1 = strtotime($d1[0]);
    }

    fclose($f1);
    if ($f2) fclose($f2);

    exit;
}

header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

?>
<html>
<head>
</head>
<body><div><table>
<form action="csvmerge.php" enctype="multipart/form-data" method="POST">
  <tr><td>Master File (CSV): </td><td><input type="file" name="file1"/></td></tr>
  <tr><td>Complementary File (CSV): </td><td><input type="file" name="file2"/></td></tr>
  <tr><td>Precision (sec): </td><td><input name="precision" type="text" value="<?echo $default_precision;?>"/></td></tr>
  <tr><td colspan="2"><input type="submit" value="Merge"/></td></tr>
</form>
</table></div></body>
</html>
<?




?>