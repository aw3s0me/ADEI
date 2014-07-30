<?php

try {
    $req = new DATARequest();
    $export = $req->CreateExporter();
    if (isset($_GET["cache"])) {
        $export->SetCacheMode(true);
    }
    $export->Export();
} catch(ADEIException $ex) {
    header("Content-type: text/plain");
    $ex->logInfo(NULL, $export);
    echo "ERROR: " . $ex->getInfo();
}

?>