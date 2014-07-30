<?php
$source_title = _("Data Source");

function sourceJS() {
    global $ADEI_MODE;
    global $config_options;
    global $SOURCE_KEEP_WINDOW;
    
?>
    function source_moduleSetDisplayInfo(s, module_name) {
	if (module_name == "time") {
	    s.interval.window.axes_on_display = false;
	    s.interval.window.module_on_display = true;
	    s.interval.module_on_display = true;
	    s.module_on_display = false;
	} else if (module_name == "axes") {
	    s.interval.window.axes_on_display = true;
	    s.interval.window.module_on_display = false;
	    s.interval.module_on_display = false;
	    s.module_on_display = false;
	} else /* source */ {
	    s.interval.window.axes_on_display = false;
	    s.interval.window.module_on_display = false;
	    s.interval.module_on_display = false;
	    s.module_on_display = true;
	}
	s.FixHidden();
    }

    function source_popupSetDisplayInfo(s, status) {
	if (status) {
	    source_moduleSetDisplayInfo(s, source_modules.current_module);
	} else {
	    s.interval.window.module_on_display = false;
	    s.interval.module_on_display = false;
	    s.module_on_display = false;
	}
    }
    
    function source_fixItemSelSize() {
	/* We defined a source_db_item_sel size in procents, to fill completely two table cells.
	However, this causes problems in Gecko while using adaptive SetWidth. The SELECT would
	be extend during first step, but not shrinked back on the second. */
	 
	var itemsel = document.getElementById("source_db_item_sel");
	domSetWidth(itemsel,itemsel.offsetWidth);
    }

    if ((!client.isIE())&&(!client.isKonqueror())) {
	    /* This problem only occurs in Gecko 1.8. The fix by iteself causes problems in IE (no
	    idea why). The Opera does not need the fix, but it not harmless as well */
        domShow("popup_source");
	source_fixItemSelSize();
	domHide("popup_source");
    }



    source_modules = new MODULE("source");
    if (adei.popup) source_modules.RegisterGeometryCallback(popupUpdateGeometryCallback, { 'popup': adei.popup, 'module': "source" });
    source_modules.Open("source");

    if (adei.popup) {
	adei.popup.RegisterOnCallback("source", moduleUpdateGeometry, source_modules);
	adei.popup.RegisterControlsModule("source", source_modules);
	adei.popup.RegisterControl("source", "source");
	adei.popup.RegisterControl("source", "time");
	adei.popup.RegisterControl("source", "axes");
    }
    

    var source_opts = new Object();
    <?if ($SOURCE_KEEP_WINDOW) {?> source_opts.keep_window = true; <?}?>

    source_window = new WINDOW("source_timewindow_sel", "source_winstart_inp", "source_winend_inp", "source_axes_table", "source_miny_inp", "source_maxy_inp", "source_logy_inp");
    source_interval = new INTERVAL(source_window, "source_db_experiment_sel", "source_timestart_inp", "source_timeend_inp");
    source = new SOURCE(source_interval, "source_db_server_sel", "source_db_name_sel", "source_db_group_sel","source_c_group_sel",  "source_db_mask_sel", "source_db_item_sel", "source_apply_button", source_opts);

    adei.AttachSourceModule(source, source_interval, source_window);
	
    if (adei.popup) {
        source.RegisterGeometryCallback(moduleUpdateGeometry, source_modules);
	if (isIE()||client.isKonqueror()) {
	    adei.popup.RegisterOffCallback("source", source_popupSetDisplayInfo, source);
	    adei.popup.RegisterOnCallback("source", source_popupSetDisplayInfo, source);
	    source_modules.RegisterCallback(source_moduleSetDisplayInfo, source);
	    source.FixHiddenIE();
	}
    }
<?
    if ($ADEI_MODE == "iAdei") {
?>
    settingmodule = new SETTINGS(source_window);
<?
    }
}

function sourcePage() {
    global $ZEUS_TIMINGS;
?>
<table width="100%"><tr>
    <th class="module_source_link_current" id="module_link_source_source"><a href="javascript:source_modules.Open('source')"><?echo _("Source");?></a></th>
    <th class="module_source_link" id="module_link_source_time"><a href="javascript:source_modules.Open('time')"><?echo _("Time");?></a></th>
    <th class="module_source_link" id="module_link_source_axes"><a href="javascript:source_modules.Open('axes')"><?echo _("Axes");?></a></th>
</tr><tr><td colspan="3">
 <div class="controls">
    <div class="module" id="module_source_dummy" style="display: none;"></div> <?/* Most of the browsers add some empty space, 
	if hidden divs are stacked before displayed one, this is added to make it work uniform among first and others. */?>
    <div class="module" id="module_source_source">
	<table class="select_table" width="100%"><tr><?
	    echo "<td>" . _("Server") . "</td><td><select id=\"source_db_server_sel\" onchange='javascript:source.UpdateServer(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr><?
	    echo "<td>" . _("Database") . "</td><td><select id=\"source_db_name_sel\" onchange='javascript:source.UpdateDatabase(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr><?
	    echo "<td><span class=\"hide_source_control\">" . _("Controls") . "</span></td><td><span class=\"hide_source_control\"><select id=\"source_c_group_sel\" onchange='javascript:source.UpdateCGroup(this.value)'><option>Loading...</option></select></span></td>";
	?></tr><tr><?
	    echo "<td><span class=\"hide_source_history\">" . _("LogGroup") . "</span></td><td><span class=\"hide_source_history\"><select id=\"source_db_group_sel\" onchange='javascript:source.UpdateGroup(this.value)'><option>Loading...</option></select></span></td>";
	?></tr><tr><?
	    echo "<td><span class=\"hide_source_history\">" . _("ItemMask") . "</span></td><td><span class=\"hide_source_history\"><select id=\"source_db_mask_sel\" onchange='javascript:source.UpdateMask(this.value)'><option>Loading...</option></select></span></td>";
	?></tr><tr id="source_db_item_tr"><?
	    echo "<td colspan=\"2\"><select id=\"source_db_item_sel\" onchange='javascript:source.UpdateItem(this.value)'><option>Loading...</option></select></td>";
	?></tr></table>
    </div>
    <div class="module" id="module_source_time" style="display: none;">
	<table class="select_table"><tr><?
	    echo "<td>" . _("Experiment") . "</td><td><select id=\"source_db_experiment_sel\" onchange='javascript:source_interval.UpdateExperiment(this.value)'><option>Loading...</option></select></td>";
	?></tr><tr>
	    <td><div class="hide_experiment_custom"><?echo _("Start");?></div></td>
	    <td id="source_timestart"><div class="hide_experiment_custom">
		<input type="text" id="source_timestart_inp" maxlength="25" onchange="javascript:source_window.UpdateRange()"/>
		<a href="javascript:NewCal('source_timestart_inp','mmmddyyyy',true,24,windowUpdateRangeFunction(source_window))">
		    <img src="images/cal.png"/>
		</a>
	    </div></td>
	</tr><tr>
	    <td><div class="hide_experiment_custom"><?echo _("End");?></div></td>
	    <td id="source_timeend"><div class="hide_experiment_custom">
		<input type="text" id="source_timeend_inp" maxlength="25" onchange="javascript:source_window.UpdateRange()"/>
		<a href="javascript:NewCal('source_timeend_inp','mmmddyyyy',true,24,windowUpdateRangeFunction(source_window))">
		    <img src="images/cal.png"/>
		</a>
	    </div></td>
	</tr><tr>
	    <td><?echo _("Window");?></td><td><select id="source_timewindow_sel" onchange='javascript:source_window.UpdateWidth(this.value)'><option>Loading...</option></select></td>
	</tr><tr>
	    <td><div class="hide_window_custom"><?echo _("Start");?></div></td>
	    <td id="source_winstart"><div class="hide_window_custom">
		<input type="text" id="source_winstart_inp" maxlength="31" onchange="javascript:source_window.UpdateRange()"/>
		<a href="javascript:NewCal('source_winstart_inp','mmmddyyyy',true,24,windowUpdateRangeFunction(source_window))">
		    <img src="images/cal.png"/>
		</a>
	    </div></td>
	</tr><tr>
	    <td><div class="hide_window_custom"><?echo _("End");?></div></td>
	    <td id="source_winend"><div class="hide_window_custom">
		<input type="text" id="source_winend_inp" maxlength="31" onchange="javascript:source_window.UpdateRange()" />
		<a href="javascript:NewCal('source_winend_inp','mmmddyyyy',true,24,windowUpdateRangeFunction(source_window));">
		    <img src="images/cal.png"/>
		</a>
	    </div></td>
	</tr></table>
    </div>
    <div class="module" id="module_source_axes" style="display: none;">
	<table id="source_axes_table" class="select_table"><tr>
	    <td><div><span class="axis_name"><?echo _("Y");?></span> [<a href='javascript:source_window.ResetY("0")'><?echo _("R");?></a>]</div></td>
	    <td><div><span class="source_yrange">
		<input type="text" id="source_miny_inp" maxlength="16" onchange="javascript:source_window.UpdateRange()"/>
	    </span></div></td>
	    <td><div><span class="source_yrange">&nbsp;-&nbsp;</span></div></td>
	    <td><div><span class="source_yrange">
		<input type="text" id="source_maxy_inp" maxlength="16" onchange="javascript:source_window.UpdateRange()"/>
	    </span></div></td>
	    <td><div><span class="source_ymode">
		<input type="checkbox" id="source_logy_inp" onchange="javascript:source_window.UpdateRange()"/>
	    </span></div></td><td><div>Log</div></td>
	<?/*</tr><tr>
	    <td colspan="2"><div id="source_yopts">
		<input type="text" id="source_y_units" maxlength="16" onchange="javascript:source_window.UpdateYUnits()"/>
		-
		<input type="text" id="source_y_name" maxlength="16" onchange="javascript:source_window.UpdateYRange()"/>
	    </div></td>
	*/?></tr>
	</table>
    </div>
 </div>
</td></tr><tr class="source_apply"><td colspan="3">
    <button id="source_apply_button" type="button" onclick="javascript:source.Apply()"><?echo translate("Apply");?></button>
</td></tr></table>
<?}?>