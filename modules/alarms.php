<?php
$alarms_title = _("Alarms");
function alarmsJS() {
?>
    alarms = new XMLMODULE("alarms_div");
    alarms.SetModuleType(CONTROL_MODULE_TYPE);
<?
    return "alarms";
}

function alarmsPage() {
?>
    <div id="alarms_div" class="xml_module">Loading...</div>
<?}?>