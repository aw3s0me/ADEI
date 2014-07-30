function domGetStyleProp(node, jsname, cssname) {
    if (jsname) return jsname;
    if ((node.currentStyle)&&(node.currentStyle[cssname])) return node.currentStyle[cssname];
    if ((document.defaultView)&&(document.defaultView.getComputedStyle)) {
	var style = document.defaultView.getComputedStyle(node,null);
	if (style) return style.getPropertyValue(cssname);
    }
    return null;
}

function domNodeSetText(node, text) {
    var text_node = document.createTextNode(text);
    if (node.firstChild) node.replaceChild(text_node, node.firstChild);
    else node.appendChild(text_node);
}

function domHide(id) {
    var node = document.getElementById(id);
    
    if (node) node.style.display = "none";
    return node;
}

function domShow(id, display) {
    if (typeof display == "undefined") {
	display = "block";
    }

    var node = document.getElementById(id);
    if (node) node.style.display = display;
    return node;
}


function cssSetClass(node, css_class) {
    if (typeof node == "string") 
	node = document.getElementById(node);
    
    if (node) {
	node.className = css_class;
/*	
	node.setAttribute("className", css_class);
	node.setAttribute("class", css_class);
*/
    }
    
    return node;
}

function cssSetProperty(css_class, prop, value) {
    var IE;
    var css;
    
    if (document.styleSheets[0].cssRules) {
	IE = false;
	css = document.styleSheets[0].cssRules;
    } else { // IE (Bad Billy and standards, part N)
	IE = true;
	css = document.styleSheets[0].rules;
    }

    for (i=0;i<css.length;i++) {
	if (css[i].selectorText.toLowerCase()==css_class) {
	    css[i].style[prop]=value;
	    return ;
	}
    }
    
	// creating new class
    try {
	var stylesheet = document.styleSheets[0];
	if (IE) stylesheet.addRule(css_class, prop + ":" + value);
	else stylesheet.insertRule(css_class + "{" + prop + ":" + value + "}", css.length);
	//alert('new class:' + css_class);
    } catch (e) {
    }
}

function cssHideClass(css_class) {
    cssSetProperty(css_class, 'display', 'none');
}

function cssShowClass(css_class) {
    cssSetProperty(css_class, 'display', 'inline');
}

function domGetValue(id) {
    var node = document.getElementById(id);
    if (node) {
	return node.value;
    } else return null;
}

function windowGetWidth() {
    return window.innerWidth != null? window.innerWidth : document.documentElement && document.documentElement.clientWidth ? document.documentElement.clientWidth : document.body != null ? document.body.clientWidth : null;
} 

function windowGetHeight() {
    return  window.innerHeight != null? window.innerHeight : document.documentElement && document.documentElement.clientHeight ? document.documentElement.clientHeight : document.body != null? document.body.clientHeight : null;
} 
                
function domAdjustGeometry(node, width, height, diminish) {
    if (typeof diminish == "undefined") diminish = false;
    if (node) {
	var cur_value = 0;

	if (!diminish) cur_value = node.offsetHeight;
	if (cur_value < height) node.style.height = height + "px";

	if (!diminish) cur_value = node.offsetWidth;
	if (cur_value < width) node.style.width = width + "px";
    }
}

function domGetMouseOffset(evt) {
    if (evt.offsetX != null) return [ evt.offsetX, evt.offsetY ];
    
    var obj = evt.target || evt.srcElement;
    var tmp = obj;
    var top = 0, left = 0;
    
    while (tmp.offsetParent) {
	left += tmp.offsetLeft;
	top += tmp.offsetTop;
	tmp = tmp.offsetParent;
    }
    
    return [ evt.clientX - left, evt.clientY - top ];
}

function domGetEventTarget(event) {
    if (typeof event.target != "undefined")
	return event.target;
    else
	return event.srcElement;
}

function domGetEventPageOffset(ev) {
	/* offset in the document */
    if (typeof ev.pageX == "undefined") 
	return [ ev.x, ev.y ];
    else
	return [ ev.pageX, ev.pageY ];
}

function domGetEventLayerOffset(ev) {
	/* offset in the target element */
    if (typeof ev.layerX == "undefined") 
	return [ ev.offsetX, ev.offsetY ];
    else
	return [ ev.layerX, ev.layerY ];
}


function domGetScrollEventDelta(ev) {
    var delta = 0;
    
    if (ev.detail) {	/* GECKO */
	delta = ev.detail;
    } else if (ev.wheelDelta) { /* IE/Opera */
	delta = -ev.wheelDelta/40;
    }
    
	// Still, delta absolute value could vary depending on browser version
    return delta;
}

function domGetNodeOffset(obj) {
    var curleft = curtop = 0;
    if (obj.offsetParent) {
	do {
	    curleft += obj.offsetLeft;
	    curtop += obj.offsetTop;
	} while (obj = obj.offsetParent);
    } else {
	alert("Can't calculate node position");
    }
    return [curleft,curtop];
}

function domGetNodeSize(obj) {
/*    if ((document.defaultView)&&(document.defaultView.getComputedStyle)) {
	var style = document.defaultView.getComputedStyle(obj,null);
	if (style) {
	    var x = parseInt(style.getPropertyValue("width"),10);
	    var y = parseInt(style.getPropertyValue("height"),10);
	    if ((x>0)&&(y>0)) return [x,y];
	}
    }*/
//    if (obj.clientWidth) return [obj.clientWidth, obj.clientHeight];
    return [obj.offsetWidth, obj.offsetHeight];
}


function domAlignNode(node, border, default_x, default_y, resize) {
    var width = windowGetWidth();
    var height = windowGetHeight();
    var size = domGetNodeSize(node);

    var size_x = 0;
    var size_y = 0;
    var pos_x = default_x;
    var pos_y = default_y;

    if ((pos_x + size[0] + border) > width) {
	var move = border + (pos_x + size[0]) - width;
	if (move > pos_x) {
	    if (pos_x > border) move = pos_x - border;
	    else {
		if (resize) {
		    // Using 2/3 of the screen
		    size_x = (width - 2 * border) * 2 / 3;
		    move = pos_x - (border + (width - 2 * border) / 6);
		} else move = 0;
	    }
	}
		    
	if (move) pos_x -= move;
    }

    if ((pos_y + size[1] + border) > height) {
	var move = border + (pos_y + size[1]) - height;
	if (move > pos_y) {
	    if (resize) {
		size_y = (height - 2 * border);
		move = pos_y - border;
	    } else {
		if (pos_y > border) move = pos_y - border;
		else move = 0;
	    }
	}
		    
	if (move) pos_y -= move;
    }

    if ((size_x)||(size_y)) {
	if (typeof resize == "function") resize(node, size_x, size_y);
	else {
	    if (size_x) node.style.width = size_x + "px";
	    if (size_y) node.style.height = size_y + "px";
	}
    }

    node.style.left = pos_x + "px";
    node.style.top = pos_y + "px";

	// Checking if everything is in place
    if ((!size_x)&&(!size_y)) {
	var new_size = domGetNodeSize(node);
	if ((new_size[0] != size[0])||(new_size[1] != size[1])) {
	    if (new_size[0] == size[0]) size_x = 0;
	    else size_x = size[0];
	    if (new_size[1] == size[1]) size_y = 0;
	    else size_y = size[1];
	    
	    if (typeof resize == "function") resize(node, size_x, size_y);
	    else {
		if (size_x) node.style.width = size_x + "px";
		if (size_y) node.style.height = size_y + "px";
	    }
	}
    }
}

function domSetMinHeight(node, height, extend, check_node) {
    if (typeof node.corr_y == "undefined") {
	if (domGetStyleProp(node, node.style.minHeight, "min-height"))
    	    node.style.minHeight = height + "px";
	else
    	    node.style.height = height + "px";

	if (check_node) node.corr_y = check_node.offsetHeight - height;
	else node.corr_y = node.offsetHeight - height;

	if (!node.corr_y) return;
	else if ((!extend)&&(node.corr_y<0)) {
	    node.corr_y = 0;
	    return;
	}
    }

    if (domGetStyleProp(node, node.style.minHeight, "min-height"))
	node.style.minHeight = (height - node.corr_y) + "px";
    else
        node.style.height = (height - node.corr_y) + "px";
}

function domSetHeight(node, height, extend, check_node, check_size, scroll_mode) {
    if (typeof node.corr_y == "undefined") {
    	node.style.height = height + "px";

	if (check_node) {
	    var check_height;
	    if ((typeof scroll_mode == "undefined")||(!scroll_mode)) check_height = check_node.offsetHeight;
	    else check_height = check_node.scrollHeight;
		
	    if (check_size)
		node.corr_y = check_height - check_size;
	    else 
	        node.corr_y = check_height - height;
	} else node.corr_y = node.offsetHeight - height;

	if (!node.corr_y) return;

	else if ((!extend)&&(node.corr_y<0)) {
	    node.corr_y = 0;
	    return;
	}
    }

    node.style.height = (height - node.corr_y) + "px";
}

function domSetMinWidth(node, width, extend, check_node) {
    if (typeof node.corr_x == "undefined") {
	
	    // In Opera/IE check returns true, but fails. With minHeight everything is OK
	if ((Prototype.Browser.Gecko)&&(domGetStyleProp(node, node.style.minWidth, "min-width")))
    	    node.style.minWidth = width + "px";
	else
	    node.style.width = width + "px";

	if (check_node) node.corr_x = check_node.offsetWidth - width;
	else node.corr_x = node.offsetWidth - width;

	if (!node.corr_x) return;
	else if ((!extend)&&(node.corr_x<0)) {
	    node.corr_x = 0;
	    return;
	}
    }
    
    if ((Prototype.Browser.Gecko)&&(domGetStyleProp(node, node.style.minWidth, "min-width")))
        node.style.minWidth = (width - node.corr_x) + "px";
    else
        node.style.width = (width - node.corr_x) + "px";
}

function domSetWidth(node, width, extend, check_node) {
    if (typeof node.corr_x == "undefined") {
	node.style.width = width + "px";

	if (check_node) node.corr_x = check_node.offsetWidth - width;
	else node.corr_x = node.offsetWidth - width;

	if (!node.corr_x) return;
	else if ((!extend)&&(node.corr_x<0)) {
	    node.corr_x = 0;
	    return;
	}
    }
    
    node.style.width = (width - node.corr_x) + "px";
}


function domGetChildsByName(node, name) {
    var ucname = name.toUpperCase();
    
    var arr = new Array();
    for (var n = node.firstChild; n; n = n.nextSibling) {
	if (n.nodeName.toUpperCase() == ucname) {
	    arr.push(n);
	}
    }
    return arr;
}

function domGetFirstChildByName(node, name) {
    var ucname = name.toUpperCase();
    
    for (var n = node.firstChild; n; n = n.nextSibling) {
	if (n.nodeName.toUpperCase() == ucname) return n;
    }
    return false;
}

function domGetLastChildByName(node, name) {
    var ucname = name.toUpperCase();
    
    var res = false;
    for (var n = node.firstChild; n; n = n.nextSibling) {
	if (n.nodeName.toUpperCase() == ucname) res = n;
    }
    return res;
}


function dateFormat(d) {
    return (d.getUTCMonth()+1) + '/' + d.getUTCDate() + '/' + d.getUTCFullYear() + ' ' +
	d.getUTCHours() + ':' + d.getUTCMinutes() + ':' + d.getUTCSeconds();
}

function eventCanceler(ev) {
    Event.stop(ev);
}

function tableAddRow(tbl, td) {
  if (typeof tbl == "string") var tbl = document.getElementById(tbl);
  if (!tbl) return;
  
  var lastRow = tbl.rows.length;
  var row = tbl.insertRow(lastRow);

  for (var i = 0; i < td.length; i++) {
    var cell = row.insertCell(i);
    cell.innerHTML = td[i];
  }

  return row;
}
