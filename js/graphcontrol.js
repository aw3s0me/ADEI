function GRAPHCONTROL(showid,showcontrolsdiv,imgdiv,windowref,graphimg,emaildiv,sensordiv){
    this.showhidebutton = document.getElementById(showcontrolsdiv);
    this.controls = document.getElementById(showid);
    this.imgdiv = document.getElementById(imgdiv);
    this.graphimg = document.getElementById(graphimg);
    this.emaildiv = document.getElementById(emaildiv);
    this.sensordiv = document.getElementById(sensordiv);
    var num = 0;
    this.win = windowref;
    this.path;
    Event.observe(this.showhidebutton,'click', this.showhide.bindAsEventListener(this));
    Event.observe(this.controls, 'click', this.showhide.bindAsEventListener(this));
}
GRAPHCONTROL.prototype.showhide = function(ev){
    if (this.num == 1){
        this.controls.style.visibility = 'visible';
        this.num=0;
    }else{
        this.controls.style.visibility = 'hidden';
        this.num=1;
    }
    this.StopPropagation(ev);
}
GRAPHCONTROL.prototype.StopPropagation = function(ev){
    if (!ev) var ev = window.event;
    if (ev)if (ev.cancelBubble)ev.cancelBubble = true;
    if (ev)if (ev.stopPropagation) ev.stopPropagation();

}
GRAPHCONTROL.prototype.sendEmail = function(){
    try{
        var	props = "url="+ window.location;
        props += "&from="+ document.getElementById('from').value;
        props += "&to="+document.getElementById('tomail').value;
        props += "&message="+document.getElementById('message').value;
        props += "&attachement=" + this.path;
        props += "&task=Send";
        props = props.replace('#','&');
        new Ajax.Request(
            adei.GetServiceURL('email',props),
    	    {
		method: 'get',
		onSuccess: this.MailSent(this),
		onFailure: this.MailFail(this)
    	    }
        );
    } catch (err) {
        alert("FAILED : "+ err);
    }
}


GRAPHCONTROL.prototype.MailSent = function(graphcontrol) {
    return function(transport) {
        if (!transport && !transport.responseText) alert("Unexpected content received");
        else alert("Success : " + transport.responseText);
    }
}

GRAPHCONTROL.prototype.MailFail = function(graphcontrol) {
    return function(transport) {
        alert("No data was returned by the service");
    }
}

GRAPHCONTROL.prototype.openMailForm = function() {
    var serviceurl = adei.GetService('email');
    this.emaildiv.style.visibility = 'visible';
    adei.UpdateDIV('emailform',serviceurl,'email');
}

GRAPHCONTROL.prototype.closediv = function(type) {
    if (type=='imgdiv')this.imgdiv.style.visibility = 'hidden';
    if (type=='emailform')this.emaildiv.style.visibility = 'hidden';
    if (type=='sensordiv')this.sensordiv.style.visibility = 'hidden';
    this.StopPropagation();
}

GRAPHCONTROL.prototype.getSensors = function(){
    if (this.sensordiv) {
        var serviceurl = adei.GetService('email');
        serviceurl += "&"+adei.config.GetProps();
        serviceurl += "&task=getSensorList";
        this.sensordiv.style.visibility = 'visible';
        adei.UpdateDIV('sensorlist',serviceurl,'sensorlist',true);
    }
}

GRAPHCONTROL.prototype.loadImage = function(){
    var props = adei.config.GetProps();
    props += "&width=1024&height=640&task=genpic";
    try{
        new Ajax.Request(
            adei.GetServiceURL('email',props),
    	    {
		method: 'get',
		onSuccess: this.getFilePath(this),
		onFailure: this.failed(this)
    	    }
        );
        this.openMailForm();
    } catch (err) {
        alert("Request Failed : "+ err);
    }
}

GRAPHCONTROL.prototype.failed = function(graphcontrol) {
    return function(transport) {
        alert("Request returned onFailure : " + transport.responseText);
    }
}
GRAPHCONTROL.prototype.getFilePath = function(self) {
    return function(transport) {
        if (!transport && !transport.responseText) alert("Request returned no content");
        else{
            if (transport.responseText=='FAIL') alert("Creating temporary folder for image failed");
            else self.path = transport.responseText;
        }
    }
}

GRAPHCONTROL.prototype.genIMG = function(ev) {
    this.StopPropagation();
    this.loadImage();
}

GRAPHCONTROL.prototype.UseWindow = function(prop) {
    switch (prop){
    case "centerzoomin":
        this.win.window.CenterZoomIn();
        break;
    case "centerzoomout":
        this.win.window.CenterZoomOut();
        break;
    case "moveright":
        this.win.window.MoveRight();
        break;
    case "moveleft":
        this.win.window.MoveLeft();
        break;
    case "moveup":
        this.win.window.MoveUp();
        break;
    case "movedown":
        this.win.window.MoveDown();
        break;
    }
    this.StopPropagation();
}
