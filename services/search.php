<?php
global $ADEI;

header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

try {
    $req = new REQUEST();

    $xslt = $req->GetProp('xslt');

    $search = $req->CreateSearcher();
    $results = $search->Search();    
    if ($results) $res = $results->GetResults();
    else $res = false;
    
    if (!$res) $res = array();
//    if (!$res) throw new ADEIException(translate("Nothing is found"));
    
} catch (ADEIException $ex) {
    $ex->logInfo(NULL, $reader?$reader:$req);
    $error = xml_escape($ex->getInfo());
}


if ($xslt) {
    $temp_file = tempnam(sys_get_temp_dir(), 'adei_search.');
    $out = @fopen($temp_file, "w");
    if (!$out) $error = translate("I'm not able to create temporary file \"%s\"", $temp_file);
} else {
    $out = fopen("php://output", "w");
}


if ($out) {
  fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
  fwrite($out, "<result>");
  if ($error) {
    fwrite($out, "<Error>$error</Error>");
    $error = false;
  } else {
    foreach ($res as &$mod) {
	if ($mod['title']) {
	    $title = xml_escape($mod['title']);
	    $title = "title=\"$title\"";
	} else $title = "";

	fwrite($out, "<module name=\"{$mod['module']}\" $title>");
	if ($mod['description']) {
	    fwrite($out, "<description>");
	    fwrite($out, $mod['description']);
	    fwrite($out, "</description>");
	}
	if ($mod['results']) {
		fwrite($out, "<results>");
		foreach ($mod['results'] as &$r) {
		    $rreq = new REQUEST($r['props']);
		    $props = xml_escape($rreq->GetQueryString());
		    if ($r['title']) {
		        $title = xml_escape($r['title']);
		    } else {
			$title = preg_replace("/&amp;/", ",", $props);
		    }

		    $extra = "";
		    if ($r['certain']) $extra=" certain=\"1\"";
		
		    fwrite($out, "<Value  title=\"$title\" props=\"$props\"$extra>");
		    if ($r['description']) {
			fwrite($out, "<description>");
			fwrite($out, $r['description']);
			fwrite($out, "</description>");
		    }
		    fwrite($out, "</Value>");
		}
		fwrite($out, "</results>");
	} else if ($mod['content']) {
		fwrite($out, "<Content>");
		fwrite($out, $mod['content']);
		fwrite($out, "</Content>");
	}
	fwrite($out, "</module>");
    }
  }
  fwrite ($out, "</result>");
  fclose($out);
}

if (($xslt)&&(!$error)) {
    try {
	echo $ADEI->TransformXML($xslt, $temp_file);
    } catch (ADEIException $ex) {
	$ex->logInfo(NULL, $reader?$reader:$req);
	$error = $ADEI->EscapeForXML($ex->getInfo());
    }
    @unlink($temp_file);
}

if ($error) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<result><Error>$error</Error></result>";
}

?>