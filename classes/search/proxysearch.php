<?php

/*
Supported options
    xml  - URL providing actual search
    xslt - XSLT to process results (optional)
    noprops - Do not pass current props to the proxying URL
    nolimits - Do not pass limits to the proxying URL
    searchprop - Search property (search by default)

*/

class PROXYSearch extends SEARCHEngine {
 function __construct(REQUEST $req = NULL, $opts = false) {
    parent::__construct($req, $opts);
    
    $this->modules = array("proxy");
 }

 function Search($search_string, $module, SEARCHFilter $filter = NULL, $opts = false) {
    global $ADEI;

    $xml = $this->GetOption("xml", $opts);
    $xslt = $this->GetOption("xslt", $opts, "null");
    $noprops = $this->GetOption("noprops", $opts);
    $nolimits = $this->GetOption("nolimits", $opts);
    $searchprop = $this->GetOption("searchprop", $opts, false);

    if ($xml) {
	if (preg_match("/^(services\/)?([\w\d_]+\.php)(\?(.*))?$/", $xml, $m)) {
	    $adei_url = $ADEI->GetBaseURL();
	    $xml_url = "{$adei_url}services/" . $m[2];
	    $xml_props = $m[4];
	} else {
	    if (($opts)&&($opts['xml'])) {
		throw new ADEIException(translate("Proxy-search is allowed to ADEI-services only"));
	    } else {
		list($xml_url, $xml_props) = preg_split("/\?/", $xml, 2);
	    }
	}
    } else {
	throw new ADEIException(translate("The proxy URL is required by search module"));
    }
    
    
    if ($noprops) {
	$props = array();
    } else {
	$props = $this->req->GetProps();
	unset($props['search']);
	unset($props['search_modules']);
    }
    
    if (($filter)&&(!$nolimits)) {
	$ivl_filter = $filter->GetLimit('interval');
	if ($ivl_filter) $props['window'] = $ivl_filter;
    }
    
    if ($searchprop === false) {
	$props['search'] = $search_string;
    } else {
	if ($searchprop) $props[$searchprop] = $search_string;
    }
    
    if ($props) {
	$req = new REQUEST($props);
	$xml_props = $req->GetQueryString($xml_props);
	$xml = $xml_url . "?" . $xml_props;
    } else {
	if ($xml_props) $xml = $xml_url . "?" . $xml_props;
	else $xml = $xml_url;
    }

    $html = $ADEI->TransformXML($xslt, $xml);

    $result = new SEARCHResults(NULL, $this, $module, "");
    $result->Append(preg_replace("/^\s*<\?xml.*$/m", "", $html));
    return $result;
 }

}
?>