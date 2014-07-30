<?php

require_once($ADEI_ROOTDIR . "/classes/jpgraph.php");

class histogramview extends VIEW {
    var $title = "Histogram";

    function __construct(REQUEST $req  = NULL, $opts) {
        //$this->shelldisplay = false;
        parent::__construct($req, $opts);
        $this->object = $this->req->GetProp("view_object", false);

        if ($this->object)
            $this->max_points = $this->GetOption('max_points', 500);
        else
            $this->max_points = $this->GetOption('max_points', 5000);

        $this->min_width = $this->GetOption('min_width', 300);
        $this->min_height = $this->GetOption('min_height', 300);

	$this->num_bins = $this->GetOption('bins', array(0));
    }

    function GetOption($opt, $default = false) {
	if (isset($this->options[$opt])) return $this->options[$opt];
	return $default;
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
        foreach ($caches as $key => $cachewrap) {
            array_push($result, array("label" => $cachewrap->GetGroupTitle(), "disabled" => 1));

            $id = 0;
            $list = $cachewrap->GetItemList();
            foreach ($list as $id => $info) {
                $title = $info['name'];
                array_push($result, array("value" => "$gid:$id", "label" => "  $title"));
                $id++;
            }
            $gid++;
        }
	
	$bins = array();
	foreach ($this->num_bins as $bin) {
	    array_push($bins, array("value" => $bin, "label" => ($bin?$bin:"Auto")));
	}

        $checkboxNorm = array("label" => _("Normalize"), "id" => "hist_norm");
        if ($req->GetProp("view_hist_norm", 0)) $checkboxNorm["checked"] = "checked";

        $checkboxGFit = array("label" => _("Gaussian Fit"), "id" => "hist_fit");
        if ($req->GetProp("view_hist_fit", 0)) $checkboxGFit["checked"] = "checked";

        return array(
                   array("select" => array("id" => "x", "label" => _("x"), "options" => $result)),
                   array("select" => array("id" => "bins" , "label" => _("Bins"),  "options" => $bins)),
                   array("checkbox" => $checkboxNorm),
                   array("checkbox" => $checkboxGFit)
	);
    }

    function GetView() {
        global $TMP_PATH;

        $req = $this->req->CreateDataRequest();
        $x = $req->GetProp("view_x", false);

        if (!$x) throw new ADEIException(translate("Parameter view_x is not set"));

        list($x_gid, $x_id) = explode(":", $x);

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


        $rdr = $req->CreateReader();
        $group = $rdr->CreateGroup();
        $caches = $rdr->CreateCacheSet($group, $mask);


        $myreq = $this->req->CreateDataRequest();
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
            if (($gid != $x_gid)) {
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


            foreach($points as $t => $v) {
                /*	    if (($t < $window_start)||(($t + $size) > $window_end)) {
                	        continue;
                	    }*/
                if (($gid == $x_gid)&&(is_numeric($v['mean'.$x_id]))) {
                    if (!is_array($res[$t])) $res[$t] = array();
                    $res[$t]['x'] = $v['mean'.$x_id];
                    $res[$t]['t'] = $t;
                }
            }
            $gid++;
        }

        $x = array();
        $t = array();

        foreach ($res as $val) {
            if (isset($val['x'])) array_push($x, $val['x']);
        }

        if (!$x) {
            throw new ADEIException(translate("No data found"));
        }
        
        $bins = $req->GetProp("view_bins", 0);
        if (!$bins) $bins = ceil(sqrt(sizeof($x)));
	
	$norm = $req->GetProp("view_hist_norm", 0);
	$fit = $req->GetProp("view_hist_fit", 0);


        $min = min($x);
        $max = max($x);
        $step = ($max - $min) / $bins;
        
        $coef = $norm?(1/($step * sizeof($x))):1;

	$h = array_fill(0, $bins, 0); 
        foreach ($x as $val) {
    	    $idx = ($val - $min)/$step;
    	    if ($idx == $bins) $idx--;
    	    $h[$idx] += $coef;
        }

        for($i = 0 ; $i < $bins ; $i++)
            array_push($t, sprintf("%3.1e", $min + $i*$step));

        $tmp_file1 = ADEI::GetTmpFile();

        $graph = new Graph($width,$height);
/*        $title = "Resolution: $res_x";

        $graph->title->SetFont(FF_ARIAL,FS_BOLD,10);
        $graph->title->Set($title);*/
        $graph->SetTickDensity(TICKD_SPARSE, TICKD_SPARSE);
        $graph->img->SetMargin(55,5,10,20);

        $graph->SetScale("textlin");
        $graph->xaxis->SetPos("min");
        $graph->yaxis->SetPos("min");
//        $graph->xaxis->SetLabelFormat('%3.1e');
//        if (abs(max($h))<9999 && (abs(min($h))>0.01)) $graph->yaxis->SetLabelFormat('%01.2f');
//        else $graph->yaxis->SetLabelFormat('%3.1e');
        $graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,8);
        $graph->yaxis->SetFont(FF_ARIAL,FS_NORMAL,8);
//        $graph->yaxis->HideFirstTickLabel();

        $graph->xaxis->title->SetFont(FF_ARIAL,FS_BOLD);
        $graph->yaxis->title->SetFont(FF_ARIAL,FS_BOLD);

        //$graph->xaxis->title->Set($arr[0]['select']['options'][$x_idg]['label']);
        if($bins > 8) $graph->xaxis->SetTextLabelInterval(ceil(($bins / 6)));
        $graph->xaxis->SetTickLabels($t);

        $bplot = new BarPlot($h);

        $bplot->SetWidth(1);
        $graph->Add($bplot);

        $graph->yaxis->scale->SetGrace(14);

        $mean = array_sum($x)/sizeof($x);
        $stddev = stats_standard_deviation($x);
        $var = stats_variance($x);
        $sigma = sqrt($var);
        $re = 100 * $sigma/$mean;
        
        sort($x);
        if (sizeof($x) % 2) 
    	    $median = $x[(sizeof($x) - 1)/2];
    	else
    	    $median = ($x[sizeof($x)/2 - 1] + $x[sizeof($x)/2])/2;


	    // Gaussian fitting
	if ($fit) {
            $ydata = array();
            $xdata = array();
            if ($norm) $coef = (1/(sqrt(2 * pi() * $var)));
            else $coef = ((sizeof($x)*$step)/(sqrt(2 * pi() * $var)));
            
            $xi2 = 0;
            for($i = 0; $i <= $bins; $i++) {
        	$offset = $i * $step;
        	$y = $coef * exp(-pow($min + $offset - $mean, 2)/(2*$var));
                array_push($xdata, $i);
        	array_push($ydata, $y);
                $xi2 += (pow($y - $h[$i], 2)) / $y;
    	    }
    	    $xi2 /= $bins;

            $lineplot = new LinePlot($ydata , $xdata);
            $graph->Add($lineplot);
        }

        $char_sigma = SymChar::Get('sigma', false);

/*
        $txt = new Text();
        $txt->SetFont(FF_ARIAL,FS_BOLD,10);
        if( $req->GetProp("view_GFit", false) == "true")
            $txt->Set("m=$mean\n$char_sigma=$sigma\nRE=$RE%\nx^2=$xi2");//\ns=$stdDev
        else
            $txt->Set("m=$mean\n$char_sigma=$sigma\nRE=$RE%");//\ns=$stdDev
        $txt->ParagraphAlign('right');
        $txt->SetPos(0.96,0.1,'right');
        //$txt->SetBox('white');
        $graph->Add($txt);
*/

        $graph->Stroke("$TMP_PATH/$tmp_file1");

        if ($this->object) {
            $res = array(
                array("img" => array("id" => $tmp_file1)),
                array("info" => array(
            	    array("title"=>_("From"), "value" => date('c', $iv->GetWindowStart())),
            	    array("title"=>_("To"), "value" => date('c', $iv->GetWindowEnd())),
            	    array("title"=>_("Resolution"), "value" => $res_x),
            	    array("title"=>_("Bins"), "value" => $bins),
            	    array("title"=>_("First Bin"), "value" =>  $min),
		    array("title"=>_("Last Bin"), "value" =>  $min + $bins * $step),
            	    array("title"=>_("Mean"), "value" => $mean),
            	    array("title"=>_("Median"), "value" => $median),
            	    array("title"=>_("StdDev"), "value" => $stddev),
            	    array("title"=>_("Sigma"), "value" => $sigma),
            	    array("title"=>_("RE"), "value" => ($re . "%")),
            	))
    	    );
            if ($fit) {
    		array_push($res[1]["info"], array("title"=>_("xi2"), "value" => $xi2));
            }
            return $res;
        } else {
            return array(
		"img" => array("id" => $tmp_file)
            );
        }

    }

};

?>
