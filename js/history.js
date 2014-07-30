function HISTORY(callback) {
    window.dhtmlHistory.create({
        toJSON: function(o) {
                return Object.toJSON(o);
        },
        fromJSON: function(s) {
                return s.evalJSON();
        }
    });
    
    dhtmlHistory.initialize();
//    dhtmlHistory.addListener(callback);

    var curtime = new Date();
    this.busy = curtime.getTime();

    this.endpos = false;
    this.automode = false;
    this.started = false;
    this.callback = callback;
    dhtmlHistory.addListener(this.onHistoryEvent(this));
}

HISTORY.prototype.onHistoryEvent = function(self) {
    return function(newLocation, historyData) {
	if (newLocation == "__startup__") {
	    history.forward();
	    if (!self.automode) {
		adeiReportError(translate("History is exhausted"));
	    }

	    return;
	}
	
	var props = self.GetProps(newLocation);
	var hid = self.GetID(newLocation);
	
	var res = self.callback(props, historyData, hid);

	self.automode = false;

	var curtime = new Date();
	self.busy = curtime.getTime();
	
	return res;
    }
}

HISTORY.prototype.GetProps = function(res) {
    if (typeof res == "undefined")
	var res = dhtmlHistory.getCurrentLocation();

    return res.replace(/\&history_id=\d+/,"");
}

HISTORY.prototype.GetID = function(res) {
    if (typeof res == "undefined")
	var res = dhtmlHistory.getCurrentLocation();

    var m = /\&history_id=(\d+)/.exec(res);
    if (m) return m[1];
    return 0;
}

HISTORY.prototype.CreateLocation = function(props, hid) {
/*    if (props.indexOf("#")>=0) {*/
	var res = props + "&history_id=" + hid;
/*    } else {
	var res = props + "#history_id=" + hid;
    }*/
    return res;
}

HISTORY.prototype.Add = function(page, cfg) {
    if (!this.started) {
	dhtmlHistory.add("__startup__", cfg);
	this.started = true;
    }

    var curtime = new Date();
    this.endpos = curtime.getTime();

    var loc = this.CreateLocation(page, this.endpos)
    dhtmlHistory.add(loc, cfg);
}

HISTORY.prototype.ReplaceLocation = function(new_props, props, hid) {
    if (typeof current == "undefined") {
        var current = dhtmlHistory.getCurrentLocation();

	if (typeof hid == "undefined")
	    var hid = this.GetID(current);
    }
	
    var loc = this.CreateLocation(props, hid)
    var new_loc = this.CreateLocation(new_props, hid)
    
    var orig = window.location.toString();
    var replacement;
    if (orig.match('#')) {
	replacement = orig.replace(/#.*$/, '#' + new_loc);
	this.AddSynonim(loc, new_loc);
    } else replacement = '#' + new_loc;

    window.location.replace(replacement);
}

HISTORY.prototype.AddSynonim = function(old_page, new_page) {
    var cfg = historyStorage.get(old_page);
    
    if (cfg) {    
	if (historyStorage.hasKey(new_page))
    	    historyStorage.remove(new_page);

	historyStorage.put(new_page, cfg);
    }
}

HISTORY.prototype.Back = function() {
    if (!this.busy) return;

    var curtime = new Date();
    if ((curtime.getTime() - this.busy) < 50) return;

    this.busy = false;
    this.automode = true;
    history.back();
}

HISTORY.prototype.Forward = function() {
    var pos = this.GetID();
    if (pos == this.endpos) return;
    
    if (!this.busy) return;
    
    var curtime = new Date();
    if ((curtime.getTime() - this.busy) < 50) return;

	// if no room to go forward, the busy flag will remain
    this.busy = false;
    history.forward();
}

