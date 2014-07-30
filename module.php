<?php

foreach (array_merge($POPUPS, $MODULES) as $module) {
    if (file_exists("setups/$ADEI_SETUP/modules/$module.php")) require("setups/$ADEI_SETUP/modules/$module.php");
    else $inc = require("modules/$module.php");
}

function moduleLinkModules($list = false) {
    global $MODULES;
    global $config_module;

    $separator_flag = 0;

    foreach (($list===false)?$MODULES:$list as $module) {
	if ($separator_flag) echo " | ";
	else $separator_flag = 1;

	if (strcmp($config_module, $module)) $css = "module_link";
	else $css = "module_link_current";
		
	echo "<a class=\"$css\" id=\"module_link_$module\" href=\"javascript:adei.OpenModule('$module')\">";
		
	if (isset($GLOBALS[$module . "_title"])) echo $GLOBALS[$module . "_title"];
	else echo $module;
	echo "</a>";
    }
}

function moduleLinkPopups($list = false) {
    global $POPUPS;
    
    foreach (($list===false)?$POPUPS:$list as $module) {
	$text = implode("<br/>",str_split((isset($GLOBALS[$module . "_title"]))?$GLOBALS[$module . "_title"]:$module));
	?><div id="main_sidebar_<?echo $module;?>" class="sidebar"><table><tr style="height: 100%;">
	    <td class="holder" style="height: 100%;"><div class="popup" id="popup_<?echo $module;?>" style="display: none; height: 100%;">
		<?if (function_exists($module . "Page")) call_user_func($module . "Page");?>
	    </div></td>
	    <td class="switch" id="popup_switch_<?echo $module;?>">
		<button onclick="javascript:adei.SwitchPopup('<?echo $module;?>')"><table width="100%" height="100%"><tr><td><?echo $text;?></td></tr></table></button>
	    </td>
	</tr></table></div><?
    }
}

function modulePlacePages($list = false) {
    global $MODULES;
    global $POPUPS;
    global $config_module;
    
    foreach (($list===false)?$MODULES:$list as $module) {
	if (strcmp($config_module, $module)) $tmp_attr = " style=\"display: none;\"";
	else $tmp_attr = "";
    
	echo "<div class=\"module\" id=\"module_" . $module . "\"" . $tmp_attr . ">";
	if (function_exists($module . "Page")) call_user_func($module . "Page");
	echo "</div>";
    }
}


function modulePlaceJS($popup_list = false, $module_list = false) {
    global $MODULES;
    global $POPUPS;

    foreach (($popup_list===false)?$POPUPS:$popup_list as $module) {
	if (function_exists($module . "JS")) call_user_func($module . "JS");
    }

    foreach (($module_list===false)?$MODULES:$module_list as $module) {
	if (function_exists($module . "JS")) {
	    $module_class = call_user_func($module . "JS");
?>
	    adei.RegisterModule("<?echo $module;?>", <?echo $module_class?$module_class:"null";?>);
    <?
	}
    }
}


function moduleAdjustGeometry($width_var, $height_var) {
    global $MODULES;
    foreach ($MODULES as $module) {
?>
    adei.UpdateModuleGeometry("<?echo $module;?>", <?echo $width_var;?>, <?echo $height_var;?>);
<?
    }
}

function moduleSetupDragger() {
    global $POPUPS;

    foreach ($POPUPS as $module) {
?>
        {
            var node = document.getElementById("popup_<?echo $module;?>");
            var dragger = new DIALOG_DRAGGER(node);
            dragger.ControlMinimumSize(adei.popup.popups_width, 0);     // It is actually not scalled to real size
            dragger.RegisterCallbacks(null, null, UpdatePopupGeometry, "<?echo $module;?>");
            dragger.Disable(true, "nsw");
            dragger.Setup();
        }
<?
    }
}


?>