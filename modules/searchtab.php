<?php

$searchtab_title = _("Search");

function searchtabJS() {
?>
    search = new SEARCH("searchtab", "search_div");
    adei.RegisterSearchEngine(search);
<?
}

function searchtabPage() {
?>
    <div class="search" id="search_div"></div>
<?}?>