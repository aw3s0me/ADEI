function POPUP(cb, cbattr) {
    this.popup_states = new Array();
    this.popup_callback = new Array();
    this.popup_rewidth_callback = new Array();
    this.popup_reheight_callback = new Array();
    this.popup_on_callbacks = new Array();
    this.popup_off_callbacks = new Array();
    this.current_height = new Array();
    this.popups = new Array();
    this.modules = new Array();
    this.controls = new Array();
    this.popups_width = 300;
    this.num_opened = 0;

    this.super_popup = false;

    this.resizable = null;
    
    if (typeof cb != "undefined") this.RegisterCallback(cb,cbattr);
}

POPUP.prototype.RegisterPopup = function(module) {
    this.popups.push(module);
    this.controls[module] = new Array();
    this.popup_states[module] = 0;
}

POPUP.prototype.RegisterControlsModule = function(module, cmod) {
    if (typeof this.controls[module] == "undefined") {
	this.RegisterPopup(module);
    }

    this.modules[module] = cmod;
}

POPUP.prototype.RegisterControl = function(module, control) {
    if (typeof this.controls[module] == "undefined") {
	this.RegisterPopup(module);
    }
    
    this.controls[module].push(control);
}

POPUP.prototype.FindControl = function(control) {
    for (var i = 0; i < this.popups.length; i++) {
        var popup = this.popups[i];
	if (typeof this.controls[popup] != "undefind") {
	    for (var j = 0; j < this.controls[popup].length; j++) {
		if (this.controls[popup][j] == control) {
		    return popup;
		}
	    }
	}
    }
    return false;
}

POPUP.prototype.OpenControl = function(control) {
    var popup = this.FindControl(control);
    var module = this.modules[popup];
    
    if ((popup)&&(module)) {
	this.Open(popup);
	module.Open(control);
    }
}


POPUP.prototype.TryFixedSizeMode = function(sidebar, popup, geometry_node) {
    this.super_popup = false;
    
    var node = domGetLastChildByName(sidebar, "div");
    if (!node) return;
    
    if (!domGetFirstChildByName(node, "table")) {
	node = domGetLastChildByName(node, "div");
	if (!node) return;
	
	var subnodes = node.getElementsByTagName("table");
	if ((!subnodes)||(!subnodes.length))
	    alert("problem detecting popups structure");
    }
    this.super_node = node;

    var switch_node = document.getElementById("popup_switch_" + popup);
    nodes = switch_node.getElementsByTagName("button");
    if ((!nodes)||(!nodes.length)) {
	alert("problem detecting popups structure");
	return;
    }
    
    this.geometry_nodes = new Array();
    
    if (geometry_node) {
	node = document.getElementById(geometry_node);
	if (!node) {
	    alert("Invalid geometry_node is supplied");
	    return;
	}
	this.geometry_nodes.push(node);
    }

    this.super_button = nodes[0];
    this.super_popup = popup;
}


POPUP.prototype.RegisterOnCallback = function (module, cb, cbattr, call_once) {
    if (typeof this.popup_on_callbacks[module] == "undefined")
	this.popup_on_callbacks[module] = new Array();
	
    if (typeof call_once == "undefined") call_once = false;
    
    this.popup_on_callbacks[module].push({ 'cb': cb, 'cbattr': cbattr, 'once': call_once });
}

POPUP.prototype.RegisterOffCallback = function(module, cb, cbattr) {
    if (typeof this.popup_off_callbacks[module] == "undefined")
	this.popup_off_callbacks[module] = new Array();
    
    this.popup_off_callbacks[module].push({ 'cb': cb, 'cbattr': cbattr });
}

POPUP.prototype.RegisterCallback = function(cb, cbattr) {
	this.popup_callback.push ({ 'cb': cb, 'cbattr': cbattr });
}

POPUP.prototype.RegisterReWidthCallback = function(cb, cbattr) {
	this.popup_rewidth_callback.push ({ 'cb': cb, 'cbattr': cbattr });
}

POPUP.prototype.RegisterReHeightCallback = function(cb, cbattr) {
	this.popup_reheight_callback.push ({ 'cb': cb, 'cbattr': cbattr });
}

POPUP.prototype.GetEncompasingNode = function(node) {
//    return node.parentNode.parentNode.parentNode.parentNode.parentNode;
    return node.parentNode.parentNode.parentNode.parentNode;
}

POPUP.prototype.RunReWidthCallbacksFunction = function(self) {
    return function() {
	for (var i = 0; i < self.popup_rewidth_callback.length; i++) {
    	    var cb = self.popup_rewidth_callback[i];
    	    cb.cb(cb.cbattr);
	}
    }
}

POPUP.prototype.RunReHeightCallbacksFunction = function(self) {
    return function() {
	for (var i = 0; i < self.popup_reheight_callback.length; i++) {
    	    var cb = self.popup_reheight_callback[i];
    	    cb.cb(cb.cbattr);
	}
    }
}

POPUP.prototype.RunReWidthCallbacks = function() {
    setTimeout(this.RunReWidthCallbacksFunction(this), adei.cfg.parse_delay);
}

POPUP.prototype.RunReHeightCallbacks = function() {
    setTimeout(this.RunReHeightCallbacksFunction(this), adei.cfg.parse_delay);
}


POPUP.prototype.UpdateGeometry = function(module) {
    this.UpdateGlobalGeometry();
    
    with (this) {
	var node = document.getElementById("popup_" + module);
	var switch_node = document.getElementById("popup_switch_" + module);

	    /* Table fills not all available space, we can't fix it by width
	    attribute in HTML since it will prevent hiding. So, fixing here */
	var tbl_node = this.GetEncompasingNode(node);
	
	if (node) {
	    var cur_width_value = tbl_node.offsetWidth;
	    
	    if (cur_width_value > this.popups_width) {
		this.popups_width = cur_width_value;

//		for (var popup in this.popup_states) {
		for (var i = 0; i < this.popups.length; i++) {
		    var popup = this.popups[i];
		    if (this.popup_states[popup]) {
			var popup_node = document.getElementById("popup_" + popup);
			var popup_tbl_node = this.GetEncompasingNode(node);
			if (popup_node) {
//			    if (popup_tbl_node.offsetWidth < this.popups_width) {
				domSetMinWidth(popup_node, this.popups_width, false, popup_tbl_node.parentNode);
//			    }
			}
		    }
		}

		if (this.num_opened) { /* otherwise calling in Open */
		    this.RunReWidthCallbacks();
		}
	    } else if (cur_width_value < this.popups_width) {
		domSetMinWidth(node, this.popups_width, false, tbl_node.parentNode);
	    }
	}
	
	if ((node)&&(switch_node)&&(module!=this.super_popup)) {
	    var a = switch_node.getElementsByTagName("button")[0];
	    if (a) {
		var new_height_value = node.parentNode.offsetHeight; // td
		if (!this.current_height[module]) this.current_height[module] = a.offsetHeight;
				
		if (this.current_height[module] < new_height_value) { 
		    this.current_height[module] = new_height_value;
		    domSetMinHeight(a, new_height_value, false, a.parentNode); //td checking
		    this.RunReHeightCallbacks();
		}
	    }
	}

    }
}

POPUP.prototype.Open = function (module) {
    if (!this.popup_states[module]) {
	this.Switch(module);
    }
}

POPUP.prototype.Close = function (module) {
    if (this.popup_states[module]) {
	this.Switch(module);
    }
}

POPUP.prototype.Switch = function (module) {
 with (this) {
    if (typeof this.popup_states[module] == "undefined") {
	this.RegisterPopup(module);
    }

    if (this.popup_states[module]) {
	if (client.isKonqueror()) {
		// domHide is not working properly in Konqueror 
	    var node = document.getElementById("popup_" + module);
	    if (node) {
		var tdnode = node.parentNode;
//		node.style.witdth = "0px";
		tdnode.style.width = "0px";
//		tdnode.style.display = "none";
	    }
	}
	
	domHide("popup_" + module);

	this.popup_states[module] = 0;

        if (typeof this.popup_off_callbacks[module] != "undefined") {
	    for (var i = 0; i < this.popup_off_callbacks[module].length; i++) {
    		var cb = this.popup_off_callbacks[module][i];
    		cb.cb(cb.cbattr, false);
	    }
	}
	
	this.num_opened--;

	if (!this.num_opened) this.RunReWidthCallbacks();
    } else {
	domShow("popup_" + module);

	if (client.isKonqueror()) {
		// domHide is not working properly in Konqueror 
	    var node = document.getElementById("popup_" + module);
	    if (node) {
		var tdnode = node.parentNode;
		tdnode.style.width = "1px";
	    }
	}


	this.UpdateGeometry(module);
	popup_states[module] = 1;

        if (typeof this.popup_on_callbacks[module] != "undefined") {
	    for (var i = 0; i < this.popup_on_callbacks[module].length; i++) {
    		var cb = this.popup_on_callbacks[module][i];
    		cb.cb(cb.cbattr, true);
		if (cb.once) {
		    this.popup_on_callbacks[module].splice(i,1);
		    i = i - 1;
		}
	    }

	}


	if (!this.num_opened) this.RunReWidthCallbacks();
	this.num_opened++;
    }
    
    for (var i = 0; i < popup_callback.length; i++) {
	var cb = popup_callback[i];
	cb.cb(cb.cbattr);
    }
 }
}

POPUP.prototype.RunCallbacks = function() {
 with (this) {
    for (var i = 0; i < popup_callback.length; i++) {
	var cb = popup_callback[i];
	cb.cb(cb.cbattr);
    }
 }
}

POPUP.prototype.UpdateGlobalGeometry = function(height, vend) {
    if (!this.super_popup) return;
    
    if (vend) this.global_end = vend;
    else if (this.global_end) vend = this.global_end;
    else return;

    var mboffset = domGetNodeOffset(this.super_node);
    var real_size = vend - mboffset[1];

    var mboffset = domGetNodeOffset(this.super_button);
    var new_size = vend - mboffset[1];
    domSetHeight(this.super_button, new_size, true, this.super_node, real_size);

    if (!this.popup_states['controls']) return;

    for (var i = 0; i < this.geometry_nodes.length; ++i) {
	var node = this.geometry_nodes[i];
	var mboffset = domGetNodeOffset(node);

	var new_size = vend - mboffset[1];
	domSetHeight(node, new_size, true, this.super_node, real_size);
    }
}


function popupUpdateGeometryCallback(me, submodule) {
    me.popup.UpdateGeometry(me.module);
}

