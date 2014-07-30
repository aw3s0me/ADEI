<?php
$virtual_title = _("Source Tree");

function virtualJS() {
?>
    function virtual_moduleSetDisplayInfo(v, module_name) {
	if (module_name == "virtual") {
	    v.UpdateTreeGeometry();
	}
    }

    function virtual_popupSetDisplayInfo(v, status) {
	if (status) {
	    virtual_moduleSetDisplayInfo(v, control_modules.current_module);
	} 
    }
    
    function virtual_geometry(v) {
	virtual_popupSetDisplayInfo(v, adei.popup.popup_states["controls"]);
    }

    virtual_control = new VIRTUAL("virtual_div", "virtual", adei.superpopup_geometry_node);
    adei.RegisterVirtualControl(virtual_control);
    
    if (adei.popup) {
	adei.popup.RegisterOffCallback("controls", virtual_popupSetDisplayInfo, virtual_control);
	adei.popup.RegisterOnCallback("controls", virtual_popupSetDisplayInfo, virtual_control);
	adei.popup.RegisterReHeightCallback(virtual_geometry, virtual_control);
    }
    control_modules.RegisterCallback(virtual_moduleSetDisplayInfo, virtual_control);
<?
    return "virtual_control";
}

function virtualPage() {
?>
 <div>
    <?/*<button id="virtual_apply_button" type="button" onclick="javascript:adei.virtual.Apply()"><?echo translate("Apply");?></button>*/?>
    <div><div id="virtual_div"></div></div>
 </div>
<?}?>