<?php
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    date_default_timezone_set("UTC");

    $SETUP_MULTI_MODE = true;
    
    //$ADEI_RELEASE = true;
    $ADEI_RELEASE = false;

    $TITLE = "ADEI";

    $DEFAULT_MODULE = "wiki";

    $MODULES = array(/*"slowcontrol", "alarms",*/ "infopage", "graph", "download", "wiki");
    $POPUPS = array("source", "controls");

    $CONTROLS = array("infotab", "searchtab", "virtual", false, "export", "aggregator", "plot"); // false/null indicates line break
    
    $ADEI_SRCTREE_EXTRA = array();
    $ADEI_INFO_MODULES = array(
        "legend" => array(
            'title' => _("Channel Overview"),
            'handler' => "CHANNELView",
            'opts' => array()
        ),
        "scatter" => array(
            'title' => _("Scatter Plot"),
            'handler' => "SCATTERView",
            'opts' => array()
        ),
        "histogram" => array(
            'title' => _("Histogram"),
            'handler' => "histogramview",
            'opts' => array(
		'bins' => array(0, 5, 10, 20, 50)
            )
        ),
    );


	/* more specific configs should go after less specific ones */
    $OPTIONS = array (
	"default" => array( 
	    "min_resolution" => 600, 		// in db config could be array by id
//	    "cache_config" => $ADEI_CACHE	// alter default cache timing configuration
	    "ignore_subseconds" => true,	// Timestamps with second precision
	    "omit_raw_cache" => false,		// Use data source instead of cache0 tables
	    "fill_raw_first" => false,		// Fill RAW cache table completely prior to processing agregating cache tables
	    "optimize_empty_cache" => false,	// Do not fill lower resolution cache intervals if encompassing one is empty
	    "use_cache_timewindow" => true,	// Shrink time window to currently cached data
	    "use_cache_reader" => false,	// Do not access data source while reading cache
	    "overcome_reader_faults" => false,	// Use CACHE if connection to reader is failed
	    "optimize_time_axes" => false,	// Limit window size by available data
	    "use_md5_postfix" => false,		// Use md5 for table name postfixes
	    "null_value" => 0,			// The numeric value to use instead of NULL (missing data) when needed
	    "disable_caching" => false,		// The CACHE should not be generated, Item for RT display only
	    "channel_uids" => false,		// If channels have unique identifactors within ADEI setup
	    "private_axes" => false,            // The READER has his own set of axes which shall not be mixed with global ones
	)
    );
    

    $ADEI_DB = array (
	"host" => "localhost",
	"port" => 0,
	"database" => "adei",
	"user" => "adei",
	"password" => "adei"
    );

/*
    The TSQLFile support in ROOT is not mature enough    
    $ROOT_DB = array (
	"driver" => "mysql",
	"host" => "localhost",
	"port" => 0,
	"user" => "zeus",
	"password" => "zeus"
    );
*/
    
    $BACKUP_DB = array (
	"driver" => "mysql",
	"host" => "localhost",
	"port" => 0,
	"user" => "zeus",
	"password" => "zeus"
    );

    $ADEI_TIMINGS = array (
	_("1 Year") => 31536000,
	_("1 Month") => 2592000,
	_("1 Week") => 604800,
	_("1 Day") => 86400,
	_("6 Hours") => 21600,
	_("1 Hour") => 3600,
	_("15 Min") => 900,
	_("5 Min") => 300
    );
    
    
    $ADEI_CACHE = array (
	array("min" => 31536000, "res" => 43200), /* year - 12 hour data, 730 points min */
	array("min" => 2592000, "res" => 3600), /* month - 1 hour data, 720 - 8760 data points */
	array("min" => 604800, "res" => 600), /* week - 10 min data, 1008 - 4320 data points */
	array("min" => 86400, "res" => 60), /* day - 1 min data, 1440 - 10080 data points */
	array("min" => 7200, "res" => 10), /* 2 hours - 10 s, 720 - 8640 data points */
	/* 1 - 7200 data points */
    );
    
    
    $ADEI_VIRTUAL_READERS = array(
/*	"virtual_vg" => array(
	    "title" => _("User Groups"),
	    "reader"=> "VGReader"
	),*/
	"virtual" => array(
	    "title" => _("Virtual"),
	    "reader" => "VIRTUALReader"
	)
    );

    $ADEI_AXES = array(
/*	"0" => array(
	),*/
	"countrate" => array(
	    "axis_name" => _("Count Rate"),
	    "axis_units" => false,
	    "axis_mode" => "STD",
	    "axis_range" => false
	),    
	"percent" => array(
	    "axis_name" => false,
	    "axis_units" => _("%"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),    
	"temperature" => array(
	    "axis_units" => _("C"),
	    "axis_name" => _("Temperature"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"temperature/kelvin" => array(
	    "axis_units" => _("K"),
	    "axis_name" => _("Temperature"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"voltage" => array(
	    "axis_units" => _("V"),
	    "axis_name" => _("Voltage"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"voltage/kilo" => array(
	    "axis_units" => _("kV"),
	    "axis_name" => _("Voltage"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"current" => array(
	    "axis_units" => _("A"),
	    "axis_name" => _("Current"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"current/micro" => array(
	    "axis_units" => _("uA"),
	    "axis_name" => _("Current"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"current/nano" => array(
	    "axis_units" => _("nA"),
	    "axis_name" => _("Current"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"resistance" => array(
	    "axis_units" => _("Ohm"),
	    "axis_name" => _("Resistance"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	), 
	"pressure" => array(
	    "axis_units" => _("bar"),
	    "axis_name" => _("Pressure"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"pressure/psi" => array(
	    "axis_units" => _("psi"),
	    "axis_name" => _("Pressure"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"pressure/milli" => array(
	    "axis_units" => _("millibar"),
	    "axis_name" => _("Pressure"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"pressure/hpa" => array(
	    "axis_units" => _("hPa"),
	    "axis_name" => _("Pressure"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"mass" => array(
	    "axis_units" => _("kg"),
	    "axis_name" => _("Mass"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"rpm" => array(
	    "axis_units" => _("rpm"),
	    "axis_name" => _("RPM"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"volume-flow" => array(
	    "axis_units" => _("m3/s"),
	    "axis_name" => _("Flow"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"mass-flow" => array(
	    "axis_units" => _("g/s"),
	    "axis_name" => _("Mass-flow"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"power" => array(
	    "axis_units" => _("W"),
	    "axis_name" => _("Power"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	),
	"magnetic_field" => array(
	    "axis_units" => _("T"),
	    "axis_name" => _("Magnetic field"),
	    "axis_mode" => "STD",
	    "axis_range" => false
	)
    );

    $DEFAULT_MISSING_VALUE = NULL;	/* Value to use instead of NULL values */

    $EXPORT_DEFAULT_FORMAT = "csv";
    
    /* Joiner/Filter: Joiner is a filter supporting multiple groups. From the
    software point of view there is no difference and both are implemented 
    using the same base class. It is possible to have arbitrary sequence of
    enclosed filters/joiners within format configuration.
    Only joiner or filter should be specified on the one level, if both
    are specified the filter will be used.
    
	@PROP@ 			- replaced by passed value
	?{@PROP@?@PROP@:nothing}

      Default:
	@TMPFILE@
	@BLOCK_TITLE@
	@EXPECTED_BLOCKS@
	@BLOCK_NUMBER@


	@ROOT__COMBHIST@	
    */
    
    $ADEI_SYSTEM_FORMATS = array (
	"labview32" => array(
	    'title' => "LabVIEW array",
	    'handler' => "LABVIEW",
	    'hidden' => true,
	    'type' => "streamarray"
	)
    );
    
    $EXPORT_FORMATS = array (
	"csv" => array(
	    'title' => "CSV",
	    'accept_null_values' => true
	),
     	"image" => array(
    	    'title' => "PNG Image",
            'handler' => 'Image',
 	    'type' => 'image'
     	),
	"xls" => array(
	    'title' => "Excel",
	    'handler' => "EXCEL",
//	    subsec_format => "DD.MM.YYYY hh:mm:ss.000;@"
//	    subsec_format => "text()" /* Use text (CSV) */
//	    subsec_format => "text(format)" /* Use text (format) */
//	    subsec_format => "DD.MM.YYYY hh:mm:ss.000000;@" /* HP, OpenOffice */
//	    subsec_width => 26
//	    date_format => "DD.MM.YYYY hh:mm:ss"
//	    date_width => 20
//	    value_format => "0.0000E+##"
//	    value_width => 12
/*	    filter => array (
		"type" => "ZIP"
	    )*/
	),
/*	"root" => array(
	    'title' => "ROOT",
	    'filter' => array (
		'app' => "csv2root",
		'opts' => "--file @TMPFILE@ ?{@BLOCK_NUMBER@===0?--overwrite} ?{@EXPECTED_BLOCKS@!=1?--group @BLOCK_TITLE@}",
		'joiner' => true,
		'groupmode' => true,	// Run filter app for each group
	        'extension' => "root"
	    )
	),
	"root_hist" => array(
	    'title' => "ROOT+Hist",
	    'filter' => array(
		'app' => "csv2root",
		'opts' => "--file @TMPFILE@ ?{@BLOCK_NUMBER@===0?--overwrite} ?{@EXPECTED_BLOCKS@!=1?--group @BLOCK_TITLE@} --save-histograms ?{@ROOT__COMBHIST@?--combined-histogram}",
		'joiner' => true,
		'groupmode' => true,	// Run filter app for each block
		'extension' => "root"
	    )
	),*/
	"tdms" => array(
	    'title' => "TDMS",
	    'handler' => "LABVIEW",
	)
    );
    
    $EXPORT_SAMPLING_RATES = array (
	_("Hourly") => 3600,
	_("Minutely") => 60,
	_("1 Hz") => 1,
	_("1000 Hz") => 0.001
    );
    
    $SEARCH_ENGINES = array (
	"ITEMSearch" => array(),
	"INTERVALSearch" => array(),
	"PROXYSearch" => array()
    );
    
    
    $ADEI_ID_DELIMITER = "__";
    
    $LOGGER_LOG_REQUESTS = false;	/* Log all ADEI requests */
    $LOGGER_LOG_OUTPUT = false;		/* Log output of ADEI requests */
    $LOGGER_STORE_OBJECTS = false;	/* Will produce big, but very detailed logs */

/* This forces MySQL to use INDEXes while SELECT queries returning huge 
rowsets are executed. After certain threshold, MySQL optimizator stops
using INDEXes what bringing to huge slowdown. This option is intended
to fix this behaviour. */
    
    $MYSQL_FORCE_INDEXES = true;	
    
    $CACHE_PRECISE_GAPS = false;	/* Enables more precise maxgap calculation in DATAInterval */
    
    $AJAX_UPDATE_RATE = 60; 		/* in seconds */
    $AJAX_WINDOW_BORDER = 10; 		/* in pixels */
    $AJAX_PARSE_DELAY = 100; 		/* html, in milliseconds */

    $SOURCE_KEEP_WINDOW = false;	/* Preserve time range when group is changed */
    

    $DHTMLX_SKIN = "standard";
//    $DHTMLX_SKIN="dhx_blue";
    $DHTMLX_ICONSET = "csh_bluefolders";
    $MENU_SHOW_ITEMS = false;		/* Show separate items in popup menu */
    $MENU_SCROLL_LIMIT = 10;		/* Add scrolling if more than that items present */
    
    $PHP_BINARY = "/usr/bin/php";
    $DOWNLOAD_DECAY_TIME = 72000;       /*  Time after unused download is removed from server*/
  
    $JPGRAPH_PATH = "/usr/share/php5/jpgraph";
    $EXCEL_WRITER_PATH ="";
    $TMP_PATH=$ADEI_ROOTDIR . "tmp";

    $ADEI_ROOT_STORAGE = "$ADEI_ROOTDIR/storage/";
    
    $ADEI_APP_PATH = array (
	"default" => "/usr/bin/"
    );
    
    $CSV_SEPARATOR = ",";
    $CSV_DATE_FORMAT = "d-M-y H:i:s";
    $EXCEL_DATE_FORMAT = "DD.MM.YYYY hh:mm:ss";
    $EXCEL_SUBSEC_FORMAT = "DD.MM.YYYY hh:mm:ss.000;@"; /* 
	    Unfortunately Excel doesn't support more than 3 digits,
	    OpenOffice does */
    $ROOT_COMBIHIST_LIMIT = 604800; /* No more, than 1 week */


    $STATUS_DEFAULT_DURATION = 2000;	/* in milliseconds */


    $GRAPH_DEFAULT_HEIGHT = 768; /* in pixels */
    $GRAPH_DEFAULT_WIDTH = 1024; /* in pixels */
    $GRAPH_MAX_AXES = 6;
/* Maximal number of points (all plots) on Graph, this allows us to produce 
rough multiplots to sustain high update rate (most of resources are used to 
graph->Stroke()) */
    $GRAPH_MAX_POINTS_PER_GRAPH = 5000;
/* Maximal approximation interval (in pixels). I.e. maximal distance between
to approximation points. This option have a priority over MAX_POINTS_PER_GRAPH
option. */
    $GRAPH_MAX_APPROXIMATION_INTERVAL = 10; /* in pixels */

    $GRAPH_AUTOAGGREGATION_MINMAX_THRESHOLD = 10; /* in precision, approx. px/2 */

    $GRAPH_INTERPOLATE_DATA_GAPS = false; /* Do not indicate missing data */

/* Maximal distance between currently CACHEd data and the data available in the
data source, measured in seconds. 0 means - unlimited */
    $GRAPH_MAX_CACHE_GAP = 0;

    
    $GRAPH_ZOOM_RATIO = 2.;	// This coeficent defines product of division of original window by zoomed window
    $GRAPH_DEEPZOOM_AREA = 10;	// In pixels (actual interval is 2x)
    $GRAPH_STEP_RATIO = 5.;	// This coeficent defines product of division of original window by step
    $GRAPH_EDGE_RATIO = 6;	// Product of division of while window by edge size

    $GRAPH_DELTA_SIZE = 10; /* delta neighborhood size, in pixels */
    $GRAPH_MARGINS = array (
	"left" => 0,
	"top" => 20,
	"right" => 20,
	"bottom" => 30,
	"axis" => 80
    );
    $GRAPH_SELECTION = array (
	"min_width" => 20,
	"min_height" => 20
    );


    $GRAPH_LINE_WEIGHT = 2;
/* Enables approximation point marks starting with accuracy greater than the
specified number of pixels. 0 - to disable. Significatly degradate performance
if used */
    $GRAPH_ACCURACY_MARKS_OUTSET = 14;
    $GRAPH_ACCURACY_MARKS_MULTIOUTSET = 49;
    $GRAPH_ACCURACY_MARKS_IF_GAPS = false;	// Force if data gaps are found
    $GRAPH_ACCURACY_MARKS_COLOR = "blue";
#   $GRAPH_ACCURACY_MARKS_TYPE = MARK_FILLEDCIRCLE;
    $GRAPH_ACCURACY_MARKS_SIZE = 3;
    
/* Display a bar on top of the graph, indicating the density of the data. The
four modes are supported:
    SHOW_NONE: do not show
    SHOW_EMPTY: show only if missing (due to inavalability of the data) points 
    are available on the graph
    SHOW_POINTS: Indicate all set points on the graph (somehow duplicates 
    'GRAPH_ACCURACY_MARKS' functionality, but displayed even if precision is 
    high and GRAP_ACCURACY_MARKS are disable by OUTSET
    SHOW_GAPS: Will display information on data gaps. Even if point placed on
    the graph, but there is less data than expected, all such points will be
    indicated on the bar 
*/

    $GRAPH_INDICATE_DATA_DENSITY = 'SHOW_NONE';
//    $GRAPH_INDICATE_DATA_DENSITY = SHOW_POINTS;//SHOW_GAPS;

    $GRAPH_DENSITY_PLOT_INVALID_SIZE = 3;
    $GRAPH_DENSITY_PLOT_INVALID_COLOR = 'red';

    $GRAPH_DENSITY_PLOT_VALID_SIZE = 0;
    $GRAPH_DENSITY_PLOT_VALID_COLOR = 'green';
    
    $GRAPH_DENSITY_POINTS_TYPE = 'MARK_FILLEDCIRCLE';
    $GRAPH_DENSITY_POINTS_SIZE = 1;
    $GRAPH_DENSITY_POINTS_COLOR = 'green';
    $GRAPH_DENSITY_POINTS_OUTLINE = 'black';
    
    $GRAPH_FAST_LEGEND = false;		// Inpricese in rapidly changing data    

    $GRAPH_LOWPRECISION_UPDATE_RATE = 10800; // How often we should update low precision graphs
    
    $GRAPH_SUBSECOND_THRESHOLD = 5;	/* in seconds, for shorter intervals the subsecond handling is performed */

/* This option defines a plot colors (used sequently), comment out to use black
only */
    $GRAPH_COLORS =  array("black", "blue", "orange", "brown",
	    "#90EE90", "#ADD8E6", "#FFC0CB", "#A020F0", "#1E90FF");

    $AXES_COLORS = array(
	array("black"),
	array("blue"),
	array("#f800e9"),
	array("green"),
	array("yellow")
    );
/*
    $GRAPH_COLORS =  array("black", "#483D8B","#2F4F4F","#00CED1","#9400D3","#FF1493","#00BFFF","#696969","#1E90FF","#D19275","#B22222","#FFFAF0","#228B22","#FF00FF","#DCDCDC",
	    "#808080","#008000","#ADFF2F","#F0FFF0","#FF69B4","#CD5C5C","#4B0082","#FFFFF0","#F0E68C","#E6E6FA","#FFF0F5","#7CFC00","#FFFACD","#ADD8E6",
	    "#D3D3D3","#90EE90","#FFB6C1","#FFA07A","#20B2AA","#87CEFA","#8470FF","#778899","#B0C4DE","#FFFFE0","#00FF00","#32CD32","#FAF0E6","#FF00FF",
	    "#BA55D3","#9370D8","#3CB371","#7B68EE","#00FA9A","#48D1CC","#C71585","#191970","#F5FFFA","#FFE4E1","#FFE4B5","#FFDEAD","#000080","#FDF5E6",
	    "#FF4500","#DA70D6","#EEE8AA","#98FB98","#AFEEEE","#D87093","#FFEFD5","#FFDAB9","#CD853F","#FFC0CB","#DDA0DD","#B0E0E6","#800080","#FF0000",
	    "#FA8072","#F4A460","#2E8B57","#FFF5EE","#A0522D","#C0C0C0","#87CEEB","#6A5ACD","#708090","#FFFAFA","#00FF7F","#4682B4","#D2B48C","#008080");
*/

    if (!$ADEI_SETUP) {
        if (file_exists("config.actual.php")) {
	    require("config.actual.php");
        } else {
	    $ADEI_SETUP = "all";
        }
    }


    if ($SETUP_MULTI_MODE) {
	if ($_GET['setup']) {
	    $ADEI_SETUP = $_GET['setup'];
	    unset($_GET['setup']);
	} else {
	    $params = sizeof($_SERVER['argv']);
    	    if ($params>1) {
		$pos = array_search("-setup", $_SERVER['argv']);
		if (($pos)&&(($pos + 1) < $params)) $ADEI_SETUP =  $_SERVER['argv'][$pos + 1];
		else $SETUP_MULTI_MODE = 0;
	    } else $SETUP_MULTI_MODE = 0;
	}
    }

    $SETUP_CONFIG = "setups/$ADEI_SETUP/config.php";
    $SETUP_CSS = "setups/$ADEI_SETUP/$ADEI_SETUP.css";
    
    if (!file_exists("config.php")) {
        $curdir = getcwd();
	if (preg_match("/(services|admin|system|test)$/", $curdir)) chdir("..");
	if (!file_exists("config.php")) {
	    if (preg_match("/tmp\/admin$/", $curdir)) chdir("..");
	}
    }
    
    if (file_exists($SETUP_CONFIG)) require($SETUP_CONFIG);
    
    if (file_exists("config.override.php")) require("config.override.php");
?>
