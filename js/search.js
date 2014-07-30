function SEARCH(module, div) {
    if (typeof div == "object") this.div = div;
    else this.div = document.getElementById(div);
    if (!this.div) adei.ReportError(translate("Element \"%s\" is not present in current document model", div));

    this.module = module;

}
/*
WIKI.prototype.SetConfiguration = function(query) {
    adei.config.Load(query, true);
}
*/

SEARCH.prototype.StartSearch = function(text) {
    this.div.innerHTML = "Searching...";
//    adei.UpdateDIV(this.div, adei.GetSearchService(text), "search");
    adei.xslpool.Load("search", adei.GetSearchService(text), searchResults, this);
}

SEARCH.prototype.Results = function(xmldoc, error) {
    adei.OpenControl(this.module);

    if (xmldoc) {
        htmlReplace(xmldoc, this.div, true);
    } else {
	htmlReplace(translate("Search is failed due \"%s\"", error), this.div);
	//adeiReportError(translate("Search is failed due \"%s\"", error));
    }
}


function searchResults(xmldoc, search, error) {
    search.Results(xmldoc, error);
}
