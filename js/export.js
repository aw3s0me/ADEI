function EXPORT_CONFIG() {
    this.format = null;
    this.resample = null;
    this.mask = null;
    this.window = null;
    this.frame_height = 768;
    this.frame_width = 1024;
  
}

function EXPORT(format_sel_id, resample_sel_id, mask_sel_id, window_sel_id) {
    this.cfg = new EXPORT_CONFIG();

    this.formatsel = new SELECT(format_sel_id, exportUpdateFormat, this);
    this.samplesel = new SELECT(resample_sel_id, exportUpdateSampling, this);
    this.masksel = new SELECT(mask_sel_id, exportUpdateMask, this);
    this.winsel = new SELECT(window_sel_id, exportUpdateWindow, this);

    this.config = null;
}

EXPORT.prototype.AttachConfig = function(config) {
    this.config = config;
    config.Register(this);
}

EXPORT.prototype.Init = function(opts) {
    if (typeof opts == "undefined") opts = new Object;

    opts.reload = true;
    opts.confirm = true;

    this.Update(opts);
}

EXPORT.prototype.Export = function(embedded_mode, window_type) {    
    var opts = "format=" + this.cfg.format + "&resample=" + this.cfg.resample + "&mask_mode=" + this.cfg.mask;
    if (this.GetFormatType(this.cfg.format) == 'image') {
        opts += '&width=' + this.cfg.frame_width + '&height=' + this.cfg.frame_height;
    }
    if (typeof window_type != "undefined") {
	opts += "&" + this.config.GetProps(window_type);
    } else if (embedded_mode) {
	opts += "&" + this.config.GetProps(-1);
    } else {
	opts += "&" + this.config.GetProps(this.cfg.window);
    }   
    // If downloading image or !downloadmanager -> direct download.
    if(this.GetFormatType(this.cfg.format) == 'image' || !window.dlmanager)  { //TODO change format check
      document.location = adei.GetServiceURL("getdata", opts);      
    }
    else if(this.cfg.format == null) alert("Please select data source.");
    else {
      dlmanager.AddDownload(opts);
    }
}

EXPORT.prototype.StartDownload = function(download) {
  var opts = "target=dlmanager_download&dl_id="+download;
  document.location = adei.GetServiceURL("download", opts);
}

EXPORT.prototype.SetFormat = function(val) {
    this.Update(new Object({ 'format': val, 'apply': true}));
}

EXPORT.prototype.SetSampling = function(val) {
    this.Update(new Object({ 'export_sampling': val, 'apply': true}));
}

EXPORT.prototype.SetMask = function(val) {
    this.Update(new Object({ 'export_mask': val, 'apply': true}));
}

EXPORT.prototype.ReadConfig = function(opts) {
    if (this.config) {
	var opts = new Object({'apply': true});
	if (this.config.cfg_extra.format) 
	    opts.format = this.config.cfg_extra.format;

	if (this.config.cfg_extra.resample) 
	    opts.export_sampling = this.config.cfg_extra.resample;

	if (this.config.cfg_extra.mask_mode) 
	    opts.export_mask = this.config.cfg_extra.mask_mode;
	
	this.Update(opts);
    }
}


EXPORT.prototype.Update = function(opts) {
    if (opts) {
	if (opts.reload) {
	    this.formatsel.Update(adei.GetSelectService("formats"), opts, opts?opts.format:null);
	    this.samplesel.Update(adei.GetSelectService("sampling_rates"), opts, opts?opts.export_sampling:null);
	    this.masksel.Update(adei.GetSelectService("export_mask_modes"), opts, opts?opts.export_mask:null);
	    this.winsel.Update(adei.GetSelectService("export_window_modes"), opts, opts?opts.export_window:null);
	} else {
	    if (opts.format) {
	    	this.UpdateFormat(opts.format, opts);
	    	if (this.formatsel.GetValue() != opts.format)
    		    this.formatsel.SetValue(opts.format);
	    }
	    if (opts.export_sampling) {
	    	this.UpdateSampling(opts.export_sampling, opts);
	    	if (this.samplesel.GetValue() != opts.export_sampling)
    		    this.samplesel.SetValue(opts.export_sampling);
	    }
	    if (opts.export_mask) {
	    	this.UpdateMask(opts.export_mask, opts);
	    	if (this.masksel.GetValue() != opts.export_mask)
    		    this.masksel.SetValue(opts.export_mask);
	    }
	    if (opts.export_window) {
	    	this.UpdateWindow(opts.export_window, opts);
	    	if (this.winsel.GetValue() != opts.export_window)
    		    this.winsel.SetValue(opts.export_window);
	    }
	}
    }
}

EXPORT.prototype.GetFormatType = function(val) {    
	return (typeof adei.cfg.export_formats[val].type == 'undefined')	? 'data'  //default type
	 									: adei.cfg.export_formats[val].type ;
}

EXPORT.prototype.UpdateFormat = function(val, opts) {
    	this.cfg.format = val;
	var export_type = this.GetFormatType(val);
        $$('.export_control').each(function(el){
		if ( el.hasClassName(export_type + '_export_control')) {
			el.show();
		} else {
			el.hide();	
		}
	});
		

    if ((typeof opts == "undefined")||(opts.apply))
        this.Apply(val, null, null);
}


EXPORT.prototype.UpdateResolutionX = function(val) {
    this.cfg.frame_width = val;
}

EXPORT.prototype.UpdateResolutionY = function(val) {
    this.cfg.frame_height = val;
}

EXPORT.prototype.UpdateSampling = function(val, opts) {
    this.cfg.resample = val;

    if ((typeof opts == "undefined")||(opts.apply))
	this.Apply(null, val, null);
}

EXPORT.prototype.UpdateMask = function(val, opts) {
    this.cfg.mask = val;

    if ((typeof opts == "undefined")||(opts.apply))
	this.Apply(null, null, val);
}

EXPORT.prototype.UpdateWindow = function(val, opts) {
    this.cfg.window = val;
}

EXPORT.prototype.Apply = function(format, sampling, mask) {
    if ((format)||(sampling)||(mask))
	this.config.SetExportSettings(format, sampling, mask);
    else
	this.config.SetExportSettings(this.cfg.format, this.cfg.resample, this.cfg.mask);
    
    this.config.ReplaceURL();
}


EXPORT.prototype.GetTooltip = function() {
    var res = this.formatsel.GetTitle();

    if ((this.cfg.resample)&&(this.cfg.resample != "0")) {
	res += ", " + translate("Resampling %s", this.samplesel.GetTitle());
    }
    if ((this.cfg.mask)&&(this.cfg.mask != "0")) {
	res += ", " + translate("MultiMask");
    }
    
    return res;
}

function exportUpdateFormat(self, val, opts) {
    return self.UpdateFormat(val, opts);
}


function exportUpdateSampling(self, val, opts) {
    return self.UpdateSampling(val, opts);
}

function exportUpdateMask(self, val, opts) {
    return self.UpdateMask(val, opts);
}

function exportUpdateWindow(self, val, opts) {
    return self.UpdateWindow(val, opts);
}

