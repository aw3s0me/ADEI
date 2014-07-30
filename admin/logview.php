<?php
$cur = time();

$interval = $_REQUEST['interval'];
$filter = json_decode(stripslashes($_REQUEST['filter']), true);

if (isset($_REQUEST['priority'])) {
    $priority = $_REQUEST['priority'];
} else {
    $priority = false;//LOG_WARNING;
}


if (preg_match("/^(\d+)-(\d+)$/", $interval, $m)) {
    $from = $m[1];
    $to = $m[2];
    
    if ($to > $cur) $to = $cur;
    
    if ($to < $from) {
	$from = false;
	$to = false;
    }
}

if ((!$from)||(!$to)) {
    $to = $cur;
    $from = $to - $to%86400;
} 


function getLogURL() {
    global $priority;
    global $filter;
    $res = "index.php?page=logview.php";
    if ($priority) $res .= "&priority=$priority";
    if ($filter) $res .= "&filter=" . $_REQUEST['filter'];
    return $res;
}

?>
<script type="text/javascript" src="../includes/datetimepicker.js"></script>
<script type="text/javascript" src="../js/datetime.js"></script>
<script type="text/javascript" src="../js/tools.js"></script>

<script type="text/javascript">
//<![CDATA[

    function selectDate(sel) {
        var istart, iend;
	if (sel) {
    	    istart = adeiDateParse(sel);
/*	    var new_date = new Date();
	    new_date.setTime(Date.parse(sel));
	    var istart = new_date.getTime()/1000;*/
	    iend = istart + 86400;
	}
	window.location = "<?echo getLogURL();?>&interval=" + istart + "-" + iend;
    }
//]]>
</script>

<br/><a href="javascript:NewCal(null,'mmmddyyyy',false,24, selectDate)">
    <img src="../images/cal.png"/>
</a>
<?

function ShowTime($tm) {
    return $tm->format("Y-m-d\Th:i:s.uP");
}

function Filter(&$log, $add, $value=false) {
    global $interval;
    global $filter;

    $nfilter = $filter;    
    if ($value !== false) $nfilter[$add] = $value;
    else $nfilter[$add] = $log[$add];
    return "index.php?page=logview.php&interval=" . $interval . "&filter=" . urlencode(json_encode($nfilter));
}


echo translate("ADEI Logs for: ") . date("c", $from) . " - " . date("c", $to) . "<br/><br/>";

$logs = adeiGetLogs($from, $to, $priority, $filter?$filter:false);

?><table class="logtable"><tr>
    <th><?echo translate("Time");?></th>
    <th><?echo translate("Setup");?></th>
    <th><?echo translate("Server");?></th>
    <th><?echo translate("Source");?></th>
    <th><?echo translate("Session");?></th>
    <th><?echo translate("PID");?></th>
    <th><?echo translate("Client");?></th>
    <th><?echo translate("Priority");?></th>
</tr></th><?
foreach($logs as $log) {
    $info = adeiGetLogInfo($log['logfile'], $log['filepos']);
    if ($filter) {
	if (($filter["db_server"])&&($info['GET']['db_server'] != $filter["db_server"])) continue;
    }
    echo "<tr>";
    echo "<td>" . ShowTime($log['time']) . "</td>";
    echo "<td><a href=\"" . Filter($log, 'setup') . "\">" . $log['setup'] . "</a></td>";
    echo "<td><a href=\"" . Filter($log, 'db_server', $info['GET']['db_server']) . "\">" . $info['GET']['db_server'] . "</a></td>";
    echo "<td><a href=\"" . Filter($log, 'source') . "\">" . $log['source'] . "</a></td>";
    echo "<td><a href=\"" . Filter($log, 'session') . "\">" . $log['session'] . "</a></td>";
    echo "<td><a href=\"" . Filter($log, 'pid') . "\">" . $log['pid'] . "</a></td>";
    echo "<td><a href=\"" . Filter($log, 'client') . "\">" . $log['client'] . "</a></td>";
    echo "<td>" . $log['priority'] . "</td>";
    echo "<tr><td colspan=\"7\"><a href=\"index.php?page=loginfo.php&logfile=" . $log['logfile'] . "&logpos=" . $log['filepos']  . "\">" . $log['message'] . "</a></td></tr>";
    
    echo "</tr>";
//    print_r($log);
}
?></table><?



?>