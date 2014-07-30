function CALLBACK(obj, method, attr) {
    this.obj = obj;
    this.method = method;
    this.attr = attr;
}

CALLBACK.prototype.Call = function(value) {
    eval("this.obj." + this.method + "(value, this.attr)");
}
