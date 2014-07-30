<?php

    function ShowTime($tm) {
	return $tm->format("F j, Y H:i:s.uP");
    }


    $logfile = $_REQUEST['logfile'];
    $logpos = $_REQUEST['logpos'];

    $info = adeiGetLogInfo($logfile, $logpos);
    
    ?><br/><table class="loginfo">
	<tr>
	    <th><?echo translate("Time");?></th>
	    <td><?echo ShowTime($info['time']);?></td>
	</tr>
	<tr>
	    <th><?echo translate("Setup");?></th>
	    <td><?echo $info['setup'];?></td>
	</tr>
	<tr>
	    <th><?echo translate("Source");?></th>
	    <td><?echo $info['source'];?></td>
	</tr>
	<tr>
	    <th><?echo translate("Session");?></th>
	    <td><?echo $info['session'];?></td>
	</tr>
	<tr>
	    <th><?echo translate("Priority");?></th>    
	    <td><?echo $info['priority'];?></td>
	</tr>
	<tr>
	    <th><?echo translate("Request");?></th>    
	    <td><?
		if ($info['request']) echo $info['request'];
		else echo "-";
	    ?></td>
	</tr>
	<tr>
	    <th><?echo translate("Message");?></th>    
	    <td><?
		if ($info['message']) echo $info['message'];
		else echo "-";
	    ?></td>
	</tr>
    </table><br/><?
    
    if ($info['exception']) {
	echo "<h4>" . translate("Exception: ") . "</h4>";
	
	$ae = LOGGER::DecodeObject($info['exception']);
	echo translate("Code: %d", $ae->getCode()) . "<br/>";
	echo translate("Message: %s", $ae->getMessage()) . "<br/>";
	echo translate("Location: %s:%d", $ae->getFile(), $ae->getLine()) . "<br/>";
    }
    
    if ($info['GET']) {
	echo "<h4>" . translate("GET options: ") . "</h4>";
	echo "<pre>";
	print_r($info['GET']);
	echo "</pre>";
    }

    if ($info['POST']) {
	echo "<h4>" . translate("POST options: ") . "</h4>";
	echo "<pre>";
	print_r($info['POST']);
	echo "</pre>";
    }

    if ($info['RAW_DATA']) {
	echo "<h4>" . translate("POST data: ") . "</h4>";
	echo "<pre>";
	print_r($info['RAW_DATA']);
	echo "</pre>";
    }

    if ($info['REQUEST_DETAILS']) {
	echo "<h4>" . translate("Request details: ") . "</h4>";
	
	echo "<table>
	    <tr><td>" . translate("Client") . ":</td><td>" . $info['REQUEST_DETAILS']['REMOTE_ADDR'] . "</td></tr>
	    <tr><td>" . translate("User Agent") . ":</td><td>" . $info['REQUEST_DETAILS']['HTTP_USER_AGENT'] . "</td></tr>
	</table>";

	echo "<pre>";
	print_r($info['REQUEST_DETAILS']);
	echo "</pre>";
    }

    if ($info['object']) {
	echo "<h4>" . translate("Affected Object: ") . "</h4>";

	echo "<pre>";
	print_r(LOGGER::DecodeObject($info['object']));
	echo "</pre>";
    }

    if ($info['result']) {
	echo "<h4>" . translate("Result: ") . "</h4>";
	if (preg_match("/(getimage|getlogo)/", $info['source'])) {
	    $data = pack("H*", $info['result']);
	    $image = new Imagick();
	    if ($image) {
	        $image->readImageBlob($data);
		$format = $image->getImageFormat();
		$geometry = $image->getImageGeometry();
		$size = $image->getImageSize();
		echo translate("Image Information: %s, %dx%d, %s", $format, $geometry['width'], $geometry['height'], dsPrintSize($size)) . "<br/>";
#		print_r($image->identifyImage());
	    }    
	    //PNG print_r($image->getImageFormat());
	    
	    echo "<img src=\"logimage.php?logfile=$logfile&logpos=$logpos\"/>";
	} else {
	    $data = pack("H*", $info['result']);
	    echo "<pre>";
	    echo htmlentities($data);
	    echo "</pre>";
	}
    }

    if ($info['exception']) {
	echo "<h4>" . translate("Backtrace: ") . "</h4>";
	echo "<pre>";
	print_r($ae->getTrace());
	echo "</pre>";
    }

//    print_r($info);
?>