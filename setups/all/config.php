<?php
    $ALL_EXPORT_FORMATS = $EXPORT_FORMATS;
    $ALL_MODULES = $MODULES;
    $ALL_POPUPS = $POPUPS;
    $ALL_CONTROLS = $CONTROLS;

    $dh = opendir("setups/");
    while (($subdir = readdir($dh)) !== false) {
        if (!is_dir("setups/$subdir")||($subdir == $ADEI_SETUP)||(!strncmp($subdir,".",1))) continue;
	
	if (is_file("setups/$subdir/config.php")&&(!is_file("setups/$subdir/.exclude")))
    	    include("setups/$subdir/config.php");
    }
    
    $TITLE = "ADEI";
    $MODULES = $ALL_MODULES;
    $POPUPS = $ALL_POPUPS;
    $CONTROLS = $ALL_CONTROLS;
    $EXPORT_FORMATS = $ALL_EXPORT_FORMATS;
    $ADEI_SRCTREE_EXTRA = array();

    $SEARCH_ENGINES = array (
	"ITEMSearch" => array(),
	"INTERVALSearch" => array(),
	"PROXYSearch" => array()
    );
?>