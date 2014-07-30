<?php

$graph_title = _("Graph");

function graphJS() {
    global $ADEI_MODE;
    global $GRAPH_MARGINS;
    global $GRAPH_SELECTION;
	
?>
    zeus_graph = new GRAPH("graph_image_div", "graph_selector_div");
    zeus_graph.SetMargins(<?echo $GRAPH_MARGINS['left'] . "," . $GRAPH_MARGINS['top'] . "," . $GRAPH_MARGINS['right'] . "," .$GRAPH_MARGINS['bottom']?>);
    zeus_graph.SetAllSizes(<?echo $GRAPH_SELECTION['min_width'] . "," . $GRAPH_SELECTION['min_height']?>);
    adei.AttachGraphModule(zeus_graph);
<?
    if ($ADEI_MODE == "iAdei") {
?>    
    graph_control = new GRAPHCONTROL("shadow","show_controls_div","imgdiv",zeus_graph,"graphimg","emailform","sensorlist");
		
<?
    }
    return "zeus_graph";
}


function graphPage() {
    global $ADEI_MODE;
    
/*    <div id="graph_selector_div" class="selector">selector1</div>*/
?>
<table width="100%" <?/*height="100%"*/?> cellspacing="0" cellpadding="0"><tr><td>
</td></tr><tr><td>
    <div id="graph_image_div">
	<img id="graph_image" alt="Loading..."/>
	<?/* onMouseDown="zeus_graph.MouseStart(event)" onMouseUp="zeus_graph.MouseDone(event)" onMouseMove="zeus_graph.MouseVisualize(event)" onClick="zeus_graph.MouseStart()"/>*/?>
    </div>
<?
    if ($ADEI_MODE == "iAdei") {
?>
	<div class="show_controls_div" id="show_controls_div">
	</div>
	<div id="shadow">
		<table>
			<tr><td></td><td><button onclick="graph_control.UseWindow('moveup');">Move Up</button></td><td></td></tr>
			<tr><td><button onclick="graph_control.UseWindow('moveleft');">Move Left</button></td><td><button onclick="graph_control.UseWindow('movedown');">Move Down</button></td><td><button onclick="graph_control.UseWindow('moveright');">Move Right</button></td></tr>
		</table>
		<table>
		 	<tr><td><button onclick="graph_control.UseWindow('centerzoomin');">Zoom in</button></td></tr>
		 	<tr><td><button onclick="graph_control.UseWindow('centerzoomout');">Zoom out</button></td></tr>
			<tr><td><button onclick="graph_control.genIMG();">Send Graph as email </button></td></tr>
			<tr><td><button onclick="graph_control.getSensors();">Show Legend </button></td></tr>
		</table>
		<button onclick="adei.updater.Update();">Reload graph </button>
	</div>
	<div id="imgdiv">
		<button onclick="graph_control.openMailForm();">Send as email</button>
		<button onclick="graph_control.closediv('imgdiv');">Close</button>
		<img src="images/blank.png" id="graphimg"/>
	</div>
	<div id="emailform">
	</div>
	<div id="sensorlist">
	</div>
<?
    }
?>
</td></tr></table>

<?}?>