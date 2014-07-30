<?php
include($JPGRAPH_PATH . "/jpgraph.php");
include($JPGRAPH_PATH . "/jpgraph_line.php");
include($JPGRAPH_PATH . "/jpgraph_log.php");
include($JPGRAPH_PATH . "/jpgraph_scatter.php");
include($JPGRAPH_PATH . "/jpgraph_date.php");
include($JPGRAPH_PATH . "/jpgraph_canvas.php");
include($JPGRAPH_PATH . "/jpgraph_canvtools.php");
include($JPGRAPH_PATH . "/jpgraph_bar.php");
#include($JPGRAPH_PATH . "/jpgraph_regstat.php");


if (method_exists(JpGraphError, "Install")) {
    $GLOBALS['JPGRAPH_VERSION'] = 2;

    class JpGraphErrObjectADEIException extends JpGraphErrObject {
	function Raise($aMsg,$aHalt=true) {
	    $num = ADEIException::PLOTTER_EXCEPTION;
	    if (preg_match("/to\s+small\s+plot\s+area/i", $aMsg)) $num = ADEIException::PLOTTER_WINDOW_TOO_SMALL;

	    throw new ADEIException(translate("JpGraph Exception: %s", $aMsg), $num);
	}
    }

    JpGraphError::Install("JpGraphErrObjectADEIException");
} else {
    $GLOBALS['JPGRAPH_VERSION'] = 3;

     JpGraphError::SetImageFlag(false);
/*
    This is actually is not needed any more, and exceptions should be caught
    by try blocks, I suppose.
    
    class JpGraphADEIException extends JpGraphException {
	static public function defaultHandler($aMsg,$aHalt=true) {
	    $num = ADEIException::PLOTTER_EXCEPTION;
	    if (preg_match("/to\s+small\s+plot\s+area/i", $aMsg)) $num = ADEIException::PLOTTER_WINDOW_TOO_SMALL;

	    throw new ADEIException(translate("JpGraph Exception: %s", $aMsg), $num);
	}
    }

    that would cause problems
    set_exception_handler(array('JpGraphADEIException', 'defaultHandler'));
*/
}

?>