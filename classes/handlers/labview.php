<?php

class LABVIEWHandler extends DATAHandler {
 var $type;

 var $hout;		// temporary override for $h
 var $tmpfile;		// for $hout

 var $output_endianess;	
 var $system_endianess;

 var $integer_format;	// for pack (big or little endian)
 var $convert_values;	// flag determines if convetation of fp numbers is needed due to the endianess mistmatch 

 var $buffer_size;	// Size of data buffer in bytes
 var $segment_size;	// Number of data vectors per TDMS segment

 var $group_title;
 var $group_channels; 
 var $data;
 
 var $first_group_segment;
 
 const LITTLE_ENDIAN = false;
 const BIG_ENDIAN = true;
 
 const TYPE_TDMS = 0;
 const TYPE_ARRAY = 1;
 const TYPE_STREAMARRAY = 2;

 const TDMS_BUFFER_SIZE = 1048576;
/*
 All numbers are Little-Endian. Following non-standard types are used in 
 segment specification:
  pstring	- Pascal String: length (32 bit, length itself is not counted),
		utf8 string (null termination is allowed but not required)
 timestamp	struct { unsigned long long fraction; long long sec }

 TDMS segment header:
  id		- 4 byte TDMS segment idetificator =TDSm=
  flags		- 32 bit
		    * flag indicating if metadata is present (metadata_size is
		    present anyway)
		    * flag indicating if new order of objects is defined
		    * flag indicating if raw data is included
  version	- 32 bit =0x1268=
  full_size	- 64 bit, size of segment without header (everything after
		metadata size, excluding)
  metadata_size - 64 bit, size of metadata
 TDMS segment metadata:
  n_objects	- 32 bit, number of new objects
  Objects:
    path	- pstring, object id/path: 
		    a) 3 levels of deepness: File (single), Groups, Channels
		    b) "/" is separator
		    c) All names should be quoted with '
		    d) Symbol "'" could be encoded as "''"
		* File Object is "/" 
		* Example of channel object: "/'Group'/'Channel'
   type_descr_sz- 32 bit, type description size, special values are:
		  0x00000000 - now raw data (for this object) in this segment
		  0xffffffff - exactly as in previous segment
   Type Description:
    type	- 32 bit, TDS Type
    dimension	- 32 bit, Value dimension (always 1!!!) =1=
    size	- 64 bit, number of values stored in segment
    total_size	- Optional, total size of stored data in bytes (only stored
		from variable length (i.e. strings) TDS types)
  n_properties	- 32 bit, Number of properties
  Properties:
   name		- pstring
   type		- int 32, TDS Type
   value	- preceded by length (32 bit) if variable length
 Raw Data:
    All values for each object are stored sequentialy, objects are also
    stored sequentially.
  
*/

 const TDMS_SEGMENT_ID = "TDSm";
 
 const TDMS_SEGMENT_METADATA_FLAG = 2;
 const TDMS_SEGMENT_NEWOBJECTS_FLAG = 4;
 const TDMS_SEGMENT_RAWDATA_FLAG = 8;
 
 const TDMS_VERSION = 0x1268;
 
 const TDMS_TYPE_VOID = 0;
 const TDMS_TYPE_INT8 = 1;
 const TDMS_TYPE_INT16 = 2;
 const TDMS_TYPE_INT32 = 3;
 const TDMS_TYPE_INT64= 4;
 const TDMS_TYPE_UINT8 = 5;
 const TDMS_TYPE_UINT16 = 6;
 const TDMS_TYPE_UINT32 = 7;
 const TDMS_TYPE_UINT64= 8;
 const TDMS_TYPE_FLOAT = 9;
 const TDMS_TYPE_DOUBLE = 0x0A;
 const TDMS_TYPE_EXTENDED = 0x0B;
 const TDMS_TYPE_STRING = 0x20;
 const TDMS_TYPE_BOOLEAN = 0x21;
 const TDMS_TYPE_TIMESTAMP = 0x22; 
 

 function __construct(&$opts = NULL, STREAMHandler $h  = NULL) {
    if ($opts['type']) {
	$name = "self::TYPE_" .  strtoupper($opts['type']);
	if (defined($name)) $this->type = constant($name);
	else throw new ADEIException(translate("LABVIEW Data Handler is not supporting requested output type (%s)", $opts['type']));
    } else {
	$this->type = self::TYPE_TDMS;
    }	

    if (pack("N", 1) == pack("L", 1)) {
	$this->system_endianess = self::BIG_ENDIAN;
    } else {
	$this->system_endianess = self::LITTLE_ENDIAN;
    }
    
    switch ($this->type) {
     case self::TYPE_TDMS:
	$this->content_type = "application/binary";
	$this->extension = "tdms";
	$this->multigroup = true;
	$this->output_endianess = self::LITTLE_ENDIAN;
	
	if ($opts['buffer_size']>1024) $this->buffer_size = $opts['buffer_size'];
	else $this->buffer_size = self::TDMS_BUFFER_SIZE;
     break;
     default:
        if ($this->type == self::TYPE_ARRAY) $this->filewriter = true;
	else $this->multigroup = true;

	$this->content_type = "application/binary";
	$this->extension = "lvbin";
	if ($opts['big_endian']) $this->output_endianess = self::BIG_ENDIAN;
	else $this->output_endianess = self::LITTLE_ENDIAN;
    }

    if ($this->output_endianess == self::BIG_ENDIAN) $this->integer_format = "N";
    else $this->integer_format = "V";
    
    if ($this->output_endianess == $this->system_endianess) $this->convert_values = false;
    else $this->convert_values = true;
    
    parent::__construct($opts, $h);
 }

 function ImportUnixTime($unix_time) {
    if (!$unix_time) return 0;
    return dsMathPreciseAdd($unix_time, (2082837600+7200));
 }

 function TDMSString($string) {
    return pack("V", strlen($string)) . $string;
 }
 
 function TDMSPath($group, $item = false) {
    $res = "/'" . str_replace("'", "''", $group) . "'";
    if ($item) $res .= "/'" . str_replace("'", "''", $item) . "'";
    return $this->TDMSString($res);
 }

 function TDMSChannelInfo($n) {
    $index = pack("VVVV",
		self::TDMS_TYPE_DOUBLE,
		1,
		$n,	// lo dword
		0	// hi dword
    );
    return pack("V", 4 + strlen($index)) . $index;
 }
 
 function TDMSChannelProperties($time = false) {
    $metadata .= pack("V", 2);

    $metadata .= $this->TDMSString("displaytype");
    $metadata .= pack("V", self::TDMS_TYPE_STRING);
    if ($time) $metadata .= $this->TDMSString("Time");
    else $metadata .= $this->TDMSString("Numeric");

    $metadata .= $this->TDMSString("datatype");
    $metadata .= pack("V", self::TDMS_TYPE_STRING);
    $metadata .= $this->TDMSString("DT_DOUBLE");

    return $metadata;
 }

 function TDMSMetaData($group_title, $items, $n) {
    $metadata = pack("V", 1 + sizeof($items));

    $info = $this->TDMSChannelInfo($n);
    
    $metadata .= $this->TDMSPath($group_title, "Timestamp");
    $metadata .= $info;
    $metadata .= $this->TDMSChannelProperties(true);


	// Channels
    foreach ($items as $item) {
	$metadata .= $this->TDMSPath($group_title, $item['name']);
	$metadata .= $info;
	$metadata .= $this->TDMSChannelProperties();
    }

    return $metadata;
 }


 function TDMSWriteSegment($last = false) {
    $flags = self::TDMS_SEGMENT_RAWDATA_FLAG;
    if  ($this->first_group_segment) $flags |= self::TDMS_SEGMENT_NEWOBJECTS_FLAG|self::TDMS_SEGMENT_METADATA_FLAG;
    elseif ($last) $flags |= self::TDMS_SEGMENT_METADATA_FLAG;


    if ($flags&self::TDMS_SEGMENT_METADATA_FLAG) {
	$metadata = $this->TDMSMetaData($this->group_title, $this->group_channels, $this->processed_vectors);
	$metasize = strlen($metadata);
    } else {
	$metasize = 0;
    }

    
    $fullsize = $metasize;
    foreach($this->data as &$data) {
	$fullsize += strlen($data);
    }

	// Header
    $this->h->Write(self::TDMS_SEGMENT_ID);
    $this->h->Write(pack("VVVVVV",
	    $flags,
	    self::TDMS_VERSION,
	    $fullsize,		/* lo dword */
	    0,			/* hi dword */
	    $metasize,		/* lo dword */
	    0			/* hi dword */
    ));
    if ($metasize) $this->h->Write($metadata);
 
	// Writing raw data
    foreach($this->data as &$data) {
	$this->h->Write($data);
    }

    $this->first_group_segment = false;
 }


 function GroupStart($title, $subseconds = false, $flags = 0) {
    switch ($this->type) {
     case self::TYPE_TDMS:
	$this->group_title = $title;
	$this->data = array();
     break;
     case self::TYPE_ARRAY:
        $this->tmpfile = GetTmpFile("adei_stream_excel_", $this->extension);
        $this->hout = new IO($this->tmpfile);
	$this->hout->Open();
     break;
     case self::TYPE_STREAMARRAY:
        $this->hout = $this->h;
     break;
    }

    parent::GroupStart($title, $subseconds, $flags);
 }

 function DataHeaders(&$names, $flags = 0) {
    switch ($this->type) {
     case self::TYPE_TDMS:
	$this->segment_size = ceil($this->buffer_size / (strlen(pack("d",0)) * $this->vector_length));
	$this->group_channels = $names;
	$this->first_group_segment = true; 

	for ($i=0;$i<=$this->vector_length;$i++)
	    $this->data[$i] = "";
     break;
     case self::TYPE_ARRAY:
	$this->hout->Write(pack("{$this->integer_format}2", 0, 1 + sizeof($names)));
    }
 }


 function DataVector(&$time, &$values, $flags = 0) {
    switch ($this->type) {
     case self::TYPE_TDMS:
	if ($this->convert_values) {
	    $this->data[0] .= strrev(pack("d", $time + 62167132800));
	    foreach (array_keys($values) as $i) {
		$this->data[$i+1] .= strrev(pack("d", $values[$i]));
	    }
	} else {
	    $this->data[0] .= pack("d", $time + 62167132800);
	    foreach (array_keys($values) as $i) {
		$this->data[$i+1] .= pack("d", $values[$i]);
	    }
	}
	
	if (++$this->processed_vectors == $this->segment_size) {
	    $this->TDMSWriteSegment();
	    
	    $this->processed_vectors = 0;
	    $this->data = array();
	    for ($i=0;$i<=$this->vector_length;$i++)
		$this->data[$i] = "";
	}
     break;
     case self::TYPE_STREAMARRAY:
	$this->hout->Write(pack("{$this->integer_format}", 1 + sizeof($values)));
     default:
	if ($this->convert_values) {
	    $this->hout->Write(strrev(pack("d", $time)));
	    foreach (array_keys($values) as $i) {
		$this->hout->Write(strrev(pack("d", $values[$i])));
	    }
	} else {
	    $this->hout->Write(pack("d", $time));
	    foreach (array_keys($values) as $i) {
		$this->hout->Write(pack("d", $values[$i]));
	    }
	}
    }
 }

 function GroupEnd($flags = 0) {
    switch ($this->type) {
     case self::TYPE_TDMS:
        if (($this->data)&&($this->data[0])) {
	    $this->TDMSWriteSegment(true);
	}
     break;
     case self::TYPE_STREAMARRAY:
	$this->hout->Write(pack("{$this->integer_format}", 0));
     break;
     case self::TYPE_ARRAY:
	$this->hout->Close();
	unset($this->hout);

        $data = pack($this->integer_format, $this->processed_vectors);
	$f = fopen($this->tmpfile, "r+");
	if (!$f) {
	    throw new ADEIException(translate("Internal error. LABVIEW data handler is not able to open temporary file (%s)", $this->tmpfile));
	}
	fwrite($f, $data, 4);
	fclose($f);

	if ($this->filemode)
	    $this->h->WriteData($this->tmpfile);
	else
	    $this->h->WriteFile($this->tmpfile);

	unlink($this->tmpfile);
     break;
    }

    parent::GroupEnd($flags);
 }
}

?>