function INFOTAB(module, selid, ctrldiv, div) {
    this.module = module;

    if (typeof div == "object") this.div = div;
    else {
	this.div = document.getElementById(div);
	if (!this.div) adei.ReportError(translate("Element \"%s\" is not present in current document model", div));
    }

    if (typeof ctrldiv == "object") this.ctrldiv = ctrldiv;
    else {
	this.ctrldiv = document.getElementById(ctrldiv);
	if (!this.ctrldiv) adei.ReportError(translate("Element \"%s\" is not present in current document model", ctrldiv));
    }


    this.infomode = new SELECT(selid, infotabUpdateModule, this);
    this.infomode.SetupSource(adei.GetService("view", "target=list"), "infomod");

//    if (adei.source) adei.source.RegisterGroupChild(infomode);
//    else 
    
    adei.RegisterCropperButton({
	name: 'view_info',
	tooltip: 'View Info',
	object: this,
	callback: 'onSelect',
	css: 'imgCrop_button_info',
	vertical: true,
	keep_selection: true
    });

    this.mod = null;
    this.window = null;
    this.displayed = false;
    this.page = false;

    this.width = 0;
    this.height = 0;

    adei.updater.RegisterListener(this);
    adei.RegisterExtraProperty("infomod");
    adei.config.RegisterPropertyGroup(/view_/, true);
    adei.config.Register(this);

        // wait until set by Apply call from source in ReadConfig
//    this.infomode.Update(adei.GetService("view", urlJoinProps(adei.config.GetProps(), "target=list")));
}


INFOTAB.prototype.RegisterPage = function(page) {
    this.page = page;
}

INFOTAB.prototype.SetupGeometry = function() {
    this.width = this.div.offsetWidth;
    this.height = this.div.offsetHeight;
}

INFOTAB.prototype.NotifySource = function() {
    this.window = null;
    this.displayed = false;
    this.div.innerHTML = "";

    var val = undefined;
    if (this.mod) val = this.mod;
    else if (adei.config.cfg_extra.custom['infomod']) val = adei.config.cfg_extra.custom['infomod'];

    this.infomode.Update(adei.GetService("view", urlJoinProps(adei.config.GetProps(), "target=list")), undefined, val);
}

INFOTAB.prototype.NotifyInterval = function() {
    this.window = null;
    this.displayed = false;
    this.div.innerHTML = "";

    var val = undefined;
    if (this.mod) val = this.mod;
    else if (adei.config.cfg_extra.custom['infomod']) val = adei.config.cfg_extra.custom['infomod'];

    this.infomode.Update(adei.GetService("view", urlJoinProps(adei.config.GetProps(), "target=list")), undefined, val);
}

INFOTAB.prototype.NotifySelectionCancel = function() {
    this.window = null;
}

INFOTAB.prototype.NotifyPage = function() {
    this.div.innerHTML = "";
    this.window = null;
}

INFOTAB.prototype.ReadConfig = function(opts) {
    if (opts) {
        if (opts.reload) {
            opts = new Object;
            if (typeof adei.config.cfg_extra.custom['infomod'] != "undefined") {
                opts.infomod = adei.config.cfg_extra.custom['infomod'];
            }
            this.infomode.Update(opts);
        } else {
            // something changed
            if (typeof adei.config.cfg_extra.custom['infomod'] != "undefined") {
                var cur = this.infomode.GetValue();
                this.mod = adei.config.cfg_extra.custom['infomod'];
                if ((cur != this.mod)&&(this.mod)) {
                    this.infomode.SetValue(this.mod);
                    this.Reload();
                } 
            } 
            //this.LoadOptions();
        }
    }

/*    if (this.config) {
	var opts = new Object();
	if (this.config.cfg.plot_mode) 
	    opts.plot_mode = this.config.cfg.plot_mode;
	
	this.Update(opts);
    }*/
}

INFOTAB.prototype.ApplyOptions = function() {
    var re = /^view_(.*)$/;
    var nodes = this.ctrldiv.getElementsByTagName("input");

    for (var i in nodes) {
        var id = nodes[i].id;
        if (re.exec(id)) {
            adei.RegisterExtraProperty(id);
            adei.SetCustomProperties(id + "=" + nodes[i].value, false);
        }
    }

    nodes = this.ctrldiv.getElementsByTagName("select");

    for (var i in nodes) {
        var id = nodes[i].id;
        if (re.exec(id)) {
            adei.RegisterExtraProperty(id, null);
            adei.SetCustomProperties(id + "=" + nodes[i].value, false);
        }
    }

}

INFOTAB.prototype.LoadOptions = function() {
            var re = /^view_(.*)$/;
            for (var i in adei.config.cfg_extra.custom) {
	        if (a = re.exec(i)) {
	            var node = document.getElementById(i);
	            if (node) {
	                node.value = adei.config.cfg_extra.custom[i];
	            }
	        }
            }
            
            this.ApplyOptions();
}

INFOTAB.prototype.optionsLoadCallback = function(self, props) {
    return function() {
        self.LoadOptions();
        self.SetupGeometry();

        props.infotab_width = self.width;
        props.infotab_height = self.height;

        if (self.page == adei.module.current_module) {
            adei.updater.Update();
        }
        
        if (self.displayed) {
            props.target = "get";
            adei.ServiceDIV(self.div,
                "view",
                "views/" + self.mod,
                props,
                true
            );
        }
    }

}

INFOTAB.prototype.Reload = function() {
    var props = new Object;
    if (this.window) props.window = this.window;
    props.view_object = "infotab";

//    alert(cnode.width);

    this.div.innerHTML = "";
    
    if (this.mod) {
        adei.SetCustomProperties("infomod=" + this.mod, false);
    }

    props.target = "get_options";
    adei.ServiceDIV(this.ctrldiv,
        "view",
        "views/" + this.mod + "_options",
        props,
        true,
        this.optionsLoadCallback(this, props)
    );
}

INFOTAB.prototype.Update = function() {
    this.displayed = true;
    this.Reload();
}

INFOTAB.prototype.UpdateModule = function(val, opts) {
    if (typeof val == "undefined") {
	val = this.infomode.GetValue();
    }

    this.mod = val;

    if (typeof opts == "undefined") this.displayed = true;
    this.Reload();
}

INFOTAB.prototype.OptionsUpdater = function(id, val) {
    adei.RegisterExtraProperty(id);

    if (typeof val == "boolean") {
	if (val) val = 1;
	else val = 0;
    }

    adei.SetCustomProperties(id + "=" + val, false);

    this.displayed = true;
    this.Reload();
}

INFOTAB.prototype.onSelect = function(from, to) {
    this.displayed = true;
    this.window = from + "-" + to;

    this.Reload();
    adei.OpenControl(this.module);
}

function infotabUpdateModule(infotab, mod, opts) {
    return infotab.UpdateModule(mod, opts);
}
