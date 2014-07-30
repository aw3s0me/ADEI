<?php
$settings_title = _("Settings");

function settingsJS() {
?> 
    settings = new XMLMODULE("settings_div");
    settings.EnableJS();
    settings.SetModuleType(CONTROL_MODULE_TYPE);
    adei.RegisterCustomProperty("p_id","main");
    searcher = new SEARCH("settings","searchresults");
<?    
    return "settings";
}


function settingsPage() {
?>
    <div id="settings_div" class="xml_module">Loading...
    <div id="searchresults"></div>
    </div>
<?}?>