<?php

class MUNINAxes extends GRAPHAxes {

    function __construct(REQUEST $props = NULL, $munin_axes) {
        parent::__construct($props);

        $this->req = $props;

        $this->default_axis = false;
        $this->axes = array();
        $this->aids = array();

        foreach($munin_axes as $axis_key => $axis)
        {
            $this->axis_info[$axis_key] = $axis;
        }
    }
}

//this class is used to iterate Munin data
class MUNINData implements Iterator {
    var $rdr;
    var $period;//the time difference of two datapoints in seconds
    var $from, $to;//timestamps for start and end of iterated data
    var $resample, $nextsample;
    var $pos;//current x-position (timestamp)
    var $opts;// a handle to readers configuration options

    var $items;//rrds in the data (mask)

    var $start;
    var $fetch;

    const DEFAULT_START = "May 18, 2011";

    public function __construct(RRDReader &$reader, OPTIONS &$opts, &$items, INTERVAL &$ivl, $resample) {
        $this->rdr = $reader;
        $this->opts = $opts;

        $start = NULL;
        $end = NULL;
        $minend = NULL;
        $step = NULL;
        
        //fetch to find out the start, end and step of the rrds(they should all have the same ones)
        //if not, then the start end will be the values that every rrd has data at
        //if steps are different this throws an exception
        for ($i = 0 ; $i < count($items) ; $i++) {
            $file = $this->rdr->rrd_folder . "/"  . $this->rdr->rrd_prefix . $items[$i]['db_uid'] . ".rrd";

	    $info = rrd_info($file);
	    $fetch = array(
		'start' => rrd_first($file),
		'end' => rrd_last($file),
		'step' => $info['step']
	    );
            
            if ($start === NULL) {
        	$start = $fetch['start'];
        	$end = $fetch['end'];
        	$minend = $fetch['end'];
                $step = $fetch['step'];
            } else {
        	if ($start > $fetch['start']) $start = $fetch['start'];
        	if ($end < $fetch['end']) $end = $fetch['end'];
        	if ($minend > $fetch['end']) $minend = $fetch['end'];
        	if ($step !== $fetch['step']) {
            	    throw new ADEIException(translate("File %s has an irregular data interval!", $file));
        	}
            }

        }

        if (!$step) {
    	    throw new ADEIException(translate("Can't determine data set for file %s", $file));
        }
        
    	    // Handling the case when we are executed while RRDs are updated
    	    // i.e. part of group is already has value, but other part is not yet updated
        if ($minend != $end) {
    	    $curtime = time();
    	    if (($curtime - $minend) < 2 * $step) $end = $minend;
        }


        $this->period = $step;

        $this->resample = $resample;

        $from =  $ivl->GetWindowStart();
        if ($this->from < $start) $this->from = $start;

        $this->to = $ivl->GetWindowEnd();
        if ($this->to > $end) $this->to = $end + 0.1;

        $limit = $ivl->GetItemLimit();//get interval's item limit, if any (defines how many datapoints to show and adjusts to or from based on that?)
        if ($limit) {
            if ($limit > 0) {
                $to = $this->from + $this->period * $limit;
                if ($to < $this->to) $this->to = $to;
            } else {
                $from = $this->to + $this->period * $limit;
                if ($from > $this->from) $this->from = $from;
            }
        }

        $this->pos = $this->from;

        $ifrom = ceil($from);//get start point as integer
        $rem = $ifrom % $this->period;//checks whether start point as integer is a multiple of period
        if ($rem) $this->from = $ifrom + ($this->period - $rem);//if not, add to start period - the remnants of the division
        else $this->from = $ifrom;//if yes, from is ifrom

        $this->items = &$items;//items to show (rra:s in this case)

        $limit = $opts->GetDateLimit($this::DEFAULT_START, time());//gets the window limits (as in, the first and last possible timestamp values)

        $this->start = $limit[0];
    }

    function doResample() {
        for ($next = $this->pos + $this->period;(($next < $this->to)&&($next < $this->nextsample));$next += $this->period)
            $this->pos = $next;

        $this->nextsample += $this->resample;
        if ($this->nextsample < $this->pos) {
            $add = ceil(($this->pos - $this->nextsample) / $this->resample);
            $this->nextsample += $add * $this->resample;
        }
    }

    function rewind() {
        $this->pos = $this->from;

        if ($this->resample) {
            $this->nextsample = $this->resample*ceil($this->pos / $this->resample);
            $this->doResample();
        }
    }

    function current() {

        $values = array();
        $good = 0;
        
        foreach($this->items as $item)
        {
            $file = $this->rdr->rrd_folder . "/" . $this->rdr->rrd_prefix . $item['db_uid'] . ".rrd";//construct filename

            $fetch_opts = array("AVERAGE", "--start", ($this->pos - $this->period), "--end", ($this->pos));

            $fetch = rrd_fetch($file, $fetch_opts);// count($fetch_opts));//fetch data from file

            foreach($fetch['data'] as $index => $data) {
        	$found = false;
                foreach ($data as $ts => $val) {
                    if ($ts === intval($this->pos)) {
                        if (strval($val) == "NAN") $val = NULL;//if the data value is NAN, convert it to NULL
                        else $good++;
                        
                        array_push($values, $val);
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
            	    array_push($values, NULL);
                }
                /* DS Fix
                	    if(($fetch['start'] + ($index + 1) * $fetch['step']) === intval($this->pos))//check if the data is from current time
                	    {
                		if(strval($data) == "NAN") $data = NULL;//if the data value is NAN, convert it to NULL
                		array_push($values, $data);
                	    }
                */
            }
        }

        return $values;
    }

    function key() {//this returns a timestamp for the current position
        $res = $this->pos;
        if (is_float($res)) return sprintf("%.8f", $res);
        return $res;
    }

    function next() {//moves position to next timestamp
        $this->pos += $this->period;

        if ($this->resample) $this->doResample();
    }

    function valid() {//checks whether this datapoint is valid  ( timestamp is smaller than the last one )
        return ($this->pos < $this->to);
    }
}

//this class is used to iterate general RRD data (not Munin)
class RRDData implements Iterator {

    var $file; //filename of the rrd-file that is iterated
    var $period;//the time difference of two datapoints in seconds
    var $from, $to;//timestamps for start and end of iterated data
    var $resample, $nextsample;
    var $pos;//current x-position (timestamp)

    var $items;//items in the data (mask)

    var $start;//what's this for?
    var $info;//contains information about the rrd

    const DEFAULT_START = "May 18, 2011";

    public function __construct(RRDReader &$reader, OPTIONS &$opts, &$items, INTERVAL &$ivl, $resample) {
        parent::__construct($props);

        $data_start = $opts->Get('data_start');
        $data_start = date("F j, Y", $data_start);

        $this->period = $opts->Get('step');

        $this->file = $reader->rrd_folder . "/" . $reader->rrd_prefix . $opts->Get('file') . ".rrd";

        $this->info = $reader::GetRRDInfo($opts->Get('file'), $reader); //get information on the rrd and its archives


        $this->resample = $resample;

        $from =  $ivl->GetWindowStart();

        $ifrom = ceil($from);//get start point as integer
        $rem = $ifrom % $this->period;//checks whether start point as integer is a multiple of period
        if ($rem) $this->from = $ifrom + ($this->period - $rem);//if not, add to start period - the remnants of the division
        else $this->from = $ifrom;//if yes, from is ifrom


        $this->pos = ($this->from);

        $this->to = $ivl->GetWindowEnd();

        $limit = $ivl->GetItemLimit();//get interval's item limit, if any (defines how many datapoints to show and adjusts to or from based on that?)

        if ($limit) {
            if ($limit > 0) {
                $to = $this->from + $this->period * $limit;
                if ($to < $this->to) $this->to = $to;
            } else {
                $from = $this->to + $this->period * $limit;
                if ($from > $this->from) $this->from = $from;
            }
        }

        $this->items = &$items;//items to show (rra:s in this case)

        //this sets the caching starting point based on the longest rra:s datapoints
        $limit = $opts->GetDateLimit($data_start, time());
        //$limit = $opts->GetDateLimit($this::DEFAULT_START, time());//gets the window limits (as in, the first and last possible timestamp values)

        $this->start = $limit[0];
    }

    function doResample() {
        for ($next = $this->pos + $this->period;(($next < $this->to)&&($next < $this->nextsample));$next += $this->period)
            $this->pos = $next;

        $this->nextsample += $this->resample;
        if ($this->nextsample < $this->pos) {
            $add = ceil(($this->pos - $this->nextsample) / $this->resample);
            $this->nextsample += $add * $this->resample;
        }
    }

    function rewind() {
        $this->pos = $this->from;

        if ($this->resample) {
            $this->nextsample = $this->resample*ceil($this->pos / $this->resample);
            $this->doResample();
        }
    }

    function current() {

        $current_timestamp = $this->pos;

        $info = $this->info;//info about the rrd-file
        $items = $this->items;//rra:s to show
        $from = $this->from;
        $to = $this->to;
        $file = $this->file;
        $period = $this->period;

        $values = array();//return array

        $item_keys = array();

        //get an array of item ids that should be in the return array
        foreach($items as $array)
        {
            foreach($array as $key => $value)
            {
                if ($key == "id")
                {
                    array_push($item_keys, $value);
                }
            }
        }

        foreach($info["rra"] as $key => $rra)//go through all rra:s
        {

            $datafound = false; //flag set to true if valid data is found on current timestamp

            if (array_search($key, $item_keys) === false)//if current rra is not in the iterators item list, it is skipped
            {
                continue;
            }

            $step = $info["info"]["step"] * $rra["pdp_per_row"]; //time difference of the current rra's datapoints in seconds
            $span = $step * $rra["rows"]; //time difference of first and last datapoint of the current rra

            //fetch options set in a way that it only returns data close to the current timestamp
            $fetch_opts = array($rra["cf"], "-r", $step, "--start", ($this->pos - ($step)), "--end", ($this->pos + ($step)));

            $fetch = rrd_fetch($file, $fetch_opts);// count($fetch_opts));//fetch rra data

            if ($fetch["step"] > $step)//sometimes the fetch returns data at bigger intervals than the current rra should, this fixes that
            {
                $fetch_opts = array($rra["cf"], "-r", $step, "--end",  ("N - " . ($span - 2 * $step)), "--start", ("N - " . $span));
                $fetch = rrd_fetch($file, $fetch_opts);// count($fetch_opts));
            }

            $count = count($fetch["data"]);//number of datapoints returned by fetch

            //this keeps track of that no rra returns values from before its beginning, needs a bit more work
            $first_timestamp = $to - ($info["info"]["step"] * $rra["pdp_per_row"] * $rra["rows"]);

            if ($first_timestamp > $current_timestamp)//if the current timestamp is not valid for the current rra we skip it
            {
                array_push($values, NULL);
                continue;
            }
            //go through all the data returned by fetch
            foreach($fetch["data"] as $index => $data)
            {

                if (strval($data) == "NAN") $data = NULL; //if there's no data, data is a float type with value NAN,
                //this makes no sense so we convert it to NULL

                //construct timestamp for the datapoint based on the datapoint index and the end timestamp returned by fetch
                $timestamp = ($fetch["end"] - (($count-($index + 2)) * $fetch["step"]));//+2 because rrd

                //if the current datapoints timestamp is valid
                if ($timestamp >= $first_timestamp && $timestamp <= $to)
                {
                    //if the current datapoint is the one we are interested in
                    if ($timestamp == $current_timestamp)
                    {
                        $value = $data;
                        $datafound = true;//data found for current timestamp,
                        //this makes the $value stay the same while going through the rest of the rra:s
                    }
                }
                else if (!$datafound)//if no data is found, $value is NULL
                {
                    $value = NULL;
                }

            }
            if (!$datafound && $value !== NULL)//in some cases if no data was found the value still wasn't NULL, this fixes that
            {
                $value = NULL;
            }

            if ($value !== NULL)//if there is a valid value, insert it into return array
            {
                array_push($values, $value);
            }
            else
            {
                //if there was no valid value for this timestamp in the rra,
                //(eg. if the rra data interval is 30 mins it only has values on every sixth period if the iterator period is 5 min)
                //this will fetch the previous valid value from the current rra
                $fetch_opts = array($rra["cf"], "-r", $step, "--start", ((floor($this->pos / $step) * $step) - ($step)), "--end", ((floor($this->pos / $step) * $step) + ($step)));

                $fetch = rrd_fetch($file, $fetch_opts);// count($fetch_opts));

                foreach($fetch["data"] as $index => $data)
                {
                    if (strval($data) == "NAN") $data = NULL;
                    $stamp = ($fetch["end"] - (($count-($index + 2)) * $fetch["step"]));

                    if ($stamp == (floor($this->pos / $step)) * $step)
                    {
                        $value = $data;
                    }
                }
                array_push($values, $value);
            }
        }

        return $values;
    }

    function key() {//this returns a timestamp for the current position
        $res = $this->pos;
        if (is_float($res)) return sprintf("%.8f", $res);
        return $res;
    }

    function next() {//moves position to next timestamp
        $this->pos += $this->period;

        if ($this->resample) $this->doResample();
    }

    function valid() {//checks whether this datapoint is valid  ( timestamp is smaller than the last one )
        return ($this->pos <= $this->to);
    }

}

class RRDReader extends READER {
    var $cache;

    var $items;//RRDs
    var $groups;//RRD-groups for Munin or RRDs for non Munin
    var $rrd_folder;
    var $rrd_prefix;
    var $munin_data;
    var $munin_datafile;
    var $munin_lockfile;
    var $munin_filter;
    var $axes;

    function __construct(&$props) {
        parent::__construct($props);

        $opts = $this->req->GetOptions();

        if ($this->dbname) {
            $munin = $opts->Get('munin', false);
            if ($munin) {
                $db = $this->server['database'];
                $this->rrd_folder = $opts->Get('rrd_folder', "{$munin}/{$db}");
                $this->rrd_prefix = $opts->Get('rrd_prefix', "{$db}-");
                $this->munin_datafile = $opts->Get('munin_datafile', "$munin/datafile");
                $this->munin_lockfile = $opts->Get('munin_lockfile', false);
                $this->munin_filter = "/^$db;/";
            } else {
                $this->rrd_folder = $opts->Get('rrd_folder', false);
                $this->rrd_prefix = $opts->Get('rrd_prefix', "");
                $this->munin_datafile = $opts->Get('munin_datafile', false);
                $this->munin_filter = false;

                if ($this->rrd_folder)
                    throw new ADEIException(translate("Folder with RRD files is not specified"));
            }

            $this->items = $this::ReadRRDs();//items holds all the RRD files

            if (empty($this->items)) {
                throw new ADEIException(translate("No files found in the specified folder %s", $this->folder));
            }
            if ($this->munin_datafile) {
                $this->groups = $this::GetMuninGroups();//read Munin file groups from the specified Munin datafile
                if (!$this->groups) throw new ADEIException("Can't parse munin groups");
                $this->axes = $this::CreateAxes();
            } else {
                $this->munin_datafile = false;

                if ($this->items) {
                    $this->groups = array();

                    foreach ($this->items as $gid)//go through the files
                    {
                        if ($names[$gid])//if the file had a human readable name, give the loggroup a name
                        {
                            $name = $names[$gid];
                        }
                        else//else the loggroups name will be it's group identifier (filename)
                        {
                            $name = false;
                        }
                        $this->groups[$gid] = array(
                                                  'name' => $name
                                              );
                    }
                }
            }
        }
        else
        {
        }
    }

    function GidToFilename($gid, $reader) {
        $opts = $reader->req->GetGroupOptions();
        return "{$this->rrd_folder}/{$this->rrd_prefix}{$gid}.rrd";
    }

//this function reads the RRD filenames from the folder specified in the rrd server configuration
//and returns them in an array
    function ReadRRDs()
    {
        $opts = $this->req->GetGroupOptions();

        //check if a regexp is defined for filtering the RRD files
        $exclude_mask = $opts->Get('rrd_exclude_files', false);
        $filemask = $opts->Get('rrd_file_mask', false);

        if (!$this->rrd_folder) {
            throw new ADEIException(translate("No RRD folder specified in config."));
        }

        if (!is_dir($this->rrd_folder)) {
            throw new ADEIException(translate("The RRD folder (%s) is not found", $this->rrd_folder));
        }

        $rrd_array = array();//return array


        if (chdir($this->rrd_folder))//change dir to specified folder
        {
            if ($filehandle = opendir($this->rrd_folder))//open folder
            {
                while (($filename = readdir($filehandle)) !== false)//read files in folder
                {
                    if (preg_match("/\.rrd$/", $filename) != 0)//if file is .rrd file
                    {
                        $filename = str_replace(".rrd", "", $filename);//strip the rrd suffix from the filename
                        $filename = str_replace($this->rrd_prefix, "", $filename);//strip the rrd prefix from the filename
                        if ($filemask) {
                            if (preg_match($filemask, $filename)) {
                        	if ($exclude_mask) {
                    		    if (!preg_match($exclude_mask, $filename)) {
                            		array_push($rrd_array, $filename);
                    		    }
                        	} else {
                            	    array_push($rrd_array, $filename);
                            	}
                            }
                        } else if ($exclude_mask) {
                    	    if (!preg_match($exclude_mask, $filename)) {
                                array_push($rrd_array, $filename);
                    	    }
                        } else {
                            array_push($rrd_array, $filename);
                        }
                    }
                }
                closedir($filehandle);//close folder
            }
        }

        asort($rrd_array);//sort the array by filename

        return $rrd_array;
    }

//this function gets information about an rrd-file
    function GetRRDInfo($gid, RRDReader $reader)//reader is passed here because apparently the call to this function from RRDDATA's constructor prevents the use of $this pointer here
    {
        $file = $reader::GidToFilename($gid, $reader);//construct the complete filename out of the group id

        $info = shell_exec("rrdtool info ". $file);//get info of the rrd into a string

        $array = explode("\n", $info);//make the string into an array
        $ds_array = array();//array with info about the rrd:s data source
        $rra_array = array();//array with info about the rrd:s archives(rra)
        $info_array = array();//general info about the rrd
        $return_array = array();//an array that will contain the other arrays

        foreach($array as $data)//go through the data returned by rrdtool info and sort the rows into correct arrays
        {
            $key = substr($data, 0, strpos($data , " = "));
            $value = str_replace("\"", "", (substr($data, strpos($data, " = ") + 3)));
            if (preg_match("/^ds\[/", $key))
            {
                $ds_key = substr($key, (strpos($key, "[") + 1), (strpos($key, "]") - 3));
                $ds_array_key = substr($key, (strpos($key, ".") + 1));
                $ds_array_value = $value;
                $ds_array[$ds_key][$ds_array_key] = $ds_array_value;
            }
            else if (preg_match("/^rra\[/", $key))
            {
                $rra_key = substr($key, (strpos($key, "[") + 1), (strpos($key, "]") - 4));
                $rra_array_key = substr($key, strpos($key, ".") + 1);
                $rra_array_value = $value;
                $rra_array[$rra_key][$rra_array_key] = $rra_array_value;
            }
            else if ($key != "0")
            {
                $info_array[$key] = $value;
            }
        }
        $info_array["start"] = "";//start and end are left empty here and filled in with rrd_fetch in GetGroupInfo()
        $info_array["end"] = "";//they are only filled in GetGroupInfo() if REQUEST::NEED_INFO flag is given in the function call


        $return_array["info"] = $info_array;
        $return_array["ds"] = $ds_array;
        $return_array["rra"] = $rra_array;

        return $return_array;
    }

//this function reads the Munin groups from the  specified datafile and returns them in an array containing their id and name
    function GetMuninGroups() {
        $opts = $this->req->GetGroupOptions();

        if ($this->munin_lockfile) {
    	    $lock_started = time();
    	    do {
    		if (time() > ($lock_started + $this->lock_timeout)) {
    		    throw new ADEIException(translate("Can't obtain munin datafile lock (%s)", $this->munin_lockfile));
    		}
    		$lockf = @fopen($this->munin_lockfile, "x");
    		usleep(100);
    	    } while ($lockf === false);
    	    fwrite($lockf, getmypid());
	    fclose($lockf);
	    $this->munin_data = file_get_contents($this->munin_datafile);
	    unlink($this->munin_lockfile);
        } else {
	    $this->munin_data = file_get_contents($this->munin_datafile);
	}
        $this->munin_data = explode("\n", $this->munin_data);//make the string into an array of rows
	$info = @$this->munin_data;

        //this strips the setup dependant prefix from the datafile rows
        //(first row has version number and no prefix so we skip that)
        /*        foreach($info as $key => &$line)
                {
                    if ($key !== 0)
                    {
                        $line = str_replace(substr($line, 0, strpos($line, ":") + 1), "", $line);
                    }
                }*/

        //find all datafile rows that define a graph title and get the group ids and names and axis info from those rows
        $group_key = false;
        foreach($info as $key => $line)
        {
            if ($this->munin_filter) {
                if (!preg_match($this->munin_filter, $line)) continue;
            }

            if ($key !== 0) {
                $row = str_replace(substr($line, 0, strpos($line, ":") + 1), "", $line);
            } else {
                $row = $line;
            }

            if (substr_count($row, ".graph_title") !== 0) {
                $group_key = substr($row, 0, strpos($row, ".graph"));
                if (!isset($group_array[$group_key])) $group_array[$group_key] = array();
                
                $group_value =  str_replace(substr($row, 0, (strpos($row, " ") + 1)), "", $row);

                $group_array[$group_key] = array_merge($group_array[$group_key],array(
                    "gid" => $group_key,
                     "name" => $group_value
		));
            }

            if (substr_count($row, ".graph_category") !== 0) {
                $group_key = substr($row, 0, strpos($row, ".graph"));
                if (!isset($group_array[$group_key])) $group_array[$group_key] = array();
                
                $cat =  str_replace(substr($row, 0, (strpos($row, " ") + 1)), "", $row);

                $group_array[$group_key] = array_merge($group_array[$group_key],array(
                     "category" => $cat
        	));
            }

            if (substr_count($row, ".graph_args") !== 0 && strstr($row, $group_key)) {
                $group_key = substr($row, 0, strpos($row, ".graph"));

                $args = explode(" ", substr($row, strpos($row, " ")));

                $log = NULL;
                $min = NULL;
                $max = NULL;

                if (array_search("--logarithmic", $args)) $log = true;
                if (array_search("--lower_limit", $args)) $min = $args[array_search("--lower_limit", $args) + 1];
                if (array_search("--upper_limit", $args)) $max = $args[array_search("--upper_limit", $args) + 1];

                if ($log) $mode = "LOG";
                else $mode = "STD";
                if ($min||$max) $range = array($min, $max);
                else $range = false;

                if (!isset($opts->options['axes_table'][$group_key])) $opts->options['axes_table'][$group_key] = array();
                $opts->options['axes_table'][$group_key] = array_merge($opts->options['axes_table'][$group_key],array(
                    "axis_units" => false,
                    "axis_mode" => $mode,
                    "axis_range" => $range
        	));
            }

            if (substr_count($row, ".graph_vlabel") !== 0 && strstr($row, $group_key)) {
                $group_key = substr($row, 0, strpos($row, ".graph"));
                $label = str_replace(substr($row, 0, (strpos($row, " ") + 1)), "", $row);
                $label = str_replace("\${graph_period}", "second", $label);

                if (!isset($opts->options['axes_table'][$group_key])) $opts->options['axes_table'][$group_key] = array();
                $opts->options['axes_table'][$group_key] = array_merge($opts->options['axes_table'][$group_key],array(
                    "axis_name" => $label,
        	));
            }
        }
        
        $emask = $opts->Get('rrd_exclude_files', false);
        $imask = $opts->Get('rrd_file_mask', false);
        if (($imask||$emask)) {
    	    foreach(array_keys($group_array) as $gid) {
    		if ($emask) {
    		    if (preg_match($emask, $gid)) {
    			unset($group_array[$gid]);
    			continue;
    		    }
    		}
    		if ($imask) {
    		    if (!preg_match($imask, $gid)) {
    			unset($group_array[$gid]);
    			continue;
    		    }
    		}
    	    }
        }

        foreach ($group_array as $gid => &$grp) {
    	    if ($grp['category']) {
    		$grp['name'] = "{$grp['category']}: {$grp['name']}";
    	    }
        }
        
        
        uasort($group_array, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

        return $group_array;
    }

    function ParseSeconds($seconds)//parses seconds into more readable formats (because no one knows how much eg. 3338000 seconds is)
    {
        if ((($seconds % (3600 * 24)) == 0) && (($seconds / (3600 / 24) >= 1)))
        {
            $return = ($seconds / (3600 * 24)) . "d";
        }
        else if ((($seconds % 3600) == 0) && (($seconds / 3600) >= 1))
        {
            $return = ($seconds / 3600) . "h";
        }
        else if ((($seconds % 60) == 0) && (($seconds / 60) >= 1))
        {
            $return = ($seconds / 60) . "min";
        }
        else
        {
            $return = $seconds . "s";
        }
        return $return;
    }

//function to simplify calling rrd_fetch, mainly because the complete file need to be constructed every time fetch is called
    function Fetch($gid, $fetch_opts)
    {
        $file = $this::GidToFilename($gid, $this);

        $return = rrd_fetch($file, $fetch_opts);//, count($fetch_opts));

        return $return;
    }

    function CreateAxes($flags = 0) {
        if ($this->axes) return $this->axes;

        $axes_table = $this->req->GetGroupOptions()->options['axes_table'];

        if ($axes_table) {

            $this->axes = new MUNINAxes($this->req, $axes_table);//try to change this to GRAPHAxes and then something...

            return $this->axes;
        } else {
            return parent::CreateAxes($flags);
        }
    }


    function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
        $groups = array();//array for the groups to be returned

        if ($this->munin_datafile)//Munin datafile specified
        {
            foreach ($this->groups as $gid => &$group)//if there are groups, go through them
            {
                if (($grp)&&(strcmp($grp->gid, $gid))) continue;//if a group is specified and existing group has same group id then skip this group

                if ($group['name']) $name = $group['name'];//if this group has a name, name = group name
                else $name = false;//else name = false

                if ((!$name)||($flags&REQUEST::NEED_INFO)) {//if there was no name and there's a NEED_INFO flag
                    if ($grp) {//if group is specified
                        $grtest = $grp;
                        $opts = $this->opts;
                    } else {//no group is specified (easier to just throw error here since there can't really be a default .rrd-file)?
                        $ginfo = array("db_group" => $gid);//create new groupinfo array
                        $grtest = $this->CreateGroup($ginfo);//create new group
                        $opts = $this->req->GetGroupOptions($grtest);//create new options for group
                    }

                    if (!$name) {//if name = false
                        $name = $opts->Get('name', $gid);//get name from options, if there's no name use group id
                    }
                }

                $groups[$gid] = array(//set values into group array at index group id
                                    'gid' => $gid,
                                    'name' => $name
                                );

                if ($flags&REQUEST::NEED_INFO) {
            	    $tmp_group = new LOGGROUP($tmp_groupid=array("db_group" => $gid));
                    $items = $this::GetItemList($tmp_group);//change to getitemlist
                    $rrd_fetch_opts = array("AVERAGE");
                    
                    $min = false; $max = false;
		    foreach ($items as $item) {
                	$rrd_file = $this::GidToFilename($item['db_uid'], $this);
                	if ($min === false) {
                	    $min = rrd_first($rrd_file);
                	    $max = rrd_last($rrd_file);
                	} else {
                	    $first = rrd_first($rrd_file);
                	    if ($min > $first) $min = $first;
                	    $last = rrd_last($rrd_file);
                	    if ($max < $last) $max = $last;
                	}
            	    }
                    $groups[$gid]['first'] = $min;
                    $groups[$gid]['last'] = $max;

                    if ($flags&REQUEST::NEED_COUNT) {//if there's a NEED_COUNT flag
			$fetch = rrd_info($file);
                        $record_num = ( (  $groups[$gid]['last'] - $groups[$gid]['first'] ) / $fetch['step'] );//calculate number of records

                        $groups[$gid]['records'] = $record_num;
                    }

                    if ($flags&REQUEST::NEED_ITEMINFO) {//if there's a NEED_ITEMINFO flag
                        $groups[$gid]['items'] = $this->GetItemList($grp);//get itemlist for specified group
                    }
                }
            }//went through existing groups
        }
        else//no Munin datafile
        {
            if (!$this->groups) return false;
            
            foreach ($this->groups as $gid => &$group)//if there are groups, go through them
            {
                if (($grp)&&(strcmp($grp->gid, $gid))) continue;//if a group is specified and existing group has same group id then skip this group

                if ($group['name']) $name = $group['name'];//if this group has a name, name = group name
                else $name = false;//else name = false

                if ((!$name)||($flags&REQUEST::NEED_INFO)) {//if there was no name and there's a NEED_INFO flag
                    if ($grp) {//if group is specified
                        $grtest = $grp;
                        $opts = $this->opts;
                    } else {//no group is specified (easier to just throw error here since there can't really be a default .rrd-file)?
                        $ginfo = array("db_group" => $gid);//create new groupinfo array
                        $grtest = $this->CreateGroup($ginfo);//create new group
                        $opts = $this->req->GetGroupOptions($grtest);//create new options for group
                    }

                    if (!$name) {//if name = false
                        $name = $opts->Get('name', $gid);//get name from options, if there's no name use group id
                    }
                }

                $groups[$gid] = array(//set values into group array at index group id
                                    'gid' => $gid,
                                    'name' => $name
                                );

                if ($flags&REQUEST::NEED_COUNT)//if there's a NEED_COUNT flag
                {
                    $rrd_fetch_opts = array("AVERAGE");
                    $fetch = $this::Fetch($gid, $rrd_fetch_opts);

                    $record_num = ( ( $fetch['end'] - $fetch['start'] ) / $fetch['step'] );

                    $groups[$gid]['records'] = $record_num;
                }

                if ($flags&REQUEST::NEED_INFO) {//if there's a NEED_INFO flag

                    $rrd_fetch_opts = array("AVERAGE");
                    $fetch = $this::Fetch($gid, $rrd_fetch_opts);
                    $groups[$gid]['first'] = $fetch['start'];
                    $groups[$gid]['last'] = $fetch['end'];

                    if ($flags&REQUEST::NEED_COUNT) {//if there's a NEED_COUNT flag
                        $record_num = ( ( $fetch['end'] - $fetch['start'] ) / $fetch['step'] );//calculate number of records

                        $groups[$gid]['records'] = $record_num;
                    }

                    if ($flags&REQUEST::NEED_ITEMINFO) {//if there's a NEED_ITEMINFO flag
                        $groups[$gid]['items'] = $this->GetItemList($grtest);//get itemlist for specified group
                    }
                }
            }//went through existing groups
        }
        if (($grp)&&(!$groups)) {//if group is specified and there are no defined groups
            throw new ADEIException(translate("Invalid group (%s) is specified", $grp->gid));
        }

        return $grp?$groups[$grp->gid]:$groups;//if group was specified, return group in groups table at index group id, else return groups table
    }

    function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {//gets list of specified items in the specified group, returns an array containing item id, uid and name(here maybe rra index,

        $grp = $this->CheckGroup($grp, $flags);//check if the group is valid
        if (!$mask) $mask = $this->CreateMask($grp, $info=NULL, $flags);//if there's no mask, create a new mask with default settings
        $opts = $this->req->GetGroupOptions();

        $gid = $grp->gid;

        $res = array();//return array

        if ($this->munin_datafile)//if there's Munin datafile
        {
            $groups = $this->items;//contains all RRDs in the specified RRD folder

            $info = @$this->munin_data;

            $res = array();//array for the items of this Munin group

	    $item_ordered_ids = array();
	    $item_all_ids = array();
            $item_ids = array();//array for identifiers of this groups items
            $filter_ids = array();// array of identifiers we have already seen
	    $index = 0;
	    $string = false;
	    
	    $item_name = array();
	    $item_info = array();
//	    $item_suffix = array();	// name suffixes
	    $item_draw = array();	// draw mode
	    $item_negative = array();	// double (+/-) graphs


	    $gidlen = strlen($gid);
            foreach($info as $key => $line)
            {
		if ($this->munin_filter) {
		    if (!preg_match($this->munin_filter, $line)) continue;
        	}

                if ($key !== 0) {
                    $row = str_replace(substr($line, 0, strpos($line, ":") + 1), "", $line);
                } else {
            	    $row = $line;
                }
                
		    //find the datafile row that defines the RRDs in this group
                if (!strncmp($row, "{$gid}.graph_order", $gidlen + 12)) {
                    $array = explode(" ", substr($row, (strpos($row, " ") + 1)));

                    foreach($array as $index => $value)//go through the RRDs of this group
                    {
                        if (!empty($value)) {
                            array_push($item_ordered_ids, $value);
                        }
                    }
                } else if (!strncmp($row, "{$gid}.", $gidlen + 1) && !strstr($row , "graph_")) {
		    $tmp = substr(strstr($row, "{$gid}."), strlen($gid) + 1);
		    $spacepos = strpos($tmp, " ");
		    if ($spacepos === false) continue;
		    $dotpos = strrpos($tmp, ".", $spacepos - strlen($tmp));
		    if ($dotpos === false) continue;
		    if (strrpos($tmp, ".", $dotpos - strlen($tmp) - 1)) continue;

		    $last_string = $string;
            	    $string = substr($tmp, 0, $dotpos);
		    
//		    if (strstr($tmp, "per device")) $item_suffix[$string] = " ($string)";
		    if (strstr($tmp, ".label")) $item_name[$string] = substr($tmp, $spacepos + 1);
		    else if (strstr($tmp, ".info")) $item_info[$string] = substr($tmp, $spacepos + 1);
		    else if (strstr($tmp, ".draw")) $item_draw[$string] = substr($tmp, $spacepos + 1);
		    else if (strstr($tmp, ".negative")) {
			$item_negative[$string] = true;
			$item_negative[substr($tmp, $spacepos + 1)] = $string;
		    }

                    if ($string != $last_string) {
                	$filter_ids[$string] = array_push($item_all_ids, $string) - 1;
                    }

                }
            }

            foreach ($item_ordered_ids as $value) {
        	$pos = $filter_ids[$value];
        	if (is_int($pos)) {
        	    array_push($item_ids, $value);
        	    unset($item_all_ids[$pos]);
        	}
            }
            
            foreach ($item_all_ids as $value) {
        	if (!in_array($value, $item_ids)) {
        	    array_push($item_ids, $value);
        	}
            }
            
            foreach($item_ids as $id => $value)
            {
                if (($mask)&&(!$mask->Check($id))) continue;
		
            	    // Look up RRD for this item, to prevent Fan1 matching Fan10, we expect '-' after name
                $string = (str_replace(".", "-", $gid) . "-" . $value . "-"); 
                foreach($groups as $rrd) {
                    if (substr_count($rrd, $string) !== 0) break;
                }
                
		$draw_mode = isset($item_draw[$value])?$item_draw[$value]:"LINE";
		if ($draw_mode) {
            	    if (strstr($draw_mode, "LINE")) $draw_mode = "LINE";
                }
                
                $suffix = "";
                $val = $value;
                if (isset($item_negative[$value])) {
            	    if (is_string($item_negative[$value])) $val = $item_negative[$value];
            	    $pos = strrpos($value, "_");
            	    if ($pos !== false) $suffix = " (" . substr($value, $pos + 1) . ")";
            	    else $suffix = " ($value)";
                }
                $name = (isset($item_name[$val])?($item_name[$val] . $suffix):$val);


                array_push($res, array(
		    'id' => $id,
//		    'name' => (isset($item_name[$value])?($item_name[$value] . (isset($item_suffix[$value])?$item_suffix[$value]:"")):$value),
		    'name' => $name,
		    'description' => (isset($item_name[$value])?$item_name[$value]:$value),
		    'axis' => $gid,
		    'db_uid' => $rrd,
		    'draw_mode' => $draw_mode
		));
            }
        }
        else//no Munin file
        {
            $info = $this::GetRRDInfo($gid, $this);

            foreach($info["rra"] as $key => $rra)//go through all af the archive info on current rrd-file and construct names for the archives
            {
                if ($mask)
                {
                    if (!$mask->Check($key)) continue;
                }//if rra key wasn't in the mask, skip it

                $step = $info["info"]["step"];
                $filename = $info["info"]["filename"] . "_";
                $string = "RRA" . $key;
                $string .= "_" . $rra["cf"];
                $string .= "_SPAN";
                $span = (( $step ) * $rra["pdp_per_row"] * $rra["rows"]);
                $string .= $this::ParseSeconds($span);
                $string .= "_INTERVAL";
                $interval = ( $step ) * $rra["pdp_per_row"];
                $string .= $this::ParseSeconds($interval);
                $uid = str_replace("\"", "", ($filename . $string));
                $res[$key]["id"] = $key;
                $res[$key]["name"] = str_replace("\"", "", $string);
                $res[$key]["db_uid"] = $uid;

                $string = "";
            }
        }

        return $res;//returns an array containing uid, id and name of the item
    }

    function GetRawData(LOGGROUP $grp = NULL, $from = 0, $to = 0, DATAFilter $filter = NULL, &$filter_data = NULL) {//get iterator for raw data of rrd:s archives
        $grp = $this->CheckGroup($grp);//checks if the group is valid

        $ivl = $this->CreateInterval($grp);//creates a data interval for the group

        if ($filter)//if there is a filter, set filter options for the current data
        {
            $mask = $filter->GetItemMask();
            $resample = $filter->GetSamplingRate();
            $limit = $filter->GetVectorsLimit();
            if ($limit) $ivl->SetItemLimit($limit);//if item limit was specified for the filter, then set item limit for interval

            if (isset($filter_data)) {
                if ($mask) $filter_data['masked'] = true;
                if ($resample) $filter_data['resampled'] = true;
                if ($limit) $filter_data['limited'] = true;
            }
        }
        else//if there's no filter
        {
            $mask = NULL;
            $resample = 0;
            $limit = 0;
        }

        $ivl->Limit($from, $to);//set interval timelimit

        $opts = $this->req->GetGroupOptions($grp);//get options for group

	$null_mask = new MASK($mreq = array());
        $items = $this::GetItemList($grp, $null_mask);//get list of items for current group

        if (($mask)&&(is_array($ids = $mask->GetIDs())))//if there's a mask and you can get an array of ids from that mask
        {
            $tmp = array();
            foreach ($ids as $id)//go through all ids of the mask
            {
                if ($id >= sizeof($items))//if id goes out of bounds of items array, throw an error
                {
                    throw new ADEIException(translate("Invalid item mask is supplied. The ID:%d refers non-existing item.", $id));
                }
                array_push($tmp, $items[$id]);// create a new items array that contains just the items specified by the mask
            }
            $items = $tmp;
        }

        if ($this->munin_datafile) {
            return new MUNINData($this, $opts, $items, $ivl, $resample);
        } else {
            $gid = $grp->gid;

            $info = $this::GetRRDInfo($gid, $this);//get rrd info for the current rrd-file

            $step = $info["info"]["step"];


            //get the minimum and maximum interval between two archive datapoints in seconds
            $min_itv = 0;
            $max_itv = 0;
            $max_itv_index = -1;
            foreach($info["rra"] as $key => $rra)
            {
                if ($mask)
                {
                    if (!$mask->Check($key)) continue;
                }

                $itv = $info["info"]["step"] * $rra["pdp_per_row"];

                if ($itv > $max_itv)
                {
                    $max_itv = $itv;
                    $max_itv_index = $key;
                }
                if ($min_itv === 0)
                {
                    $min_itv = $itv;
                }
                else if ($itv < $min_itv)
                {
                    $min_itv = $itv;
                }
            }

            //passing a few parameters in options to RRDData
            $opts->options[count($opts->options)]['step'] = $min_itv;
            $opts->options[count($opts->options)]['file'] = $gid;
            $opts->options[count($opts->options)]['data_start'] = (time() - ($max_itv * $info["rra"][$max_itv_index]["rows"]));
            
            return new RRDData($this, $opts, $items, $ivl, $resample);// return raw data
        }
    }

    function HaveData(LOGGROUP $grp = NULL, $from = 0, $to = 0) {//checks whether file has data or not
        $grp = $this->CheckGroup($grp);//checks if group is valid

        $ivl = $this->CreateInterval($grp);//creates interval
        $ivl->Limit($from, $to);//set limits for interval

        $period = $this->req->GetGroupOption('period', $grp);//gets period of the records

        $from = $ivl->GetWindowStart();
        $to = $ivl->GetWindowEnd();

        if (($from - $to) > 2 * $period)
        {
            $return = true;
        }
        else
        {
            $return = false;
        }
        return $return;
    }
}

?>