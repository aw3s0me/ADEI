function nope() {
}

function objectClone(obj, skip) {
    if (typeof skip == "undefined") skip = new Array();
    
    var res = new Object;
    for (var i in obj) {
	if ((obj[i])&&(typeof obj[i] == "object")&&(skip.indexOf(i)<0))
	    res[i] = objectClone(obj[i]);
	else	    
	    res[i] = obj[i];
    }
    return res;
}


function parseFloatArray(floats) {
    if (typeof floats != "object") floats = floats.split(",");
    for (var i=0;i<floats.length;i++) {
	floats[i] = parseFloat(floats[i]);
    }
    return floats;
}

function parseIntArray(floats) {
    if (typeof floats != "object") floats = floats.split(",");
    for (var i=0;i<floats.length;i++) {
	floats[i] = parseInt(floats[i]);
    }
    return floats;
}

function parseStringArray(floats) {
    if (typeof floats != "object") floats = floats.split(",");
    else {
	for (var i=0;i<floats.length;i++) {
	    floats[i] = floats[i].toString();
	}
    }
    return floats;
}

function parseArray(floats) {
    if (typeof floats != "object") floats = floats.split(",");
    return floats;
}

function objectToProps(props) {
    if (typeof props == "object") {
        var res = false;
        for (var i in props) {
	    if ((typeof props[i] != "object")&&(typeof props[i] != "function")) {
	        if (res) res += "&" + i + "=" + props[i];
	        else res = i + "=" + props[i];
	    }
        }
        return res;
    }
    
    return props;
}

function htmlEntityDecode(str) {
    var ta=document.createElement("textarea");
    ta.innerHTML=str.replace(/</g,"&lt;").replace(/>/g,"&gt;");
    return ta.value;
}
