<?php

switch($_GET['target']) {
    case 'xslt':
//	header("Content-type: text/xsl");
	header("Content-type: text/xml");
	try {
	    $req = new REQUEST();
	    $xslt = $req->GetProp("xslt");
	    if (!$xslt) throw new ADEIException(translate("No xslt stylesheet is specified"));
	    $file = ADEI::GetXSLTFile($xslt);
	} catch (ADEIException $ex) {
	    $ex->logInfo(NULL, $export);
	    echo
'<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
 <xsl:template match="/">
    <span class="error">' . $ex->getInfo() . '</span>
 </xsl:template>
</xsl:stylesheet>';
	    break;
	}
	readfile($file);
    break;
    default:
	if (isset($_GET['target'])) $errror = translate("Unknown get target (%s) is specified", $_GET['target']);
	else $error = translate("The get target is not specified");
}

?>