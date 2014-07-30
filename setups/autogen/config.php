<?php
    $TITLE = "ADEI";
    $MODULES = array("config", "download", "graph", "wiki");

    $READER_DB_TEST = array (
	"autogen" => array (
	    "title" => _("Slow Generator"),
	    "reader" => "TESTReader",
	    "database" => array("hourly", "minutely")
	),
	"fastgen" => array (
	    "title" => _("Fast Generator"),
	    "reader" => "TESTReader",
	    "database" => array("10hz", "kHz")
	)
    );


    if (is_array($READER_DB)) $READER_DB = array_merge($READER_DB_TEST, $READER_DB);
    else $READER_DB = $READER_DB_TEST;

    function autogen_start_of_month() {
	$cur = time();
	return  $cur - ((date('j', $cur) - 1) * 86400 + $cur % 86400);
    }


    $test_items = array(
	array(
	    "name" => _("Noisy Line"),
	    "func" => '$y=-8',
	    "noise" => 0.5
	),
	array(
	    "name" => _("Cosinus"),
	    "func" => '$y=cos(deg2rad($x/$period))'
	),
	array(
	    "name" => _("Noisy Cosinus"),
	    "func" => '$y=cos(deg2rad($x/$period))',
	    "noise" => 0.1
	),
	array(
	    "name" => _("Sinus"),
	    "func" => '$y=sin(deg2rad($x/$period))'
	),
	array(
	    "name" => _("Wide Sinus"),
	    "func" => '$y=5*sin(deg2rad($x/86400))'
	),
	array(
	    "name" => _("Combined Sinuses"),
	    "func" => '$y=8 + 5*sin(deg2rad($x/86400)) + sin(deg2rad($x/$period))/3'
	),
	array(
	    "name" => _("Fading Sinus"),
	    "func" => '
		$scale = 345600;
		$coef = 90 * $scale;
		$tmp=((($x/$coef)%2)?($x%$coef):($coef-1-$x%$coef))/$coef;
		$sin=(3600*(sin(deg2rad(270 + (($coef + $x)%(2*$coef)/$scale)))));
		$y=(3 + 12*$tmp)*sin(deg2rad($sin))
	    ',
	    'uid' => 'AG1'
	)
/*	,
	array(
	    "name" => _("Test"),
	    "func" => '$y=-8 + 5*sin(deg2rad($x/86400)) + sin(deg2rad($x/$period))/3'
	),*/
    );
    
    $extra_items = array(
	array(
	    "name" => _("Extra Sinus"),
	    "func" => '$y=0.25*sin(deg2rad(($x + $period/4)/$period))'
	),
	array(
	    "name" => _("Extra Cosinus"),
	    "func" => '$y=0.75*cos(deg2rad(($x + $period/4)/$period))'
	)
    );

    $fast_items = array(
	array(
	    "name" => _("Noisy Cosinus"),
	    "func" => '$y=cos(deg2rad($x/300))',
	    "noise" => 0.1
	),
	array(
	    "name" => _("Combined Cosinus"),
	    "func" => '$y=cos(deg2rad($x/300)) +  cos(deg2rad(2*$x))'
	),
	array(
	    "name" => _("Combined Sinus"),
	    "func" => '$y=5*sin(deg2rad($x/300)) + sin(deg2rad($x/$period))/3'
	)
    );



    $OPTIONS = array_merge ($OPTIONS, array(
	"autogen" => array(
	    "optimize_empty_cache" => true,
	    "cache_config" => array (
		array("min" => 315360000, "res" => 432000), /* 10 * year - 12 hour data, 730 points min */
		array("min" => 31536000, "res" => 43200), /* year - 12 hour data, 730 - 7300 data points */
		array("min" => 2592000, "res" => 3600), /* month - 1 hour data, 720 - 8760 data points */
		array("min" => 604800, "res" => 600), /* week - 10 min data, 1008 - 4320 data points */
		array("min" => 86400, "res" => 60), /* day - 1 min data, 1440 - 10080 data points */
		array("min" => 7200, "res" => 10), /* 2 hours - 10 s, 720 - 8640 data points */
		/* 1 - 7200 data points */
	    )
	),
	"autogen__minutely" => array(
	    "date_limit" => "2013-01-01 00:00:00",
	    "period" => 60,		/* seconds, the data generation period */
	    "maximal_allowed_gap" => 300,
	    "min_resolution" => 600
	),
	"autogen__hourly" => array(
	    "date_limit" => "1900-01-01 00:00:00",
	    "period" => 3600,		/* seconds, the data generation period */
	    "maximal_allowed_gap" => 18000,
	    "min_resolution" => 43200
	),
	"autogen__minutely__default" => array(
	    "items" => &$test_items,
	    "name" => _("Default Group"),
	    "axis" => array(
		"/^AG1$/" => "voltage",
		"*" =>"temperature"
	    )
	),
	"autogen__hourly__default" => array(
	    "items" => &$test_items,
	    "name" => _("Default Group"),
	),
	"autogen__minutely__extra" => array(
	    "items" => &$extra_items,
	    "name" => _("Extra Group")
	),
	"fastgen" => array(
	    "optimize_empty_cache" => true,
	    "maximal_allowed_gap" => 300,
	    "min_resolution" => 10,
	    "date_limit" => date("Y-m-d H:i:s", autogen_start_of_month()),
	    "ignore_subseconds" => false
	),
	"fastgen__10hz" => array(
	    "period" => 0.1,		/* seconds, the data generation period */
	    "items" => &$fast_items
	),
	"fastgen__kHz" => array(
	    "period" => 0.001,
	    "items" => &$fast_items,
	    "disable_caching" => true
	),
	"virtual__autogen" => array(
	    "name" => _("Test Virtual Group"),
	    "srctree" => "autogen__minutely__default(1,3),AG1,autogen__minutely__extra,autogen__minutely__extra__0,autogen__minutely__extra__1"
	)
    ));

    $ADEI_TIMINGS = array (
	_("1 Year") => 31536000,
	_("1 Month") => 2592000,
	_("1 Week") => 604800,
	_("1 Day") => 86400,
	_("6 Hours") => 21600,
	_("1 Hour") => 3600,
	_("30 Min") => 1800,
	_("5 Min") => 300,
    );

?>