<?php
$download_title = _("Downloads");
function downloadJS() {
?>		
    download = new XMLMODULE("download_div");
    download.EnableJS();
    dlmanager = new DLMANAGER();
    tooltip = new TOOLTIP("preview");
    tooltipdet = new TOOLTIP("details");
<?
    return "download";
}

function downloadPage() {
?>   
    <div id="download_div" class="xml_module" >Loading...</div>     
    <div id="preview" onclick="javascript:tooltip.Hide()" style="width: 512px; height: 384px; border: solid 1px gray; position: absolute; visibility:hidden; background-color:#FFFFFF;"></div>
    <div id="details" onclick="javascript:tooltipdet.Hide()" style="width: 512px; height: 384px; border: solid 2px gray; position: absolute; visibility:hidden; background-color:#FFFFFF; overflow: auto;"></div> 
<?}?>