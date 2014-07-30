function PLOT_CONFIG() 
{
    this.plot_mode = null;
}

function PLOT(plot_sel_id) 
{
    this.cfg = new PLOT_CONFIG();
    
    this.plotsel = new SELECT(plot_sel_id, plotUpdatePlotMode, this);
    
    this.updater = null;
    this.config = null;
}

PLOT.prototype.AttachConfig = function(config) {
    this.config = config;
    config.Register(this);
}

PLOT.prototype.Init = function(updater, opts) {
    if (typeof opts == "undefined") opts = new Object;

    this.updater = updater;

    opts.reload = true;
    opts.confirm = true;

    this.Update(opts);
}

PLOT.prototype.SetPlot = function(val) {
    this.Update(new Object({ 'plot_mode': val, 'apply': true}));
}

PLOT.prototype.ReadConfig = function(opts) {
    if (this.config) {
	var opts = new Object(/*{'apply': true}*/);
	if (this.config.cfg.plot_mode) 
	    opts.plot_mode = this.config.cfg.plot_mode;
	
	this.Update(opts);
    }
}

PLOT.prototype.Update = function(opts) {
    if (opts) {
	if (opts.reload) {
	    this.plotsel.Update(adei.GetSelectService("plot_modes"), opts, opts?opts.plot_mode:null);
	} else {
	    if (opts.plot_mode) {
	    	this.UpdatePlotMode(opts.plot_mode, opts);
	    	if (this.plotsel.GetValue() != opts.plot_mode)
    		    this.plotsel.SetValue(opts.plot_mode);
	    }
	}
    }
}

PLOT.prototype.UpdatePlotMode = function(val, opts)
{
    this.cfg.plot_mode = val;
    
    if ((typeof opts == "undefined")||(opts.apply))
    this.Apply(val);
}

PLOT.prototype.Apply = function(plot_mode) {
    if (plot_mode)
	this.config.SetPlotSettings(plot_mode);
    else
	this.config.SetPlotSettings(this.cfg.plot_mode);

    this.config.Save();
    if (this.updater) this.updater.Update();
}

function plotUpdatePlotMode(self, val, opts)
{
    return self.UpdatePlotMode(val, opts);
}
