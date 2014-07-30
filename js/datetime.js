function adeiDateFormat(d, range) {
    var mydate = new Date(Math.floor(d)*1000);
    if ((range)&&(range <= adei.cfg.subsecond_threshold)) {
	var ds = d.toString();
	var subsecs = ds.indexOf('.');
	if (subsecs >= 0) return dateFormat(mydate) + '.' + ds.substr(subsecs + 1);
	else return dateFormat(mydate);
    } else return dateFormat(mydate);
}

function adeiDateReadableFormat(d, range) {
    if ((range)&&(range <= adei.cfg.subsecond_threshold)) {
	var mydate = new Date(Math.floor(d*1000));
    } else {
	var mydate = new Date(Math.floor(d)*1000);
    }

//    return mydate.toUTCString();
    return mydate.format("isoFullDateTime", true);
}

function adeiDateParse(d) {
    var subsecs = d.indexOf('.');
    if (subsecs < 0) {
	var res = Date.parse(d + " UTC");
	if (isNaN(res)) {
	    res = serverGetResult(adei.GetToolService("parse_date", "timezone=UTC&date=" + d));
	    if (isNaN(res)) return "";
	    return res;
	} 
	return res / 1000;
    }
    
    var dd = d.substr(0,subsecs);
    if (dd.indexOf('.') < 0)  {
	var res = Date.parse(dd + " UTC");
	if (isNaN(res)) {
	    res = serverGetResult(adei.GetToolService("parse_date", "timezone=UTC&date=" + d));
	    if (isNaN(res)) return "";
	    return res;
	}
    } else {
	res = serverGetResult(adei.GetToolService("parse_date", "timezone=UTC&date=" + d));
	if (isNaN(res)) return "";
	return res;
    }
    
    return Math.floor(res / 1000) + '.' + d.substr(subsecs + 1);
}
