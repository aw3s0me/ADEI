function DIALOG(close_cb, title, content, id) {
    var node = document.createElement("div");
    cssSetClass(node, "dialog");

    if (typeof "id" == "string") {
	node.setAttribute("id", id);
    }
    
    node.style.display = "none";
    node.style.left = "0px";
    node.style.top = "0px";

    var label = document.createElement("span");
    label.appendChild(document.createTextNode(title));

    var lnode = document.createElement("div");
    
    lnode.appendChild(label);
    cssSetClass(lnode, "titlebar");
    
    var cnode = document.createElement("div");
    cssSetClass(cnode, "close");

    var onode = document.createElement("div");
    cssSetClass(onode, "maximize");

    var mnode = document.createElement("div");
    cssSetClass(mnode, "content");

    if (typeof css_class != "undefined") cssSetClass(mnode, css_class);
    if (typeof content == "string")
	mnode.innerHTML = content;
    else
	mnode.appendChild(content);
	
    node.appendChild(cnode);
    node.appendChild(onode);
    node.appendChild(lnode);
    node.appendChild(mnode);


    if (isIE()) {
	var comment = document.createElement("div");
	comment.innerHTML = "<!--[if lte IE 6.5]><iframe></iframe><![endif]-->";
	node.appendChild(comment);

	this.iframe = comment;
    }

    this.CloseBind = close_cb.bindAsEventListener( this );
    
    this.node = node;
    this.close_node = cnode;
    this.maximize_node = onode;
    this.titlebar = lnode;
    this.label = label;
    this.content = mnode;
    this.parent = null;

    this.dragger = new DIALOG_DRAGGER(this);
}

DIALOG.prototype.AlterContent = function(content) {
	// Fixing size
    if ((!this.node.style.height)||(!this.node.style.width))
	this.Resize(this.node.offsetWidth, this.node.offsetHeight, true);	

	// Altering content
    if (typeof content == "string")
	this.content.innerHTML = content;
    else
	this.content.replaceChild(content, this.content.firstChild);
}

DIALOG.prototype.Setup = function(default_x, default_y) {
    this.node.style.display = "block";

    domAlignNode(this.node, adei.cfg.window_border, default_x, default_y, this.ResizeFunction(this, true));

//    new Draggable(this.node, {handle: this.titlebar});
    Event.observe(this.close_node, 'click', this.CloseBind );
    
    this.dragger.Setup();
}

DIALOG.prototype.Clean = function() {
    this.dragger.Clean();
    
    Event.stopObserving(this.node, 'click', this.LegendCloseBind );
}

DIALOG.prototype.Show = function(parent, instead_of, default_x, default_y) {
    if (this.parent) return;
    
    if ((typeof instead_of != "undefined")&&(instead_of)) {
	instead_of.Clean();
	parent.replaceChild(this.node, instead_of.node);
	instead_of.parent = null;
    } else {
	parent.appendChild(this.node);
    }

    this.parent = parent;
    
    setTimeout(this.SetupFunction(this,default_x, default_y), adei.cfg.parse_delay);
}

DIALOG.prototype.Hide = function() {
    if (this.parent) {
	this.Clean();
	this.parent.removeChild(this.node);
	this.parent = null;
    }
}

DIALOG.prototype.Resize = function(size_x, size_y, offset_sizes) {
    var node = this.node;
    
    if (size_x) {
	node.style.width = size_x + "px";

	if (offset_sizes) {
	    var diff = (node.offsetWidth - size_x);
	    if (diff) node.style.width = (size_x - diff) + "px";
	}
    }
    
    if (size_y) {
	var orig = node.offsetHeight;
	var corig = this.content.offsetHeight;
	
	this.content.style.height = "0px";
	node.style.height = size_y + "px";

	if (offset_sizes) {
	    var diff = (node.offsetHeight - size_y);
	    if (diff) node.style.height = (size_y - diff) + "px";
	}

	corig += (node.offsetHeight - orig); 
	
	this.content.style.height = corig + "px";
	diff =  this.content.offsetHeight - corig;
	this.content.style.height = (corig - diff) + "px";
    }
}

DIALOG.prototype.CheckReusability = function() {
    if ((this.dragger.is_moved)) {
	var node = this.node;
	
	var left = parseInt(domGetStyleProp(node, node.style.left, "left") ,10);
	left += this.dragger.minimumWidth + adei.cfg.window_border;
	var top = parseInt(domGetStyleProp(node, node.style.top, "top"), 10);
	top += this.dragger.minimumHeight + adei.cfg.window_border;

        if ((left < windowGetWidth())&&(top <  windowGetHeight())) return true;
    }
    
    return false;
}


DIALOG.prototype.ResizeFunction = function (self, offset_sizes) {
    return function(node, size_x, size_y) {
	self.Resize(size_x, size_y, offset_sizes);	
    }
}

DIALOG.prototype.SetupFunction = function (self, x, y) {
    return function() {
	self.Setup(x,y);
    }
}

    