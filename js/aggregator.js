function AGGREGATOR_CONFIG() {
    this.aggregator = null;
    this.interpolation = null;
    this.show_marks = null;
    this.show_gaps = null;
}

function AGGREGATOR(aggregator_sel_id, interpolation_sel_id, marks_sel_id, gaps_sel_id) {
    this.cfg = new AGGREGATOR_CONFIG();

    this.asel = new SELECT(aggregator_sel_id, aggregatorUpdateAggregator, this);
    this.isel = new SELECT(interpolation_sel_id, aggregatorUpdateInterpolation, this);
    this.marksel = new SELECT(marks_sel_id, aggregatorUpdateMarks, this);
    this.gapsel = new SELECT(gaps_sel_id, aggregatorUpdateGaps, this);

    this.updater = null;    
    this.config = null;
}


AGGREGATOR.prototype.AttachConfig = function(config) {
    this.config = config;
    config.Register(this);
}

AGGREGATOR.prototype.Init = function(updater, opts) {
    if (typeof opts == "undefined") opts = new Object;

    this.updater = updater;

    opts.reload = true;
    opts.confirm = true;

    this.Update(opts);
}

/*
AGGREGATOR.prototype.Export = function(embedded_mode, window_type) {

    var opts = "format=" + this.cfg.format + "&resample=" + this.cfg.resample + "&mask_mode=" + this.cfg.mask;
    if (typeof window_type != "undefined") {
	opts += "&" + this.config.GetProps(window_type);
    } else if (embedded_mode) {
	opts += "&" + this.config.GetProps(-1);
    } else {
	opts += "&" + this.config.GetProps(this.cfg.window);
    }

    document.location = adei.GetServiceURL("getdata", opts);
}
*/

AGGREGATOR.prototype.SetAggregator = function(val) {
    this.Update(new Object({ 'aggregation': val, 'apply': true}));
}

AGGREGATOR.prototype.SetInterpolation = function(val) {
    this.Update(new Object({ 'interpolate': val, 'apply': true}));
}

AGGREGATOR.prototype.SetMarks = function(val) {
    this.Update(new Object({ 'show_marks': val, 'apply': true}));
}

AGGREGATOR.prototype.SetGaps = function(val) {
    this.Update(new Object({ 'show_gaps': val, 'apply': true}));
}

AGGREGATOR.prototype.ReadConfig = function(opts) {
    if (this.config) {
	var opts = new Object(/*{'apply': true}*/);
	if (this.config.cfg.aggregation) 
	    opts.aggregation = this.config.cfg.aggregation;

	if (this.config.cfg.interpolate) 
	    opts.interpolate = this.config.cfg.interpolate;

	if (this.config.cfg.show_marks) 
	    opts.show_marks = this.config.cfg.show_marks;

	if (this.config.cfg.show_gaps) 
	    opts.show_gaps = this.config.cfg.show_gaps;
	
	this.Update(opts);
    }
}


AGGREGATOR.prototype.Update = function(opts) {
    if (opts) {
	if (opts.reload) {
	    this.asel.Update(adei.GetSelectService("aggregation_modes"), opts, opts?opts.aggregation:null);
	    this.isel.Update(adei.GetSelectService("interpolation_modes"), opts, opts?opts.interpolate:null);
	    this.marksel.Update(adei.GetSelectService("marks_modes"), opts, opts?opts.show_marks:null);
	    this.gapsel.Update(adei.GetSelectService("gaps_modes"), opts, opts?opts.show_gaps:null);
	} else {
	    if (opts.aggregation) {
	    	this.UpdateAggregator(opts.aggregation, opts);
	    	if (this.asel.GetValue() != opts.aggregation)
    		    this.asel.SetValue(opts.aggregation);
	    }
	    if (opts.interpolate) {
	    	this.UpdateInterpolation(opts.interpolate, opts);
	    	if (this.isel.GetValue() != opts.interpolate)
    		    this.isel.SetValue(opts.interpolate);
	    }
	    if (opts.show_marks) {
	    	this.UpdateMarks(opts.show_marks, opts);
	    	if (this.marksel.GetValue() != opts.show_marks)
    		    this.marksel.SetValue(opts.show_marks);
	    }
	    if (opts.show_gaps) {
	    	this.UpdateGaps(opts.show_gaps, opts);
	    	if (this.gapsel.GetValue() != opts.show_gaps)
    		    this.gapsel.SetValue(opts.show_gaps);
	    }
	}
    }
}

AGGREGATOR.prototype.UpdateAggregator = function(val, opts) {
    this.cfg.aggregator = val;

    if ((typeof opts == "undefined")||(opts.apply))
        this.Apply(val, null, null, null);
}

AGGREGATOR.prototype.UpdateInterpolation = function(val, opts) {
    this.cfg.interpolation = val;

    if ((typeof opts == "undefined")||(opts.apply))
	this.Apply(null, val, null, null);
}

AGGREGATOR.prototype.UpdateMarks = function(val, opts) {
    this.cfg.show_marks = val;

    if ((typeof opts == "undefined")||(opts.apply))
	this.Apply(null, null, val, null);
}

AGGREGATOR.prototype.UpdateGaps = function(val, opts) {
    this.cfg.show_gaps = val;

    if ((typeof opts == "undefined")||(opts.apply))
	this.Apply(null, null, null, val);
}

AGGREGATOR.prototype.Apply = function(aggregator, interpolation, show_marks, show_gaps) {
    if ((aggregator)||(interpolation)||(show_marks)||(show_gaps))
	this.config.SetAggregationSettings(aggregator, interpolation, show_marks, show_gaps);
    else
	this.config.SetAggregationSettings(this.cfg.aggregator, this.cfg.interpolation, this.cfg.show_marks, this.cfg.show_gaps);

    this.config.Save();
    if (this.updater) this.updater.Update();
}

function aggregatorUpdateAggregator(self, val, opts) {
    return self.UpdateAggregator(val, opts);
}

function aggregatorUpdateInterpolation(self, val, opts) {
    return self.UpdateInterpolation(val, opts);
}

function aggregatorUpdateMarks(self, val, opts) {
    return self.UpdateMarks(val, opts);
}

function aggregatorUpdateGaps(self, val, opts) {
    return self.UpdateGaps(val, opts);
}

