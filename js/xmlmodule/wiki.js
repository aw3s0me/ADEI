function WIKI(div) {
    WIKI.superclass.call(this, div);
    this.pageid = 1;
}

classExtend(WIKI, XMLMODULE);

WIKI.prototype.SetID = function(id) {
    this.div.innerHTML=translate("Loading...");
    adei.config.SetWikiSettings(id);
    adei.config.Save();
    adei.updater.Update();
}

WIKI.prototype.SetConfiguration = function(query) {
    adei.config.Load(query, true);
}
