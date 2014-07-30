<?php
header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

	
//Commented lines are missing properties still to be added.

	$return = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><result>";

	if(isset($_GET['p_id'])){
		$page = $_GET['p_id'];
	 	switch($page){
			case "main":				
				$return .= "<history></history>";
				$return .= "<heading>Main</heading>";
				$return .= "<page>p_id=main</page>";
				$return .= "<value name='Data Source' page='p_id=sourcefront'/>";		
				$return .= "<value name='Controls' page='p_id=controls'/>";		
				//$return .= "<value name='Current' page='p_id=sourcefront'/>";		
				break;
			case "controls":
				$return .= "<history>p_id=main</history>";
				$return .= "<heading>Controls</heading>";
				$return .= "<page>p_id=controls</page>";
				//$return .= "<value name='Export' page='p_id=controlsexp'/>";
				$return .= "<value name='Aggregator' page='p_id=controlsaggr'/>";
				$return .= "<value name='Search' page='p_id=controlssearch'/>";
				break;
			case "controlssearch":
				$return .= "<history>p_id=controls</history>";
				$return .= "<heading>Search</heading>";
				$return .= "<page>controlssearch</page>";
				break;
			case "sourcefront":
				$return .= "<history>p_id=main</history>";
				$return .= "<heading>Data Source</heading>";
				$return .= "<page>p_id=sourcefront</page>";
				$return .= "<value name='Source' page='p_id=sourceselect'/>";
				$return .= "<value name='Time' page='p_id=sourcetime'/>";
			//	$return .= "<value name='Axes' page='p_id=sourceaxes'/>";
				break;
			case "sourcetime":
				$return .= "<history>p_id=sourcefront</history>";
				$return .= "<heading>Time</heading>";
				$return .= "<page>p_id=sourcetime</page>";
				$return .= "<value name='Custom' page='p_id=sourcetimecustom'/>";
				$return .= "<value name='Window' page='p_id=sourcetimewindow'/>";
				break;
			case "sourcetimecustom":
				$return .= "<history>p_id=sourcetime</history>";
				$return .= "<heading>CustomTime</heading>";
				$return .= "<page>p_id=sourcetimecustom</page>";
				break;
			case "sourcetimewindow":
				$return .= "<history>p_id=sourcetime</history>";
				$return .="<heading>Window</heading>";
				$return .= "<page>p_id=sourcetimewindow</page>";
				$return .= "<value window='window=31536000' name='1 Year' />";
				$return .= "<value window='window=2592000' name='1 Month' />";
				$return .= "<value window='window=604800' name='1 Week' />";
				$return .= "<value window='window=86400' name='1 Day' />";
				$return .= "<value window='window=3600' name='1 Hour' />";
				break;
			default: 	
				$return .= "<history>p_id=main</history>";
				$return .= "<heading>ERROR</heading>";
	  			$return .= "<value name='Invalid pageid' page = 'p_id=main'/>";						
				break;
		}
	} else {
		$return .= "<history></history>";
		$return .= "<heading>Main</heading>";
		$return .= "<value name='Data Source' page='p_id=sourcefront'/>";		
		$return .= "<value name='Controls' page='p_id=controls'/>";		
		$return .= "<value name='Current' page='p_id=sourcefront'/>";	
	}
	$return .= "</result>";
	echo $return;
?>