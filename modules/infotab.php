<?php

$infotab_title = _("Info");

function infotabJS() {
?>
    infotab = new INFOTAB("infotab", "infotab_mod_sel", "infotab_controls_div", "infotab_div")
<?
    return "infotab";
}

function infotabPage() {
    echo _("Type") . ": <select id=\"infotab_mod_sel\" onchange='javascript:infotab.UpdateModule(this.value)'><option>Select Source...</option></select>";
?>
    <button id="infotab_reload_button" onclick="infotab.Update()">&nbsp;</button>
    <br/>
    <div class="infotab" id="infotab_controls_div"></div>
    <div class="infotab" id="infotab_div"></div>
<?}?>