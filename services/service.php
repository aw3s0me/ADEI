<?php
header('Access-Control-Allow-Origin: *');

require("../adei.php");
try {
    ADEI::RequireService($_GET['service']);
} catch (ADEIException $ex) {
    $ex->logInfo();
    $service_error = xml_escape($ex->getInfo());
}

if ($service_error) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<result><Error>$service_error</Error></result>";
}

?>