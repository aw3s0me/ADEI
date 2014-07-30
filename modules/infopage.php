<?php
$infopage_title = _("Secondary");
function infopageJS() {
?>
    infopage = new XMLMODULE("infopage_div");
    if (typeof infotab != "undefined") infotab.RegisterPage("infopage");
<?
    return "infopage";
}

function infopagePage() {
?>
    <div id="infopage_div" class="infopage">Loading...</div>
<?}?>