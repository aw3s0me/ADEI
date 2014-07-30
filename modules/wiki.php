<?php
$wiki_title = _("WiKi");
function wikiJS() {
?>
    wiki = new WIKI("wiki_div");
<?
    return "wiki";
}

function wikiPage() {
?>
    <div id="wiki_div" class="wiki">Loading...</div>
<?}?>