function CONFIG_CUSTOM(custom_props) {
    if (typeof custom_props != "undefined") {
	for (i in custom_props) {
	    this[i] = custom_props[i];
	}
    }
}

function CONFIG_CONFIG(custom_props) {
    this.db_server = null;
    this.db_name = null;
    this.db_group = null;
    this.control_group = null;
    this.db_mask = null;

    this.experiment = "0-0";
    this.window = "0";
    
    this.width = 0;
    this.height = 0;
    
    this.aggregation = null;
    this.interpolate = null;
    this.show_marks = null;
    this.show_gaps = null;

    this.plot_mode = 0;
    
    this.virtual = null;
    this.srctree = null;
    
    this.pageid = null;
    
    this.module = null;
    
    this.custom = new CONFIG_CUSTOM(custom_props);
}

function CONFIG_EXTRA(custom_props) {
    this.format = null;
    this.resample = null;
    this.mask_mode = null;
    
    this.custom = new CONFIG_CUSTOM(custom_props);
}

CONFIG_CONFIG.prototype.Clone = function(skip_custom) {
    if (typeof skip_custom == "undefined") skip_custom = false;
    
    var res = new Object;
    for (i in this) {
	if ((this[i])&&(typeof this[i] == "object")) {
	    if (!skip_custom) {
	        res[i] = objectClone(this[i]);
	    }
	} else {
	    res[i] = this[i];
	}
    }
    return res;
}

function CONFIG() {
    this.custom_props = new Object;	/* Array causes problems here, due to 'in' cycling over its methods */
    this.custom_extra = new Object;
    
    this.autoreg_props = [];
    this.autoreg_extra = [];

    this.cfg = new CONFIG_CONFIG(this.custom_props);
    this.cfg_extra = new CONFIG_EXTRA(this.custom_extra);
    
    this.win_width = 0;
    this.win_from = "";
    this.win_to = "";
    this.win_min = "";
    this.win_max = "";
    
    this.sel_from = 0;
    this.sel_to = 0;

    this.confirmed = 0;
    this.initializing = true;
    this.ready = false;

    this.page_width = 0;
    this.page_height = 0;

    this.groups = {
        page: {
            notifier: 'Page',
            props: [ "module" ],
            saved: null
        },
        source: {
            notifier: 'Source',
            props: [ "db_server", "db_name", "db_group", "db_mask", "srctree" ],
            saved: null
        },
        window: {
            notifier: 'Interval',
            props: [ "interval", "window" ],
            saved: null
        }
    }
    
    this.objlist = new Array;
}

CONFIG.prototype.ConfigureHistory = function() {
    this.history = new HISTORY(this.HistoryEvent(this));
}

CONFIG.prototype.RegisterUpdater = function(updater) {
    // DS: Multiple updaters support
    this.updater = updater;
}

CONFIG.prototype.Register = function(obj) {
    this.objlist.push(obj);
}


CONFIG.prototype.RegisterCustomProperty = function (property, default_value, extra) {
    if (typeof extra == "undefined") extra = false;
    
    if (typeof default_value == "undefined") {
	if (extra) {
	    if (typeof this.custom_extra[property] == "undefined") {
		this.custom_extra[property] = null;
		this.cfg_extra.custom[property] = null;
	    }
	} else {
	    if (typeof this.custom_props[property] == "undefined") {
		this.custom_props[property] = null;
		this.cfg.custom[property] = null;
	    }
	}
    } else {
	if (extra) {
	    this.custom_extra[property] = default_value;
	    this.cfg_extra.custom[property] = default_value;
	} else {
	    this.custom_props[property] = default_value;
	    this.cfg.custom[property] = default_value;
	}
    }
}


CONFIG.prototype.RegisterPropertyGroup = function(property_re, extra) {
    if (typeof extra == "undefined") extra = false;

    if (extra) this.autoreg_extra.push(property_re);
    else this.autoreg_props.push(property_re);
}


CONFIG.prototype.SetSource = function(server, db, group, cgroup, mask) {
    this.cfg.db_server  = server;
    this.cfg.db_name = db;
    this.cfg.db_group = group;
    this.cfg.control_group = cgroup;
    this.cfg.db_mask = mask;
}



CONFIG.prototype.SetInterval = function(from, to) {
    this.cfg.experiment = from + '-' + to;
}

CONFIG.prototype.GetAxisName = function(aid) {
    var prop;
    if (isNaN(aid)) {
	prop = aid + "_axis";
    } else {
	var anum = parseInt(aid);
	if (anum>0) prop = "axis" + anum;
	else prop = "axis";
    } 
    
    return prop;
}

CONFIG.prototype.SetAxisRange = function(aid, min, max) {
    var value;
    var prop = this.GetAxisName(aid) + "_range";

    if ((min)||(max)) value = min + ":" + max;
    else value = null;

    this.cfg.custom[prop] = value;
}

CONFIG.prototype.SetAxisMode = function(aid, log_scale) {
    var value;
    var prop = this.GetAxisName(aid) + "_mode";

    if (typeof log_scale == "undefined") {
        value = null;
    } else {
        if (log_scale) value = "log";
        else value = "std";
    }

    this.cfg.custom[prop] = value;
}


CONFIG.prototype.GetAxisRange = function(aid) {
    var prop = this.GetAxisName(aid) + "_range";

    if (typeof this.cfg.custom[prop] == "string") {
	var value = this.cfg.custom[prop];
	
	var m = /^(-?[^:]+):(-?[^:]+)$/.exec(value);
	if (m) {
	    return [m[1], m[2]];
	}
    }

    return false;
}

CONFIG.prototype.GetAxisMode = function(aid) {
    var prop = this.GetAxisName(aid) + "_mode";

    if (typeof this.cfg.custom[prop] == "string") {
	var value = this.cfg.custom[prop];
	if (value == "log") return [true];
	else return [false];
    }

    return false;
}

CONFIG.prototype.SetWindow = function(width, from, to, miny, maxy) {
    if (width<0) {
	this.win_width = -1;
	
//	if (miny != maxy) {
	    this.win_min = miny + "";
	    this.win_max = maxy + "";
//	}
//	if (from != to) {
	    this.win_from = from + "";
	    this.win_to = to + "";
//	}
	
    	if ((this.win_from||this.win_to)&&(this.win_min&&this.win_max)) {
	    this.cfg.window = this.win_from + '-' + this.win_to + ',' + this.win_min + ':' + this.win_max;
	} else if (this.win_min&&this.win_max) {
	    this.cfg.window = this.win_min + ':' + this.win_max;
	} else if (this.win_from||this.win_to) {
	    this.cfg.window = this.win_from + '-' + this.win_to;
	} else {
	    this.win_width = 0;
	    this.cfg.window = 0;
	}
    } else {
	this.win_width = width;

	this.win_from = "";
	this.win_to = "";
	this.win_min = "";
	this.win_max = "";

	this.cfg.window = width;
    }
}

CONFIG.prototype.SetSelection = function(from, to) {
    if (typeof from == "undefined") {
	this.sel_from = 0;
	this.sel_to = 0;
    } else {
	this.sel_from = from;
	this.sel_to = to;
    }
}


CONFIG.prototype.SetExportSettings = function(format, resample, mask_mode) {
    if (format) this.cfg_extra.format = format;
    if (resample) this.cfg_extra.resample = resample;
    if (mask_mode) this.cfg_extra.mask_mode = mask_mode;
    
    this.updater.Notify("ExportSettings");
}


CONFIG.prototype.SetAggregationSettings = function(a, i, marks, gaps) {
    if (a) this.cfg.aggregation = a;
    if (i) this.cfg.interpolate = i;
    if (marks) this.cfg.show_marks = marks;
    if (gaps) this.cfg.show_gaps = gaps;
}


CONFIG.prototype.SetPlotSettings = function(plot_mode) {
    if (plot_mode) this.cfg.plot_mode = plot_mode;
}

CONFIG.prototype.SetVirtualSettings = function(mode, settings) {
    if (mode) {
	this.cfg.virtual = mode;
	eval("this.cfg." + mode + "=settings");
    } else {
	this.cfg.virtual = null;
    }
}


CONFIG.prototype.SetWikiSettings = function(pageid) {
    this.cfg.pageid = pageid;
}

CONFIG.prototype.ParseWindow = function(winprm) {
    var win;
    
    if (typeof win == "undefined") win = this.cfg.window;
    else win = winprm;

    if (isNaN(win)) {
	this.win_width = -1;
	
	this.win_from = "";
	this.win_to = "";
	this.win_min = "";
	this.win_max = "";
	
	var err = false;
	var p = win.split(",");
	
	if ((p.legth < 1)||(p.length > 2)) err = true;
	else {
	    var process = function(self, prm) {

		var t = prm.split(":");

		if (t.length == 2) {
		    if ((isNaN(t[0]))||(isNaN(t[1]))) return true;
		    else {
			self.win_min = t[0];
			self.win_max = t[1];
		    }
		} else {
		    var m = /^(-?[^\-]*)-(-?[^\-]*)$/.exec(prm);
		    if (m) {
			if ((isNaN(m[1]))||(isNaN(m[2]))) return true;
			else {
			    self.win_from = m[1];
			    self.win_to = m[2];
			}
		    } else return true;
		}
		return false;
	    }
	    
	    err = process(this, p[0]);
	    if ((!err)&&(p.length == 2)) err = process(this, p[1]);
	}
		
	
	if (err) {
	    this.win_width = 0;
	    adeiReportError(translate("Invalid window \"%s\" is specified",win));
	}
    } else {
        this.win_width = win;

	this.win_from = "";
	this.win_to = "";
	this.win_min = "";
	this.win_max = "";
    }
    
    if (typeof win != "undefined") {
	this.SetWindow(this.win_width, this.win_from, this.win_to, this.min, this.max);
    }
}

CONFIG.prototype.ApplyConfig = function(save_on_apply, filter) {
    this.ParseWindow();

//    this.onConfirmation = this.UpdateAndSaveFunction(this, save_on_apply, filter);

    var len = this.objlist.length;
    for (var i = 0; i < len; i++) {
	var obj = this.objlist[i];
	if (obj.SimpleReadConfig) {
	    obj.SimpleReadConfig();
	}
    }
    
    for (var i = 0; i < len; i++) {
	var obj = this.objlist[i];
//	if (typeof obj.ReadConifg == "function") {	// DS: Comparison fails in Firefox
	if (obj.ReadConfig) {

		/* DSERROR: this is buggy (if we would have more than one 
		object here. After SOURCE completion it will call UpdateAndSave
		which will call Apply functions of all objects and, therefore,
		they will overwrite current config with values they still have
		from old one */
	    var opts = new Object({
		reset: true,
		success_cb: this.UpdateAndSaveFunction(this, save_on_apply, filter)
	    });

	    if (!this.ready) opts.reload = true;
	    
	    if (typeof filter != "undefined")
		eval("if (obj instanceof " + filter + ") obj.ReadConfig(opts);");
	    else {
		obj.ReadConfig(opts);
	    }
	}
    }

//    if (this.updater) this.updater.Update();
}


CONFIG.prototype.UpdateAndSaveFunction = function(self, save_on_apply, filter) {
    return function() {
        var len = self.objlist.length;
	for (var i = 0; i < len; i++) {
	    var obj = self.objlist[i];
	    if (obj.ApplyConfig) {
		if (typeof filter != "undefined")
		    eval("if (obj instanceof " + filter + ") obj.ApplyConfig(true);");
		else 
		    obj.ApplyConfig(true);
	    }
	}
	
	if (self.cfg.module) adei.OpenModule(self.cfg.module);

	if (self.updater) self.updater.Update();
	if (save_on_apply) self.Save();
    }
}

/*
CONFIG.prototype.UpdateFunction = function(self) {
    return function() {
	if (self.updater) self.updater.Update();
    }
}
*/

CONFIG.prototype.SetupGeometry = function(node, y) {
	/* correction in IE required */
    if (typeof node == "object") {
	this.cfg.width = node.offsetWidth;
	this.cfg.height = node.offsetHeight;
    } else {
	this.cfg.width = node;
	this.cfg.height = y;
    }
}

CONFIG.prototype.SetupPageGeometry = function(x, y) {
    this.page_width = x;
    this.page_height = y;
}

/*
CONFIG.prototype.Setup = function(src, ivl, wnd) {
    with (this) {
	SetSource(src.db_server, src.db_name, src.db_group, src.db_mask);
	SetInterval(ivl.start, ivl.end);
	SetWindow(wnd.width, wnd.from, wnd.to, wnd.miny, wnd.maxy);
    }
}
*/


CONFIG.prototype.SetupModule = function(m) {
    if (m != this.cfg.module) {
	if (this.cfg.module) {
	    this.cfg.module = m;
	    this.Save();
	} else {
	    this.cfg.module = m;
	}
    }
}

CONFIG.prototype.GetModule = function() {
    return this.cfg.module;
}

CONFIG.prototype.GetConfig = function() {
    return this.cfg;
}

CONFIG.prototype.Get = function(addon) {
    var res = this.cfg.Clone(true);
    Object.extend(res, this.cfg.custom);
    Object.extend(res, this.cfg_extra);
    Object.extend(res, this.cfg_extra.custom); res.custom = null;
    if (typeof addon != "undefined") Object.extend(res, addon);
    if (this.page_width > 0) {
        Object.extend(res, {
            page_width: this.page_width,
            page_height: this.page_height
        });
    }
/*
    if (typeof addon == "undefined") return Object.toJSON(this.cfg);
    else return Object.toJSON(Object.extend(this.cfg.Clone(), addon));
*/

    return res;
}

CONFIG.prototype.GetJSON = function(addon) {
    var res = this.Get(addon);
    return Object.toJSON(res);
}

CONFIG.prototype.GetProps = function(window_type) {
    var res;

    //if (!(cfg instanceof CONFIG_CONFIG)) cfg = this.cfg;

    with (this.cfg) {
	var win = this.cfg.window;
	if (typeof window_type != "undefined") {
	    if (window_type > 0) {
		win = "0";
	    } else if (window_type < 0) {
		if (this.sel_from != this.sel_to) 
		    win = this.sel_from + "-" + this.sel_to;
		else 
		    adeiReportError("The selection is empty. Storing full window", "CONFIG");
	    }
	}
	
	res = "";
	if (this.cfg.module) {
	    res += "module=" + this.cfg.module + "&";
	}
	
	if (this.cfg.pageid) {
	    res += "pageid=" + this.cfg.pageid + "&";
	}
	
	if (db_server) {
	    res += "db_server=" + this.cfg.db_server + "&db_name=" + this.cfg.db_name + "&db_group=" + this.cfg.db_group + "&control_group=" + this.cfg.control_group + "&db_mask=" + this.cfg.db_mask + "&experiment=" + this.cfg.experiment + "&window=" + win;
	    if (this.cfg.aggregation) res += "&aggregation=" + this.cfg.aggregation;
	    if (this.cfg.interpolate) res += "&interpolate=" + this.cfg.interpolate;
	    if (this.cfg.show_marks) res += "&show_gaps=" + this.cfg.show_marks;
	    if (this.cfg.show_gaps) res += "&show_gaps=" + this.cfg.show_gaps;
	    if (this.cfg.module) res+= "&module=" + this.cfg.module;
	    if (this.cfg.virtual) {
		res += "&virtual=" + this.cfg.virtual;
		if (this.cfg.virtual) {
		    eval("res += \"&\" + this.cfg.virtual + \"=\" + this.cfg." + this.cfg.virtual);
		}
	    }
	} else {
	    res += "no_source";
	}
    }

    with (this.cfg_extra) {
	if (format) res += "&format=" + format;
	if (resample) res += "&resample=" + resample;
	if (mask_mode) res += "&mask_mode=" + mask_mode;
    }
    
    for (var i in this.cfg.custom) {
	if (typeof this.cfg.custom[i] != "object") {
	    if ((this.cfg.custom[i] != null)&&(this.cfg.custom[i] != false)) {
	        res += "&" + i + "=" + this.cfg.custom[i];
	    }
	}
    }

    for (var i in this.cfg_extra.custom) {
	if (typeof this.cfg.custom[i] != "object") {
	    if ((this.cfg_extra.custom[i] != null)&&(this.cfg_extra.custom[i] != false)) {
	        res += "&" + i + "=" + this.cfg_extra.custom[i];
	    }
	}
    }
    
    return res;
}

CONFIG.prototype.ExtractProps = function(list) {
    res = "";
    for (var i = 0; i < list.length; i++) {
        var item = list[i];
        
        if (typeof this.cfg[item] != "undefined") res += "&" + item + "=" + this.cfg[item];
        else if (typeof this.cfg_extra[item] != "undefined") res += "&" + item + "=" + this.cfg_extra[item];
        else if (typeof this.cfg.custom[item] != "undefined") res += "&" + item + "=" + this.cfg.custom[item];
        else if (typeof this.cfg_extra.custom[item] != "undefined") res += "&" + item + "=" + this.cfg_extra.custom[item];
    }

    return res.substr(1);
}


CONFIG.prototype.ParseProps = function(props, partial, register_custom) {
    var cfg;
    var extra;
    var done = false;
    
    if (typeof partial == "undefined") partial = false;
    if (typeof register_custom == "undefined") register_custom = false;
    
    if (partial) {
	cfg = objectClone(this.cfg);
	extra = objectClone(this.cfg_extra);

	if (/db_server=/.exec(props)) {
	    cfg.db_name = null;
	    cfg.db_group = null;
	    cfg.control_group = null;
	    cfg.db_mask = null;
	} else if (/db_name=/.exec(props)) {
	    cfg.db_group = null;
	    cfg.control_group = null;
	    cfg.db_mask = null;
	} else if (/db_group=/.exec(props)) {
	    cfg.db_mask = null;
	}
	
	if (/experiment=/.exec(props)) {
	    cfg.window = "0";
	}
    } else {
	cfg = new CONFIG_CONFIG(this.custom_props);
	extra = new CONFIG_EXTRA(this.custom_extra);
    }
    
    
    var vars = props.split("&");

    for (var i = 0; i < vars.length; i++) {
	var pair = vars[i].split("=",2);
	if (pair.length != 2) continue;
	
	if (typeof cfg[pair[0]] != "undefined") {
	    if (pair[0] == "virtual") {
		cfg[pair[1]] = null;
	    }
	    cfg[pair[0]] = pair[1];
	    done = true;
	} else if (typeof extra[pair[0]] != "undefined") {
	    extra[pair[0]] = pair[1];
	    done = true;
	} else if (typeof cfg.custom[pair[0]] != "undefined") {
	    cfg.custom[pair[0]] = pair[1];
	    done = true;
	} else if (/axis\d*_(range|mode)$/.exec(pair[0])) {
	    cfg.custom[pair[0]] = pair[1];
	    done = true;
	} else if (typeof extra.custom[pair[0]] != "undefined") {
	    extra.custom[pair[0]] = pair[1];
	    done = true;
	} else if (register_custom) {
	    this.RegisterCustomProperty(pair[0]);
	    
	    if (typeof this.custom_props[pair[0]] != "undefined") {
		cfg.custom[pair[0]] = pair[1];
	    } else {
	        extra.custom[pair[0]] = pair[1];
	    }

	    done = true;
	} else {
	    var found = false;
	    for (var j=0; j<this.autoreg_props.length; j++) {
	        if (this.autoreg_props[j].exec(pair[0])) {
		    cfg.custom[pair[0]] = pair[1];
		    found = true;
		    break;
	        }
	    }
	    if (!found) {
	        for (var j=0; j<this.autoreg_extra.length; j++) {
	            if (this.autoreg_extra[j].exec(pair[0])) {
		        extra.custom[pair[0]] = pair[1];
		        break;
		    }
	        }
	    }
        }
    }

      // overriding 
    cfg['width'] = this.cfg.width;
    cfg['height'] = this.cfg.height;
    
    if (done) return new Object({'cfg': cfg, 'extra': extra});
    return false;
}


CONFIG.prototype.Notify = function() {
    for (var i in this.groups) {
        var cur = this.ExtractProps(this.groups[i].props);
        if (cur != this.groups[i].saved) {
            this.updater.Notify(this.groups[i].notifier);
            this.groups[i].saved = cur;
        }
    }
}

CONFIG.prototype.Save = function() {
    if ((this.cfg)&&(this.history)) {
        this.Notify();
	this.history.Add(this.GetProps(), this.cfg.Clone());
    }
}

CONFIG.prototype.FixLocation = function(props, hid) {
    if ((this.cfg)&&(this.history)) {
	if (typeof props == "undefined") {
	    var props = this.history.GetProps();
	    
	    if (typeof hid == "undefined")
		var hid = this.history.GetID();
	}
	    
	var new_props = this.GetProps();
	if (props != new_props) this.history.ReplaceLocation(new_props, props, hid);
    }
}

CONFIG.prototype.ReplaceURL = function() {
    this.FixLocation();
}

CONFIG.prototype.ReplaceConfig = function() {
    if ((this.cfg)&&(this.history)) {
/*	
	history.back(); // prevent complains from history module???
	var props = this.history.GetProps();
	var new_props = this.GetProps();
	
    	this.history.Replace(props, new_props, this.cfg.Clone());*/
	adeiReportError(translate('ReplaceConfig is not supported, yet'));
    }
}

CONFIG.prototype.HistoryEvent = function (self) {
    return function (newLocation, historyData, historyID) {
	if ((!historyData)||(typeof historyData.Clone != "function")) {
	    //return self.Load();
	    return;
	}
	
	self.cfg = historyData.Clone();
        self.Notify();
        self.FixLocation(newLocation, historyID);
	self.ApplyConfig();
    }
}

CONFIG.prototype.SetCustomProperties = function(props, module) {
    if ((typeof module != "undefined")&&(module == this.cfg.module)) {
        this.Load(props, true, true);
    } else {
	var cfg = this.ParseProps(props, true, true);
        var props = this.GetProps();
	this.cfg = cfg.cfg;
	this.cfg_extra = cfg.extra;
	var new_props = this.GetProps();
        
        if (this.ready) {
	    if (props != new_props) this.history.ReplaceLocation(new_props, props, this.history.GetID());
	}
    }
}

CONFIG.prototype.Load = function(props, partial, register_custom) {
    if (typeof props == "undefined") {
            // DS: Problem if we set extra custom (both) property before
	props = this.history.GetProps();
    }

    if (props) {
	if (typeof partial == "undefined") partial = false;
	if (typeof register_custom == "undefined") register_custom = false;
	
	var cfg = this.ParseProps(props, partial, register_custom);
	if (cfg) {
		/*  This should come first, otherwise the problems in IE6 will
		arise! */
	    if (this.cfg.module) {
		adei.module.Open(this.cfg.module);
	    }

	    if (cfg.cfg.db_server) {
	        this.cfg = cfg.cfg;
		this.cfg_extra = cfg.extra;
		
	        adei.SetStatus(translate("Loading..."), 0);
		this.ApplyConfig(true);
	    } else {
		var have_custom = false;
		for (var i in cfg.cfg.custom) {
		    have_custom = true;
		    break;
		}
		if (!have_custom) {
		    for (var i in cfg.extra.custom) {
			have_custom = true;
			break;
		    }
		}
		
		if (have_custom) {
	    	    this.cfg = cfg.cfg;
		    this.cfg_extra = cfg.extra;
		
	    	    adei.SetStatus(translate("Loading..."), 0);
		    this.ApplyConfig(true);
		}
	    }
	}
    }
}


CONFIG.prototype.ApplyDataSource = function(srv, db, grp, cgrp, mask, exp) {
    if (typeof srv != "undefined") {
	this.cfg.db_server = srv;
    } else this.cfg.db_server = null;

    if (typeof db != "undefined") {
	this.cfg.db_name = db;
    } else this.cfg.db_name  = null;
    
    if (typeof grp != "undefined") {
	this.cfg.db_group = grp;
    } else this.cfg.db_group  = null;

    if (typeof cgrp != "undefined") {
	this.cfg.control_group = cgrp;
    } else this.cfg.control_group  = null;

    if (typeof mask != "undefined") {
	this.cfg.db_mask = mask;
    } else this.cfg.db_mask  = null;

    if (typeof exp != "undefined") {
	this.cfg.experiment = exp;
	this.cfg.window = "0";
    } /* keeping otherwise */
    

    this.ApplyConfig(true, "SOURCE");
}


/*
CONFIG.prototype.RequestConfirmation = function(onConfirm) {
    if (this.onConfirmation) {
	if (this.onConfirmation == onConfirm) return 1;
	return 2;
    }
    this.onConfirmation = onConfirm;
    return 0;
}
*/

CONFIG.prototype.Confirm = function(me, val) {
/* 
	    BEWARE: 
	    SOURCE can confirm WINDOW with val=2 
	    INTERVAL can confirm WINDOW with val=1

    if (me instanceof SOURCE) this.confirmed |= (1<<val);
    else if (me instanceof INTERVAL) this.confirmed |= 4;
    else if (me instanceof WINDOW) this.confirmed |= 4;
*/

    if (me instanceof SOURCE) this.confirmed |= 1;

    if ((this.confirmed&1)==1) {
	this.confirmed = 0;

	if (this.initializing) {
	    this.ready = true;
	    this.initializing = false;

	    adei.SetStatus(translate("Ready..."), 0);
	    this.Load();
	} else if (this.onConfirmation) {
	    var func = this.onConfirmation;
	    this.onConfirmation = null;
	    func();
	}
    }
}

function configSetupModule(config, m) {
    config.SetupModule(m);
}
