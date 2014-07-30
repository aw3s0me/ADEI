var currnet_module = null;
var module_callback = null;

function MODULE(name, callback, callback_attr) {
    if (typeof callback == "undefined") callback = null;
    if (typeof callback_attr == "undefined") callback_attr = null;

    this.module_name = name;
    this.current_module = null;

//    this.module_callback = callback;
//    this.attr = callback_attr;

    this.no_height_adjustments = false;
    this.module_height = 0;

    this.module = new Array();
    this.names = new Array();
    this.geometry_callback = new Array();
    this.module_callback = new Array();
    
    if (callback) this.RegisterCallback(callback, callback_attr);
}

MODULE.prototype.DisableHeightAdjustments = function() {
    this.no_height_adjustments = true;
}

MODULE.prototype.Register = function(module, module_class) {
    if (module_class) {
	this.names.push(module);
	this.module[module] = module_class;
	return true;
    }
    return false;
}

MODULE.prototype.RegisterCallback = function(cb, cbattr) {
    this.module_callback.push ({ 'cb': cb, 'cbattr': cbattr });
}

MODULE.prototype.RegisterGeometryCallback = function(cb, cbattr) {
    this.geometry_callback.push ({ 'cb': cb, 'cbattr': cbattr });
}


MODULE.prototype.GetModule = function(m) {
    return this.module[m];
}

MODULE.prototype.GetOpenModule = function() {
    if (this.current_module)
	return this.GetModule(this.current_module);
    return null;
}

MODULE.prototype.Open = function(mod) {
    var prefix;
    
    if (mod != this.current_module) {
	this.UpdateGeometry(); // Updating just current height
	
	if (this.module_name) prefix = this.module_name + "_";
	else prefix = "";
	
	if (this.current_module) {
	    domHide("module_" + prefix + this.current_module);
	    cssSetClass("module_link_" + prefix + this.current_module, "module_" + prefix + "link");
	}
	domShow("module_" + prefix + mod);
	cssSetClass("module_link_" + prefix + mod, "module_" + prefix + "link_current");


	this.current_module = mod;
//	alert(mod);

	this.RunModuleCallbacks();
	this.UpdateGeometry();
//	alert(done);
    }
}



MODULE.prototype.RunGeometryCallbacks = function() {
    for (var i = 0; i < this.geometry_callback.length; i++) {
	var cb = this.geometry_callback[i];
	cb.cb(cb.cbattr);
    }
}

MODULE.prototype.RunModuleCallbacks = function() {
    for (var i = 0; i < this.module_callback.length; i++) {
	var cb = this.module_callback[i];
	cb.cb(cb.cbattr, this.current_module);
    }
}

/*
The problem: offsetHeight includes 'borders' and style.heigh - not. So insert
borders inside of the main divs.
*/
MODULE.prototype.UpdateGeometry = function() {
    if (this.no_height_adjustments) return;
    
    with (this) {
	if (module_name) prefix = module_name + "_";
	else prefix = "";
	
	var node = document.getElementById("module_" + prefix + current_module);
	if (node) {
	    var cur_height_value = node.offsetHeight;
	    var runcb = false;
	    
	    if (node.scrollHeight) {
		var full_height = node.scrollHeight;
		if (full_height > cur_height_value) {
		    if (full_height > module_height) {
			module_height = full_height;
			runcb = true;
		    }
		    cur_height_value = 0;
		}
	    }


	    //alert(cur_height_value + " - " + module_height);
	    
	    if (cur_height_value > module_height) {
		module_height = cur_height_value;

		    // We still should increase, window so it could not be decreased back
		domSetMinHeight(node, module_height);
		
		this.RunGeometryCallbacks();
	    } else if (module_height > 0) {
		domSetMinHeight(node, module_height);
		this.RunGeometryCallbacks();
	    }

	}
    }
}


var default_module = new MODULE(null);

function moduleSetCurrent(module) {
    default_module.SetCurrent(module);
}

function moduleOpen(module) {
    default_module.Open(module);
}

function moduleUpdateGeometry(module) {
    module.UpdateGeometry();
}


function moduleGetElement(module, mclass_name) {
    if (typeof mclass_name == "undefined")  prefix = "";
    else prefix = mclass_name + "_";

    return document.getElementById("module_" + prefix + module);
}
