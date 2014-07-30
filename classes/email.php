<?php
class EMAIL{
    function __construct() {
        global $ADEI_ROOTDIR;
        require_once($ADEI_ROOTDIR .'/includes/PHPMailer-Lite_v5.1/class.phpmailer-lite.php');
    }
    
    function createPNG($props){
        try {
            $req = new DATARequest();
            $fullpath = $ADEI_ROOTDIR . "tmp/tmpimg/";
            //$fullpath .= $pic;
            $picname;
            foreach($props as $key => $value)
            {
                $req->SetProp($key,$value);
                if ($key=='db_server' || $key=='db_name' || $key == 'window')
                {
                    $picname .= $value ."-";
                }
            }
            $picname .= rand(1,200);
            $picname .= ".png";
            $fullpath .= $picname;

            $draw = $req->CreatePlotter();
            $draw->Create();
            $draw->Save($fullpath);
            return $fullpath;
        }
        catch (ADEIException $ex) {
            $ex->logInfo(NULL, $draw);
            return "FAILED";
        }
    }
    
    function listSensors($opts){
        $req = new DATARequest($opts);
        $reader = $req->CreateReader();
        $groups = $reader->GetGroups();
        $grouplist = $reader->GetGroupList();
        $mask = new MASK($opts);
        $i=0;
        foreach($grouplist as $group => $details){
            $itemlist[$det['gid']] = $reader->GetItemList($groups[$i],$mask);
            $i++;
        }
        foreach($itemlist as $gid => $items) {
            if (!is_numeric($gid)) {
                foreach($items as $key => $item ) {
                    $details['groups'][$gid][$key] = array("id" =>"{$item['id']}", "name" => "{$item['name']}");
                }
            }
        }
        return $details;
    }
    
    function createFolder(){
        $dir = $ADEI_ROOTDIR .'tmp/tmpimg';
        if (!@is_dir($dir)) {
            if (@mkdir($dir, 0777, true)){
                @chmod($dir, 0777);
                return "Success";
            }
            else return "Failure";
        }
        else return "Success";
    }
    
    function sendMail($props){
        $mail = new PHPMailerLite(); // defaults to using php "Sendmail" (or Qmail, depending on availability)
        $mail->IsMail(); // telling the class to use native PHP mail()
        try {
            $mail->SetFrom('adei@adei.com', 'Adei User');
            $mail->AddAddress($props['email'], 'User');
            $mail->Subject = 'Adei Graph';
            $mail->MsgHTML($props['message']);
            $mail->AddAttachment($props['attachement']); // attachment
            $mail->Send();
            return $props['message'];
        } catch (phpmailerException $e) {
            return 'phpmailerException';//$e->errorMessage(); //Pretty error messages from PHPMailer
        } catch (Exception $e) {
            return 'exception';//$e->getMessage();//Boring error messages from anything else!
        }
    }
}
?>