function isInt(text) {
    var i = parseInt(text);
    
    if (isNaN(i)) return false;

    if (i.toString() == text) return true;
    return false;
}

function valueHandler(xmldoc, prefix) {
    var values = xmldoc.getElementsByTagName("Value");

    for (var i = 0; i < values.length; i++) {
	var value = values[i];
	var id = prefix + "-" + value.getAttribute("id");
	var value_node = document.getElementById(id);
	if ((value.firstChild)&&(value_node)) {
	    var value_text = value.firstChild.data;
	    var is_int = isInt(value_text);
	    var text_node;
	    
	    if ((is_int)&&(value_text<0)) text_node = document.createTextNode("No Info");
	    else text_node = document.createTextNode(value_text);
	
	    value_node.replaceChild(text_node, value_node.firstChild);
	    if ((is_int)&&(value_text<0)) {
		value_node.setAttribute("class", "badvalue");
	    }
	}
    }
}


function valueRun(url, handler, prefix, renew_time) {
    loadXML(url, handler, prefix);
    setTimeout("valueRun(\"" + url + "\"," + handler + ",\"" + prefix + "\"," + renew_time + ");", renew_time);
}

function valueRunner(url, handler, prefix, init_time, renew_time) {
    setTimeout("valueRun(\"" + url + "\"," + handler + ",\"" + prefix + "\"," + renew_time + ");", init_time);
}

function valueRun2(rattr) {
    loadXML(rattr.url, rattr.handler, rattr.attr);
    setTimeout(valueRun2, rattr.renew_time, rattr);
}

function valueRunner2(url, handler, prefix, init_time, renew_time, attr) {
    var rattr = new Object();

    rattr.url = url;
    rattr.handler = handler;
    rattr.prefix = prefix;
    rattr.renew_time = renew_time;
    rattr.attr = attr;
    
    setTimeout(valueRun2, init_time, rattr);
}
