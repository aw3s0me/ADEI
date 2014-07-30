function ADEI(maindiv_id, sidebar_id, statusbar_id, menu_id, session_id) {
    this.maindiv_node = document.getElementById(maindiv_id);
    this.statusbar_node = document.getElementById(statusbar_id);

    if (sidebar_id) this.sidebar_node = document.getElementById(sidebar_id);
    else this.sidebar_node = false;
    
    if ((!this.statusbar_node)||(!this.maindiv_node)) {
	alert("ADEI INTERNAL ERROR: Some of importnant HTML elements are not found");
    }

    this.config = new CONFIG;
    this.config.ConfigureHistory();
    
    this.module = new MODULE(null, this.SetupModule, this);
    if (this.sidebar_node) this.popup = new POPUP(Geometry, null);

    this.search = null; //new SEARCH();
    this.xslpool = new XSLPOOL(false/*true*/);
    this.virtual = null;
    
    this.status = new STATUS(this.statusbar_node);
    this.SetStatus(translate("Starting..."), 0, true);

    this.updater = new UPDATER(this.config, this.module, this.popup);

    if (menu_id) this.menu = new MENU(this.config, this.updater, menu_id);
    else this.menu = null;

	// Screen geometry, will be updated on load event
    this.screen_width = false;
    this.screen_height = false;
    
    this.source = null;
    this.source_interval = null;
    this.source_window = null;
    
    this.data_export = null;
    this.data_aggregator = null;
    this.graph = null;
    
    this.plot = null;
    
    this.superpopup_geometry_node = null;
    
    this.extra_cropper_buttons = new Array();
    
    this.options = null;
    
    this.cfg = new Object({
	'window_border': 10,
	'parse_delay': 100,
	'subsecond_threshold': 0,
	'zoom_ratio': 2,
	'query': null
    });

    
    if (typeof session_id == "undefined")
	this.adei_session = Math.floor(Math.random() * 4294967295);
    else
	this.adei_session = session_id;

    this.AddToQuery('adei_session=' + session_id);
    
	// Tracking key presses and currently pressed key    
    this.key = false;
    
    document.onkeydown = this.keyDown(this);
    document.onkeyup = this.keyUp(this);
    document.onkeypress = this.keyPress(this);
}

ADEI.prototype.keyDown = function (self) {
    return function(ev) {
	var keyID = (window.event) ? event.keyCode : ev.keyCode;
	self.key = keyID;

	self.keyHelp(keyID);
    }
}

ADEI.prototype.keyUp = function (self) {
    return function(ev) {
//	var keyID = (window.event) ? event.keyCode : ev.keyCode;
	self.key = false;
	if (self.key_status_id) {
	    self.ClearStatus(self.key_status_id);
	    self.key_status_id = false;
	}
    }
}

ADEI.prototype.keyPress = function (self) {
    return function(ev) {
	var keyID = (window.event) ? event.keyCode : ev.keyCode;
	if (keyID == 0) keyID = ev.charCode;
	
//	alert(keyID);
//	alert(ev.charCode);
    }
}

ADEI.prototype.keyHelp = function (key) {
    var duration = 3;

    if (this.config.GetModule() == "graph") {
	switch(key) {
	 case 72:	// h
	    this.key_status_id = this.SetStatus("wheel: Navigate history", duration);
	    break;
	 case 83:	// s
	    this.key_status_id = this.SetStatus("wheel: Select Server", duration);
	    break;
         case 68:	// d
	    this.key_status_id = this.SetStatus("wheel: Select Database", duration);
	    break;
	 case 71:	// g
	    this.key_status_id = this.SetStatus("wheel: Select Group", duration);
	    break;
	 case 77:	// m
	    this.key_status_id = this.SetStatus("wheel: Select Mask", duration);
	    break;
	 case 73:	// i
	    this.key_status_id = this.SetStatus("wheel: Select Item", duration);
	    break;
	 case 69:	// e
	    this.key_status_id = this.SetStatus("wheel: Select Experiment", duration);
	    break;
         case 84:	// t
	 case 16:	// Shift
	    this.key_status_id = this.SetStatus("wheel: Move on time scale", duration);
	    break;
	 case 86:	// v
	    this.key_status_id = this.SetStatus("wheel: Move on value scale", duration);
	    break;
	 case 87:	// w
	    this.key_status_id = this.SetStatus("wheel: Edge-zoom on time scale", duration);
	    break;
	 case 89:	// y
	    this.key_status_id = this.SetStatus("wheel: zoom value scale at mouse position", duration);
	    break;
	 case 67:	// c
	    this.key_status_id = this.SetStatus("wheel: Zoom time scale at middle of window, click: Center window", duration);
	    break;
	 case 76:	// l
	    this.key_status_id = this.SetStatus("wheel: Zoom time scale at mouse position", duration);
	    break;
         case 90:	// z
	    this.key_status_id = this.SetStatus("wheel: Zoom time scale at mouse position, click: Deep Zoom", duration);
	    break;
         default:
	    this.key_status_id = false;
	    this.SetStatus("Unknown key (code: " + key + ")", duration);
	}
	
	this.SetExtraStatus("Zoom(z,c,l,w,y), Shift(t,v,c), Source (s,d,g,m,i,e), Other (h) ", this.key_status_id);

    }
    
}


ADEI.prototype.SetOptions = function(opts) {
    this.options = opts;
}

ADEI.prototype.SetProperty = function(name, val) {
    this.cfg[name] = val;
}

ADEI.prototype.AddToQuery = function(props) {
    if (this.cfg['query']) this.cfg['query'] += '&' + props;
    else this.cfg['query'] = props;
}

ADEI.prototype.RegisterSearchEngine = function(search) {
    this.search = search;
}

ADEI.prototype.RegisterModule = function(mod, mod_class) {
    this.module.Register(mod, mod_class);
}

ADEI.prototype.RegisterSuperPopup = function(popup, geometry_node) {
    this.superpopup_geometry_node = document.getElementById(geometry_node);
    if (this.popup) {
	this.popup.TryFixedSizeMode(this.sidebar_node, popup, geometry_node);
    }
}

ADEI.prototype.RegisterVirtualControl = function(vc) {
    this.virtual = vc;
    this.virtual.AttachConfig(this.config);
}



ADEI.prototype.RegisterCustomProperty = function (property, default_value, extra) {
    this.config.RegisterCustomProperty(property, default_value, extra);
}

ADEI.prototype.RegisterCropperButton = function(button_info) {
    if (this.graph) {
	this.graph.RegisterCropperButton(button_info);
    } else {
	this.extra_cropper_buttons.push(button_info);
    }
}

ADEI.prototype.AttachSourceModule = function(source, ivl, wnd) {
    this.source = source;
    this.source_interval = ivl;
    this.source_window = wnd;

    source.AttachConfig(this.config);
    source.Init(this.updater, this.options);

    if (this.menu) this.menu.AttachWindow(wnd);
}

ADEI.prototype.AttachExportModule = function(data_export) {
    this.data_export = data_export;

    data_export.AttachConfig(this.config);    
    data_export.Init();
    
    if (this.menu) this.menu.AttachExporter(data_export);
}

ADEI.prototype.AttachAggregationModule = function(data_aggregator) {
    this.data_aggregator = data_aggregator;

    data_aggregator.AttachConfig(this.config);    
    data_aggregator.Init(this.updater);
/*    
    if (this.menu) this.menu.AttachAggregator(data_aggregator);
*/
}

ADEI.prototype.AttachPlotModule = function(plot) {
    this.plot = plot;

    plot.AttachConfig(this.config);    
    plot.Init(this.updater);
}

ADEI.prototype.AttachGraphModule = function(graph) {
    this.graph = graph;

    graph.AttachConfig(this.config);    
    graph.AttachWindow(this.source_window);
    graph.AttachSource(this.source);
    graph.AttachExporter(this.data_export);

    for (var i = 0; i < this.extra_cropper_buttons.length; i++) {
	this.graph.RegisterCropperButton(this.extra_cropper_buttons[i]);
    }
    
    this.extra_cropper_buttons = new Array();
}

ADEI.prototype.Start = function(mod, update_rate) {
    if (this.virtual) this.virtual.Start();
    this.module.Open(mod);
    if (this.menu) this.menu.Load();
    this.updater.Start(update_rate);
}

/* This functions are used by external code to change current module/popup */
ADEI.prototype.OpenModule = function(mod) {
    this.module.Open(mod);
}

ADEI.prototype.SwitchPopup = function(mod) {
    if (this.popup) this.popup.Switch(mod);
}

/* This is functions get notification if module/popup are changed */
ADEI.prototype.SetupModule = function(self, mod) {
    self.config.SetupModule(mod);
    self.UpdateModuleGeometry(mod, self.screen_width, self.screen_height);
    
    var m = self.module.GetModule(mod);
    self.source.SetupModule(m);

    if ((self.config)&&(self.config.ready)) self.updater.Update();
}

ADEI.prototype.UpdateModuleGeometry = function(mod, width, height) {
    if ((!width)||(!height)) return;

    this.screen_width = width;
    this.screen_height = height;
    
    var m = this.module.GetModule(mod);
    if (!m) return;

    var mboffset = domGetNodeOffset(this.maindiv_node);
/*
    This was a rougher approach
    var h1 = height - 
	document.getElementById("header_div").offsetHeight - 
	this.statusbar_node.offsetHeight;
    var w1 = width - this.sidebar_node.offsetWidth;
*/
    
    var h = this.statusbar_node.offsetTop - mboffset[1]; /* substract something to fix IE6 */
    var w = width - mboffset[0]; 
    adei.config.SetupPageGeometry(w, h);

    if (typeof m.AdjustGeometry != "undefined") {
        m.AdjustGeometry(w, h, width, this.statusbar_node.offsetTop);
    } else if (typeof m.GetNode() != "undefined") {
	var node = m.GetNode();

	if (node.offsetParent) { // on screen
	    var mboffset = domGetNodeOffset(node);
	    var w = (width) - mboffset[0];
	    var h = (this.statusbar_node.offsetTop) - mboffset[1] - 1;
	    domAdjustGeometry(node, w, h, true);
	}

    }
    
    if (this.popup) this.popup.UpdateGlobalGeometry(h, this.statusbar_node.offsetTop);
    this.status.UpdateWidth(width);
}

ADEI.prototype.GetURL = function(page, props) {
    props = objectToProps(props);
    
    if (this.cfg.query) {
	if (props) return page + "?" + this.cfg.query + "&" + props;
	return page + "?" + this.cfg.query;
    } else {
	if (props) return page + "?" + props;
	return page;
    }
}

ADEI.prototype.GetServiceURL = function(name, props) {
    return this.GetURL("services/" + name + ".php", props);
}

ADEI.prototype.GetService = function(name, props) {
    return this.GetServiceURL(name, props);
}

ADEI.prototype.GetListService = function(name, props) {
    props = objectToProps(props);
    return this.GetService('list', props?(props+"&target="+name):("target="+name));
}

ADEI.prototype.GetSelectService = function(name, props) {
    props = objectToProps(props);
    return this.GetService('list', props?(props+"&menu=1&target="+name):("menu=1&target="+name));
}

ADEI.prototype.GetToolService = function(name, props) {
    props = objectToProps(props);
    return this.GetService('tools', props?(props+"&target="+name):("target="+name));
}

ADEI.prototype.GetSearchService = function(text, props) {
    var cprops = this.config.GetProps();
    
    return this.GetService('search', urlJoinProps(cprops, props, "search="+text));
}

ADEI.prototype.SetSuccessStatus = function(msg, duration, non_ready) {
    if (this.status) {
	if ((non_ready)||(this.config.ready)) {
	    if (typeof duration == "undefined")
		return this.status.Set(msg, this.cfg.default_status_duration, true);
	    else
		return this.status.Set(msg, duration, true);
	}
    }
}


ADEI.prototype.SetStatus = function(msg, duration, non_ready) {
    if (this.status) {
	if ((non_ready)||(this.config.ready)) {
	    if (typeof duration == "undefined")
		return this.status.Set(msg, this.cfg.default_status_duration);
	    else
		return this.status.Set(msg, duration);
	}
    }
}

ADEI.prototype.SetExtraStatus = function(msg, id) {
    if (this.status) {
	this.status.SetComment(msg, id);
    }
}

ADEI.prototype.ProposeStatus = function(msg, duration) {
    if (this.status) {
	if (this.config.ready) {
	    if (typeof duration == "undefined")
		return this.status.Propose(msg, this.cfg.default_status_duration);
	    else
		return this.status.Propose(msg, duration);
	}
    }
}

ADEI.prototype.ClearStatus = function(id) {
    if (this.status) {
	this.status.Clean(id);
    }
}

ADEI.prototype.Search = function(text) {
    if (this.search) {
	this.search.StartSearch(text);
    }
}

ADEI.prototype.OpenControl = function(name) {
    if (this.popup) this.popup.OpenControl(name);
}

ADEI.prototype.divUpdateCallback = function(place, jsexec, onload) {
    return function(xmldoc, error) {
        if (xmldoc) {
            htmlReplace(xmldoc, place, jsexec);
            if (onload) onload();
        } else {
	    htmlReplace(translate("Update failed due \"%s\"", error), place);
        }
    }
}

ADEI.prototype.UpdateDIV = function(div, xmlurl, xslt, jsexec, onload) {
    var cb = false;
    
    if (typeof jsexec == "undefined") jsexec = false;
    if (typeof onload == "undefined") onload = false;

    if ((jsexec)||(onload)) cb = this.divUpdateCallback(div, jsexec, onload);

    this.xslpool.Load(xslt, xmlurl, cb, div);
}

ADEI.prototype.ServiceDIV = function(div, xml, xslt, extra, jsexec, onload) {
    if (typeof jsexec == "undefined") jsexec = false;
    if (typeof onload == "undefined") onload = false;

    var cfg = this.config.Get(extra);
    this.UpdateDIV(div, this.GetService(xml, cfg), xslt, jsexec, onload);
}

ADEI.prototype.ReportError = function(msg, module_name, module) {
    adeiReportError(msg, module_name, module);
}

ADEI.prototype.ReportException = function(e, msg, module_name, module) {
    adeiReportException(e, msg, module_name, module);
}

ADEI.prototype.SetConfiguration = function(query) {
    this.config.Load(query, true);
}

ADEI.prototype.RegisterCustomProperty = function(property, default_value, extra) {
    if (typeof extra == "undefined") extra = false;
    if (typeof default_value == "undefined") default_value = null;
    this.config.RegisterCustomProperty(property, default_value, extra);
}

ADEI.prototype.RegisterExtraProperty = function(property, default_value) {
    if (typeof default_value == "undefined") default_value = null;
    this.config.RegisterCustomProperty(property, default_value, true);
}

ADEI.prototype.SetCustomProperties = function(query, per_module) {
    if (typeof per_module == "undefined") 
        this.config.Load(query, true, true);
    else
        this.config.SetCustomProperties(query, per_module);
}
