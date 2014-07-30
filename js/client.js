function isIE() {
    return !!(window.attachEvent && !window.opera);
}

function isIE6() {
    if (isIE()) {
	return ((!window.XMLHttpRequest)&&(typeof document.addEventListener != 'function'));
    }
    return false;
}

function isOpera() {
    return !!(window.opera);
}

function isKonqueror() {
	// Both KHTML and WebKit engines has konqueror in agent name but in different cases
    return (navigator.userAgent.toLowerCase().indexOf("konqueror") > -1) ? true : false;
}

function isiDevice() {
  return (navigator.userAgent.indexOf('iPhone') > -1 || navigator.userAgent.indexOf('iPod') > -1 || navigator.userAgent.indexOf('iPad') > -1 || navigator.userAgent.indexOf('android') > -1) ? true : false;
}
function isiPhone() {
  return (navigator.userAgent.indexOf('iPhone') > -1 || navigator.userAgent.indexOf('iPod')) ? true : false;
}
function isiPad(){
  return (navigator.userAgent.indexOf('iPad') > -1) ? true : false;
}

function isSafari() {
    return (navigator.userAgent.indexOf("Safari") > -1) ? true : false;
}

function CLIENT() {
    this.browser = 0;
    this.version = 0;
}

CLIENT.prototype.isIE = function() {
    return isIE();
}

CLIENT.prototype.isIE6 = function() {
    return isIE6();
}

CLIENT.prototype.isOpera = function() {
    return isOpera();
}

CLIENT.prototype.isSafari = function() {
    return isSafari();
}

CLIENT.prototype.isKonqueror = function() {
    return isKonqueror();
}
CLIENT.prototype.isiDevice = function() {
  return isiDevice();
}
CLIENT.prototype.isiPhone = function() {
  return isiPhone();
}
CLIENT.prototype.isiPad = function() {
  return isiPad();
}

client = new CLIENT();
