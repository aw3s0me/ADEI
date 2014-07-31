<script type="text/javascript" src="../includes/prototype.js"></script>


<script type="text/javascript">
//<![CDATA[
    function httpGet(theUrl) {
        var xmlHttp = null;
        xmlHttp = new XMLHttpRequest();
        xmlHttp.open( "GET", theUrl, false );
        xmlHttp.send( null );
        return xmlHttp.responseText;
    }

    function httpPost(theUrl, paramsArr) {
        var http = new XMLHttpRequest();
        var params = JSON.stringify({
            param1: 'ololo',
            param2: 'ololo2'
        });
        //params = 'ololo=ololo';
        console.log(params);
        var url = theUrl;
        http.open("POST", url, true);

        //Send the proper header information along with the request
        http.setRequestHeader("Content-type", "application/json");
        //http.setRequestHeader("Content-length", params.length);
        //http.setRequestHeader("Connection", "close");

        http.onreadystatechange = function() {//Call a function when the state changes.
            if(http.readyState == 4 && http.status == 200) {
                alert(http.responseText);
            }
        }
        http.send(params);
    }

    function removeDownloadFromDom(download) {
        var download = document.getElementById('dlrow_' + download);
        var parent = download.parentNode;
        parent.removeChild(download);
    }

    function removeDownload(download) {
        var url = 'http://'+window.location.hostname+'/adei/services/download.php?target=dlmanager_remove&dl_id=' + download;
        //alert(url);
        httpGet(url);
        removeDownloadFromDom(download);
    }

    function removeDownloads(downloads) {
        var url = 'http://'+window.location.hostname+'/adei/services/download.php?target=dlmanager_multi_remove';
        httpPost(url, downloads);
    }

    function doDrop() {
        var checkedDlId = [];
        var checkedDomElems = [];
        var chkArr = document.getElementsByClassName('chk_downloads_drop');
        var arrLength = chkArr.length;
        for (var i = 0; i < arrLength; i++) {
            if (chkArr[i].checked) {
                var dl_id = chkArr[i].getAttribute('name');
                checkedDlId.push(dl_id);
                checkedDomElems.push(chkArr[i]);
            }
        }

        //removeDownloads(checkedDlId);

        for (var i = 0; i < checkedDomElems.length; i++) {
            removeDownload(checkedDomElems[i].getAttribute('name'));
            //removeDownloadFromDom(checkedDomElems[i].getAttribute('name'));
            
        }
        
    }

    function markShared() {
        var chkArr = document.getElementsByClassName('chk_downloads_shared');
        for (var i = 0; i < chkArr.length; i++) {
            chkArr[i].checked = true;   
        }
    }



//]]>
</script>


<div class="downloads_panel">
    <input type="submit" value="<?echo translate("Drop Selected");?> " onClick="javascript:doDrop()"/>
    <input type="submit" value="<?echo translate("Mark Shared");?> " onClick="javascript:markShared()"/>
</div>

<?php 

    global $ADEI;
    $ADEI->RequireClass("download");
    $ADEI->RequireClass("common");
    $dm = new DOWNLOADMANAGER(); 

    //if (isset($_GET['downloads_list'])) {
    try{
        ///$response = file_get_contents('services/download.php?target=dlmanager_list');
        $path = 'http://' . $_SERVER['HTTP_HOST'].'/adei/services/download.php?target=dlmanager_list&isadmin=true';
        $download_xml_list = file_get_contents($path);
        $path_to_xslt = 'http://' . $_SERVER['HTTP_HOST'].'/adei/xslt/download.xsl';
        $download_xslt = file_get_contents($path_to_xslt);
        if (!$download_xml_list){
            throw new ADEIException(translate("Error with download xml list. Target: $target \n Error: $ex")); 
        }
        if (!$download_xslt){
            throw new ADEIException(translate("Error with download xslt list. Target: $target \n Error: $ex")); 
        }

        //echo $download_xslt;
        //echo '<h1>'.$ADEI_SETUP.'</h1>';
        echo "<div class='adm_downloads' >";
        echo $ADEI->TransformXML($download_xslt, $download_xml_list, true);
        echo "</div>";

?>

<script type="text/javascript">
    var chkAll = document.getElementById('mark_all');
    chkAll.onclick = function(e) {
        if (this.checked) {
            var chkArr = document.getElementsByClassName('chk_downloads_drop');
            for (var i = 0; i < chkArr.length; i++) {
                chkArr[i].checked = true;
            }
        }
        else {
            var chkArr = document.getElementsByClassName('chk_downloads_drop');
            for (var i = 0; i < chkArr.length; i++) {
                chkArr[i].checked = false;
            }
        }
    }

</script>


<?php
    }
    catch(Exception $e) {
        echo $e;
    }
    //try { 
       //$dlmanager = new DLMANAGER();
    //}
    //catch(ADEIException $ex) {  
       // throw new ADEIException(translate("Error with download service. Target: $target \n Error: $ex"));   
    //}

    




?> 
