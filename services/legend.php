<?php

header("Content-type: application/json");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

//$_POST['props'] = '{"xslt": "legend", "time_format": "text", "db_server": "katrin", "db_name": "hauptspektrometer", "db_group": "0", "control_group": "0", "db_mask": "0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56", "experiment": "0-0", "window": "0", "width": 854, "height": 719, "aggregation": null, "interpolate": null, "show_marks": null, "show_gaps": null, "virtual": null, "srctree": null, "pageid": null, "module": "graph", "format": null, "resample": null, "mask_mode": null, "custom": null, "xmin": "1183593600", "xmax": "1190332800", "ymin": 0, "ymax": 900, "x": "1185099906.9767441861", "y": 141.25560538116588}';


try {
    $req = new DATARequest();
    $encoding = $req->GetResponseEncoding(REQUEST::ENCODING_XML);
    $xslt = $req->GetProp('xslt');
    
    $draw = $req->CreatePlotter();
    $legend = $draw->Legend();
} catch(ADEIException $ex) {
    if (!$req) $req = new REQUEST();
    $ex->logInfo(NULL, $draw?$draw:$req);
    $error = $ex->getInfo();
}

$req->CreateResponse($legend, $error);

?>