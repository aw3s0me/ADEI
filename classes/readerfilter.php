<?php

interface READER_SIMPLEDataFilter extends SIMPLEDataFilter {
 function __construct(READER $rdr, LOGGROUP $grp, DATAFilter $filter, &$opts = NULL);
}

class READER_DATAFilter extends DATAFilter {
 function __construct(READER $rdr, LOGGROUP $grp = NULL, array $filters = NULL, MASK $mask = NULL, $resample = 0, $limit = 0) {
    parent::__construct($mask, $resample, $limit);
    if ($filters) $this->AddReaderFilters($rdr, $grp, $filters);
 }
 
 function ProcessReaderData(READER $rdr, LOGGROUP $grp, $from = 0, $to = 0, $filter_data) {
    if (!$filter_data) $filter_data = array();

    $data = $rdr->GetRawData($grp, $from, $to, $this, $filter_data);
    return $this->Process($data, $filter_data);
 }
}

class READER_SUPERDataFilter extends SUPERDataFilter {
 var $filter;
 
 function __construct(DATAFilter $sub_filter, READER $rdr, LOGGROUP $grp = NULL, array $filters = NULL, MASK $mask = NULL, $resample = 0, $limit = 0) {
    parent::__construct($sub_filter, $rdr, $grp, $filters, $mask, $resample, $limit);
    if ($filters) $this->AddReaderFilters($rdr, $grp, $filters);
 }

 function ProcessReaderData(READER $rdr, LOGGROUP $grp, $from = 0, $to = 0, $filter_data) {
    if (!$filter_data) $filter_data = array();

    $data = $rdr->GetRawData($grp, $from, $to, $this, $filter_data);
    return $this->filter->Process($this->Process($data, $filter_data), $filter_data);
 }

}

?>