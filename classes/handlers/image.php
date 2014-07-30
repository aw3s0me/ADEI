<?php

class IMAGEHandler extends DATAHandler {
 public $data_not_needed = true;

 function __construct(&$opts = NULL, STREAMHandler $h  = NULL) {
    $this->content_type = "image/png";
    $this->extension = "png";

    parent::__construct($opts, $h);

    $this->multigroup = true;
 }

 function End($flags = 0) {
    try {
        $req = new DATARequest();
        $draw = $req->CreatePlotter();
        $draw->Create();
        $id = $draw->Save();
    }
    catch(ADEIException $e) {
        $error = $e->getInfo();
            try {
                if (!$draw) {
                if (!$req) $req = new REQUEST();
                $draw = $req->CreateTextPlotter();
                }
        //	    $draw->CreateMessage("Error", "No Data");
                $draw->CreateMessage("Error", $error);
                $id = $draw->Save();
                $error = false;
            } catch (ADEIException $ex) {
                $ex->logInfo(NULL, $draw);
            }
    }
     $res = DRAW::Display($id);

     $this->h->Write($res);
 }


}

?>
