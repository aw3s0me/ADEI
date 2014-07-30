function GRAPH(id, selid) {
    this.config = null;
    this.window = null;
    this.source = null;
    this.exporter = null;
    this.selector = document.getElementById(selid);
    this.frame = document.getElementById(id);
    
    this.start_xy = [ 0, 0 ];
    this.margins = { left: 0, top: 0, right: 0, bottom: 0 };
    this.crop_margins = { left: 0, top: 0, right: 0, bottom: 0 };
    this.allWidth = 10;
    this.allHeight = 10;
    
    this.height = 0;
    this.width = 0;
    this.plot_mode = -1;
    
    this.idevice = isiDevice();   
    this.startX = 0; 
    this.startY = 0;
    this.curX = 0;  
    this.curY = 0;
    this.startValuesSet = 0;    
    this.swiping = 0;
    this.fader = 0;
    this.throwLeft = 0;
    this.test = 0;
    this.onlyLegend = 0;
    
    if (this.idevice) {
        this.fader = new Animator().addSubject(new NumericalStyleSubject($('graph_image_div'), 'opacity', 1, 0.13));        
    }
    
    if (this.frame) {
	this.img = this.frame.getElementsByTagName('img')[0];
    } else {
	this.frame = null;
    }
    
/*    
    Event.observe(id,'click',this.MouseStart);
    Event.observe(id,'MouseMove', this.MouseStart);
    Event.observe(id,'MouseDown', this.MouseStart);
*/

    this.crop = null;
    this.extraButtons = new Array();

    if (this.img) {
	     Event.observe(this.img, 'load', this.Configure.bindAsEventListener(this));	
    } else {
	if (this.frame) alert('The "img" element is not found within specified block "' + id + '"');
	else alert('GRAPH is not able to locate specified ("' + id + '") element');
    }

    this.LegendCloseBind = this.LegendClose.bindAsEventListener( this );
    this.LegendCloseHelperBind = eventCanceler.bindAsEventListener();

	// Handling no data cases
    if (!isIE()) {
	this.img.addEventListener("DOMMouseScroll", this.onLocalScroll(this), false);
    }
    
    this.onload_message = 0;
    
    this.img.onmousewheel =  this.onLocalScroll(this);
    this.img.ondblclick = this.onLocalDblClick(this);
    this.img.onload = this.onImageLoad(this);

}


GRAPH.prototype.onImageLoad = function(self) {
    return function() {
	adei.SetSuccessStatus(self.onload_message?self.onload_message:translate("Done"));  

	if(self.fader){
	    self.AnimatorEffects(self,2);   
	} 
    }
}

GRAPH.prototype.RegisterCropperButton = function(info) {
    info.onClick = this.onButtonClick(this, info);
    this.extraButtons.push(info);
}


GRAPH.prototype.AttachConfig = function(config) {
    this.config = config;
//    config.Register(this);
}

GRAPH.prototype.Clear = function() {
    if (this.crop) {
	this.crop.clear();
    }
}

GRAPH.prototype.PointerToY = function(axis_num, pos) {
    if (this.ylog[axis_num]) {
	var res = (Math.log(this.ymax[axis_num]) - (Math.log(this.ymax[axis_num]) - Math.log(this.ymin[axis_num]))*(pos - this.margins.top) / this.real_height) / Math.LN10;
	return Math.pow(10, res);
    } else {
	var res = this.ymax[axis_num] - (this.ymax[axis_num] - this.ymin[axis_num])*(pos - this.margins.top) / this.real_height;
	return res;
    }
}

GRAPH.prototype.onEndCrop = function (self) {
    return function( coords, dimensions ) {
	if (self.window) {
	    if ((dimensions.width < self.allWidth)&&(dimensions.height < self.allHeight)) {
		self.window.SetCustomWindow();

		for (var i = 0; i < self.axis.length; i++) {
		    self.window.SetCustomAxis(self.axis[i]);
		}
    	    } else if (dimensions.height < self.allHeight) {
		var xmin = self.xmin + (self.xmax - self.xmin)*(coords.x1 - self.margins.left) / self.real_width;
		var xmax = self.xmin + (self.xmax - self.xmin)*(coords.x2 - self.margins.left) / self.real_width;
		self.window.SetCustomWindow(xmin, xmax);
		
		for (var i = 0; i < self.axis.length; i++) {
		    self.window.SetCustomAxis(self.axis[i]);
		}
	    } else {
		for (var i = 0; i < self.axis.length; i++) {
		    //var ymin = self.ymax[i] - (self.ymax[i] - self.ymin[i])*(coords.y2 - self.margins.top) / self.real_height;
	    	    //var ymax = self.ymax[i] - (self.ymax[i] - self.ymin[i])*(coords.y1 - self.margins.top) / self.real_height;

		    var ymin = self.PointerToY(i, coords.y2);
		    var ymax = self.PointerToY(i, coords.y1);

		    self.window.SetCustomAxis(self.axis[i], ymin, ymax);
		}

	        if (dimensions.width >= self.allWidth) {
		    var xmin = self.xmin + (self.xmax - self.xmin)*(coords.x1 - self.margins.left) / self.real_width;
		    var xmax = self.xmin + (self.xmax - self.xmin)*(coords.x2 - self.margins.left) / self.real_width;
		    self.window.SetCustomWindow(xmin, xmax);
		}
	    }
	}
    }
}

GRAPH.prototype.onCancelCrop = function (self) {
    return function() {
	if (self.window) {
	    self.window.SetCustomWindow();
	    for (var i = 0; i < self.axis.length; i++) {
		self.window.SetCustomAxis(self.axis[i]);
	    }
	}
	if (adei.updater) 
	    adei.updater.Notify("SelectionCancel");
    }
}

GRAPH.prototype.onApply = function (self) {
    return function ( coords, dimensions ) {
	self.window.Apply();
	self.crop.clear();
    }
}

GRAPH.prototype.onSave = function (self) {
    return function ( coords, dimensions ) {
	if (self.exporter) 
	    self.exporter.Export(true);
	else
	    adeiReportError("Data Exporter is not registered", "GRAPH");
    }
}

GRAPH.prototype.onButtonClick = function(self, info) {
    return function (corrds, dimensions) {
	var from = self.config.sel_from;
	var to = self.config.sel_to;

	eval('info.object.' + info.callback + '(from, to)');
    }
}



GRAPH.prototype.GetYAxisNumber = function(mouse_x) {
    if (mouse_x > this.margins.left) return 0;
    var dist = this.margins.left - mouse_x;
    var axis = Math.floor(dist / this.axis_size);
    if (axis >= this.axis.length) axis = this.axis.length - 1;
    return axis;
}

GRAPH.prototype.ProcessMouseScroll = function ( delta, mouse_x, mouse_y ) {
	switch (adei.key) {
	    case 72:	// h
		if (this.source) {
		    if (delta > 0)
			this.config.history.Back();
		    else
			this.config.history.Forward();
		}
	    break;
	    case 83:	// s
		if (this.source) {
		    if (delta > 0)
			this.source.NextServer();
		    else
			this.source.PrevServer();
		}
	    break;
	    case 68:	// d
		if (this.source) {
		    if (delta > 0)
			this.source.NextDatabase();
		    else
			this.source.PrevDatabase();
		}
	    break;
	    case 71:	// g
		if (this.source) {
		    if (delta > 0)
			this.source.NextGroup();
		    else
			this.source.PrevGroup();
		}
	    break;
	    case 77:	// m
		if (this.source) {
		    if (delta > 0)
			this.source.NextMask();
		    else
			this.source.PrevMask();
		}
	    break;
	    case 73:	// i
		if (this.source) {
		    if (delta > 0)
			this.source.NextItem();
		    else
			this.source.PrevItem();
		}
	    break;
	    case 69:	// e
		alert('not supported');
		
		if (this.experiment) {
		    if (delta > 0)
			this.experiment.NextItem();
		    else
			this.experiment.PrevItem();
		}
	    break;
	    case 84:	// t
	    case 16:	// Shift
		// t - time move
		if (delta > 0)
		    this.window.MoveLeft();
		else if (delta < 0)
		    this.window.MoveRight();
	    break;
	    case 86:	// v
		if (delta > 0) {
		    this.window.MoveDown();
		} else if (delta < 0) {
		    this.window.MoveUp();
		}
	    break;
	    case 87:	// w
		if (delta > 0)
		    this.window.IncreaseWidth();
		else if (delta < 0)
		    this.window.DecreaseWidth();
		
	    break;
	    case 89:	// y
		for (var i = 0; i < this.axis.length; i++) {
//		    var y = this.ymax[i] - (this.ymax[i] - this.ymin[i])*(mouse_y - this.margins.top) / this.real_height;
		    var y = this.PointerToY(i, mouse_y);

		    if (delta > 0)
			this.window.YZoomOut(i, y);
		    else if (delta < 0)
			this.window.YZoomIn(i, y);
		}
		
	    break;
	    case 67:	// c
		if (delta > 0)
		    this.window.CenterZoomOut();
		else if (delta < 0)
		    this.window.CenterZoomIn();
		
	    break;
	    case 76:	// l
	    case 90:	// z
		x = adeiMathPreciseAdd(this.xmin, this.xsize*(mouse_x - this.margins.left) / this.real_width);

		if (delta > 0)
		    this.window.LocalZoomOut(x);
		else if (delta < 0)
		    this.window.LocalZoomIn(x);
		
	    break;
	    case false:
		if (mouse_y > (this.height - this.margins.bottom)) {
		    if (delta > 0)
			this.window.MoveLeft();
		    else if (delta < 0)
			this.window.MoveRight();
		} else if (mouse_x < this.margins.left) {
		    if (this.axis_size) {
		        var ai = this.GetYAxisNumber(mouse_x);
			if (delta > 0)
			    this.window.MoveDown(ai);
			else if (delta < 0)
			    this.window.MoveUp(ai);
		    } else {
			for (var ai = 0; ai < this.axis.length; ai++) {
			    if (delta > 0)
				this.window.MoveDown(ai);
			    else if (delta < 0)
				this.window.MoveUp(ai);
			}
		    }
		} else if ((mouse_y >= this.margins.top)&&(mouse_x <= (this.width - this.margins.right))) {
		    var x = adeiMathPreciseAdd(this.xmin, this.xsize*(mouse_x - this.margins.left) / this.real_width);
		    
		    if (delta > 0)
			this.window.ZoomOut(x);
		    else if (delta < 0)
			this.window.ZoomIn(x);
		}
	    break;
//	    default:
//		  if(this.fader) this.fader.reverse();
	}
}

GRAPH.prototype.onMouseScroll = function (self) {
    return function ( delta, point ) {
	self.ProcessMouseScroll(delta, point.x, point.y);
//	alert(adei.key);
//adeiMathPreciseAdd(self.xmin, self.xsize*(point.x - self.margins.left) / self.real_width),	
//	alert(mouse_x + "(" + self.real_width + ")");

    }
}

GRAPH.prototype.onLocalDblClick = function(self) {
    return function(ev) {
	self.window.ResetXY();
    }
}

GRAPH.prototype.onLocalScroll = function(self) {
    return function(ev) {
	switch (adei.key) {
	    case 83:	// s
	    case 68:	// d
		var delta = domGetScrollEventDelta(ev);
		self.ProcessMouseScroll(delta,0,0);
	    break;
	    case 71:	// g
	    case 67:	// c
	    case 76:	// l
	    case false:
		self.window.CenterZoomOut();
		// zoom out
	    break;
	}
    }
}

GRAPH.prototype.onMouseMove = function (self) {
    return function ( point, dragging, resizing ) {

//	if (self.config.GetModule() != "graph") return;
    
	if ((!point)||((point.x < self.margins.left)||(point.x > (self.width - self.margins.right)))||
	    ((point.y < self.margins.top)||(point.y > (self.height - self.margins.bottom)))) {
		adei.ClearStatus(self.mousepos_status_id);
		return;
	}
    
	var x = adeiMathPreciseAdd(self.xmin, self.xsize*(point.x - self.margins.left) / self.real_width);

	var status = translate('Mouse cursor is at time: ') + adeiDateReadableFormat(x, self.xsize) + ", Y: ";

	for (var i = self.axis.length - 1; i>=0; i--) {
//	    var y = self.ymax[i] - (self.ymax[i] - self.ymin[i])*(point.y - self.margins.top) / self.real_height;
            var y = self.PointerToY(i, point.y);
            
	    status += "<span style=\"color: " + self.color[i] + "\">" + y.toPrecision(4) + "</span>";
	    if (i) status += ", ";
	}
	
	self.mousepos_status_id = adei.ProposeStatus(status, 0);
    }
}



GRAPH.prototype.LegendClose = function(self) {
    return function(ev) {
	if (self.legend) {
	    self.legend.Hide();
	    self.legend = null;
	}
	Event.stop(ev);
    }
}

GRAPH.prototype.ShowLegend = function (self, mouse_x, mouse_y) {
    return function(transport) {
/*	if (transport.responseText.charAt(0) == '{') {
	    var json = transport.responseText.evalJSON(false);
    	    if (json.error) {
		adeiReportError(json.error);
	    } else {
		var html = "";//"<h4>Legend</h4>""<div class=\"window_close\"></div><h4>Legend</h4>";
		if ((json.legend)&&(json.legend.length>0)) {
		    html+="<table><tr><th>ID</th><th>Name</th></tr>";
		    for (var i = 0; i < json.legend.length; i++) {
			var item = json.legend[i];
			html+="<tr><td>" + item.id + "</td><td>" + item.name + "</td></tr>";
		    }
		    html+="</table>";
		} else {
		    html+="<p>Nothing selected</p>";
		}
*/
		var html = transport.responseText;
		
		if ((self.legend)&&(self.legend.CheckReusability())) {
		    self.legend.AlterContent(html);
		    adei.SetSuccessStatus("Legend is updated");
		} else {
		    var legend = new DIALOG(self.LegendClose(self), "Legend", html, "legend");
		    legend.Show(self.frame, self.legend, mouse_x, mouse_y);
		    self.legend = legend;
		    
		    adei.ClearStatus(self.legend_status_id);
		}
/*
	    }
	} else {
	    // XML
	}
*/
    }

}

GRAPH.prototype.onClick = function (self) {
    return function ( ev, point ) {
	if ((point.x < self.margins.left)||(point.x > (self.width - self.margins.right))) return;
	if ((point.y < self.margins.top)||(point.y > (self.height - self.margins.bottom))) return;
    
	var x = adeiMathPreciseAdd(self.xmin, self.xsize*(point.x - self.margins.left) / self.real_width);
	
	var y = new Array();
	for (var i = 0; i < self.axis.length; i++) {
//	    y[i] = self.ymax[i] - (self.ymax[i] - self.ymin[i])*(point.y - self.margins.top) / self.real_height;
            y[i] = self.PointerToY(i, point.y);
	}

	switch (adei.key) {
	 case 67:	// c
	    self.window.Center(x,y);
	    return;
	 case 90:	// z
	    self.window.DeepZoom(x);
	    return;
	}


//	alert(adeiMathPreciseAdd(self.xmin, self.xsize*(point.x - self.margins.left) / self.real_width));
	var params = {
	    xmin: self.xmin.toString(),
	    xmax: self.xmax.toString(),
	    ymin: self.ymin,
	    ymax: self.ymax,
	    x : x,
	    y : y,
	    xslt: "legend",
	    time_format: "text"
	};


//	alert(self.xmax + " - " + self.xmin + "(" + self.xsize + "): " + point.x + "\n");
	
	if (adei.config.cfg.plot_mode == 0) {
	    self.legend_status_id = adei.SetStatus(translate("Loading legend..."), 0);
	    new Ajax.Request(adei.GetServiceURL("legend"), 
	    {
		method: 'post',
		requestHeaders: {Accept: 'application/json'},
		parameters: { props: self.config.GetJSON(params) },
		onSuccess: self.ShowLegend(self, ev.clientX, ev.clientY),
		onFailure: function() {
		    alert('GetLegend request is failed');
		}
	    });
	}
    
	//updater.
    }
}


GRAPH.prototype.onDblClick = function(self) {
    return function (ev, point) {
	if (point.x < self.margins.left) {
	    if (self.axis_size) {
		var ai = self.GetYAxisNumber(point.x);
		self.window.ResetY(self.axis[ai]);
	    } else {
		self.window.ResetY();
	    }
	} else if (point.y > (self.height - self.margins.bottom)) {
	    self.window.ResetXY();
	}
    }
}

/**TouchStart sets startvalues
 * Saves the position of users finger
 */
GRAPH.prototype.onTouchStart = function(self){
  return function(e,pos){
    if(self.startValuesSet == 0)
    {
        self.startX = pos.x;
        self.startY = pos.y;        
        self.startValuesSet = 1;
    if(e.touches.length == 1){self.onlyLegend = 1;}
    }
    if(e.touches.length == 2){self.onlyLegend = 0;}
  }
}

/**onTouchMove 
 * 
 */
GRAPH.prototype.onTouchMove = function(self){
    return function(e,xpos){
      if(e.touches.length == 1)
      {
         self.swiping = 1;
         self.curX = xpos.x;
         self.curY = xpos.y;
         self.onlyLegend = 0;
      }
      if(e.touches.length>1){
        self.swiping = 0;
        self.onlyLegend = 0;
      }
      
      
    }
}
/**onTouchEnd
 * Does swipe, or show legend.
 */
GRAPH.prototype.onTouchEnd = function(self){
    return function(e){
        e.preventDefault();  

        self.startValuesSet=0;      
        if(self.swiping == 1){//Do swipemoving left & right & up & down
            var direction = self.getSwipeDirection(self);
            self.swiping = 0;
           // alert(direction);
            if(direction != 0)
            {    
              if(self.fader != 0) {
                self.AnimatorEffects(self,1);}
                self.window.Swipe(direction);
              //alert(direction);
              //self.window.Apply();
              //startValuesSet = 0;
            }
        }
        else if(self.onlyLegend === 1 && self.swiping === 0){
            var iphone = isiPhone();
            if(!iphone) {
                var x = adeiMathPreciseAdd(self.xmin, self.xsize*(self.startX - self.margins.left) / self.real_width);
                var y = new Array();
                for (var i = 0; i < self.axis.length; i++) {
//                    y[i] = self.ymax[i] - (self.ymax[i] - self.ymin[i])*(self.startY - self.margins.top) / self.real_height;
                    y[i] = self.PointerToY(i, self.startY);
                }
                   
                    var params = {
                    xmin: self.xmin.toString(),
                    xmax: self.xmax.toString(),
                    ymin: self.ymin,
                    ymax: self.ymax,
                    x : x,
                    y : y,
                    xslt: "legend",
                    time_format: "text"
                };           
                self.legend_status_id = adei.SetStatus(translate("Loading legend..."), 0);
                new Ajax.Request(adei.GetServiceURL("legend"), 
                {
                    method: 'post',
                    requestHeaders: {Accept: 'application/json'},
                    parameters: { props: self.config.GetJSON(params) },
                    onSuccess: self.ShowLegend(self, self.startX, self.startY),
                    onFailure: function() {
                  alert('GetLegend request is failed');
                    }
                });
        	
		self.onlyLegend = 0;
          }
        }
     }
}

/**onGestureStart
 * When 2 fingers touch the screen.
 */
GRAPH.prototype.onGestureStart = function(self){
  return function(e){ 
    self.swiping = 0;
    self.onlyLegend = 0;
  }
  
}
/**onGestureMove
 * nothing yet
 */
GRAPH.prototype.onGestureMove = function(self){
  return function(e,scale){

  }
  
}

/**onGestureEnd
 * Zoom graph x or y
 */
GRAPH.prototype.onGestureEnd = function(self){
  return function(e,scale,angle){

      var gestureValues = new Object();
      gestureValues.x = adeiMathPreciseAdd(self.xmin, self.xsize*(self.startX - self.margins.left) / self.real_width);
     // alert(self.margins.left);
      gestureValues.marginsLeft = self.margins.left;
      gestureValues.device = self.idevice;
      gestureValues.y = new Array();
      for (var i = 0; i < self.axis.length; i++) {
//        gestureValues.y[i] = self.ymax[i] - (self.ymax[i] - self.ymin[i])*(self.startY - self.margins.top) / self.real_height;        
            gestureValues.y[i] = self.PointerToY(i, self.startY);
      }
      if(self.fader != 0){self.AnimatorEffects(self,1);}
      gestureValues.scale = scale;
      gestureValues.startX = self.startX;
      self.window.PinchZoom(gestureValues);
      self.startValuesSet = 0;      
      self.window.Apply();      
  } 
}

GRAPH.prototype.onOrientationChange = function(self){
  return function(e){
  	if(self.fader != 0){self.AnimatorEffects(self,1);}
  	self.window.Apply();
  }
}


/**Gets direction of Swipe gesture
 * When user swipes the screen with 1 finger, calculates heading and return an integer.
 * Return values:
 * 1 = Swipe left
 * 2 = Swipe Right
 * 3 = Swipe Up
 * 4 = Swipe Down 
 */
GRAPH.prototype.getSwipeDirection = function(self){
  var varianceX = 0;
  var varianceY = 0;
  var leftRight = 0;
  var upDown = 0;
  var percentX =  (self.real_width)/100;
  var percentY = (self.real_height)/100;

  if(self.startX > self.curX){
    varianceX = (self.startX-self.curX)/percentX - 20; 
    leftRight = 1;
    }
  else if(self.startX < self.curX){
    varianceX = (self.curX-self.startX)/percentX + 20; 
    leftRight = 2;
    }
  if(self.startY > self.curY){
    varianceY = (self.startY-self.curY)/percentY + 20; 
    upDown = 3;
    }
  else if(self.startY < self.curY){
    varianceY = (self.curY-self.startY)/percentY + 20;
    upDown = 4;
    }
    
  if(varianceX < 30 && varianceY < 30)
  {return 0;}  
  if(varianceX > varianceY)
  {
    return leftRight;
  }
  else if(varianceX < varianceY)
  {
    return upDown;
  }

  
}

/** Animatoreffects
 * Uses animator.js to animate graph opacity.
 */
GRAPH.prototype.AnimatorEffects = function(self,id){
  if(self.fader){
    switch(id){
      case 1:
        self.fader.play();
        break;
      case 2:
        self.fader.reverse();
        break;
      default:
        break;
    }
  }
}


GRAPH.prototype.AttachWindow = function (window) {
    this.window = window;
    window.RegisterGraph(this);
}

GRAPH.prototype.AttachSource = function (source) {
    this.source = source;
}

GRAPH.prototype.AttachExporter = function (exporter) {
    this.exporter = exporter;
}

GRAPH.prototype.SetMargins = function (l,t,r,b) {
    this.margins = { left: l, top: t, right: r, bottom: b };
    this.crop_margins = { left: l, top: t, right: r, bottom: b };
    if (this.crop) this.crop.setMargins(l, t, r, b);
}

GRAPH.prototype.SetAllSizes = function (w,h) {
    this.allWidth = w;
    this.allHeight = h;
}

GRAPH.prototype.Prepare = function() {
	/* Fixing IE6. Diff is non-zero only in IE6, however certain
	CSS settings could bring it to zero even in IE6. For example:
	html, body { width: 100%; } */
    var diff = document.body.offsetWidth - windowGetWidth();
    if (diff > 0) {
	var new_size = this.frame.offsetWidth - diff;
	if (new_size > 0) {
	    this.config.SetupGeometry(new_size, this.frame.offsetHeight);
	    return;
	}
    }

    this.config.SetupGeometry(this.frame);
}

GRAPH.prototype.ConfigureFunction = function(self) {
    return function () {
	return self.Configure();
    }
}

GRAPH.prototype.Configure = function() {
	    this.real_height = this.img.height - this.margins.top - this.margins.bottom;
	    this.real_width = this.img.width - this.margins.left - this.margins.right;
	    
	    if (this.xsize) {
		var new_width = this.img.width;
		var new_height = this.img.height;
		
		if (this.crop) {
		  if ((new_width != this.width)||(new_height != this.height)) {
			this.width = new_width;
			this.height = new_height;
			this.crop.setParams();	// DS. this action slows evertything down after several steps
		  } 
		  if (adei.config.cfg.plot_mode != this.plot_mode) {
		    this.crop.setCropMode(((adei.config.cfg.plot_mode == 0)?true:false));
		  }
	        } else {
		  var tooltip;
		  if (this.exporter) 
			tooltip = " (" + this.exporter.GetTooltip() + ")";
		  else
			tooltip = "";

		  if(this.idevice) {
            		this.crop = new Cropper.Img("graph_image", {
	        	    onOrientationChange: this.onOrientationChange(this),
    			    onTouchStart: this.onTouchStart(this),
	        	    onTouchMove:this.onTouchMove(this),
	        	    onTouchEnd:this.onTouchEnd(this),
	        	    onGestureStart:this.onGestureStart(this),
	        	    onGestureMove:this.onGestureMove(this),
	        	    onGestureEnd:this.onGestureEnd(this),   
	        	    onApplyClick: this.onApply(this),
	        	    onSaveClick: this.onSave(this),
	        	    extraButtons: this.extraButtons,
			    margins: this.crop_margins,
	        	    allWidth: this.allWidth,
	        	    allHeight: this.allHeight,
	        	    monitorImage: false,
			    verticalCrop: ((adei.config.cfg.plot_mode == 0)?true:false),
	        	    imageReady: true
			});
			
			this.width = new_width;
    	    		this.height = new_height;
    		  } else {
		    this.crop = new Cropper.Img("graph_image", {
    			onEndCrop:  this.onEndCrop(this),
			onCancelCrop: this.onCancelCrop(this),
			onClick: this.onClick(this),
			onDblClick: this.onDblClick(this),
			onDblSelClick: this.onApply(this),
  			onMouseScroll: this.onMouseScroll(this),
  			onMouseMove: this.onMouseMove(this),
  			onApplyClick: this.onApply(this),
  			onSaveClick: this.onSave(this),
			extraButtons: this.extraButtons,
			margins: this.crop_margins,
			allWidth: this.allWidth,
			allHeight: this.allHeight,
			monitorImage: false,
			verticalCrop: ((adei.config.cfg.plot_mode == 0)?true:false),
			imageReady: true,
			tooltips: new Object({
			    'apply': translate('Zoom to the Selection'),
			    'save': translate('Export Selected Data') + tooltip
			})
		    });
		    this.width = new_width;
		    this.height = new_height;
		    this.plot_mode = adei.config.cfg.plot_mode;
		}
	      }
	      
	      if ((this.crop)&&(!this.crop.initialized)) {
	        this.crop = null;
	        setTimeout(this.ConfigureFunction(this), 1000);
	      }
		
	    }
}


GRAPH.prototype.Update = function(json, forced) {
    if (json.draw) {
	if (json.nodata) {
	    this.img.src = adei.GetServiceURL("getimage", "id=" + json.image);
	    
	    if (this.crop) {
		this.crop.remove();
		this.crop = null;
	    }
	    
		// indictating it is not valid window
	    this.xsize = 0;
	} else if (this.img) {
	    var doit = 1;

	    if (this.crop) {
		var crop = this.crop;
		if ((crop.selected)||(crop.dragging)||(crop.resizing)||(crop.calcW()>this.allWidth)||(crop.calcH()>this.allHeight)) {
		    if (forced) this.crop.clear();
		    else {
			adei.SetStatus(translate("Canceled because of selection"));
			doit = 0;
		    }
		}
	    }

	    if (doit) {
		adei.SetStatus(translate("Loading Image..."));
	        this.img.src = adei.GetServiceURL("getimage", "id=" + json.image);

		this.xmin = parseFloat(json.xmin);
		this.xmax = parseFloat(json.xmax);
		this.imin = parseFloat(json.imin);
		this.imax = parseFloat(json.imax);
		this.prec = parseFloat(json.precision);
		this.ymin = parseFloatArray(json.ymin);
		this.ymax = parseFloatArray(json.ymax);
		this.ylog = parseFloatArray(json.ylog);
		this.yzoom = parseIntArray(json.yzoom);
		this.axis = parseStringArray(json.axis);
		this.color = parseArray(json.color);
		this.name = parseArray(json.name);
		this.axis_size = parseFloat(json.axis_size);
		this.onload_message = json.warning;
		
		if (json.margins) {
		    this.SetMargins(json.margins[0], json.margins[1], json.margins[2], json.margins[3]);
		}
		
		this.xsize = parseFloat(adeiMathPreciseSubstract(this.xmax, this.xmin));
		
		this.ysize = new Object;
		for (var i = 0; i < this.axis.length; i++) {
	    	    this.ysize[i] = this.ymax[i] - this.ymin[i];
		}


		this.window.NewGraph(this, this.xmin, this.xmax);
	    }
	}
    }
}

GRAPH.prototype.SetExportTooltip = function () {
    var tooltip = this.exporter.GetTooltip();
    if ((this.crop)&&(tooltip)) {
	this.crop.setTooltip(new Object({'save': translate("Export Selected Data (%s)", tooltip)}));
    }
}

GRAPH.prototype.NotifyExportSettings = function() {
    this.SetExportTooltip();
}

//GRAPH.prototype.NotificationExport


GRAPH.prototype.AdjustGeometry = function(width, height, hend, vend) {
/*
    This was rougher approach
    domAdjustGeometry(this.frame, width, height - (this.selector?this.selector.offsetHeight:0), true);
*/
    
	// otherwise we are probably not on display
    if (this.frame.offsetParent) {
	var mboffset = domGetNodeOffset(this.frame);
	var w = hend - mboffset[0];
	var h = vend - mboffset[1] - 1;
	domAdjustGeometry(this.frame, w, h, true);
    }
}


GRAPH.prototype.GetPrecision = function(min) {
    var prec = this.prec;
    if (min > prec) prec = min;
    
    return (this.xsize * prec) / this.real_width;
}

/*
GRAPH.prototype.MouseSelection = function(xy) {
    alert(this.start_xy[0] - xy[0]);
}

GRAPH.prototype.MouseStart = function(evt) {
    var xy = domGetMouseOffset(evt);
    this.start_xy = xy;
    return false;
}

GRAPH.prototype.MouseDone = function(evt) {
    var xy = domGetMouseOffset(evt);
    this.MouseSelection(xy);
}

GRAPH.prototype.MouseVisualize = function(evt) {
    var xy = domGetMouseOffset(evt);
    return false;
}
*/