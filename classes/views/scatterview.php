<?php

require_once($ADEI_ROOTDIR . "/classes/jpgraph.php");

class SCATTERView extends VIEW {
 var $title = "Channel Info";
 
 function __construct(REQUEST $req  = NULL, $opts) {
    parent::__construct($req, $opts);
    $this->object = $this->req->GetProp("view_object", false);
    
    if ($this->object) 
        $this->max_points = $this->GetOption('max_points', 500);
    else
        $this->max_points = $this->GetOption('max_points', 5000);
    
    $this->min_width = $this->GetOption('min_width', 300);
    $this->min_height = $this->GetOption('min_height', 300);
    
 }
 
 function GetOption($opt, $default = false) {
    if (isset($this->options[$opt])) return $this->options[$opt];
    return $default;
 }


 function IsApplicable() {
    try {
        $req = $this->req->CreateGroupRequest();
        $rdr = $req->CreateReader();
        $group = $rdr->CreateGroup();
        $caches = $rdr->CreateCacheSet($group);
        $width = $caches->GetWidth();
    
        if ($width > 1) return true;
    } catch (ADEIException $ae) {
    }
    return false;
 }


 function GetOptions() {
    $req = $this->req->CreateGroupRequest();
    $x = $req->GetProp("view_x", false);
    if ($x) list($x_gid, $x_id) = explode(":", $x);
    else list($x_gid, $x_id) = array(0, 0);

    $rdr = $req->CreateReader();
    $group = $rdr->CreateGroup();
    $caches = $rdr->CreateCacheSet($group, $mask);

    $gid = 0;
    $result = array();
    $result2 = array();
    foreach ($caches as $key => $cachewrap) {
	array_push($result, array("label" => $cachewrap->GetGroupTitle(), "disabled" => 1));
	array_push($result2, array("label" => $cachewrap->GetGroupTitle(), "disabled" => 1));
        
        $id = 0;
	$list = $cachewrap->GetItemList();
	foreach ($list as $id => $info) {
	    $title = $info['name'];
	    array_push($result, array("value" => "$gid:$id", "label" => "  $title"));
	    if (($x_gid != $gid)||($x_id != $id))
	        array_push($result2, array("value" => "$gid:$id", "label" => "  $title"));
	    $id++;
	}
	$gid++;
    }

    return array(
        array("select" => array("label" => _("x"), "id" => "x", "options" => $result)),
        array("select" => array("label" => _("y"), "id" => "y", "options" => $result2)),
    );
 }
 
 function GetView() {
    global $TMP_PATH;


    $req = $this->req->CreateDataRequest();
    $x = $req->GetProp("view_x", false);
    $y = $req->GetProp("view_y", false);

    
    if ((!$x)||(!$y)) throw new ADEIException(translate("Parameters view_x and view_y are not set"));
    list($x_gid, $x_id) = explode(":", $x);
    list($y_gid, $y_id) = explode(":", $y);


    if ($this->object) {
        $width = $req->GetProp($this->object . "_width", $this->min_width + 20) - 20;
        if ($width < $this->min_width) $width = $this->min_width;
        $height = $width - 40;//$req->GetProp($this->object . "_height", $this->min_height);
    } else {
        $width = $req->GetProp("page_width", $this->min_width + 5) - 5;
        $height = $req->GetProp("page_height", $this->min_height);
        if ($width < $this->min_width) $width = $this->min_width;
        if ($height < $this->min_height) $height = $this->min_height;
    }

/*    print_r($this);
    echo "$width $height\n";
    exit;*/
    

    $rdr = $req->CreateReader();
    $group = $rdr->CreateGroup();
    $caches = $rdr->CreateCacheSet($group, $mask);


//    $iv = new INTERVAL($req->props);
    $iv = $caches->CreateInterval($req, true);
    $window_size = $iv->GetWindowSize();
    $window_start = $iv->GetWindowStart();
    $window_end = $iv->GetWindowEnd();

    $rescfg = array(
        'limit' => $this->max_points,
        'resolution' => $res
    );


    $gid = 0;
    $res = array();
    foreach ($caches as $key => $cachewrap) {
        if (($gid != $x_gid)&&($gid != $y_gid)) {
            $gid++;
            continue;
        }

        $resolution = $cachewrap->GetResolution();
        $r = $resolution->Get($iv, $width);
        
        $size = $resolution->GetWindowSize($r);
        if (($size > 0)&&(($window_size / $size) > $this->max_points)) {
            $new_r = $resolution->Larger($r);
            if ($new_r !== false) $r = $new_r;
            $size = $resolution->GetWindowSize($r);
        }
        
        $rescfg['resolution'] = $r;

	$points = $cachewrap->GetIntervals($iv, $rescfg, CACHE::TRUNCATE_INTERVALS);
        $operation_info = $points->GetOperationInfo();

        if ($gid == $x_gid) $res_x = $size;
        if ($gid == $y_gid) $res_y = $size;

	foreach($points as $t => $v) {
/*	    if (($t < $window_start)||(($t + $size) > $window_end)) {
	        continue;
	    }*/
            if (($gid == $x_gid)&&(is_numeric($v['mean'.$x_id]))) {
                if (!is_array($res[$t])) $res[$t] = array();
                $res[$t]['x'] = $v['mean'.$x_id];
            }
            if (($gid == $y_gid)&&(is_numeric($v['mean'.$y_id]))) {
                if (!is_array($res[$t])) $res[$t] = array();
                $res[$t]['y'] = $v['mean'.$y_id];
            }
        }
	$gid++;
    }

    $x = array();
    $y = array();
    
    foreach ($res as $val) {
        if ((isset($val['x']))&&(isset($val['y']))) {
            array_push($x, $val['x']);
            array_push($y, $val['y']);
        }
    }

    if (!$x) {
        throw new ADEIException(translate("No data found"));
    }

    $corr = stats_stat_correlation($x, $y);

    $tmp_file = ADEI::GetTmpFile();

    $graph = new Graph($width,$height);
    if ($res_x == $res_y) $title = "Resolution: $res_x";
    else $title = "Resolution: $res_x, $res_y";
    if (!$this->object)
        $title = "$title, " . date('c', $iv->GetWindowStart()) . " - " . date('c', $iv->GetWindowEnd());
    $graph->title->Set($title);
    $graph->SetTickDensity(TICKD_SPARSE, TICKD_SPARSE);
    $graph->img->SetMargin(55,5,10,20);
    
    $graph->SetScale("linlin");
    $graph->xaxis->SetPos("min");
    $graph->yaxis->SetPos("min");
    $graph->xaxis->SetLabelFormat('%3.1e');
    $graph->yaxis->SetLabelFormat('%3.1e');
    $graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,8);
    $graph->yaxis->SetFont(FF_ARIAL,FS_NORMAL,8);
    $graph->yaxis->HideFirstTickLabel();

    $sp = new ScatterPlot($x, $y);
    $graph->Add($sp);
/*
    $txt = new Text();
    $txt->SetFont(FF_ARIAL,FS_BOLD,11);
    $txt->Set(sprintf("R = %01.2f", $corr));
    $txt->SetPos(0.99,0.1,'right');
    $txt->SetBox('lightyellow');
    $graph->Add($txt); 
*/

    $graph->Stroke("$TMP_PATH/$tmp_file");

    if ($this->object) {
        return array(
            "img" => array("id" => $tmp_file),
            "div" => array("xml"=>
                "<b>Correlation</b>: " . $corr . "<br/>" .
                "<b>From</b>: " . date('c', $iv->GetWindowStart()) . "<br/>" .
                "<b>To</b>: " . date('c', $iv->GetWindowEnd()) . "<br/>"
            )
        );
    } else {
        return array(
            "img" => array("id" => $tmp_file)
        );
    }

 }
 
};

?>