function STATUS(statusbar_id) {
    if (typeof statusbar_id == "string")
	var node = document.getElementById(statusbar_id);
    else
	var node = statusbar_id;

    this.main_node = node;
    this.node = domGetFirstChildByName(node, "div");

    this.timeout = null;
    this.status = null;
    this.id = false;
    }

STATUS.prototype.SetNodeContent = function(msg) {
/*
    while (this.node.firstChild) {
	this.node.removeChild(this.node.firstChild);
    }

    if (typeof msg != "undefined") {
	var tnode = document.createTextNode(msg);
	if (tnode) this.node.appendChild(tnode);
    }

*/    

    if (msg) {
	this.node.innerHTML = msg;
    } else {
	this.node.innerHTML = "";
    }
}


STATUS.prototype.UpdateWidth = function(width) {
	/* There is strange problems in Opera if we would not do that */
    this.main_node.style.width = width + "px";
}

STATUS.prototype.Set = function(msg, duration, yield_to_proposed) {
    if (this.timeout) {
	clearTimeout(this.timeout);
	this.timeout = null;
    }
    
    this.status = msg;
    this.proposed = yield_to_proposed;
    this.SetNodeContent(msg);

    if (duration > 0) {
	if (duration>100) {
	    this.timeout = setTimeout(statusCleanFunction(this), duration);
	} else {
	    this.timeout = setTimeout(statusCleanFunction(this), duration * adei.cfg.default_status_duration);
	}
    } /*else {
	if (typeof duration == "undefined") {
	    this.timeout = setTimeout(statusCleanFunction(this), adei.cfg.default_status_duration);
	} // if zero supplied, - forever 
    }*/
    
    this.id = Math.random();
    return this.id;
}

STATUS.prototype.Propose = function(msg, duration) {
    if ((!this.status)||(this.proposed))
	return this.Set(msg, duration, true);
    return -1;
}

STATUS.prototype.SetComment = function(msg, id) {
    if ((this.status)&&(typeof id != "number")||(id == this.id)) {
	this.SetNodeContent(this.status + " --- " + msg);
//	this.node.innerHTML = this.status + " --- " + msg;
    }
}

STATUS.prototype.Clean = function(id) {
    if ((typeof id != "number")||(id == this.id)) {
	this.SetNodeContent();
//        this.node.innerHTML = "";
	this.status = null;
	this.id = false;
    }
}

function statusCleanFunction(self) {
    return function() {
	self.Clean();
	self.timeout = null;
    }
}
