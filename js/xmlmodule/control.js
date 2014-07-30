function CONTROL(div) {
    CONTROL.superclass.call(this, div);
    this.SetModuleType(CONTROL_MODULE_TYPE);
}

classExtend(CONTROL, XMLMODULE);

CONTROL.prototype.Set = function(id) {
    var need_mask = false;
    var control_mask = false;

    var props = new Object();
    props.target = "set";
    props.control_values = false;

    var input = this.div.getElementsByTagName("input");
    if (input) {
	var re = /^control_id_(\d+)$/;
    
	var values = new Array();
	for (var i = 0; i < input.length; i++) {
	    var m = re.exec(input[i].id);
	    if (m) {
		values[m[1]] = input[i].value;
	    }
	}
	
	if (values.length > 0) {
	    for (var i = 0; i < values.length; i++) {
		if (typeof values[i] == "undefined") {
		    need_mask = true;
		} else {
		    if (props.control_values) props.control_values += "," + values[i];
		    else props.control_values = values[i];
		    if (control_mask) control_mask += "," + i;
		    else control_mask = "" + i;
		}
	    }
	    
	    if (need_mask) props.control_mask = control_mask;

	    adei.updater.Request(props);
	    return;
	}
    }
    adei.ReportError(translate("Internal error, no input elements is found"));
}
