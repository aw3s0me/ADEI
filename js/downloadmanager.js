  function DLMANAGER() {  
    this.config = new CONFIG();
    this.tm = null;
    this.rate = 2000;
    this.defopts = "target=dlmanager_run";  
    this.download = 0;
    this.finalizing = false;
    this.starting = false;
    this.stop = false;
    this.idleruns = 0;   ;
  }

  function dlmanagerstart() {        
    //if(dlmanager.tm == null) dlmanager.tm = window.setInterval("dlmanager.Request()", dlmanager.rate); 
    dlmanager.idleruns = 0;
    dlmanager.Request();
  }

  DLMANAGER.prototype.Request = function(opts) {       
    if(!opts) var opts = this.defopts;  
    var host = 'http://'+window.location.hostname+'/adei/services/';
    new Ajax.Request(
	adei.GetServiceURL("download", opts),
	{
	    method: 'post',
	    onSuccess: dlUpdaterRequest(this),
	    onFailure: function() {		
		adei.SetStatus(translate("Downloadmanager updater POST request failed"), 0);
		this.Stop();
	    }
	}
    );      
  }  

  function dlUpdaterRequest(updater) { 
    return function(transport) {	
	var error = false;
	var result;
	var res = transport.responseXML;
	
        if (res) {
	    var values = res.getElementsByTagName("result");
	    if (values.length > 1) {
		error = translate("download service returned multiple result nodes");
	    } else if (values.length == 0) {
		var errors = res.getElementsByTagName("error");
		if (errors.length > 0) {
		    error = errors[0].firstChild.data;
		} else {
	    	    error = translate("download service returned no result or error nodes", url);
		}
	    } else {
		result = values[0];
	    }
	}
	if (error) adeiReportError(error);

	var job = result.getAttribute('job');
    
	switch (job) {
	  case 'Showprogress':	
	      updater.idleruns = 0;
	      var download = result.getAttribute('dl_id');
	      var filediv = "fcount" + download;
	      var frem = result.getAttribute('frem');
	      var prog = result.getAttribute('progress');	      
	      var finalizing = result.getAttribute('finalizing');
	      var bar = "progress" + download;
 	      con = new CONFIG();  
 	      if(con.GetModule() == 'download') setTimeout("dlmanager.Request()", updater.rate);
	      if(finalizing != 1) updater.finalizing = false;
	      if(finalizing == 1) {
		updater.finalizing = true;
		$(bar).style.width = "100%";
		$(bar).innerHTML = "Finalizing file...";
	      } else if(prog < 100 && prog != "") {
		$(bar).style.width = prog + "%";
		$(bar).innerHTML = prog + "%";
		$(filediv).innerHTML = "Data groups left: "+frem;
	      }	      	     
	  break;
	  case 'Idle':	
		var finalizing = result.getAttribute('finalizing')
		updater.idleruns = updater.idleruns + 1;
		if ((finalizing)||(updater.finalizing)) {
		  updater.finalizing = false;
		  adei.updater.Update();
		} else {
		  if(updater.idleruns < 4) setTimeout("dlmanager.Request()", updater.rate);
		}
	  break;	  
	  case 'Done':
	      adei.updater.Update();
	  break;
	
	}      
     }    
    
  }  
  
   DLMANAGER.prototype.RemoveDownload = function(download) {
     var doIt = confirm('Are you sure you want to delete download?');
     if(doIt) {
       var opts = "target=dlmanager_remove&dl_id=" + download;
       this.Request(opts);   
     }	
     else return;
   }

   DLMANAGER.prototype.SetShared = function(download) {
    var opts = "target=dlmanager_setshared&dl_id=" + download;
    //alert(opts);
    this.Request(opts);
   }
   
  DLMANAGER.prototype.AddDownload = function(opts) {
    opts += "&target=dlmanager_add";  
    //alert(opts);
    this.Request(opts);
    if(this.config.GetModule() != 'download') {
      adei.OpenModule('download');
    }            
  }
  
  DLMANAGER.prototype.Stop = function() {   
    window.clearInterval(this.tm);
  }
  
  DLMANAGER.prototype.SortBy = function(user) {
    var opts = "target=dlmanager_sort&sortby=" + user;
    this.Request(opts);
  }

  DLMANAGER.prototype.ToggleAutodelete = function(download) {
    var opts = "target=dlmanager_toggleautodelete&dl_id=" + download;
    this.Request(opts);
  }









