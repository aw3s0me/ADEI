function urlConcatenate(base, extra) {
    if (extra) {
	if (/\?/.exec(base)) return base + "&" + extra;
	else return base + "?" + extra;
    }
    return base;
}

function urlAddProperty(base, name, value) {
    if (/\?/.exec(base)) return base + "&" + name + "=" + value;
    else return base + "?" + name + "=" + value;
}

function urlJoinProps(props1, props2, props3, props4, props5) {
    var props = false;
    
    if (props1) {
	if (props) props += "&" + props1;
	else props = props1;
    }

    if (props2) {
	if (props) props += "&" + props2;
	else props = props2;
    }

    if (props3) {
	if (props) props += "&" + props3;
	else props = props3;
    }

    return props;    
}
