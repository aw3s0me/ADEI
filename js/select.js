function selectUpdater(xmldoc, param, error) {
    var sel = param.select;

    if (xmldoc) {
	sel.UpdateStart(xmldoc, param);
    } else {
	sel.UpdateConfirm(xmldoc, param, false, error);
    }
}

function SELECT(id, cb, cbattr) {
    if (typeof cb == "undefined") this.callback = null;
    else this.callback = cb;
    if (typeof cbattr == "undefined") this.cbattr = null;
    else this.cbattr = cbattr;

    this.id = id;
    this.prop = false;
    this.url = false;

    this.cb_success = null;
    this.cb_failure = null;
    
    this.node = document.getElementById(this.id);
    if (!this.node) adeiReportError(translate('SELECT "%s" is not found', this.id));
    
    this.childs = new Array();    
}

SELECT.prototype.SetupSource = function(url, prop) {
    this.url = url;
    this.prop = prop;
}

SELECT.prototype.SetupCompletionCallbacks = function(success, failure, attr) {
    this.cb_success = success;
    this.cb_failure = failure;

    if (typeof attr == "undefined")
	this.cb_completion_attr = this.cbattr;
    else
	this.cb_completion_attr = attr;
}


SELECT.prototype.AddChild = function(sel) {
    this.childs.push(sel);
}


SELECT.prototype.ParseError = function(xmldoc) {
    var error;
    var errors = xmldoc.getElementsByTagName("Error");

    if (errors.length > 0)
	error = errors[0].firstChild.data;
    else {
	var errors = xmldoc.getElementsByTagName("parsererror");
	if (errors.length > 0)
	    error = errors[0].firstChild.data;
	else
	    error = "Empty response";
    }

    return error;
}

SELECT.prototype.ParseValues = function(xmldoc) {
    var res = new Array();
    var values = xmldoc.getElementsByTagName("Value");

    for (var i = 0; i < values.length; i++) {
	var value = values[i];
	res.push(value.getAttribute("value"));
    }

    return res;
}

SELECT.prototype.UpdateStart = function(xmldoc, param, just_childs) {
    var value;        

    if (just_childs) {
	var curvalue = this.GetValue();
	if ((param.value == null)||(param.value == curvalue)) {
	    value = curvalue;
	} else {
	    this.SetValue(param.value);
	    value = this.GetValue();
	}
    } else {
	var values = this.ParseValues(xmldoc);
	if (values.length <= 0) {
	    error = this.ParseError(xmldoc);
	    return this.UpdateConfirm(xmldoc, param, false, error);
	}
	
	if (param.value == null) {
	    var valnum = 0;

	    if ((typeof param.nexttry != "undefined")&&(param.nexttry > 0)) {
		valnum = param.nexttry;
	    }
	    
	    value = values[valnum];

	    valnum += 1;
	    if (valnum < values.length) param.nexttry = valnum;
	    else param.nexttry = -1;
	} else if (values.indexOf(param.value) < 0) {
	    // action?
	    value = values[0];
	} else {
	    value = param.value;
	}
    }


//    alert(this.id + " - start - " + param.opts.parental_confirmation);
    if (!this.childs.length) {
	return this.UpdateConfirm(xmldoc, param, false, 0);
    }

    var url;
    if (this.url) {
	url = (param.url?(param.url+"&"):"") + this.prop + "=" + value;
    } else {
	url = urlAddProperty(param.url, this.prop, value);
    }

    param.child_confirmation = new Array(this.childs.length);
    
    for (var i = 0; i < this.childs.length; i++) {
	var child = this.childs[i];
	var prop_name = child.prop;
	var prop = false;

	if (prop_name) {
	    var opt_prop = eval("param.opts." + prop_name);
	    if (typeof opt_prop != "undefined") prop = opt_prop;
	}

	var opts;
	if (param.opts) {
	    opts = objectClone(param.opts, [
		"parental_confirmation",
		"child_confirmation",
		"cb_attr"
	    ]);
	} else opts = new Array();
	
	opts.parental_confirmation = {
	    parent: this,
	    index: i,
	    param: param,
	    xmldoc: xmldoc
	};
	
	child.Update(url, opts, prop);
    }
}


SELECT.prototype.UpdateConfirm = function(xmldoc, param, confirm, error, errobj) {
    if (confirm) {
	var i = confirm.index;
	var myparam = confirm.param;
	
	if (!myparam.child_confirmation[i]) {
	    myparam.child_confirmation[i] = {
		xmldoc: xmldoc,
		param: param,
		error: error,
		errobj: errobj
	    };
	    
	}
	
	for (i = 0; i < this.childs.length; i++) {
	    if (typeof myparam.child_confirmation[i] == "undefined") break;
	}
	
	if (i < this.childs.length) return;
	

	for (i = 0; i < this.childs.length; i++) {
	    if (myparam.child_confirmation[i].error) {
		error = myparam.child_confirmation[i].error;
		errobj = myparam.child_confirmation[i].errobj;
		if (typeof errobj == "undefined") errobj = this.childs[i];
	    }
	}
	
	xmldoc = confirm.xmldoc;
	param = confirm.param;
    }

    if ((error)&&(param.nexttry)) {
	this.UpdateStart(xmldoc, param, false);
    } else if (param.opts.parental_confirmation) {
	var c = param.opts.parental_confirmation;
	c.parent.UpdateConfirm(xmldoc, param, c, error, errobj);
    } else {
	if (error) {
	    var msg;
	    
	    if ((errobj)&&(errobj != this)) {
		msg = translate("SELECT \"%s\" update is failed. Error: %s", errobj.id, error);
	    } else {
		msg = translate("SELECT \"%s\" update is failed. Error: %s", this.id, error);
	    }

	    var opts = param.opts;
	    
	    if (typeof param.restore != "undefined") {
		this.SetValue(param.restore);
	    }

	    this.UpdateFailure(opts, msg);
	} else {
	    param.opts.succeeded = true;
	    this.UpdateComplete(xmldoc, param);
	}
    }

}


SELECT.prototype.UpdateComplete = function(xmldoc, param) {
    for (var i = 0; i < this.childs.length; i++) {
	var child = this.childs[i];
	var cfn = param.child_confirmation[i];
	child.UpdateComplete(cfn.xmldoc, cfn.param);
    }
    
    if (!xmldoc) {
	if (param.value) {
		// param.value is string variable, therefore should be no problem with 0
	    this.SetValue(param.value);
	}
	this.UpdateSuccess(param.opts);
	return;
    }

    var values = xmldoc.getElementsByTagName("Value");
    if (values.length > 0) {
	var value;
	var selnode = this.node;
	
		
	while (selnode.hasChildNodes()) selnode.removeChild(selnode.firstChild);

	for (var i = 0; i < values.length; i++) {
	    var value = values[i];
	    var item_value = value.getAttribute("value");
	    var item_text = value.getAttribute("name");
	    if (!item_text) item_text = item_value;
	    
	    var opt = document.createElement('option');
	    opt.setAttribute("value", item_value);
	    if (param.value) {
		if (item_value == param.value) opt.setAttribute("selected", 1);
	    } else if ((typeof param.nexttry != "undefined")&&(param.nexttry > 1)) {
		if ((i+1) == param.nexttry) opt.setAttribute("selected", 1);
	    }
	    opt.appendChild(document.createTextNode(item_text));
	    selnode.appendChild(opt);
	}
/*
	innerHTML is not working due to the bug Q276228 in IE	
	sel.node.innerHTML = res;
*/
	this.UpdateSuccess(param.opts);
    } else {
	var error = this.ParseError();
	var msg = translate("SELECT \"%s\" update is unexpectedly failed. Error: %s", this.id, error);

	var opts = param.opts;
	this.UpdateFailure(opts, msg);
    }
}


SELECT.prototype.UpdateSuccess = function(opts) {
    opts.confirmed = true;

    if (this.callback) {
	this.callback(this.cbattr, this.GetValue(), opts);
    }

    if (opts.succeeded) {
	if (this.cb_success) {
	    this.cb_success(this.cb_completion_attr, opts.success_value, opts);
	}

	if (opts.success_cb) {
	    opts.success_cb(opts.cb_attr, opts.success_value, opts);
	}
    }
}

SELECT.prototype.UpdateFailure = function(opts, msg) {
    opts.canceled = true;
    if (this.cb_failure) {
	this.cb_failure(this.cb_completion_attr, opts.failure_value, opts);
    }
    
    if (opts.failure_cb) {
	opts.failure_cb(opts.cb_attr, opts.failure_value, opts);
    }

    if (typeof msg == "undefined") msg = translate("Uncatched failure");
    adeiReportError(msg);
}



/* DS: Should do something if request failed */
SELECT.prototype.Update = function(url, opts, value) {
    var param = new Object;

    param.select = this;
    if (typeof opts == "undefined") param.opts = new Array();
    else param.opts = opts;
    if (typeof value == "undefined") param.value = null;
    else param.value = value;

    param.url = url;

    queueXML(this.url?urlConcatenate(this.url, url):url, selectUpdater, param);
}

SELECT.prototype.UpdateChilds = function(url, opts, value, restore) {
    var param = new Object;

    param.select = this;
    if (typeof opts == "undefined") param.opts = new Array();
    else param.opts = opts;
    if (typeof value == "undefined") param.value = null;
    else param.value = value;
    if (typeof restore == "undefined") param.restore = undefined;
    else param.restore = restore;

    param.url = url;

    this.UpdateStart(null, param, true);
}



SELECT.prototype.GetLastIndex = function() {
    return this.node.length - 1;
}

SELECT.prototype.GetValue = function(idx) {
    if (typeof idx == "undefined")
	return this.node.value
    else if ((idx < this.node.length)&&(idx>=0))
	return this.node[idx].value;

    //adeiReportError(translate('SELECT "%s". Index (%d) is out of range', this.id, idx));
    return null;
}

SELECT.prototype.GetIndex = function(value) {
    if (typeof value == "undefined")
	return this.node.selectedIndex;
    else {
	for (var i = 0; i < this.node.length; i++) {
	    if (this.node[i].value == value) {
		return i;
	    }
	}

	//adeiReportError(translate('SELECT "%s". Lookup for index of non-existing value \"%s\" is failed', this.id, value));
	return -1;
    }
}

SELECT.prototype.SetIndex = function(idx) {
    if ((idx < this.node.length)&&(idx >= 0)) {
	this.node.selectedIndex = idx;
	return true;
    } else {
	//adeiReportError(translate('SELECT "%s". Index (%d) is out of range', this.id, idx));
        return false;
    }
}

SELECT.prototype.SetValue = function(value) {
    for (var i = 0; i < this.node.length; i++) {
	if (this.node[i].value == value) {
	    this.node.selectedIndex = i;
	    return true;
	}
    }
    //adeiReportError(translate('SELECT "%s". Setting non-existing value: %s', this.id, value));
    return false;
}

SELECT.prototype.GetTitle = function(idx) {
    if (typeof idx == "undefined")
	idx = this.node.selectedIndex;
    else if ((idx<0)||(idx>=this.node.length))
	return adeiReportError(translate('SELECT "%s". Index (%d) is out of range', this.id, idx));

    var node = this.node.childNodes[idx];
    if (node) {
	var text = node.firstChild;
	if (text) return text.nodeValue;
    }
    return null;
}

SELECT.prototype.Next = function(cycle, filter) {
    var cur = this.GetIndex();
    
    do {
	var idx = this.GetIndex() + 1;
	if (idx < this.node.length) this.SetIndex(idx);
	else if (cycle) this.SetIndex(0);
	else {
	    this.SetIndex(cur);
	    break;
	}
    } while ((typeof filter == "function")&&(filter(this.GetIndex(), this.GetValue())));
}

SELECT.prototype.Prev = function(cycle, filter) {
    var cur = this.GetIndex();

    do {
	var idx = this.GetIndex();
	if (idx > 0) this.SetIndex(idx - 1);
	else if (cycle) this.SetIndex(this.node.length - 1);
	else {
	    this.SetIndex(cur);
	    break;
	}
    } while ((typeof filter == "function")&&(filter(this.GetIndex(), this.GetValue())));
}

SELECT.prototype.Show = function() {
    if (this.node) this.node.style.display = "inline";
}

SELECT.prototype.Hide = function() {
    if (this.node) this.node.style.display = "none";
}

SELECT.prototype.Disable = function() {
    this.node.disabled = true;
    for (var i = 0; i < this.childs.length; i++)
	this.childs[i].Disable();
}

SELECT.prototype.Enable = function() {
    this.node.disabled = false;
    for (var i = 0; i < this.childs.length; i++)
	this.childs[i].Enable();
}
