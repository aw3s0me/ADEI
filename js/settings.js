function SETTINGS(w) {
    this.db_server="";
    this.db_name;
    this.db_group;
    this.control_group;
    this.db_mask;
    this.db_item;
    this.settingprops = "&";
    this.depth = 1;
    this.interval;
    this.aggrpages = new Object();
    this.aggrprop="&";
}

SETTINGS.prototype.setValue = function(id,vlue) {
 switch (id) {
    case "server":
        this.db_server=vlue;
        this.settingprops = vlue;
        break;
    case "database":
        this.db_name=vlue;
        this.settingprops += vlue;
        break;
    case "loggroup":
        this.db_group=vlue;
        this.settingprops += vlue;
        break;
    case "itemmask":
        this.item = vlue;
        this.settingprops += vlue;
    case "controlgroup":
        this.control_group=vlue;
        this.settingprops += vlue;
        break;
    case "aggr":
        this.aggrprop += vlue;
        break;
    default:
        alert("failed");
        break;
 }
}


SETTINGS.prototype.setconf = function() {
    if (this.settingprops) {
        this.goTo(this.settingprops);
        this.settingprops = "&";
        this.depth = 1;
        this.db_server = this.db_name = this.db_group = this.db_mask = 'undefined';
        this.goTo("p_id=main");
        adei.OpenModule('graph');
        adei.updater.Update();
    }
}

SETTINGS.prototype.makeRequest = function(target) {
    var listreq;
    var head;
    var hist;
    var xslt = "settingsdyn";
    var pid;
    switch (target){
     case 'target=servers':
        listreq = adei.GetService('list',target+"&skip_uncached=true");
        hist = "p_id=sourcefront";
        head = 'Server';
        pid="source";
        break;
     case 'target=databases':
        if (this.db_server != 'undefined'){
            listreq = adei.GetService('list',target+"&"+this.db_server);
            hist = 'target=servers';
            head = 'Database';
            pid="source";
        }else alert("Define server first");
        break;
     case 'target=groups':
        if (this.db_server != 'undefined' && this.db_name != 'undefined'){
            listreq = adei.GetService('list',target+"&"+this.db_server+"&"+this.db_name);
            hist = 'target=databases';
            head = 'LogGroup';
            pid="source";
        }else alert("Define server and database first");
        break;
     case 'target=masks':
        if (this.db_server != 'undefined' && this.db_name != 'undefined' && this.db_group != 'undefined'){
            listreq = adei.GetService('list',target+"&"+"menu=1"+"&"+this.db_server+"&"+this.db_name+"&"+this.db_group);
            hist = 'target=groups';
            head = 'ItemMask';
            pid="source";
        }else alert("Define server, database and loggroup first");
        break;
     case 'target=sourcetimecustom':
        listreq = adei.GetService('settings',"p_id=sourcetimecustom");
        xslt = "idatetimepicker";
        break;
     case 'target=sourcetimewindow':
        listreq = adei.GetService('settings',"p_id=sourcetimewindow");
        xslt = "idatetimepicker";
        break;
     case 'target=items':
        if (this.db_server != 'undefined' && this.db_name != 'undefined' && this.db_group != 'undefined'){
            listreq = adei.GetService('list',target+"&"+this.db_server+"&"+this.db_name+"&"+this.db_group);
            hist = 'target=masks';
            head = 'Standalone Item';
            pid="source";
        }else alert("Define server, database and loggroup first");
        break;
     case 'target=aggregation_modes':
        listreq = adei.GetService('list',target);
        hist="p_id=controls";
        head="Aggregation Modes";
        pid = "control";
        break;
     case 'target=interpolation_modes':
        listreq = adei.GetService('list',target);
        hist = "target=aggregation_modes";
        head = "Interpolation Modes";
        pid = "control";
        break;
     case 'target=marks_modes':
        listreq = adei.GetService('list',target);
        hist="target=interpolation_modes";
        head="Show Marks";
        pid = "control";
        break;
     case 'target=gaps_modes':
        listreq = adei.GetService('list',target);
        hist="target=mark_modes";
        head="Show Gaps";
        pid = "control";
        break;
     default:
        alert("Unknown target");
        break;
    }
    var xmlresult = getXML(listreq);
    if (xslt == 'settingsdyn'){
        xmlresult = this.addElement(xmlresult,"pageid",pid);
        xmlresult = this.addElement(xmlresult,"history",hist);
        xmlresult = this.addElement(xmlresult,"heading",head);
    }
    var jsonvar = new Object();
    jsonvar.xml = xmlresult;
    jsonvar.xslt=xslt;
    settings.Update(jsonvar, 'false');
}

SETTINGS.prototype.goTo = function(page,tograph) {
    adei.SetConfiguration(page);
    if (tograph)adei.OpenModule('graph');
}

SETTINGS.prototype.updateTime = function(vlue) {
    adei.SetConfiguration(vlue + "&p_id=main");
    adei.OpenModule('graph');
    adei.updater.Update();
}

SETTINGS.prototype.addElement = function(xmlobject,element,content) {
    var root = xmlobject.documentElement;
    var el = xmlobject.createElement(element);
    var txt = xmlobject.createTextNode(content);
    el.appendChild(txt);
    root.appendChild(el);
    return xmlobject;
}


SETTINGS.prototype.ProcessWindow = function() {
    var start = document.getElementById('startdate').value;
    var end = document.getElementById('enddate').value;
    start += " " + document.getElementById('starttime').value;
    end += " " + document.getElementById('endtime').value;
    start = Date.parse(start);
    end = Date.parse(end);
    start = start/1000;
    end = end/1000;
    start += "";
    end += "";
    this.updateTime("window="+start+"-"+end);
}

SETTINGS.prototype.HandleBack = function(target) {
    this.depth--;
    if (target == 'p_id=sourcefront' || target == 'p_id=controls')this.goTo(target);
    else this.makeRequest(target);
}

SETTINGS.prototype.Search = function() {
    var searchvalue = document.getElementById('searchfield').value;
    var searchreq =	adei.GetService("search", "search="+searchvalue);
    var xmlresult = getXML(searchreq);

    xmlresult = this.addElement(xmlresult,"history","p_id=sourcefront");
    xmlresult = this.addElement(xmlresult,"heading","Search");
    xmlresult = this.addElement(xmlresult,"page","searchresults");
    var jsonvar = new Object();
    jsonvar.xml = xmlresult;
    jsonvar.xslt = "settings";
    settings.Update(jsonvar,'false');
}

SETTINGS.prototype.CreateSpinningWheel = function(type) {
    if (type == 'startdate' || type == 'enddate'){
        var now = new Date();
        var days = { };
        var years = { };
	var months = { 
		 1: 'Jan'
               , 2: 'Feb'
               , 3: 'Mar'
               , 4: 'Apr'
               , 5: 'May'
               , 6: 'Jun'
               , 7: 'Jul'
               , 8: 'Aug'
               , 9: 'Sep'
               , 10: 'Oct'
               , 11: 'Nov'
               , 12: 'Dec'
        };

        for ( var i = 1; i < 32; i += 1 ) {
            days[i] = i;
        }

        for ( i = now.getFullYear()-100; i < now.getFullYear()+1; i += 1 ) {
            years[i] = i;
        }

        SpinningWheel.addSlot(months, '', 1);
        SpinningWheel.addSlot(days, 'right', 1);
        SpinningWheel.addSlot(years, 'right', 1999);

        SpinningWheel.setCancelAction(this.Cancel);
        SpinningWheel.setDoneAction(this.getResults,type);
        SpinningWheel.open();

    } else if (type == 'starttime' || type == 'endtime') {
        var hours = {};
        var minutes = {};
        var seconds = {};
        var i = 1;

        for (i = 0; i<24; i++){
            if (i<10) hours["0"+i] = "0" + i;
            else hours[i] = i;
        }
        for (i=0; i<60; i++){
            if (i<10){
                minutes["0"+i] = "0" + i;
                seconds["0"+i] = "0" + i;
            }
            else{
                minutes[i] = i;
                seconds[i] = i;
            }
        }

        SpinningWheel.addSlot(hours,'right', 5);
	SpinningWheel.addSlot({':': 'h'},'readonly shrink');
        SpinningWheel.addSlot(minutes,'', 5);
	SpinningWheel.addSlot({':': 'm'},'readonly shrink');
        SpinningWheel.addSlot(seconds,'right',5);
	SpinningWheel.addSlot({'': 's'},'readonly shrink');

        SpinningWheel.setCancelAction(this.Cancel);
        SpinningWheel.setDoneAction(this.getResults,type);

        SpinningWheel.open();
    }
}

SETTINGS.prototype.getResults = function(id) {
    var results = SpinningWheel.getSelectedValues();
    switch (id) {
      case 'startdate':
        document.getElementById('startdate').value = results.keys.join('/');
        break;
      case 'starttime':
        document.getElementById('starttime').value = results.keys.join('');
        break;
      case 'enddate':
        document.getElementById('enddate').value = results.keys.join('/');
        break;
      case 'endtime':
        document.getElementById('endtime').value = results.keys.join('');
        break;
    }
}

SETTINGS.prototype.Cancel = function() {
    //alert("canceled");
}

SETTINGS.prototype.sendGetRequest = function(p_id,vlue) {
    switch (p_id){
      case "p_id=sourceselect":
        switch (this.depth) {
          case 1:
            this.makeRequest("target=servers");
            break;
          case 2:
            this.setValue("server","&db_server="+vlue);
            this.makeRequest("target=databases");
            break;
          case 3:
            this.setValue("database","&db_name="+vlue);
            this.makeRequest("target=groups");
            break;
          case 4:
            this.setValue("loggroup","&db_group="+vlue);
            this.makeRequest("target=masks");
            break;
          case 5:
            if (vlue != -1){
                this.setValue("itemmask","&db_mask="+vlue);
                this.goTo("p_id=main");
                this.setconf();
            }
            else{
                this.makeRequest("target=items");
            }
            break;
          case 6:
            this.setValue("itemmask","&db_mask="+vlue);
            this.goTo("p_id=main");
            this.setconf();
            break;
        }
        this.depth +=1;
        break;
      case "p_id=controlsexp":
        this.LoadControlsExp();
        break;
      case "p_id=sourcetimecustom":
        this.makeRequest("target=sourcetimecustom");
        break;
      case "p_id=sourcetimewindow":
        this.makeRequest("target=sourcetimewindow");
        break;
      case "p_id=controlsaggr":
        switch (this.depth){
          case 1:
            this.makeRequest("target=aggregation_modes");
            break;
          case 2:
            this.setValue("aggr","aggregation="+vlue);
            this.makeRequest("target=interpolation_modes");
            break;
          case 3:
            this.setValue("aggr","&interpolation="+vlue);
            this.makeRequest("target=marks_modes");
            break;
          case 4:
            this.setValue("aggr","&show_marks="+vlue);
            this.makeRequest("target=gaps_modes");
            break;
          case 5:
            this.setValue("aggr","&show_gaps="+vlue);
            adei.SetConfiguration(this.aggrprop);
            this.goTo("p_id=main");
            adei.OpenModule('graph');
            this.depth = 0;
            break;
        }
        this.depth +=1;
        break;
      default:
        this.depth=1;
        this.goTo(p_id);
        break;
    }
}

