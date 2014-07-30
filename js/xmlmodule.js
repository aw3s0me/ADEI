function XMLMODULE(div, root_node) {
    if (typeof div == "object") this.div = div;
    else if (div) {
	this.div = document.getElementById(div);
	if (!this.div) adei.ReportError(translate("Element \"%s\" is not present in current document model", div));
    } else return;  /* inheritance */
    
    if (typeof root_node == "undefined") this.root_node = this.div;
    else if (typeof root_node == "object") this.root_node = root_node;
    else this.root_node = document.getElementById(root_node);
    
    this.module_type = HISTORY_MODULE_TYPE;

    this.jsexec = false;
}

XMLMODULE.prototype.EnableJS = function() {
    this.jsexec = true;
}

XMLMODULE.prototype.Update = function(json, forced) {
    if ((json.xml)&&(json.xslt)) {
	adei.UpdateDIV(this.div, json.xml, json.xslt, this.jsexec);
	adei.SetSuccessStatus(translate("Done"));
    } else {
	adei.SetStatus(translate("Update failed: Incomplete response received by the module"));
    }
}

XMLMODULE.prototype.GetNode = function() {
    return this.root_node;
}


XMLMODULE.prototype.SetModuleType = function(type) {
    this.module_type = type;
}

XMLMODULE.prototype.GetModuleType = function() {
    return this.module_type;
}
