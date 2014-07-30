function XSLPOOL(cache) {
    if (typeof cache == "undefined") this.use_cache = false;
    this.use_cache = cache;
    
    this.cache = new Array();
    
    this.xsl_supported = true;
}

XSLPOOL.prototype.ProcessXML = function(xmldoc, xattr, error) {
    if (!xmldoc) {
	if (xattr.handler) xattr.handler(null, xattr.attr, error);
	else adei.ReportError(error);
	return;
    }
    
    if (xattr.handler) {
	var htmldoc = xsltApply(xattr.xsltdoc, xmldoc);
	xattr.handler(htmldoc, xattr.attr);
    } else {
	xsltApply(xattr.xsltdoc, xmldoc, xattr.attr);
    }
}

XSLPOOL.prototype.ApplyXSLT = function(xslt, xsltdoc, xmlurl, handler, attr) {
    var xattr = new Object;

    xattr.self = this;
    xattr.handler = handler;
    xattr.attr = attr;
    xattr.xsltdoc = xsltdoc;
    
    if (xsltdoc) { // The XSLT is supported by browser
	if (typeof xmlurl == "object") {
	    this.ProcessXML(xmlurl, xattr);
	} else {
	    loadXML(xmlurl, 
		function (xmldoc, attr, error) { 
		    attr.self.ProcessXML(xmldoc, attr, error)
		}, xattr);
	}
    } else { // XSLT is not supported
	this.xsl_supported = false;

	if ((!xslt)||(typeof xmlurl == "object")) { // We can't help in that case
	    adei.ReportError(translate("Browser is not supporting XSL transform"));
	} else { // trying server-side transformation, should be supported
	    if (!handler) handler = htmlReplace;
	    loadXML(urlAddProperty(xmlurl, "xslt", xslt), handler, attr);
	}
    }
}

XSLPOOL.prototype.ProcessXSLT = function(xsltdoc, xattr) {
    if (this.use_cache) {
	this.cache[xattr.xslt] = xsltdoc;
    }
    
    return this.ApplyXSLT(xattr.xslt, xsltdoc, xattr.xmlurl, xattr.handler, xattr.attr);
}

XSLPOOL.prototype.Load = function(xslt, xmlurl, handler, attr) {
    if (!this.xsl_supported) {
	return this.ApplyXSLT(xslt, false, xmlurl, handler, attr);
    }
    
    if (typeof this.cache[xslt] != "undefined") {
	return this.ApplyXSLT(xslt, this.cache[xslt], xmlurl, handler, attr);
    }
    
    var xattr = new Object;
    xattr.self = this;
    xattr.handler = handler;
    xattr.attr = attr;
    xattr.xslt = xslt;
    xattr.xmlurl = xmlurl;

    loadXSLT("services/get.php?target=xslt&xslt=" + xslt,
	function (xsltdoc, self) { xattr.self.ProcessXSLT(xsltdoc, xattr) }, 
	xattr
    );
}

XSLPOOL.prototype.HTMLReplaceCallback = function (htmldoc, place, error) {
    if ((typeof htmldoc != "undefined")&&(htmldoc)) {
	htmlReplace(htmldoc, place);
    } else {
	adei.ReportError(error);
    }
}

XSLPOOL.prototype.HTMLReplaceAndExecuteCallback = function (htmldoc, place, error) {
    if ((typeof htmldoc != "undefined")&&(htmldoc)) {
	htmlReplace(htmldoc, place, true);
    } else {
	adei.ReportError(error);
    }
}
