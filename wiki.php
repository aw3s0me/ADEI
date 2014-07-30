<?php
#################################################################################
#                                                                               #
#   This program is free software; you can redistribute it and/or modify        #
#   it under the terms of the GNU General Public License as published by        #
#   the Free Software Foundation; either version 2 of the License, or           #
#   (at your option) any later version.                                         #
#                                                                               #
#   This program is distributed in the hope that it will be useful,             #
#   but WITHOUT ANY WARRANTY; without even the implied warranty of              #
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               #
#   GNU General Public License for more details.                                #
#                                                                               #
#   You should have received a copy of the GNU General Public License           #
#   along with this program; if not, write to the Free Software                 #
#   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA   #
#                                                                               #
#################################################################################

// Installation Instructions:
//
// Just save wiki.phps as wiki.php in your web directory and make sure that
// your web server is able to read and write the file. If you want a backup
// functionality, create a folder named 'history' at the place where wiki.php
// lives. Don't forget to give proper write rights to this folder.
//
// If you work with htacces, you can define an array named AUTHORS with a 
// list of persons which are allowed to modify this wiki:
//   - when no array named AUTHORS is defined, everybody can write. This is default.
//   - when an array named AUTHORS is defined but empty, nobody can write
//
// examples: 
//   $AUTHORS = array('buck','jack');  // only buck and jack bauer can modify the wiki
//   $AUTHORS = array();               // nobody can edit the wiki, mr. nobody can't also :-)
//   //$AUTHORS = array();             // everybody can edit the wiki
//   $FREE4ALL = array('public site'); // in order to enable a page which is editable by everyone, 
                                       // you can define this array which containing a string with the page title

global $ADEI;
global $ADEI_SETUP;
global $ADEI_ROOTDIR;
global $WIKI_FILENAME;
global $AJAX_MODE;
global $REQ;


function adeiChannels($type, $props) {
    $bygroup = false;
    
    $translations = array(
	"[" => "&#91;",
	"]" => "&#93;"
    );
    
    switch($type) {
     case "channels_by_group":
	$bygroup = true;	
     break;
     case "channels_by_name":
	$channels = array();
     break;
    }

    $tmp = preg_split("/&(amp;)?/", $props);
    $p = array();
    foreach ($tmp as $pair) {
	$res = explode("=", $pair);
	if (sizeof($res) == 2) $p[$res[0]] = $res[1];
    }

    $res = "";

    $req = new REQUEST($tmp = array());
    $sources = $req->GetSources(REQUEST::SKIP_UNCACHED|REQUEST::LIST_ALL);
    foreach ($sources as $sreq) {
	$title = $sreq->GetSourceTitle();
	
	$groupinfo = $sreq->GetGroupList();
	$groups = $sreq->GetGroups(NULL, REQUEST::SKIP_UNCACHED);
	
	if ((isset($p["db_server"]))&&(strcmp($p["db_server"],$sreq->props["db_server"]))) continue;
	if ((isset($p["db_name"]))&&(strcmp($p["db_name"],$sreq->props["db_name"]))) continue;

	foreach ($groups as $gid => $greq) {
	    if ((isset($p["db_group"]))&&(strcmp($p["db_group"],$gid))) continue;

	    $gtitle = $title . " -- " . $groupinfo[$gid]["name"];
	    $gquery = $greq->GetGroupQueryString($props);
	    
	    $masklist = $greq->GetMaskList(REQUEST::NEED_INFO);
	    $defaultmask = array_shift($masklist);
	    $defaultmask = $defaultmask['mask'];
	    unset($masklist);
	    
	    $glink = $gquery . "&db_mask=$defaultmask";
	    
	    $list = $greq->GetItemList();
	    if ($bygroup) {
		$first = true;
		
		foreach ($list as &$item) {
		    if (isset($item["uid"])) {
			if ($first) {
			    $first = false;
			    $res .= "!!$gtitle ([link($glink)]" . _("View") . "[/link])[br]\n"; 
			}
			$link = $gquery . "&db_mask=" . $item['id'];
			
			$uid = strtr(htmlentities($item['uid']), $translations);
			$res .= "[link($link)]{$uid}[/link] - " . xml_escape($item['name']) ."[br]\n";
		    }
		}
		
		if (!$first) $res .= "[br][br]\n";
	    } else {
		foreach ($list as &$item) {
		    if (isset($item["uid"])) {
			$link = $gquery . "&db_mask=" . $item['id'];
			$uid = strtr(htmlentities($item['uid']), $translations);
			array_push($channels, array(
			    'uid' => $uid,
			    'link' => $link,
			    'name' => xml_escape($item["name"]),
			    'glink' => $glink,
			    'gname' => xml_escape($gtitle)
			));
		    }
		}
	    }
	}
    }

    switch($type) {
     case "channels_by_name":
        usort($channels, create_function('$a, $b', '
	    return strcmp($a["uid"], $b["uid"]);
	'));

	$res .= "{| border=\"1px\"\n";
	$res .= "UID || Group || Description\n|-\n";
	foreach ($channels as &$item) {
	    $res .= "[link({$item['link']})]{$item['uid']}[/link] || [link({$item['glink']})]{$item['gname']}[/link] || {$item['name']}\n|-\n";
	}
	$res .= "|}\n";

/*	
	foreach ($channels as &$item) {
	    $res .= "[link({$item['link']})]{$item['uid']}[/link] (Group: [link({$item['glink']})]{$item['gname']}[/link]) - {$item['name']}[br]\n";
	}
*/
     break;
    }
    
    return $res;
}

function adeiGroupList($props) {
    $tmp = preg_split("/&(amp;)?/", $props);
    $p = array();
    foreach ($tmp as $pair) {
	$res = explode("=", $pair);
	if (sizeof($res) == 2) $p[$res[0]] = $res[1];
    }

    $res = "";
        
    $req = new REQUEST($tmp = array());
    $sources = $req->GetSources(REQUEST::SKIP_UNCACHED|REQUEST::LIST_ALL);
    foreach ($sources as $sreq) {
	if ((isset($p["db_server"]))&&(strcmp($p["db_server"],$sreq->props["db_server"]))) continue;
	if ((isset($p["db_name"]))&&(strcmp($p["db_name"],$sreq->props["db_name"]))) continue;

	$title = $sreq->GetSourceTitle();
	
	$groupinfo = $sreq->GetGroupList();
	$groups = $sreq->GetGroups(NULL, REQUEST::SKIP_UNCACHED);
	foreach ($groups as $gid=>$greq) {
	    if ((isset($p["db_group"]))&&(strcmp($p["db_group"],$gid))) continue;

	    $res .= "!!" . xml_escape($title . " -- " . $groupinfo[$gid]["name"]) . "[br]\n";
	    $res .= "[preview(" . 
			$greq->GetGroupQueryString($props) .
		    "), link]";
	    $res .= "[br][br][br]\n";
	}
    }
    
    return $res;
}


class ErrorHandler {
	public static function reportError($error) {
		print '<b>Error:</b> '.$error.'<br/>';
	}
}

class CodeInterpreter {
	private static $instance;
	private $wiki;

	private function __construct($wiki) {
		$this->wiki = $wiki;
	}

	public static function getInstance($wiki) {
		if(!isset($instance) || $instance == null) {
			$instance = new CodeInterpreter($wiki);
		}
		return($instance);
	}

	public function parseCode($string) {
		$string = $this->compileVersion($string);
		$string = $this->compileChannelList($string);
		$string = $this->compileGroupList($string);
		$string = $this->compilePreview($string);
		$string = $this->resolveAdeiLink($string);
		$string = $this->resolveInternalLink($string);
		$string = $this->compileImage($string);
		$string = $this->compileExternalLink($string);
		$string = $this->compileExternalCustomLink($string);
		$string = $this->compileMailTo($string);
		$string = $this->compileBoldText($string);
		$string = $this->compileItalicText($string);
		$string = $this->compileUnderlineText($string);
		$string = $this->compileHeader($string);
 		$string = $this->compileEnumeration($string);
		$string = $this->compileTable($string);
		$string = $this->compileNewLine($string);
		return($string);
	}

	private function compileTable($string) {
		$string = preg_replace(
		    array(
			"/^[ \t]*\{\|([^\r\n]*)/m",
			"/^[ \t]*\|\}/m",
			"/^[ \t]*\|-/m",
			"/\|\|/m"
		    ), array(
			'<table\\1><tr><td valign="top">',
			'</td></tr></table>',
			'</td></tr><tr><td valign="top">',
			'</td><td valign="top">'
		    ), $string);
		    
		return($string);
	}
	
	private function compileNewLine($string) {
		$string = preg_replace("/^[ \t]*\r?\n?$/m","<br/>",$string);
		$string = preg_replace("/\[(br|hr)\]/i","<\\1/>",$string);
		return($string);
	}
	
	private function compileVersion($string) {
	    return preg_replace_callback(
		"/\[\s*version\s*\]/",
		create_function('$matches', '
		    if (file_exists("VERSION")) {
			$stat = stat("VERSION");
			$date = date("r", $stat["mtime"]);
			
			$version = file_get_contents("VERSION");
			if (preg_match("/^\s*([\d.]+)/", $version, $m)) $version = $m[1];
			
			return "Version: $version from $date";
		    } else {
			return "";
		    }
		'), 
		$string
	    );

	}
	
	private function compileChannelList($string) {
	    return preg_replace_callback(
		"/\[\s*(channels_by_group|channels_by_name)\s*(\(([^\)\]]+)\))?\s*\]/",
		create_function('$matches', '
		    return adeiChannels($matches[1], $matches[3]);
		'), 
		$string
	    );
	}
	
	private function compileGroupList($string) {
	    return preg_replace_callback(
		"/\[\s*grouplist\s*(\(([^\)\]]+)\))?\s*\]/",
		create_function('$matches', '
		    global $AJAX_MODE;

		    if (1||$AJAX_MODE) return adeiGroupList($matches[2]);
		    else return "[b] --- Group list would be displayed here --- [/b][br]\n";
		'), 
		$string
	    );
	}
	
	private function compilePreview($string) {
	    return preg_replace_callback(
		"/\[\s*preview\s*\(([^\)\]]+)\)(\s*,\s*link\s*(\(([^\]\)]+)\))?)?\s*\]/",
		create_function('$matches', '
		    global $REQ;
		    global $AJAX_MODE;
			/* We can get here in problems due to overriding masks, etc. */
		    $zreq = new REQUEST($props = array());
		    //$zreq = $REQ; // enable to get some props from
		    
		    $img = "[img]services/getimage.php?" . $zreq->GetQueryString($matches[1], array(
			"precision" => "LOW",
			"hide_axes" => 1
		    )) . "[/img]";
		    if (($AJAX_MODE)&&($matches[2])) {
			if ($matches[4]) {
			    $query = $zreq->GetQueryString($matches[4], array(
				"module" => "graph"
			    ));
			} else {
			    $query = $zreq->GetQueryString($matches[1], array(
				"module" => "graph"
			    ));
			}
			$query = preg_replace("/&/", "&amp;", $query);
			$img = preg_replace("/&/", "&amp;", $img);
			return \'[url="javascript:wiki.SetConfiguration(\\\'\' . $query . \'\\\')"]\' . $img . \'[/url]\';
		    } else {
			return $img;
		    }
		'),
		$string
	    );
	}
	
	private function resolveAdeiLink($string) {
	    return preg_replace_callback(
		"/\[\s*link\s*=?\s*(\(|\")([^\]]+)(\)|\")\s*\]([^\[]+)\[\s*\/link\s*\]/",
		create_function('$matches', '
		    global $AJAX_MODE;
		    global $ADEI_SETUP;

		    $req = new REQUEST($props = array());
		    $query = $req->GetQueryString($matches[2], array(
			"module" => "graph"
		    ));
		    $query = preg_replace("/&/", "&amp;", $query);
		    if ($AJAX_MODE) {
			return \'[url="javascript:wiki.SetConfiguration(\\\'\' . $query . \'\\\')"]\' . $matches[4] . \'[/url]\';
		    } else {
			return \'[url="index.php?setup=\' . $ADEI_SETUP . \'#\' . $query . \'"]\' . $matches[4] . \'[/url]\';
		    }
		'),
		$string
	    );
	}

	private function resolveInternalLink($string) {
		global $AJAX_MODE;
		global $ADEI_SETUP;
		
		preg_match_all('/\[\[[\w\s\d_-]*\]\]/', $string, $hits);
		foreach($hits[0] as $hit) {
			$title = preg_replace('/\[\[/', '', $hit);
			$title = preg_replace('/\]\]/', '', $title);
			if($this->wiki->getPage($title)->getContent() == '...') { $class = 'newpagelink';}
			else {$class = 'link';}
			if ($AJAX_MODE) {
			    $string = preg_replace("/\[\[$title\]\]/", 
					'<a class="'.$class.'" href="javascript:wiki.SetID('.$this->wiki->getPage($title)->getId().')">'.$title.'</a>',$string);
			} else {
			    $string = preg_replace("/\[\[$title\]\]/", 
					'<a class="'.$class.'" href="?setup='.$ADEI_SETUP.'&pageid='.$this->wiki->getPage($title)->getId().'">'.$title.'</a>',$string);
			}
		}
		return($string);
	}

	private function compileExternalLink($string) {
		$string=preg_replace("|\[url\](javascript:[^\[]+)\[/url\]|i",
				"<a class=\"external\" href=\"\\1\">\\1</a>",$string);

		$string=preg_replace("|\[url\]([^\[]+)\[/url\]|i",
				"<a class=\"external\" href=\"\\1\" target=\"_blank\">\\1</a>",$string);
		return($string);
	}

	private function compileExternalCustomLink($string) {
		$string=preg_replace('/\[url=\&quot;/i','[url="',$string);
		$string=preg_replace('/\&quot;\]/i','"]',$string);

		$string=preg_replace('|\[url="(javascript:[^\"]+)"]([^\[]+)\[/url\]|i',
				"<a class=\"external\" href=\"\\1\">\\2</a>",$string);
		$string=preg_replace('|\[url="([^\"]+)"]([^\[]+)\[/url\]|i',
				"<a class=\"external\" href=\"\\1\" target=\"_blank\">\\2</a>",$string);
		return($string);
	}

	private function compileMailTo($string) {
		$string = preg_replace("|\[mail\]([^\[]+)\[/mail\]|i","<a href=\"mailto:\\1\">\\1</a>",$string);
		return($string);
	}

	private function compileImage($string) {
		$string = preg_replace("|\[img\]([^\[]+)\[/img\]|i","<img src=\"\\1\" border=\"0\"/>",$string);
		return($string);
	}

	private function compileBoldText($string) {
		$string = preg_replace("|\[b\]([^\[]+)\[/b\]|i","<b>\\1</b>",$string);
		return($string);
	}

	private function compileItalicText($string) {
		$string = preg_replace("|\[i\]([^\[]+)\[/i\]|i","<i>\\1</i>",$string);
		return($string);
	}

	private function compileUnderlineText($string) {
		$string = preg_replace("|\[u\]([^\[]+)\[/u\]|i","<u>\\1</u>",$string);
		return($string);
	}

	private function compileHeader($string) {
 		$string = preg_replace('/\!\!([^\ \n].*)[\n]/e', "'\n\n<p class=\"wiki_header2\">'.'$1'.'</p>'", $string);
 		$string = preg_replace('/\!([^\ \n].*)[\n]/e', "'<p class=\"wiki_header\">'.'$1'.'</p>'", $string);
 		return($string);
 	}
 
 	private function compileEnumeration($string) {
 		$string = preg_replace("/\*([^\n]+)\n/i","<li>\\1</li>", $string);
 		$string = preg_replace("/<li>([^\n\n]+)<\/li>/i","<ul>\\0</ul>", $string);
		return($string);
	}

}

class Page {
	private $id;
	private $title;
	private $content;
	private $wiki;

	public function __construct($id, $title, $content, $wiki) {
		$this->id = $id;
		$this->title = $title;
		$this->content = $content;
		$this->wiki = $wiki;
	}

	public function getId() { return($this->id); }
	public function getTitle() { return($this->title); }
	public function getContent() { return($this->content); }
	
	public function includeHTMLContent($m) {
	    $page = $this->wiki->getPage($m[1]);
	    
	    if ($page) return $page->getHTMLContent(1);
	    return "";
	}
	
	public function getHTMLContent() { 
		$string = CodeInterpreter::getInstance($this->wiki)->parseCode($this->content);
		// $string = nl2br($string);
		$string = preg_replace_callback("/\[include\(([^)]+)\)\]/i", array($this, "includeHTMLContent"), $string);
		return $string;
	}
	public function setTitle($title) { $this->title = htmlspecialchars(str_replace("\r\n", "\n", $title)); }
	public function setContent($content) { $this->content = htmlspecialchars(str_replace("\r\n", "\n", $content)); }

}


class Wiki {
	private $fileName;
	private $data;
	private $xmlString;
	private $pages = array();

	public function __construct($fileName) {
		$this->fileName = $fileName;
		$this->readDataFromFile();
		$this->extractXMLStringFromData();
		$this->createPagesFromXML();
	}

	public function getPage($pageidentifier) {
		if(is_integer($pageidentifier)) { // search with id
			if(!isset($this->pages[$pageidentifier])) {
				// this feature is dangerous, return null
				//$this->pages[(int) $pageidentifier] = new Page((int)$pageidentifier, 'New Site', '...', $this);
				//$this->saveWikiToFile();
				return;
			}
			return($this->pages[$pageidentifier]);
		} elseif(is_string($pageidentifier)) { //search with title
			foreach($this->pages as $page) {
				if($page->getTitle() == $pageidentifier) {
					return($page);
				}
			}
			for($i = 1; true; $i++) {
				if(!array_key_exists($i, $this->pages)) { 
					break; 
				}
			}
			$this->pages[$i] = new Page($i, $pageidentifier, '...', $this);
			$this->saveWikiToFile();
			return($this->pages[$i]);
		}
	}

	public function readDataFromFile() {
		$this->data = "";
		if((is_file($this->fileName))&&(filesize($this->fileName)>0)) {
			$handle = fopen ($this->fileName, "r");
			while (!feof($handle)) {
				$this->data .= fgets($handle, 4096);
			}
			fclose ($handle);
		} else if (is_file($_SERVER['SCRIPT_FILENAME'])) {
			$handle = fopen ($_SERVER['SCRIPT_FILENAME'], "r");
			while (!feof($handle)) {
				$this->data .= fgets($handle, 4096);
			}
			fclose ($handle);
		}
	}

	public function findXMLStartPosition() {
		$i = 0;
		while($i < strlen($this->data)) {
			$i = strpos ($this->data, '<<' , $i);
			if(substr($this->data, $i+2, 2) == '>>') {
				return($i+4);
			}
			$i++;
		}
	}

	public function findXMLStopPosition() {
		$i = strlen($this->data);
		while($i >= 0) {
			$schrumpf = substr($this->data, 0, $i);
			if(substr($schrumpf, $i-2, 2) == '>>') {
				if(substr($schrumpf, $i-4, 2) == '<<') { 
					return($i-4);
				} 
			}
			$i--;
		}
	}

	public function extractXMLStringFromData() {
		$this->xmlString = substr($this->data, $this->findXMLStartPosition(), 
				$this->findXMLStopPosition() - $this->findXMLStartPosition());	
	}

	public function extractXMLStringFromMemory() {
		$xmlstr = '<!--<<';
		$xmlstr .= ">>";
		$xmlstr .= "<pages>\n";
		foreach($this->pages as $page) {
			$xmlstr .= '<page id="'.$page->getId().'" title="'.htmlspecialchars($page->getTitle()).'">'
				. htmlspecialchars($page->getContent())."</page>\n";
		}
		$xmlstr .= "</pages>\n";
		$xmlstr .= '<<';
		$xmlstr .= '>>-->';
		return($xmlstr);
	}

	public function createPagesFromXML() {
		$xml = new SimpleXMLElement($this->xmlString);
		foreach($xml->page as $page) {
			$this->pages[(int)$page['id']] = new Page($page['id'], $page['title'], $page, $this);
		}
	}

	public function saveWikiToFile() {
		global $ADEI_SETUP;
		global $ADEI_ROOTDIR;
		global $WIKI_FILENAME;
		
		$stringToWrite =/* substr($this->data,0,
				$this->findXMLStartPosition()-8) .*/ $this->extractXMLStringFromMemory();
		if (is_writable($WIKI_FILENAME)) {
			$handle = fopen($WIKI_FILENAME, "w+");
			if ($handle) {
			    fwrite($handle, $stringToWrite);
			    fclose($handle); 
			} else {ErrorHandler::reportError($WIKI_FILENAME.' not writable');}
		} else {ErrorHandler::reportError($WIKI_FILENAME.' not writable');}
		if (is_writable($ADEI_ROOTDIR . '/tmp/')) {
			if (!is_dir($ADEI_ROOTDIR . '/tmp/wiki')) {
			    @mkdir($ADEI_ROOTDIR . '/tmp/wiki');
			}
			if (is_dir($ADEI_ROOTDIR . '/tmp/wiki')) {
			    $handle = fopen($ADEI_ROOTDIR . '/tmp/wiki/'.$ADEI_SETUP.'-'.date('YmdTHis').'.xml', "w+");
			    if ($handle) {
				fwrite($handle, $stringToWrite);
				fclose($handle); 
			    }
			}
		} //else {ErrorHandler::reportError($ADEI_ROOTdIR.'/tmp/wiki'.' not writable');}
	}

	public function editPage($id, $title, $content) {
		foreach($this->pages as $page) {
			if($page->getTitle() == $title && $page->getId() != $id) {
				ErrorHandler::reportError("this pagetitle is already used by another page!");
				return;
			}
		}
		$this->pages[$id]->setTitle($title);
		$this->pages[$id]->setContent($content);
		$this->saveWikiToFile();
	}
}


if (!isset($ADEI_SETUP)) {
    if (file_exists("adei.php")) require("adei.php");
    else require("../adei.php");
}

if (file_exists("$ADEI_ROOTDIR/setups/$ADEI_SETUP/wiki.xml")) {
    $WIKI_FILENAME = "$ADEI_ROOTDIR/setups/$ADEI_SETUP/wiki.xml";
} else {
    $WIKI_FILENAME = "$ADEI_ROOTDIR/wiki.xml";
}

$REQ = new REQUEST($_GET);
$GET_ID = $REQ->GetProp('pageid', 1);

$wiki = new Wiki($WIKI_FILENAME);
if($GET_ID <= 0 || $wiki->getPage((int)$GET_ID) == null) {
	$GET_ID = 1;
}

if (preg_match("/services\\/[^\\\]+.php$/", $_SERVER['SCRIPT_FILENAME'])) {
    $AJAX_MODE = true;
    $error = false;

    header("Content-type: text/xml");

    $xslt = $REQ->GetProp('xslt');
    if ($xslt) {
	$temp_file = tempnam(sys_get_temp_dir(), 'adei_wiki.');
	$out = @fopen($temp_file, "w");
	if (!$out) $error = translate("I'm not able to create temporary file \"%s\"", $temp_file);
    } else {
	$out = fopen("php://output", "w");
    }
    
    if ($out) {
	fwrite($out, "<?xml version=\"1.0\" encoding=\"utf-8\"?>");
	fwrite($out, "<div>" . stripslashes($wiki->getPage((int)$GET_ID)->getHTMLContent(1)) . "</div>");
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
        echo "<div>$error</div>";
    }
    
    exit;
} else {
    header("Content-type: text/html");
}

?>

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Yada Yada Yada Wiki</title>
	<style type="text/css">

		body {
			background-color: #f3f3f3; 
			color: black;
			font-style: normal;
			font-size: 12px;
			line-height: 16px;
			font-family: sans-serif, arial;
			margin:0px;
		}
		td {
			color: black;
			font-style: normal;
			font-size: 12px;
			font-family: sans-serif, arial;
		}
		ul {
			list-style-type: disc;
		}
		.footer {
			color: gray;
			clear:left;
			margin-top:1em;
			margin-right:20px;
			text-align:right;
			font-size: 12px;
		}
		.outertable {
			background-color: #f3f3f3; border-width: 0px; border-color: #999999; border-style: solid; 
		}
		.innertable {
			background-color: white; border-width: 1px; border-color: #9f9f9f; border-style: solid; 
		}
		.title { 
			border-bottom: 1px; border-color: #999999; border-style: solid; 
			border-top: none; border-left: none; border-right: none;
			padding-bottom: 5px; font-size: 30px; font-weight:bold;
		}
		.edit { 
			padding: 0px; font-size: 30px; font-weight:bold;
		}
		.niceformelement {
			border: 1px solid gray; width:80px;
		}
		.shyline {
			border-bottom: 1px; border-color: #999999; border-style: dotted; 
			border-top: none; border-left: none; border-right: none;
		}
		a:link { color: blue; text-decoration:none; }
		a:visited { color: blue; text-decoration:none;}
		a:hover { color: blue; text-decoration:none;}
		a:active { color: blue; text-decoration:none;}
		a.newpagelink:link { color: red; text-decoration:none; }
		a.newpagelink:visited { color: red; text-decoration:none;}
		a.newpagelink:hover { color: red; text-decoration:none;}
		a.newpagelink:active { color: red; text-decoration:none;}
		a.external:link { color: blue; text-decoration:underline; }
		a.external:visited { color: blue; text-decoration:underline;}
		a.external:hover { color: blue; text-decoration:underline;}
		a.external:active { color: blue; text-decoration:underline;}
		.wiki_header{/*font-size: 18px;*/ font-weight:bold; margin-bottom: 18px; margin-top: 0px;}
		.wiki_header2{/*font-size: 12px;*/ font-weight:bold; margin-bottom: 12px; margin-top: 0px;}
		
	</style>
</head>
<body>
<?php
/*foreach($_GET as $key => $value) {
	print "GET: $key => $value <br/>";
}
foreach($_POST as $key => $value) {
	print "POST: $key => $value <br/>";
}*/

if($_POST['edit'] == 'true') {
	$wiki->editPage($_POST['id'], $_POST['title'], $_POST['content']);
}
if($_POST['editmenu'] == 'true') {
	$wiki->editPage(0, $_POST['title'], $_POST['content']);
}
?>

<table width="100%" class="outertable" cellspacing="20px" cellpadding="0px">
	<tr>
		<td class="title" colspan="2">
			<?print $wiki->getPage((int)$GET_ID)->getTitle();?>
		</td>
	</tr>
	<tr>
		<td valign="top" width="150px">
			<table width="100%" class="innertable" cellspacing="0px" cellpadding="10px">
			<tr>
			<td>
			<?
				if($_POST['modifymenu'] == 'true') {
					print '<form method="post">';
					print '<input type="hidden" name="editmenu" value="true" />';
					print '<input type="hidden" name="modifymenu" value="false" />';
					print '<input type="hidden" name="id" value="'.$wiki->getPage(0)->getId().'" />';
					print '<textarea class="text" cols="18" rows="10" name="content" WRAP="PHYSICAL" />';
					print stripslashes($wiki->getPage(0)->getContent());
					print '</textarea>';
					print "<br/>";
					print '<input type="submit" value="save" class="niceformelement" />';
					print "</form>";
				} else {
					print stripslashes($wiki->getPage(0)->getHTMLContent());
					if(((isset($AUTHORS) && in_array(getenv('REMOTE_USER'), $AUTHORS) || !isset($AUTHORS)))
							|| (isset($FREE4ALL) && in_array($wiki->getPage((int)$GET_ID)->getTitle(),$FREE4ALL))) 
					{
						print '<form style="text-align:left" method="post">';
						print '<p class="shyline"></p>';
						print '<input type="hidden" name="modifymenu" value="true"/>';
						print '<input type="submit" value="edit" class="niceformelement"/>';
						print '</form>';
					}
				}
			?>
			</td>
			</tr>
			</table>
		</td>
		<td colspan="1" valign="top">
			<table width="100%" class="innertable" cellspacing="0px" cellpadding="10px">
			<tr>
			<td>
			<?
				if($_POST['modify'] == 'true') {
					print '<form method="post">';
					print '<input type="hidden" name="edit" value="true"/>';
					print '<input type="hidden" name="modify" value="false"/>';
					print '<input type="hidden" name="id" value="'.$wiki->getPage((int)$GET_ID)->getId().'"/>';
					print '<input type="text" name="title" value="'.$wiki->getPage((int)$GET_ID)->getTitle().'"/>';
					print "<br/>";
					print '<textarea class="text" cols="100" rows="15" name="content" WRAP="PHYSICAL">';
					print stripslashes($wiki->getPage((int)$GET_ID)->getContent());
					print '</textarea>';
					print "<br/>";
					print '<input type="submit" value="save" class="niceformelement"/>';
					print "</form>";
				} else {
					print stripslashes($wiki->getPage((int)$GET_ID)->getHTMLContent());
					if((isset($AUTHORS) && in_array(getenv('REMOTE_USER'), $AUTHORS) || !isset($AUTHORS))) {
						print '<form style="text-align:left;" method="post">';
						print '<p class="shyline"></p>';
						print '<input type="hidden" name="modify" value="true"/>';
						print '<input type="submit" value="edit" class="niceformelement"/>';
						print '</form>';
					}
				}
			?>
			</td>
			</tr>
			</table>
		</td>
	</tr>
</table>
<div class="footer">
<!--<a href="<? print basename($WIKI_FILENAME).'s'?>">Yada Yada Yada Wiki</a> -->
<a href="http://www.pburkhalter.net/yadawiki.php">Yada Yada Yada Wiki 0.0.6</a>
</div>
</body>
</html>

<!--<<>><pages>
<page id="0" title="">[[Help]]
</page>
<page id="2" title="Help">!This is a Header
!!Images
[img]http://www.rosalux.de/cms/uploads/pics/gnu.png[/img]

!!Links
An internal link to this Wiki: [[New Page]]
(a red link means site is empty, but created)

An internal link to this Wiki: [[Help]]
(a blue link means site is not empty and created)

An external link [url]http://www.pburkhalter.net[/url]
A [url=\&amp;quot;http://www.pburkhalter.net\&amp;quot;]external[/url] link to the same destination
An [url=\&amp;quot;http://www.pburkhalter.net\&amp;quot;][img]http://www.pburkhalter.net/images/external_image_link.png[/img][/url] to the same destination

!!Formating
[b]Bold[/b]
[i]Italic[/i]
[u]Underlined[/u]

!!Enumeration
* banana
* apple
* kiwi

</page>
</pages>
<<>>-->