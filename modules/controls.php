<?php
$controls_title = _("Controls");

foreach ($CONTROLS as $module) {
    if ($module) {
	if (file_exists("setups/$ADEI_SETUP/modules/$module.php")) require("setups/$ADEI_SETUP/modules/$module.php");
	else require("modules/" . $module . ".php");
    }
}

function controlsJS() {
    global $CONTROLS;
?>
    control_modules = new MODULE("controls");
    control_modules.RegisterGeometryCallback(popupUpdateGeometryCallback, { 'popup': adei.popup, 'module': "controls" });
    control_modules.DisableHeightAdjustments();
    control_modules.Open("<?echo $CONTROLS[0];?>");

    adei.popup.RegisterControlsModule("controls", control_modules);
    adei.popup.RegisterOnCallback("controls", moduleUpdateGeometry, control_modules);
    adei.RegisterSuperPopup("controls", "module_controls_all");

<?

    foreach ($CONTROLS as $module) {
	if (($module)&&(function_exists($module . "JS"))) 
	    call_user_func($module . "JS");
?>
	adei.popup.RegisterControl("controls", "<?=$module?>");
<?
    }
}

function controlsPutLink($css, $module) {
    echo "<td class=\"$css\" id=\"module_link_controls_$module\"><a href=\"javascript:control_modules.Open('$module')\">";
    if (isset($GLOBALS[$module . "_title"])) echo $GLOBALS[$module . "_title"];
    else echo $module;
    echo "</a></td>";
}

function controlsPage() {
    global $CONTROLS;

    $css = "module_controls_link"; 

?><table width="100%"><tr><?
    $pos = 0;
    foreach ($CONTROLS as $module) {
	if (!$module) {
	    ?></tr></table><table width="100%"><tr><?
	    continue;
	}
	controlsPutLink($css, $module);
    }
?></tr></table><div id="module_controls_all" class="controls" style="width: 100%;"><?
    foreach ($CONTROLS as $module) {
	if (!$module) {
	    continue;
	}

	?><div id="module_controls_<?echo $module;?>" class="module" style="display: none">
	    <?if (function_exists($module . "Page")) call_user_func($module . "Page");?>
	</div><?
    }
?></div><?
}

?>