function adeiMathPreciseSubstract(a, b) {
    var ra, rb;
    var astr = a.toString();
    var bstr = b.toString();

    var pos = astr.indexOf(".");
    if (pos < 0) ra = 0;
    else ra = parseFloat("0." + astr.substr(pos + 1));

    pos = bstr.indexOf(".");
    if (pos < 0) rb = 0;
    else rb = parseFloat("0." + bstr.substr(pos + 1));

    if ((ra)||(rb)) {
	var ia = Math.floor(a);
	var ib = Math.floor(b);

	var r = ra - rb;
	if (r < 0) {
	    var rstr = (r+1).toString();
	    pos = rstr.indexOf('.');
	    return (ia - ib - 1).toString() + rstr.substr(pos);
	} else if (r > 0) {
	    var rstr = r.toString();
	    pos = rstr.indexOf('.');
	    return (ia - ib).toString() + rstr.substr(pos);
	} else return ia - ib;
    } 
    return a - b;	
}

function adeiMathPreciseAdd(a, b) {
    var ra, rb;

    var astr = a.toString();
    var bstr = b.toString();
    
    var pos = astr.indexOf(".");
    if (pos < 0) ra = 0;
    else ra = parseFloat("0." + astr.substr(pos + 1));

    pos = bstr.indexOf(".");
    if (pos < 0) rb = 0;
    else rb = parseFloat("0." + bstr.substr(pos + 1));

    if ((ra)||(rb)) {
	var ia = Math.floor(a);
	var ib = Math.floor(b);

	var r = ra + rb;
	if (r > 1) {
	    var rstr = r.toString();
	    pos = rstr.indexOf('.');
	    return (ia + ib + 1).toString() + rstr.substr(pos);
	} else if (r < 1) {
	    var rstr = r.toString();
	    pos = rstr.indexOf('.');
	    return (ia + ib).toString() + rstr.substr(pos);
	} else return ia + ib + 1;
    } 
    return a + b;	
}

