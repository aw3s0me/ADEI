function SOURCE_CONFIG() {
    this.db_server = null;
    this.db_name = null;
    this.db_group = null;
    this.control_group = null;
    this.db_mask = null;
    this.db_item = null;
}


function SOURCE(interval, srv_sel_id, db_sel_id, group_sel_id, cgroup_sel_id, mask_sel_id, item_sel_id, apply_id, opts) {
    this.cfg = new SOURCE_CONFIG;

//    interval.SetSource(this);
    this.interval = interval;

    this.apply_button = document.getElementById(apply_id);
    
    this.source_itemsel = new SELECT(item_sel_id, sourceUpdateItem, this);
    this.source_masksel = new SELECT(mask_sel_id, sourceUpdateMask, this);
    this.source_grsel = new SELECT(group_sel_id, sourceUpdateGroup, this);
    this.source_cgrsel = new SELECT(cgroup_sel_id, sourceUpdateCGroup, this);
    this.source_dbsel = new SELECT(db_sel_id, sourceUpdateDatabase, this);
    this.source_srvsel = new SELECT(srv_sel_id, sourceUpdateServer, this);

    this.source_srvsel.SetupSource(adei.GetSelectService("servers", "list_virtual=1&skip_uncached=1"), "db_server");
    this.source_srvsel.SetupCompletionCallbacks(sourceEnableControls, sourceEnableControls);
    this.source_dbsel.SetupSource(adei.GetSelectService("databases", "skip_uncached=1"), "db_name");
    this.source_dbsel.SetupCompletionCallbacks(sourceEnableControls, sourceEnableControls);
    this.source_grsel.SetupSource(adei.GetSelectService("groups", "list_virtual=1&list_complex=1&skip_uncached=1"), "db_group");
    this.source_grsel.SetupCompletionCallbacks(sourceEnableControls, sourceEnableControls);
    this.source_cgrsel.SetupSource(adei.GetSelectService("cgroups", "list_virtual=1"), "control_group");
    this.source_cgrsel.SetupCompletionCallbacks(sourceEnableControls, sourceEnableControls);
    this.source_masksel.SetupSource(adei.GetSelectService("masks"), "db_mask");
    this.source_masksel.SetupCompletionCallbacks(sourceEnableControls, sourceEnableControls);
    this.source_itemsel.SetupSource(adei.GetSelectService("items"), "db_item");
    this.source_itemsel.SetupCompletionCallbacks(sourceEnableControls, sourceEnableControls);

    this.source_srvsel.AddChild(this.source_dbsel);
    this.source_dbsel.AddChild(this.source_grsel);
    this.source_dbsel.AddChild(this.source_cgrsel);
    this.source_grsel.AddChild(this.source_masksel);
    this.source_grsel.AddChild(this.source_itemsel);
    this.source_grsel.AddChild(interval.source_expsel);
    

    this.config = null;
    this.updater = null;
    
    if (typeof opts == "object") this.opts = opts;
    else this.opts = new Object();
    
    this.selection_callbacks = [
	new Array(),
	new Array(),
	new Array()
    ];
    
    this.submit_callbacks = new Array();
    
    this.module_type = HISTORY_MODULE_TYPE;
    
    this.fixhidden_ie_mode = false;
}

SOURCE.prototype.FixHiddenIE = function() {
    this.fixhidden_ie_mode = true;
}

SOURCE.prototype.Init = function(updater, opts) {
    if (typeof opts == "undefined") opts = new Object;
    opts.reload = true;
    opts.confirm = true;

    opts.cb_attr = this;
    opts.success_cb = sourceConfirmInitialization;

    this.updater = updater;
    this.interval.SetUpdater(updater);
    updater.RegisterControl(this);
    
    this.Update(opts);
}


function sourceConfirmInitialization(source) {
    source.config.Confirm(source);
}

SOURCE.prototype.AttachConfig = function(config) {
    this.config = config;
    this.interval.SetConfig(config);

    config.Register(this);

//    this.Apply();
}


SOURCE.prototype.ApplyConfig = function(subcall) {
    if (this.config) {
    	this.config.SetSource(
	    this.cfg.db_server, 
	    this.cfg.db_name, 
	    this.cfg.db_group, 
	    this.cfg.control_group,
	    (isNaN(this.cfg.db_mask)||(this.cfg.db_mask>=0))?this.cfg.db_mask:this.cfg.db_item
	);
	
	this.interval.ApplyConfig(true);

	this.RunSubmitCallbacks();

	if (typeof subcall == "undefined") {
	    if (this.config.GetModule() == "wiki") {
		    // should be saved here
		adei.OpenModule("graph");
	    } else {
	        this.config.Save();
	    }
	}
    }
}

SOURCE.prototype.ReadConfig = function(opts) {
    if (this.config) {
	if (typeof opts == "undefined") opts = new Object;
	    
	opts.db_server = this.config.cfg.db_server;
	opts.db_name = this.config.cfg.db_name;
	opts.db_group = this.config.cfg.db_group;
	opts.control_group = this.config.cfg.control_group;
	
	if (this.config.cfg.db_mask != null) {
    	    var mask = this.config.cfg.db_mask;
	    if (mask.search(',')>=0) {
		opts.db_mask = mask;
		opts.db_item = 0;
	    } else if (isNaN(mask)) {
		opts.db_mask = mask;
		opts.db_item = 0;
	    } else {
		opts.db_mask = -1;
		opts.db_item = mask;
	    }
	} else if ((opts.db_server)||(opts.db_name)||(opts.db_group)) {
	    opts.reset_mask = true;
	}
	
	this.interval.ReadConfig(opts);
	this.Update(opts);
    }
}


SOURCE.prototype.Apply = function() {
    this.ApplyConfig();
    //if (conf.GetModule() == 'download') adei.OpenModule('graph');
    if (this.updater) this.updater.Update();
}

SOURCE.prototype.RegisterGraph = function(graph) {
    this.graphs.push(graph);
}


SOURCE.prototype.DisableControls = function() {
    if (this.apply_button) this.apply_button.disabled = true;
    this.source_srvsel.Disable();
    this.interval.DisableControls();
}

SOURCE.prototype.EnableControls = function() {
    this.interval.EnableControls();
    this.source_srvsel.Enable();
    if (this.apply_button) this.apply_button.disabled = false;
    
	/* We need that due to IE6 which unhides enabled controls */
    if (this.fixhidden_ie_mode) {
	this.fix_hidden	= true;
	this.FixHidden();
    }
}

    // Called on selection of a new main module 
SOURCE.prototype.SetupModule = function(m) {
    var type = HISTORY_MODULE_TYPE;

    if (m) {
        if (typeof m.GetModuleType != "undefined") {
	    type = m.GetModuleType();
	}
    }
    
    this.module_type = type;
    this.FixHidden(true);

//    source_modules.UpdateGeometry();
}


SOURCE.prototype.RegisterSelectionCallback = function(cb, db_server_re, db_name_re, db_group_re) {
    var level;
    
    if (typeof db_group_re != "undefined") level = 2;
    else if (typeof db_name_re != "undefined") level = 1;
    else if (typeof db_server_re != "undefined") level = 0;
    else return;
    
    this.selection_callbacks[level].push({
	callback: cb,
	db_server_re: db_server_re,
	db_name_re: db_name_re,
	db_group_re: db_group_re
    });
}

SOURCE.prototype.RegisterSubmitCallback = function(cb, db_server_re, db_name_re, db_group_re) {
    this.submit_callbacks.push({
	callback: cb,
	db_server_re: db_server_re,
	db_name_re: db_name_re,
	db_group_re: db_group_re
    });
}


SOURCE.prototype.RunSelectionCallbacks = function(level) {
    var value;
    var callbacks = this.selection_callbacks[level];

    for (i = 0; i < callbacks.length; i++) {
	var opts = callbacks[i];
	
	var db_server = this.source_srvsel.GetValue();
	if (!opts.db_server_re.exec(db_server)) continue;
	
	if (level > 0) {
	    var db_name = this.source_dbsel.GetValue();
	    if (!opts.db_name_re.exec(db_name)) continue;

	    if (level > 1) {
		db_group = this.source_grsel.GetValue();
		if (!opts.db_group_re.exec(db_group)) continue;
		else value = db_group;
	    } else value = db_name;
	} else value = db_server;
	
	opts.callback.Call(value);
    }
}

SOURCE.prototype.RunSubmitCallbacks = function(level) {
    var value;
    var callbacks = this.submit_callbacks;

    for (i = 0; i < callbacks.length; i++) {
	var opts = callbacks[i];
	
	var db_server = this.source_srvsel.GetValue();
	if ((opts.db_server_re)&&(!opts.db_server_re.exec(db_server))) continue;
	
	var db_name = this.source_dbsel.GetValue();
	if ((opts.db_name_re)&&(!opts.db_name_re.exec(db_name))) continue;

	db_group = this.source_grsel.GetValue();
	if ((opts.db_group_re)&&(!opts.db_group_re.exec(db_group))) continue;

	opts.callback.Call();
    }
}


SOURCE.prototype.Update = function(opts) {
    if (opts) {
	if (opts.reload) {
	    this.DisableControls();
	    this.source_srvsel.Update(null, opts, opts.db_server);
	} else if ((opts.reset)||(opts.db_server)) {
	    this.UpdateServer(opts.db_server, opts);
	}
    }
}


SOURCE.prototype.UpdateServer = function(srv, opts) {
    if (typeof srv == "undefined") {
	srv = this.source_srvsel.GetValue();
    }

    if ((opts)&&(opts.confirmed)) {
	this.cfg.db_server = srv;
	this.RunSelectionCallbacks(0);
    } else if (((opts)&&(opts.reload))||(srv != this.cfg.db_server)) {
	if (typeof opts == "undefined") opts = new Object;
	opts.reload = true;
	
	this.DisableControls();
	this.interval.FixOptions(opts);
	this.source_srvsel.UpdateChilds(null, opts, srv, this.cfg.db_server);
    } else if ((opts)&&((opts.db_name)||(opts.reset))) {
	this.UpdateDatabase(opts.db_name, opts);
    }
}


SOURCE.prototype.UpdateDatabase = function(db, opts) {
    if (typeof db == "undefined") {
	db = this.source_dbsel.GetValue();
    }

    if ((opts)&&(opts.confirmed)) {
	this.cfg.db_name = db;
	this.RunSelectionCallbacks(1);
    } else if (((opts)&&(opts.reload))||(db != this.cfg.db_name)) {
	if (typeof opts == "undefined") opts = new Object;
	opts.reload = true;

	this.DisableControls();
	this.interval.FixOptions(opts);
	this.source_dbsel.UpdateChilds("db_server=" + this.cfg.db_server, opts, db, this.cfg.db_name);
    } else if ((opts)&&((opts.db_group)||(opts.control_group)||(opts.reset))) {
	if ((opts.control_group)||(opts.reset)) {
		// synchronous
	    var opts1 = objectClone(opts);
	    opts1.success_cb = null;
	    opts1.failure_cb = null;

	    this.UpdateCGroup(opts.control_group, opts1);
	}

	if ((opts.db_group)||(opts.reset)) {
	    this.UpdateGroup(opts.db_group, objectClone(opts));
	} else if (opts.success_cb) {
	    opts.success_cb(opts.cb_attr, opts.success_value, opts);
	}
    } 
}

/* Handling of Interval/Window updates. 
    a) INTERVAL value passed - UPDATING
    b) 'keep_window' in global or local options - KEEPING
    c) Group have changed - UPDATING
    d) 'reset' in local options - UPDATING 
    e) IGNORING


*/
SOURCE.prototype.UpdateGroup = function(group, opts) {
    if (typeof group == "undefined") {
	group = this.source_grsel.GetValue();
    }

    if ((opts)&&(opts.confirmed)) {
	this.cfg.db_group = group;
	this.RunSelectionCallbacks(2);
    } else if (((opts)&&(opts.reload))||(group != this.cfg.db_group)) {
	if (typeof opts == "undefined") opts = new Object;
	opts.reload = true;
	
	this.DisableControls();
	this.interval.FixOptions(opts);
	this.source_grsel.UpdateChilds("db_server=" + this.cfg.db_server + "&db_name=" + this.cfg.db_name, opts, group, this.cfg.db_group);
    } else if ((opts)&&((opts.db_mask)||(opts.db_item)||(opts.experiment)||(opts.window)||(opts.window_width)||(opts.reset))) {
	if ((opts.db_mask)||(opts.reset)) {
		// synchronous
	    var opts1 = objectClone(opts);
	    opts1.success_cb = null;
	    opts1.failure_cb = null;
	    this.UpdateMask(opts.db_mask, opts1);
	} 

	if ((opts.db_item)||(opts.reset)) {
		// synchronous
	    var opts1 = objectClone(opts);
	    opts1.success_cb = null;
	    opts1.failure_cb = null;
	    this.UpdateItem(opts.db_item, opts1);
	}
	
	if ((opts.experiment)||(opts.window)||(opts.window_width)||(opts.reset)) {
	    this.interval.UpdateExperiment(opts.experiment, objectClone(opts));
	} else if (opts.cb_success) {
	    opts.success_cb(opts.cb_attr, opts.success_value, opts);
	}
    }
}

SOURCE.prototype.UpdateCGroup = function(cgroup, opts) {
    if (typeof cgroup == "undefined") {
	cgroup = this.source_cgrsel.GetValue();
    }

    if ((opts)&&(opts.confirmed)) {
	this.cfg.control_group = cgroup;
//	this.RunSelectionCallbacks(2);
    } else if (((opts)&&(opts.reload))||(cgroup != this.cfg.control_group)) {
	if (typeof opts == "undefined") opts = new Object;
	opts.reload = true;
	
	this.DisableControls();
	this.source_cgrsel.UpdateChilds("db_server=" + this.cfg.db_server + "&db_name=" + this.cfg.db_name, opts, cgroup, this.cfg.control_group);
    } 
}



SOURCE.prototype.UpdateMask = function(mask, opts) {
    if (typeof mask == "undefined") {
	mask = this.source_masksel.GetValue();
    }
    
    if ((opts)&&(opts.confirmed)) {
	if (mask == -1) {
	    this.cfg.db_mask = mask;
	    this.FixHidden(true);

	    if (typeof this.geometry_cb == "function") {
		this.geometry_cb(this.geometry_cbattr);
	    }
	} else {
	    if ((this.cfg.db_mask == -1)||(this.cfg.db_mask == null)) {
	        this.cfg.db_mask = mask;
	    	this.FixHidden(true);
	    } else {
	        this.cfg.db_mask = mask;
	    }
	}

	if (opts.apply) this.Apply();
    } else if (((opts)&&(opts.reload))||(mask != this.cfg.db_mask)) {
	if ((typeof opts == "undefined")&&(mask != -1)) opts = { apply: true };

	this.DisableControls();
	this.source_masksel.UpdateChilds("db_server=" + this.cfg.db_server + "&db_name=" + this.cfg.db_name + "&db_group=" + this.cfg.db_group, opts, mask, this.cfg.db_mask);
    }
}


SOURCE.prototype.UpdateItem = function(item, opts) {
    if (typeof item == "undefined") {
	item = this.source_itemsel.GetValue();
    }

    if ((opts)&&(opts.confirmed)) {
        this.cfg.db_item = item;
	if (opts.apply) this.Apply();
    } else if (((opts)&&(opts.reload))||(item != this.cfg.db_item)) {
	if (typeof opts == "undefined") opts = { apply: true };

	this.DisableControls();
	this.source_itemsel.UpdateChilds("db_server=" + this.cfg.db_server + "&db_name=" + this.cfg.db_name + "&db_group=" + this.cfg.db_group, opts, item, this.cfg.db_item);
    }
}


SOURCE.prototype.GetSourceProps = function() {
    return "db_server=" + this.cfg.db_server + "&db_name=" + this.cfg.db_name + "&db_group=" + this.cfg.db_group;
}

SOURCE.prototype.RegisterGeometryCallback = function(cb, cbattr) {
    this.interval.RegisterGeometryCallback(cb, cbattr);
    this.geometry_cb = cb;
    this.geometry_cbattr = cbattr;
}

SOURCE.prototype.FixHidden = function(process) {
		    // Fix IE bug, preventing unhiden hidden subnodes while within hidden element
    if (process) {
	if ((!this.fixhidden_ie_mode)||(this.module_on_display)) {
	    if (this.module_type&HISTORY_MODULE_TYPE) {
	        if (isNaN(this.cfg.db_mask)||(this.cfg.db_mask>=0)) {
		    this.source_itemsel.Hide();
	        } else {
		    this.source_itemsel.Show();
		}
		cssShowClass('.hide_source_history');
	    } else {
		this.source_itemsel.Hide();
		cssHideClass('.hide_source_history');
	    }
		
	    if (this.module_type&CONTROL_MODULE_TYPE) {
		cssShowClass('.hide_source_control');
	    } else {
		cssHideClass('.hide_source_control');
	    }
	    
	    this.fix_hidden = false;
	} else {
	    this.fix_hidden = true;
	}
    } else if ((this.fix_hidden)&&(this.module_on_display)) {
	if (this.module_type&HISTORY_MODULE_TYPE) {
	    if (isNaN(this.cfg.db_mask)||(this.cfg.db_mask>=0)) {
		this.source_itemsel.Show();
		this.source_itemsel.Hide();
	    } else {
		this.source_itemsel.Hide();
		this.source_itemsel.Show();
	    }
	    cssHideClass('.hide_source_history');
	    cssShowClass('.hide_source_history');
	} else {
	    this.source_itemsel.Show();
	    this.source_itemsel.Hide();
	    cssShowClass('.hide_source_history');
	    cssHideClass('.hide_source_history');
	}

	if (this.module_type&CONTROL_MODULE_TYPE) {
	    cssHideClass('.hide_source_control');
	    cssShowClass('.hide_source_control');
	} else {
	    cssShowClass('.hide_source_control');
	    cssHideClass('.hide_source_control');
	}

	this.fix_hidden = false;
    }
    
    this.interval.FixHidden();
}

SOURCE.prototype.Alter = function (type, direction) {
    var opts = new Object({
    	cb_attr: this,
	success_cb: sourceApply,
	keep_window: true,
	reset: true
    });
    
    switch (type) {
	case 's':
	    if (direction > 0) this.source_srvsel.Next(true);
	    else this.source_srvsel.Prev(true);
	    this.UpdateServer(this.source_srvsel.GetValue(), opts);
	break;
	case 'd':
	    if (direction > 0) this.source_dbsel.Next(true);
	    else this.source_dbsel.Prev(true);
	    this.UpdateDatabase(this.source_dbsel.GetValue(), opts);
	break;
	case 'g':
	    if (direction > 0) this.source_grsel.Next(true);
	    else this.source_grsel.Prev(true);
	    this.UpdateGroup(this.source_grsel.GetValue(), opts);
	break;
	case 'm':
	    var val = this.source_masksel.GetValue();
	    if (parseInt(val) < 0) this.source_masksel.SetIndex(0);
	    else {
	        if (direction > 0) 
		    this.source_masksel.Next(true, function (idx,val) {
			if (parseInt(val) < 0) return true;
			return false;
		    });
		else 
		    this.source_masksel.Prev(true, function (idx,val) {
			if (parseInt(val) < 0) return true;
			return false;
		    });
	    }
	    
	    this.UpdateMask(this.source_masksel.GetValue(), opts);
	break;
	case 'i':
	    var val = this.source_masksel.GetValue();
	    if (parseInt(val) == -1) {
		if (direction > 0) this.source_itemsel.Next(true);
		else this.source_itemsel.Prev(true);
	    } else {
		this.source_itemsel.SetIndex(0);
		
		this.source_masksel.SetValue(-1);
	        this.UpdateMask(this.source_masksel.GetValue(), opts);
	    }
	    
	    this.UpdateItem(this.source_itemsel.GetValue(), opts);
	break;
    }
}

SOURCE.prototype.NextServer = function() {
    this.Alter('s', 1);
}

SOURCE.prototype.PrevServer = function() {
    this.Alter('s', -1);
}

SOURCE.prototype.NextDatabase = function() {
    this.Alter('d', 1);
}

SOURCE.prototype.PrevDatabase = function() {
    this.Alter('d', -1);
}

SOURCE.prototype.NextGroup = function() {
    this.Alter('g', 1);
}

SOURCE.prototype.PrevGroup = function() {
    this.Alter('g', -1);
}

SOURCE.prototype.NextMask = function() {
    this.Alter('m', 1);
}

SOURCE.prototype.PrevMask = function() {
    this.Alter('m', -1);
}

SOURCE.prototype.NextItem = function() {
    this.Alter('i', 1);
}

SOURCE.prototype.PrevItem = function() {
    this.Alter('i', -1);
}



function sourceUpdateServer(src, srv, opts) {
    return src.UpdateServer(srv, opts);
}

function sourceUpdateDatabase(src, db, opts) {
    return src.UpdateDatabase(db, opts);
}

function sourceUpdateGroup(src, group, opts) {
    return src.UpdateGroup(group, opts);
}

function sourceUpdateCGroup(src, group, opts) {
    return src.UpdateCGroup(group, opts);
}

function sourceUpdateMask(src, mask, opts) {
    return src.UpdateMask(mask, opts);
}

function sourceUpdateItem(src, item, opts) {
    return src.UpdateItem(item, opts);
}

function sourceEnableControls(src) {
    src.EnableControls();
}

function sourceDisableControls(src) {
    src.DisableControls();
}

function sourceFixHidden(source) {
    source.FixHidden();
}

function sourceApply(source) {
    source.Apply();
}
