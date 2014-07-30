<?php
header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

//require("../classes/email.php");

global $ADEI;
global $ADEI_ROOTDIR;
$ADEI->RequireClass("email");
$EMAIL = new EMAIL();

function genEmailContent($props) {
    $host = $_SERVER['HTTP_HOST'];
    $location = $props['url'];
    $query;
    $i=1;
    foreach($props as $key => $value)
    {
        if ($key != 'from' && $key != 'to' && $key != 'message' && $key != 'attachement' && $key != 'adei_session' && $key != 'task' && $key != 'service' && $key != 'url') {
            $query .= "&". $key . "=" . $value;
        }
    }
    $filearray = explode('/',$props['attachement']);
    $file = $filearray[count($file)-1];
    $url ="$location". "#" ."$query";
    $message = "<html><head><title>Adei Graph</title></head><body><p>";
    $message .= "<p>". $props['message'] ."</p>";
    $message .= "<p><a href='". $url ."'>Link to Graph</a></p>";
    $message .= "<p><img src='$file' /></p>";
    $message .="</body></html>";
    return $message;
}

if (isset($_GET['task'])) {
    switch ($_GET['task']) {
      case "Send":
        if (isset($_GET['to']) && isset($_GET['message']) && isset($_GET['from']) && isset($_GET['attachement'])) {
            $mail = $_GET['to'];
            $path = $_GET['attachement'];
            $from = $_GET['from'];
            foreach($_GET as $key => $value)
            {
                $props[$key] = $value;
            }
            $msg = genEmailContent($props);
            $vlues = array('email' => $mail, 'message'=>$msg, 'attachement'=>$path, 'from' => $from);
            $result = $EMAIL->sendMail($vlues);
            echo $result;
            if ($result != 'phpmailerException' || $result != 'exception') unlink($path);
        }
        break;
      case "genpic":
        $pic .= $_GET['picname'];
        foreach($_GET as $key => $value)
        {
            $props[$key] = $value;
        }
        $r = $EMAIL->createFolder();
        if ($r!="Success") echo "FAIL";
        else {
            $result = $EMAIL->createPNG($props);
            if ($result!="FAILED") echo $ADEI_ROOTDIR ."". $result;
            else echo "FAIL";
        }
        break;
      case "getSensorList":
        foreach($_GET as $key => $value) {
            $opts[$key]= $value;
        }
        $details = $EMAIL->listSensors($opts);
        $return = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><result>";
        if ($error = $details['error'])
        {
            $return .= "<value>". $details['error'] ."</value>";
        }
        else {
//				$return .= "<value>Success</value></result>";
            foreach($details['groups'] as $gid => $itemlist) {
                $return .= "<value><groupname>". $gid ."</groupname>";
                foreach($itemlist as $item => $info) {
                    $return .= "<item><itemid>". $info['id'] ."</itemid><itemname>". $info['name'] ."</itemname></item>";
                }
                $return .= "</value>";
            }
        }
        $return .="</result>";
        echo $return;
        break;
      default:
        $return = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><result>";
        $return .= "<heading>Emaili</heading>";
        $return .= "<value name='From' id='from'/>";
        $return .= "<value name='Recipient' id='tomail'/>";
        $return .= "<value name='Message' id='message'/>";
        $return .= "<value name=\"$result\" id='ressu'/>";
        $return .= "</result>";
        echo $return;
        break;
    }
} else {
    $return = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><result>";
    $return .= "<heading>Email</heading>";
    $return .= "<value name='From' id='from'/>";
    $return .= "<value name='Recipient' id='tomail'/>";
    $return .= "<value name='Message' id='message'/>";
    $return .= "</result>";
    echo $return;
}
?>