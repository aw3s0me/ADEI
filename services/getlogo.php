<?php

header("Content-type: image/png");

$req = new REQUEST();
$w = $req->CreateImageHelper();
$w->Create();
$id = $w->Save();
$w->Display($id);

?>