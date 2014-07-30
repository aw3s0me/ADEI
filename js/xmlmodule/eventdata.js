function EVENTDATA(div, event_name, search_module) {
    EVENTDATA.superclass.call(this, div);

    this.module = null;
    this.event_name = event_name;
    this.search_module = search_module;

    adei.RegisterCropperButton({
	name: event_name,
	tooltip: translate('Search ' + event_name),
	object: this,
	callback: 'onSelect',
	css: 'imgCrop_button_' + event_name,
	vertical: true,
	keep_selection: true
    });

}

classExtend(EVENTDATA, XMLMODULE);


EVENTDATA.prototype.onSelect = function(from, to) {
    adei.Search('{' + this.search_module + '} interval:' + from + '-' + to);
}

EVENTDATA.prototype.SetAutoOpen = function(module) {
    this.module = module;
}

EVENTDATA.prototype.SetCustomProperties = function(query) {
    adei.SetCustomProperties(query+"&module="+this.module);
/*
    SetCustomProperty calls 'CONFIG.Load' which is async, therefore new
    configuration is not set than page is changed 
    adei.SetCustomProperties(query);
    if (this.module) adei.OpenModule(this.module);
*/
}
