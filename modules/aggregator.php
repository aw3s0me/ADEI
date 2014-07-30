<?php
$aggregator_title = _("Aggregator");

function aggregatorJS() {?>
    data_aggregator = new AGGREGATOR("aggregator_sel", "interpolation_sel", "show_marks_sel", "show_gaps_sel");
    adei.AttachAggregationModule(data_aggregator);
<?}

function aggregatorPage() {
?>
<div class="module" id="module_aggregator">
	<table class="select_table"><tr><?
	    echo "<td>" . _("Approach") . "</td><td><select id=\"aggregator_sel\" onchange='javascript:data_aggregator.UpdateAggregator(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr><?
	    echo "<td>" . _("Interpolation") . "</td><td><select id=\"interpolation_sel\" onchange='javascript:data_aggregator.UpdateInterpolation(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr><?
	    echo "<td>" . _("Show Marks") . "</td><td><select id=\"show_marks_sel\" onchange='javascript:data_aggregator.UpdateMarks(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr><?
	    echo "<td>" . _("Show Gaps") . "</td><td><select id=\"show_gaps_sel\" onchange='javascript:data_aggregator.UpdateGaps(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr class="export_apply">
	    <td colspan="2"><button id="export_button" type="button" onclick="javascript:data_aggregator.Apply()"><?echo translate("Apply");?></button></td>
	</tr></table>
</div>
<?
}?>