<?php

/**
* CouchDB data class
*
* returns current dataset and will be iterated till end of timestamp
*
* Iterations stages:
* rewind -> valid -> key -> current -> valid -> (output) ->
* next   -> valid -> key -> current -> valid -> (output) ->
* next   -> valid -> key -> current -> valid -> (output) ->
* ...
* next   -> valid -> valid
*
*/
class COUCHData implements Iterator {
    
    /**
     * class constructor
     *
     */
    function __construct($view_url, $alldata_view, $opts, $items, $ivl) {
       
        # indicate wheter timeStamp is available
        $this->timeStamp = false;
        $this->hasNextData = false;
        $this->hasNextPacket = false;
        # these start and end keys will change dynamically
        $this->startKey;
        $this->endKey;

        # $this->defaultStart = 1313023285;
        # $this->defaultEnd = 1313023446;
        $this->defaultStart = $ivl->GetWindowStart();
        $this->defaultEnd = $ivl->GetWindowEnd();

        # items list
        $this->items = $items;
        $this->alldata_view = $alldata_view;
        $this->view_url = $view_url;

        $this->counter = 0;

        $this->buffer = array();
        $this->pointer = 10000;
        $this->packetSize = 10000;

        # create and reuse
        $this->ch = curl_init();

	curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
            'Content-type: application/json',
	    'Accept: */*'
	));

        $this->descending = "false";
        $this->itemLimit = false;
        $this->db_mask = $opts->props['db_mask'];
        $this->resample = $opts->props['resample'];

        if ( $ivl->item_limit ) {
            if ( $ivl->item_limit < 0 ) {
                $this->pointer = abs($ivl->item_limit);
                $this->packetSize = abs($ivl->item_limit);
                $this->descending = "true";
                $this->defaultStart = $ivl->GetWindowEnd();
                $this->defaultEnd = $ivl->GetWindowStart();
                $this->itemLimit = true;
            }
        }
    }

    /*
    * helper method to rewind the timestamp to default start
    *
    * default start is the first timestamp in the database
    */ 
    function rewind() {
        $this->timeStamp = $this->defaultStart;
    }

    /*
    * helper method to show the current data set
    *
    * @return array 
    * Array([0] => -0.89309238625218
    *       [1] => -0.44987330396278)
    */
    function current() {
        try {
            if ( $this->pointer >= ($this->packetSize - 1) ) {
                if ( $this->resample ) {
                    $this->buffer = array();
                    for ($i = 0; $i <= $this->packetSize; $i++) {
                        if ( $this->itemLimit ) {
                            if ($this->timeStamp < $this->defaultEnd ) break;
                        } else {
                            if ($this->timeStamp > $this->defaultEnd ) break;
                        }
                        $queryString = implode("&", array("startkey=".floor($this->timeStamp),
                                                         "endkey=".$this->defaultEnd,
                                                         "limit=1",
                                                         "descending=".$this->descending));
                        curl_setopt($this->ch, CURLOPT_URL, $this->view_url.$this->alldata_view.'?'.$queryString);

	                $response = curl_exec($this->ch);
	                $data = json_decode($response);
                        array_push($this->buffer, $data->rows[0]);

                        if ( $this->itemLimit ) {
                            $this->timeStamp -= $this->resample;
                       } else {
                            $this->timeStamp += $this->resample;
                        }
                    }
                } else {
                    $queryString = implode("&", array("startkey=".floor($this->timeStamp),
                                                     "endkey=".$this->defaultEnd,
                                                     "limit=".($this->packetSize + 1),
                                                     "descending=".$this->descending));
                    curl_setopt($this->ch, CURLOPT_URL, $this->view_url.$this->alldata_view.'?'.$queryString);

	            $response = curl_exec($this->ch);
	            $data = json_decode($response);

                    $this->buffer = $data->rows;
                }
                $this->pointer = 0;
                if ( count($this->buffer) <= (($this->packetSize))) {
                    $this->hasNextPacket = false;
                } else { 
                    $this->hasNextPacket = true;
                }
            } else {
                $this->pointer++;
            }
             
            $curData = $this->buffer[$this->pointer];
            $this->nextData = $this->buffer[$this->pointer+1];

            # output all value
            $output = $curData->value;
 	} catch ( Exception $e) {
            # uncomment below to show amount of document shown (debug)
            # echo "\r\n"; print_r($this->counter); echo "\r\n";
	    curl_close($this->ch);
 	    throw new ADEIException(translate("CouchDB error Current") . ": " . $e->getMessage());
 	}
        $cleanOutput = array();
        if (is_array($output)){
            foreach ($output as $element) {
                if (!is_numeric($element)) {
                    $element = NULL;
                }
                array_push($cleanOutput, $element);
            }
        }
        return $cleanOutput;
    }
 
    /*
    * helper method to show current timestamp
    *
    * @return integer $this->timeStamp
    */
    function key() {
        return $this->timeStamp;
    }
 
    /*
    * helper method to update the current timestamp to the next
    * available timestamp
    *
    * @param boolean $this->nextData this shows next data 
    *                                availability status
    */
    function next() {

        if($this->hasNextPacket) {
            if ( $this->itemLimit && ($this->pointer == ($this->packetSize - 1)) ) {
                curl_close($this->ch);
                $this->timeStamp = false;
            } else {
                if ($this->pointer < (count($this->buffer)-1)) {
                    $this->timeStamp = $this->nextData->key;
                }
           }
        } else {
            if ($this->pointer < (count($this->buffer))) {
                $this->timeStamp = $this->nextData->key;
            } else {
	        curl_close($this->ch);
                $this->timeStamp = false;
            }
        }
    }
 
    /*
    * helper method to tell whether there's any current
    * timestamp
    *
    * @return boolean $this->timeStamp
    */
    function valid() {
        return $this->timeStamp?true:false;
    }
}


/**
* Adei Reader for CouchDB class
*
* This class implements the necessary methods to get data
* from CouchDB and show it on Adei
*
*/
class COUCHReader extends READER {

    /**
     * class constructor
     *
     */
    function __construct(&$props) {
        parent::__construct($props);

        # server parameter
        $server = $this->req->srv;
        $database = $server['database'][0];
        $http_method = $server['http_method'];
        $host = $server['host'];
        $port = $server['port'];
        $username = $server['user'];
        $password = $server['password'];

        # options parameter
        $options = $this->opts;
 
        # credentials
        $credential = "";
        if ($username && $password ) {
            $credential = implode(":", array($username,
                                             $password));
            $credential = $credential."@";
        }

        # build URL for queries
        $this->base_view_url = implode("", array($http_method."://",
                                            $credential,
                                            $host.":",
                                            $port."/",
                                            $database."/",
                                            "_design/",
                                            "%design_view%/",
                                            "_view/"));

        $this->base_url = implode("", array($http_method."://",
                                            $credential,
                                            $host.":",
                                            $port."/",
                                            $database."/"));

        $this->resample = $options->props['resample'];
    }
 
    /*
    * helper method to get group information
    * 
    * @parameter integer $first first timestamp in the database (UTC)
    * @parameter integer $last last timestamp in the database (UTC)
    * @parameter integer $gid group id
    *
    * @return array
    * Array([default] => Array(
    *                  [gid] => default
    *                  [name] => Default Group
    *                  [first] => -2208988800
    *                  [last] => 1394534718))                   
    *
    */
    function GetGroupInfo(LOGGROUP $grp = NULL, $flags = 0) {
        $groups = array();
        $options = $this->opts;
        # Get Groups
        $groups = $options->Get('groups', false);

        foreach ($groups as $gid => &$group) {
            
            if (($grp)&&(strcmp($grp->gid, $gid))) continue;

            $stats_view = $groups[$gid]['stats_view'];
            $itemlist_id = $groups[$gid]['itemlist_id'];
            $design_view = $groups[$gid]['design_view'];

            $this->view_url = str_replace("%design_view%", $design_view, $this->base_view_url);

            $this->alldata_view = $groups[$gid]['alldata_view'];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->view_url.$stats_view);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-type: application/json',
                'Accept: */*'
            ));
            $response = curl_exec($ch);
            curl_close($ch);
            
            $minmax = json_decode($response);
            foreach ( $minmax as $unit ) {
                foreach ( $unit as $i ) {
                    $result = $i->value;
                }
            }
            $this->min = floor($result->min);
            $this->max = ceil($result->max);
            $this->count = $result->count;

            
            
            if ($group['name']) $name = $group['name'];
	    else $name = false;
	
            # put name if there's no name specified
	    if ((!$name)||($flags&REQUEST::NEED_INFO)) {
	        if ($grp) {
		    $grtest = $grp;
		    $opts = $this->opts;
	        } else {
		    $ginfo = array("db_group" => $gid);
		    $grtest = $this->CreateGroup($ginfo);
		    $opts = $this->req->GetGroupOptions($grtest);
	        }
	    
	        if (!$name) {
		    $name = $opts->Get('name', $gid);
	        }
	    }
            
            # insert into groups
	    $groups[$gid] = array(
	        'gid' => $gid,
	        'name' => $name
	    );

            if ($flags&REQUEST::NEED_INFO) {
	        $groups[$gid]['first'] = $this->min; 
	        $groups[$gid]['last'] = $this->max; 

	        if ($flags&REQUEST::NEED_COUNT) {
	    	    $groups[$gid]['records'] = $this->count;
	        }

                # not yet implemented for couch
                # this might be the sensor list
	        if ($flags&REQUEST::NEED_ITEMINFO) {
		    $groups[$gid]['items'] = $this->GetItemList($grtest);
	        }
	    }
        }

        if (($grp)&&(!$groups)) {
            throw new ADEIException(translate("Invalid group (%s) is specified", $grp->gid));
        }
        return $grp?$groups[$grp->gid]:$groups;
    }

    /*
    * helper method to return item list from sensors
    *
    * @param LOGGROUP $grp user group
    * @param MASK $mask filter mask to determine which sensor data to show
    * @param integer $flags flag to detemine type of operation
    *
    * @return array
    * Array( [0] => Array ( [id] => default 
    *                       [name] => Default Group))                   
    *
    */
    function GetItemList(LOGGROUP $grp = NULL, MASK $mask = NULL, $flags = 0) {
        if ($flags&REQUEST::ONLY_AXISINFO) {
            if (!$this->req->GetGroupOptions($grp, "axis")) return array();
        }
        #print_r($this->req->GetGroupOptions($grp, "axis"));

        $grp = $this->CheckGroup($grp, $flags);
 
        if (!$mask) {
            $mask = $this->CreateMask($grp, $info=NULL, $flags);
        }
        $i = 0;
        $items = array();
        
        $groups = array();
        $options = $this->opts;
        # Get Groups
        $groups = $options->Get('groups', false);

        $itemlist_id = $groups[$grp->gid]['itemlist_id'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$itemlist_id);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-type: application/json',
            'Accept: */*'
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $this->itemList = json_decode($response);

        foreach ($this->itemList as $key => $value) {
            if (strcmp($grp->gid, $key)) continue;
            $itemList = $value;
        }

        if ($mask->isFull()) {
            foreach($itemList as $unit) {
                array_push($items, array('id' => $i,
                                         'name' => $unit->name,
                                         'axis' => $unit->axis,
                                         'desc' => $unit->description));
                $i++;
            }
        } else {
            foreach ($mask->ids as $bit) {
                array_push($items, array('id' => $i,
                                         'name' => $itemList[$bit]->name,
                                         'axis' => $itemList[$bit]->axis,
                                         'desc' => $itemList[$bit]->description));
                $i++;
            }
        }       

        if ($flags&REQUEST::NEED_AXISINFO) {
            $this->AddAxisInfo($grp, $items);
        }

        return $items;
    }

    /*
    * helper method to get raw data
    *
    * @param LOGGROUP $grp user group
    * @param integer $from start timestamp
    * @param integer $to end timestamp
    * @param DATAFilter $filter filtered data from the sensor
    *
    * @return COUCHData iterator
    *
    */
    function GetRawData(LOGGROUP $grp = NULL, $from = false, $to = false, DATAFilter $filter = NULL, &$filter_data = NULL) {

        $grp = $this->CheckGroup($grp);
        $ivl = $this->CreateInterval($grp);
        $ivl->Limit($from, $to);

        if ($grp) {
            $groups = array();
            $options = $this->opts;
            # Get Groups
            $groups = $options->Get('groups', false);

            foreach ($groups as $gid => &$group) {
                if (($grp)&&(strcmp($grp->gid, $gid))) continue;
                $design_view = $groups[$gid]['design_view'];
                $this->view_url = str_replace("%design_view%", $design_view, $this->base_view_url);
            }
        }

        if ($filter) {
            $mask = $filter->GetItemMask();
            $resample = $filter->GetSamplingRate();
            $limit = $filter->GetVectorsLimit();
            if ($limit) $ivl->SetItemLimit($limit);

            if (isset($filter_data)) {
                #if ($mask) $filter_data['masked'] = true;
                if ($resample) $filter_data['resampled'] = true;
                if ($limit) $filter_data['limited'] = true;
            }
        } else {
            $mask = NULL;
            $resample = 0;
            $limit = 0;
        }

        foreach ($this->itemList as $key => $value) {
            if (strcmp($grp->gid, $key)) continue;
            $itemList = $value;
        }

        $items = array();  
         
        if (($mask)&&(is_array($ids = $mask->GetIDs()))) {
            $tmp = array();
            foreach ($ids as $id) {
                if ($id >= sizeof($itemList)) {
                    throw new ADEIException(translate("Invalid item mask is supplied. The ID:%d refers non-existing item.", $id));
                }
                array_push($tmp, $itemList[$id]);
            }
            $items = $tmp;
        }
        return new COUCHData($this->view_url, $this->alldata_view, $this->req->GetGroupOptions($grp), $items, $ivl);
    }
}
?>
