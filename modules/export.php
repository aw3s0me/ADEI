<?php
$export_title = _("Export");

function exportJS() {?>
    data_export = new EXPORT("export_format_sel", "export_resample_sel", "export_mask_sel", "export_window_sel");
    adei.AttachExportModule(data_export);
<?}

function exportPage() {
    global $GRAPH_DEFAULT_HEIGHT;
    global $GRAPH_DEFAULT_WIDTH;

//  table rows with corresponding format option must have 2 CSS classes:
//      1. export_control
//      2. <type>_export_control - these will be shown when format of type <type> is selected, all other rows will be hidden
?>
<div class="module" id="module_export">
	<table class="select_table"><tr><?
	    echo "<td>" . _("Format") . "</td><td><select id=\"export_format_sel\" onchange='javascript:data_export.UpdateFormat(this.value)'><option>Loading...</option></select></td>";
	?></tr>
     <tr class="export_control image_export_control">
	    <td><?echo _("Resolution");?></td>
        <td nowrap="1">
            <input type="text" value="<?echo  $GRAPH_DEFAULT_WIDTH?>" id="export_resolutionx_inp" size="4" onchange="javascript:data_export.UpdateResolutionX(this.value)" />x<input type="text" value="<?echo  $GRAPH_DEFAULT_HEIGHT?>" id="export_resolutiony_inp" size="4" onchange="javascript:data_export.UpdateResolutionY(this.value)"/>
        </td>
	</tr>
     <tr class="export_control data_export_control"><?
	    echo "<td>" . _("Resample") . "</td><td><select id=\"export_resample_sel\" onchange='javascript:data_export.UpdateSampling(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr class="export_control data_export_control"><?
	    echo "<td>" . _("Items") . "</td><td><select id=\"export_mask_sel\" onchange='javascript:data_export.UpdateMask(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr class="export_control data_export_control"><?
	    echo "<td>" . _("Window") . "</td><td><select id=\"export_window_sel\" onchange='javascript:data_export.UpdateWindow(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr class="export_apply">
	    <td colspan="2"><button id="export_button" type="button" onclick="javascript:data_export.Export()"><?echo translate("Export");?></button></td>
	</tr>
    </table>
</div>
<?
}?>
