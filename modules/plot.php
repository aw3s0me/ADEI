<?php
$plot_title = _("Plot");

function plotJS() {?>

    plot = new PLOT("plot_sel");
    adei.AttachPlotModule(plot);
<?}

function plotPage() {
?>
<div class="module" id="module_plot">
	<table class="select_table">
	    <tr><?
		echo "<td>" . _("Plot Type") . "</td><td><select id=\"plot_sel\" onchange=\"javascript:plot.UpdatePlotMode(this.value)\"><option>Loading...</option></select></td>";
	    ?></tr>
	    <tr class="export_apply">
		<td colspan="2"><button id="export_button" type="button" onclick="javascript:plot.Apply()"><?echo translate("Apply");?></button></td>
	    </tr>
	</table>
</div>
<?
}?>