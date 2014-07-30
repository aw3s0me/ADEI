function INTERVAL_CONFIG() {
    this.start = "";
    this.end = "";
    this.custom = 0;
}

function INTERVAL(window, exp_sel_id, start_id, end_id) {
    this.cfg = new INTERVAL_CONFIG;

    this.window = window;
    //this.window.SetConfig(null);
    
    this.props = null;
    this.source_expsel = new SELECT(exp_sel_id, intervalUpdateExperiment, this);
    this.sin = document.getElementById(start_id);
    if (!this.sin) adeiReportError("Custom experiment start time input field (\"" + start_id + "\") is not found");
    this.ein = document.getElementById(end_id);
    if (!this.ein) adeiReportError("Custom experiment end time input field (\"" + end_id + "\") is not found");

    this.source_expsel.SetupSource(adei.GetSelectService("experiments"), "experiment");
    this.source_expsel.SetupCompletionCallbacks(intervalEnableControls, intervalEnableControls);
    this.source_expsel.AddChild(window.source_winsel);

}

INTERVAL.prototype.DisableControls = function() {
    this.source_expsel.Disable();
    this.window.DisableControls();
}

INTERVAL.prototype.EnableControls = function() {
    this.window.EnableControls();
    this.source_expsel.Enable();
}

INTERVAL.prototype.SetConfig = function(config) {
    this.config = config;
    this.window.SetConfig(config);
}


INTERVAL.prototype.SetUpdater = function(updater) {
    this.updater = updater;
    this.window.SetUpdater(updater);
}

INTERVAL.prototype.ApplyConfig = function(subcall) {
    this.window.ApplyConfig(subcall);

    var from, to;
    if (this.cfg.custom) {
        if (this.sin.value) from = adeiDateParse(this.sin.value);
        else from = "";
	    
        if (this.ein.value) to = adeiDateParse(this.ein.value);
        else to = "";

        this.cfg.start = from;
	this.cfg.end = to;
    } else {
	from = this.cfg.start;
	to = this.cfg.end;
    }

    this.config.SetInterval(from, to);
}

INTERVAL.prototype.ReadConfig = function(opts) {
    opts.experiment = this.config.cfg.experiment;
    this.window.ReadConfig(opts);
}

INTERVAL.prototype.FixOptions = function(opts) {
    this.window.FixOptions(opts);
}

INTERVAL.prototype.Update = function(props, opts) {
    if (props) this.props = props;

    if (opts) {    
	if (opts.reload) {
	    this.DisableControls();
	    this.source_expsel.Update(this.props, opts, opts?opts.experiment:null, this.experiment);
	} else if ((opts.experiment)||(opts.reset)) {
	    this.UpdateExperiment(opts.experiment, opts);
	} 
    }
}

INTERVAL.prototype.UpdateExperiment  = function(value, opts) {
    if (typeof value == "undefined") {
	value = this.source_expsel.GetValue();
    }

    if ((opts)&&(opts.confirmed)) {
	this.experiment = value;

	if (value == "0") {
	    if (this.cfg.custom == 0) {
		cssShowClass('.hide_experiment_custom');
		this.cfg.custom = 1;

		if (!this.module_on_display) this.fix_hidden = true;

		if (typeof this.geometry_cb == "function") {
		    this.geometry_cb(this.geometry_cbattr);
		}
	    }
	} else {
	    if (this.cfg.custom) {
		cssHideClass('.hide_experiment_custom');
		this.cfg.custom = 0;

		if (!this.module_on_display) this.fix_hidden = true;
	    }

	    var atmp = value.split('-',2);
	    this.cfg.start = atmp[0];
	    this.cfg.end = atmp[1];
        }
    } else if (((opts)&&(opts.reload))||(value != this.experiment)) {
	if (typeof opts == "undefined") opts = new Object;
	opts.reload = true;
	this.DisableControls();
	this.FixOptions(opts);
	this.source_expsel.UpdateChilds(null, opts, value, this.experiment);
    } else if ((opts)&&((opts.window)||(typeof opts.window_width != "undefined"))) {
	this.window.UpdateWidth(opts.window_width, opts);
    }
}

/*
INTERVAL.prototype.GetExperimentProps = function(read_custom) {
    if (typeof read_custom == "undefined") read_custom = 1;
    
    if (this.cfg.custom) {
	if (read_custom) {
	    var from, to;
	    
	    if (this.sin.value) from = adeiDateParse(this.sin.value);
	    else from = "";
	    
	    if (this.ein.value) to = adeiDateParse(this.ein.value);
	    else to = "";
	    
	    if (isNaN(from)) {
		alert('Invalid start time (\"' + this.sin.value + '\") is specified');
		from = "";
	    }
	    if (isNaN(to)) {
		alert('Invalid end time (\"' + this.ein.value + '\") is specified');
		to = "";
	    }
	    
	    return 'experiment='+from+'-'+to;	    
	} else {
	    return 'experiment=0-0';
	}
    } else {
	return 'experiment=' + this.cfg.start + '-' + this.cfg.end;
    }
}
*/

INTERVAL.prototype.RegisterGeometryCallback = function(cb, cbattr) {
    this.window.RegisterGeometryCallback(cb, cbattr);
    this.geometry_cb = cb;
    this.geometry_cbattr = cbattr;
}


INTERVAL.prototype.FixHidden = function() {
    if ((this.fix_hidden)&&(this.module_on_display)) {
	if (this.cfg.custom) {
	    cssHideClass('.hide_experiment_custom');
	    cssShowClass('.hide_experiment_custom');
	} else {
	    cssShowClass('.hide_experiment_custom');
	    cssHideClass('.hide_experiment_custom');
	}
	this.fix_hidden = false;
    }
    this.window.FixHidden();
}

function intervalUpdateExperiment(interval, value, opts) {
    interval.UpdateExperiment(value, opts);
}


function intervalEnableControls(interval) {
    interval.EnableControls();
}
