function DIALOG_DRAGGER(dialog) {
    if (typeof dialog.node == "undefined") {
        this.dialog = new Object;
        this.dialog.node = dialog;
        this.dialog.maximize_node = null;
        this.dialog.titlebar = null;
        this.dialog.content = null;
        
        this.float_mode = false;
    } else {
        this.dialog = dialog;
        
        this.float_mode = true;
    }
    
    this.resizeCornerSize = 16;
	/* If set to small, we will hide scrollbars of content window and
	resizing will not be operable any more */
    this.minimumWidth = 60;
    this.minimumHeight = 15;
    
    this.MouseMoveBind = this.MouseMove.bindAsEventListener( this );
    this.MouseOutBind = this.MouseOut.bindAsEventListener( this );
    this.MaximizeBind = this.Maximize.bindAsEventListener( this );
    this.ResizeBind = this.Resize.bindAsEventListener( this );
    this.MoveBind = this.Move.bindAsEventListener( this );
    
    this.TrackBind = this.Track.bindAsEventListener( this );
    this.DoneBind = this.Done.bindAsEventListener( this );
    
    this.label_displayed = true;
    
    this.in_resize = false;
    this.in_move = false;
    this.maximized = false;
    
    this.is_moved = false;
    this.is_resized = false;
    
    this.cb_start = null;
    this.cb_step = null;
    this.cb_end = null;
    this.cb_attr = null;
    
    this.ctrl_min = false;
    
    this.allow_move = true;
    this.allow_resize = true;
    this.allow_resize_e = true;
    this.allow_resize_w = true;
    this.allow_resize_n = true;
    this.allow_resize_s = true;
}

DIALOG_DRAGGER.prototype.RegisterCallbacks = function(cb_start, cb_step, cb_done, cbattr) {
    this.cb_start = cb_start;
    this.cb_step = cb_step;
    this.cb_end = cb_done;
    this.cb_attr = cbattr;
}

DIALOG_DRAGGER.prototype.ControlMinimumSize = function(min_width, min_height) {
    if ((Prototype.Browser.Gecko)&&(domGetStyleProp(this.dialog.node, this.dialog.node.style.minWidth, "min-width"))) {
        this.ctrl_min = true;
        this.min_width = min_width;
        this.min_height = min_height;
    }
}

DIALOG_DRAGGER.prototype.Disable = function(move, resize) {
    if (!move) this.allow_move = false;
    if (typeof resize == "string") {
        if (resize.indexOf('n') >= 0) this.allow_resize_n = false;
        if (resize.indexOf('s') >= 0) this.allow_resize_s = false;
        if (resize.indexOf('w') >= 0) this.allow_resize_w = false;
        if (resize.indexOf('e') >= 0) this.allow_resize_e = false;
    } else if (!resize) this.allow_resize = false;
}

DIALOG_DRAGGER.prototype.Setup = function() {
    if (this.dialog.content) {
	// IE7 and not only :) needs it to adjust widths of sub-divs
        domSetWidth(this.dialog.node, this.dialog.node.offsetWidth);
    }
	// IE6 :) fixing size for iframes
    if (this.dialog.iframe) {
    	this.SaveDimenssions();
	this.Restore();
    }

    if (this.dialog.maximize_node)
        Event.observe(this.dialog.maximize_node, 'click', this.MaximizeBind );

    if ((this.allow_move)&&(this.dialog.titlebar))
        Event.observe(this.dialog.titlebar, 'mousedown', this.MoveBind );

    if (this.allow_resize)
        Event.observe(this.dialog.node, 'mousedown', this.ResizeBind );

    Event.observe(this.dialog.node, 'mousemove', this.MouseMoveBind );
    Event.observe(this.dialog.node, 'mouseout', this.MouseOutBind );
}

DIALOG_DRAGGER.prototype.Clean = function() {
    if (this.dialog.maximize_node)
        Event.stopObserving(this.dialog.maximize_node, 'click', this.MaximizeBind );

    if ((this.allow_move)&&(this.dialog.titlebar))
        Event.stopObserving(this.dialog.titlebar, 'mousedown', this.MoveBind );

    if (this.allow_resize)
        Event.stopObserving(this.dialog.node, 'mousedown', this.ResizeBind );

    Event.stopObserving(this.dialog.node, 'mousemove', this.MouseMoveBind );
    Event.stopObserving(this.dialog.node, 'mouseout', this.MouseOutBind );
}

DIALOG_DRAGGER.prototype.MouseMove = function(ev) {
    if ((this.in_resize)||(this.in_move)) return;
    
    var target = domGetEventTarget(ev);
    if (target != this.dialog.node) {
	this.resizeDirection = "";
	this.dialog.node.style.cursor = "";
	return;
    }

    var width = this.dialog.node.offsetWidth;
    var height = this.dialog.node.offsetHeight;

    var offset = domGetEventLayerOffset(ev);
    
    var xOff = offset[0];
    var yOff = offset[1];
    
    if (!this.float_mode) {
        var node_offset = domGetNodeOffset(this.dialog.node);
        xOff -= node_offset[0];
        yOff -= node_offset[1];
    }
    
    var resizeDirection = ""
    if ((this.allow_resize_n)&&(yOff < this.resizeCornerSize))
	resizeDirection += "n";
    else if ((this.allow_resize_s)&&(yOff > (height - this.resizeCornerSize)))
	resizeDirection += "s";
    if ((this.allow_resize_w)&&(xOff < this.resizeCornerSize))
	resizeDirection += "w";
    else if ((this.allow_resize_e)&&(xOff > (width - this.resizeCornerSize)))
	resizeDirection += "e";

    if (resizeDirection) {
	this.resizeDirection = resizeDirection;
	this.dialog.node.style.cursor = resizeDirection + "-resize";
    } else {
	this.resizeDirection = "";
	this.dialog.node.style.cursor = "";
    }
}

DIALOG_DRAGGER.prototype.MouseOut = function(ev) {
/* Causes problems in Opera
    this.dialog.node.style.cursor = "";
*/
}


DIALOG_DRAGGER.prototype.Move = function(ev) {
    var target = domGetEventTarget(ev);
    if ((target != this.dialog.titlebar)&&(target != this.dialog.label)) return;

    Event.observe(document, 'mousemove', this.TrackBind );
    Event.observe(document, 'mouseup', this.DoneBind );

    var node = this.dialog.node;
    var offset = domGetEventPageOffset(ev);
    this.xOffset = parseInt(domGetStyleProp(node, node.style.left, "left") ,10) - offset[0];
    this.yOffset = parseInt(domGetStyleProp(node, node.style.top, "top"), 10) - offset[1];

    this.in_move = true;
}

DIALOG_DRAGGER.prototype.SaveDimenssions = function() {
    var node = this.dialog.node;

    this.oldLeft = parseInt(domGetStyleProp(node, node.style.left, "left") ,10);
    this.oldTop = parseInt(domGetStyleProp(node, node.style.top, "top"), 10);

	// Obtaining original window size
    var size = domGetNodeSize(node);
    node.style.width = size[0] + "px";
    node.style.height = size[1] + "px";

    var csize = domGetNodeSize(node);
    this.corr_x = csize[0] - size[0];
    this.corr_y = csize[1] - size[1];
    
    this.oldWidth = size[0] - this.corr_x;//parseInt(domGetStyleProp(node, node.style.width, "width"), 10);
    this.oldHeight = size[1] - this.corr_y;//parseInt(domGetStyleProp(node, node.style.height, "height"), 10);

    node.style.width = this.oldWidth + "px";
    node.style.height = this.oldHeight + "px";

    if (this.ctrl_min) {
        node.style.minWidth = this.min_width + "px";
        node.style.minHeight = this.min_height + "px";
    }

    if (this.dialog.content) {
        var mnode = this.dialog.content;
	    // Obtaining content element size
        size = domGetNodeSize(mnode);

//    mnode.style.width = size[0] + "px";
        mnode.style.height = size[1] + "px";

        var csize = domGetNodeSize(mnode);
//    this.corr_cx = csize[0] - size[0];
        this.corr_cy = csize[1] - size[1];

//    this.cWidth = size[0] - this.corr_cx;
        this.cHeight = size[1] - this.corr_cy;
    
//    mnode.style.width = this.cWidth + "px";
        mnode.style.height = this.cHeight + "px";
    }
}

DIALOG_DRAGGER.prototype.Resize = function(ev) {
    var target = domGetEventTarget(ev);
    if (target != this.dialog.node) return;

    var node = this.dialog.node;

    Event.observe(document, 'mousemove', this.TrackBind );
    Event.observe(document, 'mouseup', this.DoneBind );

    this.in_resize = true;

	// If max-height not supported set titlebar height
    if (this.dialog.titlebar) {
        var tb = this.dialog.titlebar;
        var mh = parseInt(domGetStyleProp(tb, tb.style.maxHeight, "max-height"),10);
        if ((!mh)||((tb.offsetHeight - mh)>10)) {
	    domSetWidth(node, node.offsetWidth);
	    if (mh>0) domSetHeight(tb, mh);
	    else domSetHeight(tb, 21);
        }
    }
    
    if (this.dialog.label) {
        if (!this.label_width) {
	    this.label_width = this.dialog.label.offsetWidth + this.dialog.label.offsetLeft;
        }
    }

    var offset = domGetEventPageOffset(ev);
    this.xPosition = offset[0];
    this.yPosition = offset[1];

    this.SaveDimenssions();    

	// Obtaining titlebar size (no correction, since preciese size is not important
    if (this.dialog.titlebar) {
        var lnode = this.dialog.titlebar;
        var size = domGetNodeSize(lnode);
        size[1] += this.corr_y;
        if ((size[1] + 10) > this.minimumHeight) this.minimumHeight = size[1] + 10;
    }

    if (ev && ev.preventDefault) ev.preventDefault();
    else window.event.returnValue = false;
    return false;
}

DIALOG_DRAGGER.prototype.Restore = function(ev) {
    var node = this.dialog.node;
    var mnode = this.dialog.content;

    node.style.left = this.oldLeft + "px";
    node.style.top = this.oldTop + "px";
    node.style.width = this.oldWidth + "px";
    node.style.height = this.oldHeight + "px";
    mnode.style.height = this.cHeight + "px";

    cssSetClass(this.dialog.maximize_node, "maximize");
    this.maximized = false;
}

DIALOG_DRAGGER.prototype.Maximize = function(ev) {
    var node = this.dialog.node;
    var mnode = this.dialog.content;

    if (this.maximized) {
	this.Restore();
    } else {
	this.SaveDimenssions();
	
	node.style.left = 0;
	node.style.top = 0;
	
	var ww = windowGetWidth();
    	var wh = windowGetHeight();

	node.style.width =  (ww - this.corr_x) + "px";
	
	if ((wh - this.corr_y) > this.oldHeight) {
	    node.style.height = (wh - this.corr_y) + "px";
	    mnode.style.height = (this.cHeight + (wh - this.corr_y - this.oldHeight)) + "px";
	}

	cssSetClass(this.dialog.maximize_node, "restore");
	this.maximized = true;
    }
}

DIALOG_DRAGGER.prototype.Track = function(ev) {
    var node = this.dialog.node;

    if (this.in_resize) {
	var north = false;
	var south = false;
	var east  = false;
	var west  = false;
	
	if (this.resizeDirection.charAt(0) == "n") north = this.allow_resize_n;
	if (this.resizeDirection.charAt(0) == "s") south = this.allow_resize_s;
	if (this.resizeDirection.charAt(0) == "e" || this.resizeDirection.charAt(1) == "e") east = this.allow_resize_e;
	if (this.resizeDirection.charAt(0) == "w" || this.resizeDirection.charAt(1) == "w") west = this.allow_resize_w;

        var offset = domGetEventPageOffset(ev);
	var dx = offset[0] - this.xPosition;
	var dy = offset[1] - this.yPosition;

	if (west) dx = -dx;
	if (north) dy = -dy;

	var w = this.oldWidth  + dx;
	var h = this.oldHeight + dy;
	
	if (w < this.minimumWidth) {
	    w = this.minimumWidth;
	    dx = w - this.oldWidth;
	}
	
	if (h < this.minimumHeight) {
	    h = this.minimumHeight;
	    dy = h - this.oldHeight;
	}
	
	if (east || west) {
	    if (w < this.label_width) {
		if (this.label_displayed) {
		    this.dialog.titlebar.removeChild(this.dialog.label);
		    this.label_displayed = false;
		}
	    } else {
		if (!this.label_displayed) {
		    this.dialog.titlebar.appendChild(this.dialog.label);
		    this.label_displayed = true;
		}
	    }
	    
	    node.style.width = w + "px";
	    if (west) node.style.left = (this.oldLeft - dx) + "px";
	}

	if (north || south) {
	    node.style.height = h + "px";

	    if (this.dialog.content) {
	        var mnode = this.dialog.content;
	        if ((this.cHeight + dy) < 0) mnode.style.height = 0 + "px";
	        else mnode.style.height = (this.cHeight  + dy) + "px";
	        if (dy < 0) {
		    var size = domGetNodeSize(mnode);
		    var rdy = size[1] - this.corr_cy - this.cHeight;
		    if (rdy > dy) {
	    	        node.style.height = (this.oldHeight + rdy) + "px";
		    }
	        }
	    }

	    if (north) node.style.top  = (this.oldTop  - dy) + "px";
	}
	
	this.is_resized = true;
    } else if (this.in_move) {
        var offset = domGetEventPageOffset(ev);
	node.style.left = (offset[0] + this.xOffset) + "px";
	node.style.top  = (offset[1] + this.yOffset) + "px";
	
	this.is_moved = true;
    }
    
    Event.stop(ev);
}

DIALOG_DRAGGER.prototype.Done = function(ev) {
    if (this.in_resize) {
	this.in_resize = false;
    }

    if (this.in_move) {
	this.in_move = false;
    }

    Event.stopObserving(document, 'mousemove', this.TrackBind );
    Event.stopObserving(document, 'mouseup', this.DoneBind );

    Event.stop(ev);

    if (this.cb_end) {
        this.cb_end(this.cb_attr, this);
    }
}
