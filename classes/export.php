<?php

require($ADEI_ROOTDIR . "classes/stream/stream.php");

class EXPORT {
 var $req;
 var $dm;
 var $dmcb;		

 var $format;
 var $multimode;	// STREAM is used for joining groups

 var $multigroup;	// Multiple groups expected
 var $multirdr;		// Multiple readers expected
 
 var $resample;		// Resampling

 var $output;
 var $stream;
 var $handler;

 var $stream_args;
 var $expected_groups; 
 
 var $specials;

 var $filename;

 var $cache_mode;	// Get data out of cache
 
 var $export_data;
 
 const MASK_STANDARD = 0;
 const MASK_GROUP = 1;
 const MASK_SOURCE = 2;
 const MASK_COMPLETE = 3;
   
 function __construct(DATARequest $props = NULL, STREAMObjectInterface $h = NULL, &$format = NULL, DOWNLOADMANAGER $dm = NULL) {
    global $TMP_PATH;      
    if ($props) $this->req = $props;
    else $this->req = new DATARequest();
    
    if ($format) $this->format = &$format;
    else $this->format = $this->req->GetFormatInfo();

    if ($this->format['handler']) {
	if (!include_once("handlers/" . strtolower($this->format['handler']) . '.php')) {
		throw new ADEIException(translate("Unsupported data handler is configured: \"%s\"", $format['handler']));
	}
	$handler = array(
	    "class" => $this->format['handler'] . "Handler"
	);
    } else {
	$handler = array(
	    "class" => "CSVHandler"
	);
    }
    
    if($dm){
      $this->dm = $dm;
      $this->dmcb = array($this->dm,"DlmanagerUpdate");
    }       
      
    $this->handler = new $handler["class"]($this->format);
    $this->export_data = ! $this->handler->data_not_needed;

    if (!$this->handler->multigroup) {
	switch ($this->req->props['mask_mode']) {
	    case EXPORT::MASK_COMPLETE:
	    case EXPORT::MASK_SOURCE:
		$this->multimode = true; //!$this->handler->multigroup;
	    break;
	    case EXPORT::MASK_STANDARD:
	    case EXPORT::MASK_GROUP:
		$this->multimode = false;
		if ($this->req->IsVirtual()) {
		    $reader = $this->req->CreateReader();
		    $grp = $reader->CreateGroup();
		    if ($grp->IsComplex()) {
			if ($this->req->props['mask_mode']==EXPORT::MASK_STANDARD) $mask = $reader->CreateMask();
			else $mask = NULL;
			$list = $reader->CreateRequestSet($grp, $mask);
			if ($list->GetSize() != 1) {
			  $this->multimode = true;
			  if($this->dm) $this->dm->UpdateFilesRemaining($this->req->GetProp('dl_id'), $list->GetSize());
			}
			unset($list);

		    }
		    unset($reader);
		}
	    break;
	    default:
		$this->multimode = true;
	}
    } else $this->multimode = false;
    
    if (isset($this->req->props['resample'])) {
	$this->resample = $this->req->props['resample'];
    } else {
	$this->resample = 0;
    }      
 
    if($this->dm) $this->output = $this->dm->GetDmOutput($this->multimode, $this->handler->GetExtension(), $this->req);     
    else $this->output = $h;
    

    if (($this->format['filter'])||($this->multimode)) {
	$this->stream = new STREAM($this->format, $this->output, ($this->multimode?STREAM::MULTIMODE:0)|($this->handler->filewriter?STREAM::FILESTART:0));
	if ($this->stream->filereader) $this->handler->RequestFilemode();
    } else {
	$this->stream = $this->output;
    }

    if ($this->stream) {
	$this->handler->SetOutput($this->stream);
    }
    
    $this->cache_mode = false;
 }

 function SetCacheMode($mode = true) {
    $this->cache_mode = $mode;
 }

 function CheckMode() {
    if ((!$this->multimode)&&(!$this->handler->multigroup)) {
	if ((!$this->stream)||(!($this->stream instanceof STREAM))||(!$this->stream->joiner)) {
	    unset($this->stream);
	    $this->stream = new STREAM($this->format, $this->output, STREAM::MULTIMODE|($this->handler->filewriter?STREAM::FILESTART:0));
	    if ($this->stream->filereader) $this->handler->RequestFilemode();
	    $this->handler->SetOutput($this->stream);

	    if (!$this->stream->joiner) {
		throw new ADEIException(translate("The attempt to create joining STREAM is failed"));
	    }

	    $this->multimode = true;
	}
    }
 }

 private function CreateReader(SOURCERequest $req = NULL) {
    if (!$req) $req = $this->req;
    if ($this->cache_mode) $rdr = $req->CreateCacheReader();
    else $rdr = $req->CreateReader();
    return $rdr;
 }

 function ExportNothing() {    
	$rdr = $this->CreateReader();    
	$grp = $rdr->CreateGroup();    
	$msk = $rdr->CreateMask($grp);    
	$ivl = $rdr->CreateInterval($grp);        

	$this->Start($rdr,$grp,$msk,$ivl);    
	$this->SendHeaders($rdr, $grp, $msk, $ivl);    
	$this->handler->Start(0);    
	$this->handler->End();    
	$this->End(); 
 }
 
 function Export() {
    if (!$this->export_data)        
	return $this->ExportNothing();

    if ($this->req->IsVirtual()) $virtual_mode = true;
    else $virtual_mode = false;
    
    switch($this->req->props['mask_mode']) {
	case EXPORT::MASK_STANDARD:
	    $msk = true;
	    $grp = true;
	break;
	case EXPORT::MASK_GROUP:
	    $msk = NULL;
	    $grp = true;
	break;
	case EXPORT::MASK_SOURCE:
	    if ($virtual_mode) {
		$msk = NULL;
		$grp = false;
	    } else {
		return $this->ExportSource();
	    }
	break;
	case EXPORT::MASK_COMPLETE:
	    return $this->ExportAll();
	default:
	    throw new ADEIException(translate("Unsupported mask mode (%u)", $this->req->props['mask_mode']));
    }
    
    $rdr = $this->CreateReader();
    if ($grp) $grp = $rdr->CreateGroup();
    else {
	$grp = $rdr->CreateJointGroup();
    }
    if ($msk) $msk = $rdr->CreateMask($grp);

    if ($virtual_mode) {
	return $this->ExportVirtualGroup($rdr, $grp, $msk);
    }
    
    $ivl = $rdr->CreateInterval($grp);

    $this->multigroup = false;
    $this->multirdr = false;
        
    $this->Start($rdr, $grp, $msk, $ivl, 1);
    $this->SendHeaders($rdr, $grp, $msk, $ivl);
    $this->ExportGroup($this->req, $rdr, $grp, $ivl, $msk);
    $this->End();
 }

 function ExportSource() {
    $this->CheckMode();

    $msk = NULL;
    $rdr = $this->CreateReader();

    $this->multigroup = true;
    $this->multirdr = false;

    $this->Start($rdr, NULL, $msk, NULL);
    $this->SendHeaders($rdr, NULL, $msk, NULL);

    $groups = $rdr->GetGroups();
    foreach ($groups as &$grp) {
	$ivl = $rdr->CreateInterval($grp);

	$this->ExportGroup($this->req, $rdr, $grp, $ivl, $msk);
    }

    $this->End();
 }
 
 function ExportAll() {
    $this->CheckMode();
    
    $msk = NULL;

    $this->multigroup = true;
    $this->multirdr = true;

    $this->Start(NULL, NULL, $msk, NULL);
    $this->SendHeaders(NULL, NULL, $msk, NULL);

    $list = $this->req->GetSources(REQUEST::LIST_ALL);
    foreach ($list as $sreq) {
	try {
	    $dreq = $sreq->CreateDataRequest();
	} catch (ADEIException $e) {
	    $dreq = &$sreq;
	}

    	$rdr = $this->CreateReader($dreq);

	$groups = $rdr->GetGroups();
	foreach ($groups as &$grp) {
	    $ivl = $rdr->CreateInterval($grp);	    
	    
	    $this->ExportGroup($sreq, $rdr, $grp, $ivl, $msk);
	}
    }

    $this->End();
 }

 function ExportVirtualGroup(READER $rdr, LOGGROUP $grp, MASK $mask = NULL) {
    $this->multigroup = true;
    $this->multirdr = true;
  
    if (!$mask) $mask = new MASK($minfo = array());
    
    $this->Start($rdr, $grp, $mask, NULL);
    $this->SendHeaders($rdr, $grp, $mask, NULL);

    $list = $rdr->CreateRequestSet($grp, $mask, "DATARequest");
    foreach ($list as $req) {
	$reader = $req->CreateReader();
	$group = $reader->CreateGroup();
	$ivl = $reader->CreateInterval($group);
	$msk = $reader->CreateMask();

	$title = $this->GetGroupName(
	    $reader->GetGroupServerID($group), 
	    $reader->GetGroupDatabaseName($group),
	    $reader->GetGroupID($group),
	    $msk?$msk->name:""
	);

	$this->ExportGroup($req, $reader, $group, $ivl, $msk, $title);	
	
    }
    $this->End();
    
    
 }



 function Start(READER $rdr = NULL, LOGGROUP $grp = NULL, MASK $msk = NULL, INTERVAL $ivl = NULL, $expected_groups = 0) {
    $this->filename = false;
    
    if ($this->stream) {
	$this->specials = $this->stream->GetSpecials();
	
	$this->stream_args = array(
	    "expected_blocks" => $expected_groups,
	    "extension" => $this->handler->GetExtension()
	);
	
	$this->stream->Open($this->stream_args);

	$this->expected_groups = $expected_groups;
	if ($this->handler->multigroup) {
	    $this->filename = $this->GetName($rdr, $grp, $msk, $ivl);

	    $stream_args = $this->stream_args;
	    $stream_args["block_number"] = 0;
	    $stream_args["block_title"] = $this->filename;

	    $this->stream->BlockStart($stream_args);
	}
    } else {
	$this->specials = array();
    }
    
    $this->handler->SequenceStart();
 }

 function End() {
    $this->handler->SequenceEnd();
    
    if ($this->stream) {
	if ($this->handler->multigroup) $this->stream->BlockEnd();
	$this->stream->Close();
    }
 }


 function ExportGroup(REQUEST $req, READER $rdr, LOGGROUP $grp, INTERVAL $ivl, MASK $msk = NULL, $title = false) {
    global $ROOT_COMBIHIST_LIMIT;
    
    if (!$title) $title = $this->GetGroupName($rdr->srvid, $rdr->dbname, $grp->gid, $msk?$msk->name:"");

    $opts = &$req->GetOptions();
    $subseconds = !$opts->Get('ignore_subsecond');
    
    if (($this->stream)&&(!$this->handler->multigroup)) {
	$stream_args = array_merge($this->stream_args, array(
	    "expected_blocks" => $this->expected_groups,
	    "block_title" => $title,
	    "block_number" => 0
	));

        $stream_args["extension"] = $this->handler->GetExtension();

	if (in_array("ROOT", $this->specials)) {
	    if ((!isset($ROOT_COMBIHIST_LIMIT))||($ivl->GetWindowSize() < $ROOT_COMBIHIST_LIMIT))
		$stream_args['root__combhist'] = 1;
	    else
		$stream_args['root__combhist'] = 0;
	}
		
	$this->stream->BlockStart($stream_args);
    }

    $this->handler->GroupStart($title, $subseconds);
    
    $rdr->Export($this->handler, $grp, $msk, $ivl, $this->resample, "", $this->dmcb);
    $this->handler->GroupEnd();    

    if (($this->stream)&&(!$this->handler->multigroup)) $this->stream->BlockEnd();    
 }

 function SendHeaders(READER $rdr = NULL, LOGGROUP $grp = NULL, MASK $msk = NULL, INTERVAL $ivl = NULL) { 
    if ($this->stream) 
	$content_type = $this->stream->GetContentType();
    else
	$content_type = false;
	
    if (!$content_type) $content_type = $this->handler->GetContentType();
    if (!$content_type) {
	if ($this->format["content_type"]) $content_type = $this->format["content_type"];
	else $content_type = "application/binary";
    }
    
    if ($this->stream)
	$extension = $this->stream->GetExtension();
    else
	$extension = false;

    if (!$extension) $extension = $this->handler->GetExtension();
    if (!$extension) {
	if($this->format["extension"]) $extension = $this->format["extension"];
	else if ($this->req->props['format']) $extension = strtolower($this->req->props['format']);
	else $extension = "adei";
    }

    if (isset($this->req->props['filename'])) {
	$name = &$this->req->props['filename'];
	if (($extension)&&(strpos($name, '.')===false)) $name .= ".$extension";
    } else {
	if ($this->filename) $name = $this->filename;
	else $name = $this->GetName($rdr, $grp, $msk, $ivl);
	if ($extension) $name .= ".$extension";
    }    
   
    //    echo "$content_type , $name\n";
    //    exit;
    if($this->dm) { 	
      $this->dm->SetContentType($content_type, $this->dm->GetDownload());
    }
    else {
      header("Cache-Control: no-cache, must-revalidate");
      header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
      header("Content-type: $content_type");
      header("Content-Disposition: attachment; filename=\"$name\"");
    }	
  }

 function GetGroupName($sname, $dname, $gname, $mname, $iname = false) {
    if ($mname) $mname = "/$mname";
    
    if ($this->multirdr) $title = $sname . "__" . $dname . "__" . $gname . $mname;
    elseif ($this->multigroup)  $title = $gname . $mname;
    else $title = $gname . $mname;
    return $title;
 }

 function GetName(READER $rdr = NULL, LOGGROUP $grp = NULL, MASK $msk = NULL, INTERVAL $ivl = NULL) {
    $name = "";

    if ((!$this->multirdr)&&($rdr)) 
	$name .= $rdr->srvid . "__" . $rdr->dbname;

    if ((!$this->multigroup)&&($grp)) {
	$name .= ($name?"__":"") . $grp->gid;
    }
	
    if ((!$this->multimask)&&($msk))
	$name .= ($name?"__":"") . $msk->name;
    
    if (!$this->multiivl) {
	if ((!$ivl)||($ivl->IsEmpty()))
	    $ivl = $this->req->CreateInterval();

	$name .= ($name?"__":"") . $ivl->GetName(NAME_FORMAT_ISO8601);
    }

    if ($name) return $name;
    return "adei_data";
 }
}


?>
