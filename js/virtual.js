function VIRTUAL(id, control, geometry_node) {
    this.node = document.getElementById(id);
    this.control = control;

    this.controls_node = geometry_node;

    this.srctree=new dhtmlXTreeObject(id,"100%","100%",0, false);
    if (adei.cfg.dhtmlx_iconset) {
	this.srctree.setImagePath(adei.cfg.dhtmlx_iconset);
    }
    this.srctree.setXMLAutoLoading(adei.GetServiceURL("srctree")); 
    this.srctree.setDataMode("xml");
    this.srctree.enableCheckBoxes(1);
    this.srctree.enableThreeStateCheckboxes(true);
}

VIRTUAL.prototype.UpdateTreeGeometry = function() {
    var s = domGetNodeSize(this.controls_node);
    if (s[1] > 0) domSetHeight(this.node, s[1], true, this.controls_node, s[1], true);
}

VIRTUAL.prototype.AttachConfig = function(config) {
    this.config = config;
    config.Register(this);
}

VIRTUAL.prototype.Start = function() {
    adei.source.RegisterSelectionCallback(new CALLBACK(adei.virtual, "OnSelect"), /^virtual$/, /^srctree$/);
    adei.source.RegisterSubmitCallback(new CALLBACK(adei.virtual, "ApplyConfig"), /^virtual$/);
    this.srctree.loadXML(adei.GetServiceURL("srctree"));
}

VIRTUAL.prototype.OnSelect = function() {
    adei.popup.OpenControl(this.control);
}

VIRTUAL.prototype.ApplyConfig = function() {
    this.config.SetVirtualSettings("srctree", this.srctree.getAllChecked());
//    alert();
}

VIRTUAL.prototype.OpenItem = function (item, check) {
    var parts = item.split("__");
    var parent = false;
    for (var j = 0; j < parts.length - 1; j++) {
        if (j) parent = parent + "__" + parts[j];
        else parent = parts[j];
    
        this.srctree.openItem(parent);
	if (typeof check != "undefined") {
	    this.srctree.setCheck(parent, check);
	}
    }
}

VIRTUAL.prototype.SimpleReadConfig = function() {
//    alert(this.config.cfg.srctree);

    var add = false;
    
    
    var checked = this.srctree.getAllChecked();
    if (checked) checked = checked.split(",");
    else checked = false;

    while ((checked)&&(checked.length > 0)) {
	for (var i = 0; i < checked.length; i++) {
	    this.OpenItem(checked[i], 0);
	    this.srctree.setCheck(checked[i], 0);
	}

	var checked = this.srctree.getAllChecked();
        if (checked) checked = checked.split(",");
	else checked = false;
    }
    
    if (this.config.cfg.srctree) {
      var checked = this.config.cfg.srctree.split(",");
      for (var i = 0; i < checked.length; i++) {
	var item = checked[i];

        if (add) {
	    var start = item.substr(0, add.length);	
	    if (start != add) {
		this.OpenItem(add, "unsure");
		this.srctree.setCheck(add, 1);
	    }
	    add = false;
	}
/*
	var parts = item.split("__");
	
	if (parts.length < 4) add = item;
	else {
	    this.OpenItem(item, "unsure");
	    this.srctree.setCheck(item, 1);
	}
*/
        add = item;
	
      }
      if (add) {
	this.OpenItem(add, "unsure");
	this.srctree.setCheck(add, 1);
      }
    }

}

