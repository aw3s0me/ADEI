function WINDOW_CONFIG() {
    this.width = 0;
/*    
    this.start = 0;
    this.end = 0;
    this.miny = 0;
    this.maxy = 0;
*/
}


function WINDOW(win_sel_id, start_id, end_id, axes_id, ymin_id, ymax_id, log_id) {
    this.cfg = new WINDOW_CONFIG;
    this.props =  null;
    this.source_winsel = new SELECT(win_sel_id,  windowUpdateWidth, this);

    this.source_winsel.SetupSource(adei.GetSelectService("window_modes"), "window_width");

    this.sin = document.getElementById(start_id);
    if (!this.sin) alert("Custom window start time input field (\"" + start_id + "\") is not found");
    this.ein = document.getElementById(end_id);
    if (!this.ein) alert("Custom window end time input field (\"" + end_id + "\") is not found");

    this.minin = document.getElementById(ymin_id);
    if (!this.minin) alert("Y-axis min input field (\"" + ymin_id + "\") is not found");
    this.maxin = document.getElementById(ymax_id);
    if (!this.maxin) alert("Y-axis max input field (\"" + ymax_id + "\") is not found");
    this.login = document.getElementById(log_id);
    if (!this.login) alert("Y-axis log-scale checkbox (\"" + ymax_id + "\") is not found");

    this.axestbl = document.getElementById(axes_id);
    this.axes = {
	'0': {
	    name: 'default',
	    minin: this.minin,
	    maxin: this.maxin,
	    login: this.login,
	    def_log_scale: false
	}
    };

    var td = this.axestbl.getElementsByTagName("td");
    for (var i = 0; i < td.length; i++ ) {
	if (td[i].firstChild) cssSetClass(td[i].firstChild, "window_axis_0");
    }

}


WINDOW.prototype.NewGraph = function(graph, xmin, xmax) {
    var css_regex = /[^\w\d_]/;
    if (graph.xsize > this.minval) {
	this.sub_edge_mode = false;
    }

    for (var aid in this.axes) {
	var css_class = ("window_axis_" + aid).replace(css_regex,"_");
	cssHideClass("." + css_class)
    }

    for (var i = 0; i < graph.axis.length; i++) {
	var aid = graph.axis[i];
	var css_class = ("window_axis_" + aid).replace(css_regex,"_");

	if (typeof this.axes[aid] == "undefined") {
	    var td = [
		"<div class=\"" + css_class + "\">" + 
		    "<span class=\"axis_name\">" + graph.name[i] + "</span>&nbsp;" +
		    "[<a href='javascript:source_window.ResetY(\"" + aid + "\")'>" + translate("R") + "</a>]" +
		"</div>",
		"<div class=\"" + css_class + "\"><span class=\"source_yrange\">" +
		    '<input type="text" maxlength="16" onchange="javascript:source_window.UpdateRange()"/>' +
		"</span></div>",
		"<div class=\"" + css_class + "\"><span class=\"source_yrange\">" +
		    '&nbsp;-&nbsp;' +
		"</span></div>",
		"<div class=\"" + css_class + "\"><span class=\"source_yrange\">" +
		    '<input type="text" maxlength="16" onchange="javascript:source_window.UpdateRange()"/>' +
		"</span></div>",
		"<div class=\"" + css_class + "\"><span class=\"source_ymode\">" +
		    '<input type="checkbox" onchange="javascript:source_window.UpdateRange()"/>' +
		"</span></div>",
		"<div class=\"" + css_class + "\">Log</div>"
	    ];
	    var row = tableAddRow(this.axestbl, td);
	    var inputs = row.getElementsByTagName("input");
	    
            var log_scale = graph.ylog[i]?true:false;

                /* DS: We are trying to detect if the axis is logarithmic by default.
                Having this information will allow us to prevent populating unneeded
                GET parameters. The idea is following. When first seen, axis is expected
                to be in the default mode (else clause) unless there were already 
                parameters in the GET line. In the last case, it will be returned
                by GetAxisMode. 
                This (!axis_mode[0]) will BREAK normal operation of the links if the 
                default mode of some axis is changed. Due to assumption that presense 
                of a parameter indicates that it is currently in non-default mode.
                Use indefinite default mode for now...
                */
	    var def_log_scale;
	    var axis_mode = this.config.GetAxisMode(aid);
	    if (axis_mode) def_log_scale = null;//!axis_mode[0];
	    else def_log_scale = log_scale;

	    this.axes[aid] = {
		minin: inputs[0],
		maxin: inputs[1],
		login: inputs[2],
	        name: graph.name[i],
	        def_log_scale: def_log_scale
            };

	    this.SetCustomAxis(aid);
	    this.axes[aid].login.checked = log_scale;
	    if (def_log_scale == log_scale) {
	        this.config.SetAxisMode(aid);
	    } else {
	        this.config.SetAxisMode(aid, log_scale);
	    }
	}

	cssShowClass("." + css_class);
	cssSetProperty("." + css_class, 'color', graph.color[i]);
    }

    if (!this.axes_on_display) this.fix_hidden_axes = true;

}


WINDOW.prototype.DisableControls = function() {
    this.source_winsel.Disable();
}

WINDOW.prototype.EnableControls = function() {
    this.source_winsel.Enable();
}


WINDOW.prototype.SetConfig = function(config) {
    this.config = config;
}

WINDOW.prototype.SetUpdater = function(updater) {
    this.updater = updater;
}


WINDOW.prototype.RegisterGraph = function(graph) {
    // DS. Provide support for multiple attached graphs
    if (!this.graph) {
	this.graph = graph;
	if (!graph.window) graph.AttachWindow(this);
    }
}

WINDOW.prototype.FixOptions = function(opts) {
    if ((!opts.window_width)&&(this.cfg.width>0)) {
	opts.window_width = this.cfg.width;
    }
}

WINDOW.prototype.Update = function(props, opts) {
    if (opts) {
	if (opts.reload) {
	    this.minval = false;
	    this.source_winsel.Update(props, opts, opts?opts.window_width:null);
	} else if ((opts.window_width)||(opts.reset)) {
	    this.UpdateWidth(opts.window_width, opts);
	}
    }
}

WINDOW.prototype.UpdateWidth = function (value, opts) {
    if (typeof value == "undefined") {
	value = this.source_winsel.GetValue();
    }

    if ((opts)&&(opts.confirmed)) {
        if (!this.minval) {
	    // Setting minimal standard window
	    var idx = this.source_winsel.GetLastIndex();
	    this.minval = this.source_winsel.GetValue(idx - 1);
	}
    
        var reset_custom = true;
    
	if ((opts)&&(opts.window == "auto")) {
	    this.SetCustomWindow();
	    this.SetCustomAxes();
	    this.SetAxesMode();
	    reset_custom = false;
	}
    
        var css;
	var show = false;
    
    
	if (value < 0) {
	    if (this.cfg.width >= 0) {
		if ((reset_custom)&&((!opts)||(!opts.keep_custom))) {
		    this.sin.value = '';
		    this.ein.value = '';
/*
		    this.minin.value = '';
		    this.maxin.value = '';
*/
		}
	    
		cssShowClass('.hide_window_custom');
		
		if (!this.module_on_display) this.fix_hidden = true;


		if (typeof this.geometry_cb == "function") {
		    this.geometry_cb(this.geometry_cbattr);
		}
	    }
	} else {
	    if (this.cfg.width < 0) {
		cssHideClass('.hide_window_custom');

		if (!this.module_on_display) this.fix_hidden = true;
	    }
	    
	
	    if (this.cfg.width != value) show = true;
	}

	this.cfg.width = value;

	if ((show)&&(opts.apply)) this.Apply();
    } else if (((opts)&&(opts.reload))||(this.cfg.width != value)||(opts.window)) {
	if (typeof opts == "undefined") opts = { apply: true };
	this.source_winsel.UpdateChilds(null, opts, value, this.cfg.width);
    }
}

WINDOW.prototype.UpdateRange = function() {
    if (this.graph) {
	this.graph.Clear();
    }
}

WINDOW.prototype.ApplyConfig = function(subcall) {
    if (this.config) {
	var sin, ein;
	var min, max;
	
	var width = this.cfg.width;
	
	if (width<0) {
	    var tmp = this.sin.value;
	    if (tmp) sin = adeiDateParse(tmp);
	    else sin = "";
	    
	    tmp = this.ein.value;
	    if (tmp) ein = adeiDateParse(tmp);
	    else ein = "";
/*
	    tmp = this.minin.value;
	    if (tmp) minin = tmp;
	    else minin = 0;
	    
	    tmp = this.maxin.value;
	    if (tmp) maxin = tmp;
	    else maxin = 0;
*/
	} else {
	    sin = ""; ein = "";
/*
	    minin = 0; maxin = 0;
*/
	}
	
	this.config.SetWindow(width, sin, ein, "", "");
	
	
	for (var i in this.axes) {
	    var tmp = this.axes[i].minin.value;
	    if (tmp) minin = tmp;
	    else minin = 0;

	    tmp = this.axes[i].maxin.value;
	    if (tmp) maxin = tmp;
	    else maxin = 0;

	    var log_scale = this.axes[i].login.checked;

	    this.config.SetAxisRange(i, minin, maxin);

	    if (log_scale == this.axes[i].def_log_scale) {
	        this.config.SetAxisMode(i);
	    } else {
	        this.config.SetAxisMode(i, log_scale);
	    }
	}

	if (typeof subcall == "undefined") {
//	    alert('save');
	    this.config.Save();
	}
    }

    if (this.graph) this.graph.Clear();
}

WINDOW.prototype.ReadConfig = function(opts) {
    opts.window = "auto";
    opts.window_width = this.config.win_width;
}

WINDOW.prototype.Apply = function() {
    this.ApplyConfig();
    if (this.updater) this.updater.Update();
}

WINDOW.prototype.ResetXY = function(aid) {
    if (typeof aid == "undefined") {
	this.SetCustomAxes(0,0);
    } else {
	this.SetCustomAxis(aid,0,0);
    }

    if (this.source_winsel.GetValue() == 0) {
	this.Apply();
    } else {
	this.source_winsel.SetValue(0);
	this.UpdateWidth(0);
    }
}

WINDOW.prototype.ResetX = function() {
	// We are reseting Y as well!
    this.source_winsel.SetValue(0);
    this.UpdateWidth(0);
}

WINDOW.prototype.ResetY = function(aid) {
    this.config.win_min = 0;
    this.config.win_max = 0;

    if (typeof aid == "undefined") {
	this.SetCustomAxes(0,0);
    } else {
	this.SetCustomAxis(aid,0,0);
    }
    this.Apply();
}


WINDOW.prototype.SetAxesMode = function() {
    for (aid in this.axes) {
        var cfgval = this.config.GetAxisMode(aid);
        if (cfgval) {
            this.axes[aid].login.checked = cfgval[0];
        } else {
                // default
            this.axes[aid].login.checked = false;
        }
    }
}

WINDOW.prototype.SetCustomAxes = function (miny, maxy) {
    if (typeof miny == "undefined") {
	for (aid in this.axes) {
    	    this.SetCustomAxis(aid);
	}
    } else {
	for (aid in this.axes) {
    	    this.SetCustomAxis(aid, miny, maxy);
	}
    }
}

WINDOW.prototype.SetCustomAxis = function (aid, miny, maxy) {
    if (typeof miny != "undefined") {
	if (miny == maxy) {
	    this.axes[aid].minin.value = '';
    	    this.axes[aid].maxin.value = '';
	} else {
    	    this.axes[aid].minin.value = isNaN(miny)?miny:miny.toPrecision(7);
    	    this.axes[aid].maxin.value = isNaN(maxy)?maxy:maxy.toPrecision(7);
	}
    } else {
	var cfgval;
	cfgval = this.config.GetAxisRange(aid);

	if (cfgval) {	
	    this.axes[aid].minin.value = cfgval[0];
    	    this.axes[aid].maxin.value = cfgval[1];
	} else {
	    this.axes[aid].minin.value = '';
    	    this.axes[aid].maxin.value = '';
	}
    }
}

WINDOW.prototype.SetCustomWindow = function (from, to) {
    if (typeof from != "undefined") {
	this.config.SetSelection(from, to);
	    
	from += ""; to += "";	// converting to string
	
	if (from&&to) {
	    var range = adeiMathPreciseSubstract(to, from);
	    this.sin.value = adeiDateFormat(from, range);
	    this.ein.value = adeiDateFormat(to, range);
	} else {
	    if (from) this.sin.value = adeiDateFormat(from);
	    else this.sin.value = from;
	    if (to) this.ein.value = adeiDateFormat(to);
	    else this.ein.value = to;
	}

	this.UpdateWidth(-1, new Object({keep_custom: true}));
	this.source_winsel.SetValue(-1);
    } else {
	if (!this.config) return;
	
	this.config.SetSelection();

	if (this.config.win_width<0) {
	    if ((this.config.win_from&&this.config.win_to)) {
		var range = adeiMathPreciseSubstract(this.config.win_to, this.config.win_from);
		
		this.sin.value = adeiDateFormat(this.config.win_from, range);
		this.ein.value = adeiDateFormat(this.config.win_to, range);
	    } else {
		if (this.config.win_from) this.sin.value = adeiDateFormat(this.config.win_from);
		else this.sin.value = "";
		if (this.config.win_to) this.ein.value = adeiDateFormat(this.config.win_to);
		else this.ein.value = "";

/*
		this.sin.value = adeiDateFormat(this.graph.xmin, this.graph.xmax - this.graph.xmin);
		this.ein.value = adeiDateFormat(this.graph.xmax, this.graph.xmax - this.graph.xmin);
*/
	    }
	} else {
	    this.sin.value = '';
	    this.ein.value = '';
	    this.UpdateWidth(this.config.win_width, new Object({keep_custom: true}));
	    this.source_winsel.SetValue(this.config.win_width);
	}
    }
}
/**PinchZoom 
 * Zooming x and y -axis when using Safari webkit browser on a touchdevice. So far twofinger gestures are only possible with Apples iPhone, iPad, iPod. Android devices don´t support that feature yet.
 */
WINDOW.prototype.PinchZoom = function(values) {
  var iphone = isiPhone();
  var ipad = isiPad();

  if(iphone)
  {
    var scale_min = 0.34;
    var scale_max = 3.5;
  }
  if(ipad){
     var scale_min = 0.26;
     var scale_max = 10;
  }
  
  if(values.device) {

    if(values.scale > scale_max){ values.scale = scale_max;}
 
    if(values.startX > values.marginsLeft) {  

         if(values.scale > 1){
            var xmin = values.x;
            var xmax = adeiMathPreciseAdd(xmin,(this.graph.xmax-xmin)/scale_max*values.scale);
            this.SetCustomWindow(xmin, xmax);
          }
          if(values.scale < 1){
            this.CenterZoomOut();
          }
      }
      else
      {
        if(values.scale < 1)
        {
          for(var i = 0; i < values.y.length; i++){
            this.YZoomOut(i, values.y[i]);
          }
        }
        else if ( values.scale > 1){
          for(var i = 0; i < values.y.length; i++){
            this.YZoomIn(i, values.y[i]);
          }
        }


      values = null;
      
    }
  }
  else{alert("Unknown touchdevice");}
}

/**Swipe
 * Moves graph axis a step to swiped direction
 */
WINDOW.prototype.Swipe = function(direction){
   switch(direction){
     case 1: //Swipeleft
      this.MoveRight();
      break;
     case 2: //Swiperight
      this.MoveLeft();
      break;
     case 3: //Swipeup
      this.MoveDown();
      break;
     case 4: //Swipedown
      this.MoveUp();
      break;
   
   }
}

WINDOW.prototype.Lock = function() {
    if ((this.graph)&&(this.cfg.width >= 0)) {
	var xmin = this.graph.xmin;
	var xmax = this.graph.xmax;
	if ((xmin)&&(xmax)) {
	    this.SetCustomWindow(xmin, xmax);
	    this.Apply();
	}
    }
}


WINDOW.prototype.MoveUp = function(ai) {
    if (typeof ai == "undefined") {
	for (var i = 0; i < this.graph.axis.length; i++) {
	    this.MoveUp(i);
	}
	return;
    }    

    if (this.graph.yzoom[ai]) {
	var step_size = this.graph.ysize[ai] / adei.cfg.step_ratio;
	var min = this.graph.ymin[ai] + step_size;
	var max = this.graph.ymax[ai] + step_size;

	var aid = this.graph.axis[ai];
	this.SetCustomAxis(aid, min, max);
    }
    this.Apply();    
}

WINDOW.prototype.MoveDown = function(ai) {
    if (typeof ai == "undefined") {
	for (var i = 0; i < this.graph.axis.length; i++) {
	    this.MoveDown(i);
	}
	return;
    }    

    if (this.graph.yzoom[ai]) {
	var step_size = this.graph.ysize[ai] / adei.cfg.step_ratio;
	var min = this.graph.ymin[ai] - step_size;
	var max = this.graph.ymax[ai] - step_size;

	var aid = this.graph.axis[ai];
	this.SetCustomAxis(aid, min, max);
    }
    this.Apply();    
}


WINDOW.prototype.MoveLeft = function() {
    if (this.graph.xmin > this.graph.imin) {
	var step_size = this.graph.xsize /  adei.cfg.step_ratio;
	var from = adeiMathPreciseSubstract(this.graph.xmin, step_size);

	if (from < this.graph.imin) {
	    step_size = adeiMathPreciseSubstract(this.graph.xmin, this.graph.imin);
	    from = this.graph.imin;
	}

	var to = adeiMathPreciseSubstract(this.graph.xmax, step_size);

	this.SetCustomWindow(from, to);
    }
    
    this.Apply();    
}

WINDOW.prototype.MoveRight = function() {
    if (this.graph.xmax < this.graph.imax) {
	var step_size = this.graph.xsize /  adei.cfg.step_ratio;
	var to = adeiMathPreciseAdd(this.graph.xmax, step_size);

	if ((this.graph.imax)&&(to > this.graph.imax)) {
	    step_size = adeiMathPreciseSubstract(this.graph.imax, this.graph.xmax);
	    to = this.graph.imax;
	}

	var from = adeiMathPreciseAdd(this.graph.xmin, step_size);

	this.SetCustomWindow(from, to);
    }

    this.Apply();    
}


WINDOW.prototype.Center = function(x, y) {
    for (var i = 0; i < y.length; i++) {
	if (this.graph.yzoom[i]) {
	    var size = this.graph.ysize[i] / 2;
	    var min = y[i] - size;
	    var max = y[i] + size;
	    var aid = this.graph.axis[i];
	    this.SetCustomAxis(aid, min, max);
	}
    }

    var size = this.graph.xsize / 2;
    from = adeiMathPreciseSubstract(x, size);
    to = adeiMathPreciseAdd(x, size);
    
    if (from < this.graph.imin) {
	from = this.graph.imin;
	to = adeiMathPreciseAdd(x, adeiMathPreciseSubstract(x, this.graph.imin));
    } else if ((this.graph.imax)&&(to > this.graph.imax)) {
	from = adeiMathPreciseSubstract(x, adeiMathPreciseSubstract(this.graph.imax, x));
	to = this.graph.imax;
    }

    this.SetCustomWindow(from, to);
    this.Apply();    

}

WINDOW.prototype.IncreaseWidth = function() {
    var width = this.config.win_width;
    if (width < 0) {
	this.source_winsel.SetValue(0);
    } else {
	this.source_winsel.Prev(false);

/*	
	Updating All is not so bad idea 
	if (this.source_winsel.GetValue() == width) return;
*/
    }

//    this.cfg.width = this.source_winsel.GetValue();
    this.UpdateWidth(this.source_winsel.GetValue());
}

WINDOW.prototype.DecreaseWidth = function() {
    var width = this.config.win_width;
    if (width < 0) {
	var idx = this.source_winsel.GetLastIndex();
	if (idx > 0) this.source_winsel.SetIndex(idx - 1);
    } else {
	this.source_winsel.Next(false, function (idx, value) {
	    if (parseInt(value) < 0) return true;
	    return false;
	});

/*	
	Updating is not so bad idea
	    // Already minimum
	if (this.source_winsel.GetValue() == width) return;
*/

    }

//    this.cfg.width = this.source_winsel.GetValue();
    this.UpdateWidth(this.source_winsel.GetValue());
}

WINDOW.prototype.YZoomIn = function(ai, y) {
    
    var new_size = this.graph.ysize[ai] / adei.cfg.zoom_ratio;

    if (typeof y == "undefined") {
	var diff = (this.graph.ysize[ai] - new_size) / 2;

	var min = this.graph.ymin[ai] + diff;
	var max = this.graph.ymax[ai] - diff;
    } else {
	var min = y - new_size/2;
	var max = y + new_size/2;
    }

    var aid = this.graph.axis[ai];
    this.SetCustomAxis(aid, min, max);

    this.Apply();    
}

WINDOW.prototype.YZoomOut = function(ai, y) {
    if (typeof y == "undefined") {
	var diff = (adei.cfg.zoom_ratio - 1) * this.graph.ysize[ai] / 2;

	var min = this.graph.ymin[ai] - diff;
	var max = this.graph.ymax[ai] + diff;
    } else {
	var new_size = this.graph.ysize[ai] * adei.cfg.zoom_ratio / 2;
	
	var min = y - new_size;
	var max = y + new_size;
    }

    var aid = this.graph.axis[ai];
    this.SetCustomAxis(aid, min, max);

    this.Apply();    
}

WINDOW.prototype.CenterZoomIn = function() {
    var new_size = this.graph.xsize /  adei.cfg.zoom_ratio;
    var diff = adeiMathPreciseSubstract(this.graph.xsize, new_size) / 2;
    
    var from = adeiMathPreciseAdd(this.graph.xmin, diff);
    var to = adeiMathPreciseSubstract(this.graph.xmax, diff);
    
    this.SetCustomWindow(from, to);
    this.Apply();    
}

WINDOW.prototype.CenterZoomOut = function() {
    if ((this.graph.xmin)&&(this.graph.xmax)) {
	var diff = (adei.cfg.zoom_ratio - 1) * this.graph.xsize / 2;

	var from = adeiMathPreciseSubstract(this.graph.xmin, diff);
	var to = adeiMathPreciseAdd(this.graph.xmax, diff);
    } else {
	var xmin = this.config.win_from;
	var xmax = this.config.win_to;
	var xsize = adeiMathPreciseSubstract(xmax, xmin);

	var diff = (adei.cfg.zoom_ratio - 1) * xsize / 2;
	var from = adeiMathPreciseSubstract(xmin, diff);
	var to = adeiMathPreciseAdd(xmax, diff);
    }
    
    this.SetCustomWindow(from, to);
    this.Apply();    
}

WINDOW.prototype.DeepZoom = function(x) {
    var prec = this.graph.GetPrecision( adei.cfg.deepzoom_area);
    var from = adeiMathPreciseSubstract(x, prec);
    var to = adeiMathPreciseAdd(x, prec);
    
    this.SetCustomWindow(from, to);
    this.Apply();    
}

WINDOW.prototype.LocalZoomIn = function(x) {
    var new_size = this.graph.xsize /  (2 * adei.cfg.zoom_ratio);
    var from = adeiMathPreciseSubstract(x, new_size);
    var to = adeiMathPreciseAdd(x, new_size);
    
    this.SetCustomWindow(from, to);
    this.Apply();    
}

WINDOW.prototype.LocalZoomOut = function(x) {
    var diff = adei.cfg.zoom_ratio  * this.graph.xsize / 2;

    var from = adeiMathPreciseSubstract(x, diff);
    var to = adeiMathPreciseAdd(x, diff);
    
    this.SetCustomWindow(from, to);
    this.Apply();    
}


WINDOW.prototype.ZoomIn = function(x) {
    var width = this.config.win_width;
    if (width > 0) {
	var idx = this.source_winsel.GetLastIndex();
	if (this.source_winsel.GetIndex() == (idx - 1)) {
		// Minimal zoom is reached
	    this.LocalZoomIn(this.graph.xmax);
	    this.sub_edge_mode = true;
	} else {
    	    this.DecreaseWidth();
	}
	return;
    } else if (width == 0) {
	if (((this.graph.xmax - x)<=0)||((this.graph.xsize / (this.graph.xmax - x)) > adei.cfg.edge_ratio)) {
	    this.DecreaseWidth();
	    return;
	}
    }
    this.LocalZoomIn(x);    
}

WINDOW.prototype.ZoomOut = function(x) {
    var width = this.config.win_width;

    if (!width) return;

    if (width > 0) {
	this.IncreaseWidth();
	return;
    } else if (width < 0) {
	if ((this.sub_edge_mode)&&(((this.graph.xmax - x)<=0)||((this.graph.xsize / (this.graph.xmax - x)) > adei.cfg.edge_ratio))) {
	    if (this.graph.xsize < this.minval) {
		var new_size = adei.cfg.zoom_ratio  * this.graph.xsize;
		if (new_size > this.minval) {
			// Setting minmal width
		    this.DecreaseWidth();
		    return;
		}
	    }
	}

	var new_size = adei.cfg.zoom_ratio  * this.graph.xsize;
	if (new_size > Math.ceil(this.graph.imax - this.graph.imin)) {
	    this.source_winsel.SetValue(0);
	    this.UpdateWidth(0);
	
	    return ;
	}
    }

    
    this.CenterZoomOut();

/*
    Double operation is a bed idea, moved up

    if ((!this.graph.yzoom)&&(this.graph.xmin <= this.graph.imin)&&(this.graph.xmax >= this.graph.imax)) {
	this.source_winsel.SetValue(0);
	this.UpdateWidth(0);
	this.Apply();
    }
*/
}


WINDOW.prototype.RegisterGeometryCallback = function(cb, cbattr) {
    this.geometry_cb = cb;
    this.geometry_cbattr = cbattr;
}

WINDOW.prototype.FixHiddenAxes = function() {
    if ((this.fix_hidden_axes)&&(this.axes_on_display)) {
	this.fix_hidden_axes = false;

        for (var i in this.axes) {
	    cssHideClass(".window_axis_" + i);
	}

	for (var i = 0; i < this.graph.axis.length; i++) {
	    var aid = this.graph.axis[i];
	    var css_class = ".window_axis_" + aid;
	    cssShowClass(css_class);
	}
    }
}

WINDOW.prototype.FixHidden = function() {
    if ((this.fix_hidden)&&(this.module_on_display)) {
	if (this.cfg.width<0) {
	    cssHideClass('.hide_window_custom');
	    cssShowClass('.hide_window_custom');
	} else {
	    cssShowClass('.hide_window_custom');
	    cssHideClass('.hide_window_custom');
	}
	this.fix_hidden = false;
    }

    this.FixHiddenAxes();

}

function windowUpdateWidth(window, value, opts) {
    window.UpdateWidth(value, opts);
}

function windowUpdateRange(window) {
    window.UpdateRange();
}

function windowUpdateRangeFunction(window) { 
    return function() { window.UpdateRange(); } 
}
