<?php

require($ADEI_ROOTDIR . "/classes/drawtext.php");


class WELCOME extends DRAWText {
 var $req;
 var $height, $width;
 var $graph;
 
 var $tmpfile;
 var $ready;
 
 function __construct(REQUEST $req = NULL) {
    parent::__construct($req);

    if (is_file($TMP_PATH . "/" .  $this->tmpfile)) $this->ready = true;
    else $this->ready = false;
 }

 function GetTmpFile() {
    global $ADEI_SESSION;
    global $TMP_PATH;

    $dir = "clients/" . $ADEI_SESSION . "/";

    if (!is_dir($TMP_PATH . "/" .  $dir)) {
	if (!@mkdir($TMP_PATH . "/" . $dir, 0755, true)) 
	    throw new ADEIException(translate("DRAW class have not access to the temporary directory"));
    }

    return $dir . "welcome-" . $this->width . "x" . $this->height .  ".png";
 }

 
 function Create() {
    if ($this->ready) return;
    
    $this->graph = new CanvasGraph($this->width, $this->height, 'auto');
    $this->graph->SetMargin(5,11,6,11);
/*
    $this->graph->SetShadow();
    $this->graph->SetMarginColor( "teal");
    $this->graph->InitFrame(); 
*/    

    $hpos = 15;
    
    $text = new Text("ADEI", $this->width/2, $hpos);
    $text->SetFont(FF_ARIAL, FS_BOLD, 24);
    $text->Align('center', 'top');
    $text->Stroke( $this->graph->img); 

    $hpos += $text->GetTextHeight($this->graph->img) + 10;

//    $msg = "Welcome to the Advanced Data Extraction Infrastructure! Please";
    $msg = preg_replace(
	array("/\n([^\n])/"),
	array(' \1'),
	file_get_contents("docs/welcome.txt")
    );
    

    $text_width = $this->width - 50;
    if ($text_width < 100) return;

    $text = new Text($msg, $this->width/2, $hpos);
    $text->SetFont(FF_ARIAL, FS_NORMAL, 18);
    $text->Align('center', 'top');

    $width = $text->GetWidth($this->graph->img);
    if ($width > $text_width) {
	$char_width = ceil($width / strlen($msg));
	$cpl = $text_width / $char_width;

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
    

//    $text->ParagraphAlign('center'); 
//    $text->SetBox( "white", "black","gray"); 


/*
//    $text->Align('left', 'top');
//    $text->ParagraphAlign('left'); 
//    $text->SetBox( "white", "black","gray"); 
    
    $width = $text->GetWidth($this->graph->img);
    
*/    
//    $text->Stroke( $this->graph->img); 
 }
}

?>