function UPDATER(config, module, popup) {
    this.run_flag = 0;
    this.idle = 1;
    this.tm = null;
    
    this.forced = false;
    
    this.updating = false;
    this.queued = false;

    this.config = config;
    this.module = module;
    this.popup = popup;
    this.control = new Array();
    this.listeners = new Array();

    config.RegisterUpdater(this);
}

UPDATER.prototype.RegisterControl = function (control) {
    this.control.push(control);
}

UPDATER.prototype.RegisterListener = function (obj) {
    this.listeners.push(obj);
}


UPDATER.prototype.Start = function(rate) {
    if (this.popup) this.popup.RegisterReWidthCallback(updaterUpdate, this);

    this.rate = rate * 1000;
    this.run_flag = 1;
    this.Run();
}


UPDATER.prototype.Stop = function() {
    this.run_flag = 0;
}

UPDATER.prototype.Run = function () {
    if (this.run_flag) {
	this.tm = setTimeout(function(param) { return function() { param.Run(); } } (this), this.rate);
	if (this.idle) {
	    this.idle = 0;
	    
	    this.Iteration();
	    
	    this.idle = 1;
	}
    }
}

UPDATER.prototype.Update = function () {
    if (this.tm) {
	clearTimeout(this.tm);
	this.tm = null;
    }
    this.forced = true;
    this.Run();
}

UPDATER.prototype.Notify = function(notifier, mod) {
    if (typeof mod != "undefined") {
	if (mod) {
	    var m = this.module.GetModule(mod);
	} else {
	    var m = this.module.GetOpenModule(mod);
	}
	if (m) {    
	    eval("if (m.Notify" + notifier + ") m.Notify" + notifier + "();");
	}
    } else {
	for (var i = 0; i < this.module.names.length; i++) {
	    this.Notify(notifier, this.module.names[i]);
	}
	for (var i = 0; i < this.listeners.length; i++) {
	    eval("if (this.listeners[i].Notify" + notifier + ") this.listeners[i].Notify" + notifier + "();");
	}
    }
}

function updaterRequest(updater) {
 return function(transport) {
    if ((!transport)||(!transport.responseText)) {
	adei.SetStatus(translate("Update failed: No data is returned by ADEI service"));
    } else if (transport.responseText.charAt(0) == '{') {
	var json = transport.responseText.evalJSON(false);
	
        if (typeof json.module  == "undefined") {
	    if (json.error) {
//		adeiReportError(json.error);
		adei.SetStatus(translate("Update failed: %s", json.error), 0);
	    } else {
		// Everything Ok, just nothing to update
//		alert('strange: ' + transport.responseText);
		adei.SetStatus(translate("Done"));
	    }
	} else {
	    var module = updater.module.GetModule(json.module);
	    if ((module)&&(typeof module.Update != "undefined")) {
		var res = module.Update(json, updater.forced);
		if (res) {
		    if (typeof res == "string") {
//			adeiReportError(res);
			adei.SetStatus(translate("Update failed: %s", res), 0);
		    } else if (json.error) {
//			adeiReportError(json.error);
			adei.SetStatus(translate("Update failed: %s", json.error), 0);
		    }
		}  /* else {
		    adei.SetStatus(translate("Done"));
		} */
		
		updater.forced = false;
	    } else {
		adei.SetStatus(translate("Update failed: Invalid module is specified"), 0);
	    }
	}
    } else {
	adeiReportError(translate("Unexpected content: ") + transport.responseText);
    }
    
    updater.updating = false;
 }
}

function updaterFailure(updater) {
    return function() {
	adei.SetStatus(translate("Update is failed: POST request is failed"), 0);
	updater.updating = false;
    }
}

UPDATER.prototype.Request = function(extra, postponned) {
    var current_module = this.module.GetOpenModule();
    
    if (!extra) {
	if (!current_module) return false; // We have passive module without update capabilities

	if (this.updating) {
	    adei.SetExtraStatus("Busy, skipping update request");
	    
		// Try to schedule if forced?
	    if ((this.forced)&&((postponned)||(!this.queued))) {
		this.queued = true;
		setTimeout(function(param) { return function() { param.Request(false, true); } } (this), 25);
	    }
	    return false;	
	} else {
	    if (postponned) {
		this.queued = false;
	    }
	}
    }

    this.updating = true;

    if (this.config.ready) {
	if (this.forced) {
	    adei.SetStatus(translate("Updating..."), 0);
	} else {
	    adei.SetStatus(translate("Starting Pereodic Update..."), 0);
	}
    }


	// we can adjust configuration here
    if (typeof current_module.Prepare != "undefined") {
        current_module.Prepare();
    }


    var cfg = this.config.Get(extra);
    
    if (typeof current_module.Request == "undefined") {
	new Ajax.Request(adei.GetServiceURL("update"), {
	    method: 'post',
	    requestHeaders: {Accept: 'application/json'},
	    parameters: { props: Object.toJSON(cfg) },
	    onSuccess: updaterRequest(this),
	    onFailure: updaterFailure(this)
	});

	return true;
    } else {
	this.updating = false;
        return current_module.Request(cfg);
    }

}

UPDATER.prototype.Iteration = function() {
    return this.Request();
}


function updaterUpdate(updater) {
    return updater.Update();
}
