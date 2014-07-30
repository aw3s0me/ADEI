<?php
$slowcontrol_title = _("Control");
function slowcontrolJS() {
?>
    slowcontrol = new CONTROL("slowcontrol_div");
<?
    return "slowcontrol";
}

function slowcontrolPage() {
?>
    <div id="slowcontrol_div" class="xml_module">Loading...</div>
<?}?>