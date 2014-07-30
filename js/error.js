function adeiGetErrorMessage(msg, module_name, module) {
    if (typeof module_name == "undefined")
	return msg;
    else
	return "Error in '" + module_name + "': " + msg;
}

function adeiReportError(msg, module_name, module) {
    alert(adeiGetErrorMessage(msg, module_name, module));
}

function adeiGetExceptionMessage(e, msg) {
    var emsg;
    if (msg) emsg = msg + ", " + translate("error: ");
    else emsg = translate("Exception caught, error: ");

    if (e.description) emsg += e.description;
    else if (e.message) emsg += e.message;
    else emsg += translate("Unknown error");
    
    if (e.fileName) {
	emsg += "(" + e.fileName;
	if (e.lineNumber) emsg += ":" + e.lineNumber;
	emsg += ")";
    }
    
    if (e.stack) {
	emsg += "\n\n" + e.stack;
    }
    
    return emsg;
}

function adeiReportException(e, msg, module_name, module) {
    var emsg = adeiGetExceptionMessage(e, msg);
    adeiReportError(emsg, module_name, module);
}
