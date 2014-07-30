<?php
require("../adei.php");

/*
$config = array (
    "db_server" => "katrin",
    "db_name" => "hauptspektrometer"
);
*/

function DoClean(&$req) {
    try {
	$reader = $req->CreateReader(REQUEST::READER_FORBID_CACHEREADER);

	$list = $req->GetGroups();
	foreach ($list as $greq) {
	    $lg = $reader->CreateGroup($greq->GetGroupInfo());
	    $reader->Clean($lg);
	}
    } catch(ADEIException $e) {
	$e->logInfo("Date cleanup is failed", $reader?$reader:$req);
	$error = $e->getInfo();
    }

    return $error?$error:0;
}

$req = new REQUEST($config);
$list = $req->GetSources();

foreach ($list as $sreq) {
    $err = DoClean($sreq);
    if ($err) 
	echo $sreq->GetLocationString() . ", Error: $err\n";
}

?>