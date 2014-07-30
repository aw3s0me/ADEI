function NSResolver(prefix) {
  if(prefix == 'xhtml') {
    return 'http://www.w3.org/1999/xhtml';
  } else {
    return null;
  }
}

function DSXMLParser(req, handler, attr) {
	this.req = req;
	this.data_handler = handler;
	this.data_attr = attr;

	this.ready = function() {
	    try {
		if (this.req.readyState == 4) {
		    if (this.req.status == 200) {
			this.data_handler(this.req.responseXML, this.data_attr, 0);
		    } else {
			this.data_handler(null, this.data_attr, translate("XML: HTTP Error %u", this.req.status));
		    }
		} 
	    } catch (e) { 
		adeiReportException(e, translate("XML: error catched while loading XML"));
	    }
	}

	return this;	
}

function getXML(url) {
    if (window.XMLHttpRequest) {
	req = new XMLHttpRequest();
    } else if (window.ActiveXObject) { 
	try {
	    req = new ActiveXObject("Msxml2.XMLHTTP");
	} catch (e) {
	    req = new ActiveXObject("Microsoft.XMLHTTP");
	}
    }

    try {
	req.open("GET", url, false);
        req.send( null );
	return req.responseXML;
    } catch (e) {
	adeiReportException(e, translate('getXML(%s) request is failed', url));
    }
}

function serverGetResult(url) {
    var error = 0;
    var xmldoc = getXML(url);

    if (xmldoc) {
	var values = xmldoc.getElementsByTagName("result");
	if (values.length > 1) {
	    error = translate("serverGetResult(%s) returned multiple result nodes", url);
	} else if (values.length > 0) {
	    return values[0].firstChild.data;
	} else {
	    var errors = xmldoc.getElementsByTagName("error");
	    if (errors.length > 0) {
		error = errors[0].firstChild.data;
	    } else {
	        error = translate("serverGetResult(%s) returned no result or error nodes", url);
	    }
	}
    } else {
	error = translate("serverGetResult(%s) got no data from server", url);
    }

    if (error) adeiReportError(error);
}

function doLoadXML(url, handler, attr) {
    var req;
    
    if (window.XMLHttpRequest) {
	req = new XMLHttpRequest();
    } else if (window.ActiveXObject) { 
	try {
	    req = new ActiveXObject("Msxml2.XMLHTTP");
	} catch (e) {
	    req = new ActiveXObject("Microsoft.XMLHTTP");
	}
    }

    var parser = DSXMLParser(req, handler, attr);
    
    try {    
      if (window.XMLHttpRequest) {
	req.data_attr = attr;
	req.data_handler = handler;
	req.onreadystatechange = function() {
	    try {
		if (req.readyState == 4) {
			// Removing circullar dependencies
		    req.onreadystatechange = null;

		    if (req.status == 200) {
			if (req.responseXML) {
			    req.data_handler(req.responseXML, req.data_attr, 0);
			} else {
			    req.data_handler(null, req.data_attr, translate("loadXML(%s) is failed: Non-xml response, check headers", url));
			}
		    } else {
			req.data_handler(null, req.data_attr, translate("loadXML(%s) is failed: HTTP Error %u", url, req.status));
		    }
		}
	    } catch (e) {
//		alert(typeof e);
//		for (i in e) alert(i + '=' + e[i]);
		adeiReportException(e, translate('loadXML(%s) handler is failed', url));
	    }
	}
      } else if (window.ActiveXObject) { 
	req.onreadystatechange = parser.ready;
      }

      req.open("GET", url, true);
      req.send( null );
    } catch (e) {
	adeiReportException(e, translate('loadXML(%s) request is failed', url));
    }
}


function loadXML(url, handler, attr) {
    if (isIE6()) queueXML(url, handler, attr);
    else doLoadXML(url, handler, attr);
}


function DSXMLQueue() {
    this.queue = new Array();
    this.busy = 0;
    this.inside = 0;
    this.completed = 0;
    
    this.ProcessNext = dsXMLQueueProcessNext;
}

function DSXMLQueueElement(url, handler, attr) {
    this.url = url;
    this.handler = handler;
    this.attr = attr;
}

function dsXMLQueueProcessNext() {
    if (this.queue.length > 0) {
	var nextelem = this.queue.shift();
	
	this.inside = 1;
	doLoadXML(nextelem.url, dsXMLQueueHandler, nextelem);
	this.inside = 0;

	if (this.completed) {
	    this.completed = 0;
	    return 1;
	}
    } else {
	this.busy = 0;
    }
    
    return 0;
}


var ds_xml_queue = new DSXMLQueue;

function dsXMLQueueHandler(xmldoc, element, error) {
    element.handler(xmldoc, element.attr, error);
    
    if (ds_xml_queue.inside) {
	ds_xml_queue.completed = 1;
    } else {
	while (ds_xml_queue.ProcessNext());
    }
}


function queueXML(url, handler, attr) {
    var element = new DSXMLQueueElement(url, handler, attr);
    
    if (ds_xml_queue.busy) {
	ds_xml_queue.queue.push(element);
    } else {
	ds_xml_queue.busy = 1;
	doLoadXML(url, dsXMLQueueHandler, element);
    }
}


function htmlReplace(htmldoc, place, jsexec) {
    if (typeof jsexec == "undefined") jsexec = false;

    if (typeof place == "string") {
	place = document.getElementById(place);
	if (!place) {
	    adei.ReportError(translate("Node %s is not found", place));
	    return;
	}
    }


    if (typeof htmldoc != "string") {
    	var serializer = new XMLSerializer();
	var htmldoc = serializer.serializeToString(htmldoc);
    }
//    alert(htmldoc);
    
    place.innerHTML = htmldoc;

    if (jsexec) {    
	var x = place.getElementsByTagName("script");
	for(var i=0;i<x.length;i++) {
	    eval(x[i].text);
	}
    }
}

function xsltApply(xsltproc, xmldoc, place) {
    if (typeof place == "string") {
	place = document.getElementById(place);
	if (!place) {
	    adei.ReportError(translate("Node %s is not found", place));
	    return;
	}
    }
    
    var htmldoc;
    
    if (typeof XSLTProcessor != "undefined") {	
	htmldoc = xsltproc.transformToFragment (xmldoc, document);

	if (place) {
	    place.innerHTML = "";
	    place.appendChild (htmldoc);
	}
    } else if (typeof xmldoc.transformNode != "undefined") { 
	htmldoc = xmldoc.transformNode(xsltproc);
	if (place) {
	    place.innerHTML = htmldoc;
	}
    } else {
	adei.ReportError(translate("Browser is not supporting XSL transform"));
    }
    
    return htmldoc;
}

function xsltHandler(xsltdoc, xattr, error) {
    if (error) {
	xattr.handler(null, xattr.attr, error);
	return;
    }
    
    if (isKonqueror()) {
	    // WebKit engine of safari defines XSLTProcessor, but the transformation is broken
	    // in current version. KHTML does not support XSLT at all. So we disabling it here.
	xattr.xslt_processor = false;
    } else if (typeof XSLTProcessor != "undefined") {	// Mozilla, Opera, Safari
	xattr.xslt_processor = new XSLTProcessor();
	xattr.xslt_processor.importStylesheet(xsltdoc);
    } else if (typeof xsltdoc.transformNode != "undefined") {  // IE
	xattr.xslt_processor = xsltdoc;
    } else { // Unsupported
	xattr.xslt_processor = false;
    }

    xattr.handler(xattr.xslt_processor, xattr.attr);
}

function loadXSLT(url, handler, attr) {
    var xattr = new Object();

    xattr.attr = attr;
    xattr.handler = handler;

    loadXML(url, xsltHandler, xattr);
}
