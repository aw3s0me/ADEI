<?php

require($ADEI_ROOTDIR . "/classes/jpgraph.php");

class DRAWText {
 var $req;
 var $height, $width;
 var $graph;
 
 var $tmpfile;
 var $ready;
 
 function __construct(REQUEST $req = NULL) {
    global $TMP_PATH;
    global $GRAPH_DEFAULT_HEIGHT;
    global $GRAPH_DEFAULT_WIDTH;
    
    if ($req) $this->req = $req;
    else $this->req = new REQUEST();
    
    $this->ready = false;

    if (isset($this->req->props['height'])) $this->height = $this->req->props['height'];
    else $this->height = $GRAPH_DEFAULT_HEIGHT;
    if (isset($this->req->props['width'])) $this->width = $this->req->props['width'];
    else $this->width = $GRAPH_DEFAULT_WIDTH;

    $this->tmpfile = $this->GetTmpFile();
 }

 function GetTmpFile() {
    global $ADEI_SESSION;
    global $TMP_PATH;

    $dir = "clients/" . $ADEI_SESSION . "/messages/";

    if (!is_dir($TMP_PATH . "/" .  $dir)) {
	if (!@mkdir($TMP_PATH . "/" . $dir, 0755, true)) 
	    throw new ADEIException(translate("DRAWText class have not access to the temporary directory"));
    }

    return $dir . time() . "_" . rand() . ".png";
 }

 
 function CreateMessage($header, $msg) {
    $this->graph = new CanvasGraph($this->width, $this->height, 'auto');
    $this->graph->SetMargin(5,11,6,11);
    $this->graph->SetShadow();
    $this->graph->SetMarginColor( "teal");
    $this->graph->InitFrame(); 
    
    $text_width = $this->width - 50;
    if ($text_width < 100) return;

    $text = new Text($msg, 25, 25);
    $text->SetFont(FF_ARIAL, FS_NORMAL, 24);

//    $text->Align('left', 'top');
//    $text->ParagraphAlign('left'); 
//    $text->SetBox( "white", "black","gray"); 
    
    $width = $text->GetWidth($this->graph->img);
    
    if ($width > $text_width) {
	$char_width = ceil($width / strlen($msg));
	$cpl = $text_width / $char_width;
/*
	Does not taken into the account by GetWidth function
	$text->SetWordWrap($cpl);
*/

	$wmsg = wordwrap($msg, $cpl, "\n", true);
	$text->Set($wmsg);
	
	$width = $text->GetWidth($this->graph->img);
        while (($width > $text_width)&&($cpl>10)) {
	    $cpl-=$cpl/10;
	    $wmsg = wordwrap($msg, $cpl, "\n", true);
	    $text->Set($wmsg);
	    $width = $text->GetWidth($this->graph->img);
	}
    }
    
    $text->Stroke( $this->graph->img); 
 }


 function Save($file = false) {
    global $TMP_PATH;

    if ($this->ready) {
	if ($file) {
	    copy($TMP_PATH . "/" .  $this->tmpfile, $file);
	    return true;
	}

	return $this->tmpfile;
    }

    if ($file) {
        $this->graph->Stroke($file);
	return true;
    }

    $fp = fopen($TMP_PATH . "/" . $this->tmpfile . ".tmp", "w+");
    if ($fp) flock($fp, LOCK_EX);
    $this->graph->Stroke($TMP_PATH . "/" . $this->tmpfile);
    if ($fp) fclose($fp);
    
    return $this->tmpfile;
 }

 static function Display($file = false) {
    global $TMP_PATH;
    
    if ($file) {
	if (preg_match("/^[A-Za-z0-9\/_]\.png$/",$str)) return false;
	
	$fp = fopen($TMP_PATH . "/" . $file . ".tmp", "w+");
	if ($fp) flock($fp, LOCK_SH);
	$res =  @readfile($TMP_PATH . "/" . $file);
        if ($fp) fclose($fp);
	
	return $res;
    }

    return false;    
 }
}

?>