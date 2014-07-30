<?
    try {
        $hybrid_config = dirname(__FILE__).'/includes/hybridauth-2.1.2/hybridauth/config.php';
        require_once('includes/hybridauth-2.1.2/hybridauth/Hybrid/Auth.php'); 
        $hybridauth = new Hybrid_Auth($hybrid_config); 
    }
    catch(Exception $e) {
?>
    <div style="text-align: center;margin: 0 auto;margin-top: 20px;"><b style="color:red; ">Error occured, please, contact administrator</b></div>
<?php
        die();
    }
    
    $connected_adapters_list = $hybridauth->getConnectedProviders(); 
    $first_connected = $connected_adapters_list[0]; 
    var_dump($first_connected);
    $connected_adapters_list = $hybridauth->getConnectedProviders(); 
    foreach ($connected_adapters_list as &$adapter_name) {
        $adapter = $hybridauth->getAdapter($adapter_name);
        $adapter->logout();
    }

?>


