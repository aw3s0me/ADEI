<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="html" encoding="utf-8"/>  
    <xsl:template match="result">
  <!-- <xsl:param name="islogged"/> -->
        <div>
            <div><h1>
                <xsl:choose>
                    <xsl:when test="@isadmin = 'false'">
                        Download Manager
                    </xsl:when>
                    <xsl:otherwise>
                        Downloads
                    </xsl:otherwise>
                </xsl:choose>
            </h1></div>

            <xsl:choose>
                <xsl:when test="@isadmin = 'false'">
                    <xsl:choose>
                        <xsl:when test="@islogged = 'true'">
                            <xsl:call-template name="my_downloads"/>
                            <xsl:call-template name="shared_downloads"/>
                            <xsl:call-template name="myip_downloads"/>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:call-template name="myip_downloads"/>
                            <xsl:call-template name="shared_downloads"/>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:call-template name="all_downloads" />
                </xsl:otherwise>

            </xsl:choose>
            
            
            <xsl:if test="@isadmin = 'false'">
                <script type="text/javascript">      
                    dlmanagerstart();
                    showText = function(id, isShared){
                        if (isShared) {
                            document.getElementById(id).innerHTML="Is shared";
                        }
                        else 
                            document.getElementById(id).innerHTML="Auto delete";
                      //document.getElementById(id).innerHTML="Auto delete";
                    }
                    clearText = function(id){
                      document.getElementById(id).innerHTML="";
                    }   
                </script>
            </xsl:if>
        </div>
    </xsl:template> 


    <xsl:template name="downloads_header">
        <tr>          
            <th width="18%">User</th>
            <th>Data</th>
            <th width="20%">Added</th>      
            <th width="25%">Progress</th>
            <th width="100px">Tools
                <xsl:if test="@isadmin = 'true'">
                    <input id="mark_all" type="checkbox"></input>
                </xsl:if>

            </th>          
        </tr>
    </xsl:template>

  <!-- Downloads by USER -->

    <xsl:template name="shared_downloads">
        <div style="text-align:center"><h4>Shared Downloads</h4></div>
        <table>
            <xsl:call-template name="downloads_header"/>
            <xsl:for-each select="download"> 
                <xsl:if test="@is_shared = 'true' and @owner = 'false'">
                    <xsl:call-template name="download_content" />
                </xsl:if>  
            </xsl:for-each>     
        </table> 
    </xsl:template>

    <xsl:template name="all_downloads">
        <table>
            <xsl:call-template name="downloads_header"/>
            <xsl:for-each select="download"> 
                <xsl:call-template name="download_content" />
            </xsl:for-each>     
        </table> 
    </xsl:template>

    <xsl:template name="myip_downloads">
        <div style="text-align:center"><h4>Downloads from my IP</h4></div>
        <table>
            <xsl:call-template name="downloads_header"/>
            <xsl:for-each select="download">
                <xsl:if test="@is_user != 'true' and @is_shared = 'false' and @owner = 'true'">
                    <xsl:call-template name="download_content"/>
                </xsl:if>  
            </xsl:for-each>   
        </table> 
    </xsl:template>

    <xsl:template name="my_downloads">
        <div style="text-align:center"><h4>My Downloads</h4></div>
        <table>
            <xsl:call-template name="downloads_header"/>
        <xsl:for-each select="download">
            <xsl:if test="@is_user != 'false' and @owner = 'true'">
                <xsl:call-template name="download_content">
                    <xsl:with-param name="can_be_shared" select="1"/>
                </xsl:call-template>
            </xsl:if>  
        </xsl:for-each> 
        </table>  
    </xsl:template>

    <xsl:template name="diffip_downloads">
        <div style="text-align:center"><h4>Downloads from different IP</h4></div>
        <table>
            <xsl:call-template name="downloads_header"/>
        <xsl:for-each select="download">
            <xsl:if test="@is_user != 'true' ">
                <xsl:call-template name="download_content"/>
            </xsl:if>  
        </xsl:for-each>      
        </table> 
    </xsl:template>

    <!-- CONTENT -->

    <xsl:template name="buttoncontainer">
        <div class="buttoncontainer" borderwidth="0">
          <table cellspacing="0" cellpadding="0"><tr>
        <td><div class="previewimg btnimg" title="Show graph" style="cursor:pointer;">
          <xsl:attribute name="onclick">javascrip:tooltip.Show(event,'<xsl:value-of select="@dl_id"/>')</xsl:attribute>
        </div></td>
        <td><div class="infoimg btnimg" title="Show details" style="cursor:pointer" >
          <xsl:attribute name="onclick">javascript:tooltipdet.Show(event,'<xsl:value-of select="@dl_id"/>', 'true')</xsl:attribute>
        </div></td>
        <td><div class="downloadimg btnimg" title="Download file" style="cursor:pointer">
          <xsl:if test="@status='Ready'">
            <xsl:attribute name="onclick">javascript:data_export.StartDownload('<xsl:value-of select="@dl_id"/>','<xsl:value-of select="@format"/>','<xsl:value-of select="@dl_name"/>','<xsl:value-of select="@ctype"/>')</xsl:attribute>
          </xsl:if>
        </div></td>

        <td><div class="deleteimg btnimg" title="Delete download" style="cursor:pointer">

          <xsl:attribute name="onclick">
                    javascript:dlmanager.RemoveDownload('<xsl:value-of select="@dl_id"/>')
            </xsl:attribute>  
        </div></td>
          </tr>
          <xsl:if test="../@islogged='true' and @is_user != 'false' and @owner = 'true'">
              <tr>
                <td colspan="1"> 
                    <input type="checkbox">
                        <xsl:attribute name="name">is_shared_cb<xsl:value-of select="@dl_id"/></xsl:attribute>
                        <xsl:if test="@is_shared='true'">
                            <xsl:attribute name="checked"></xsl:attribute>
                        </xsl:if>       
                        <xsl:attribute name="onclick">javascript:dlmanager.SetShared('<xsl:value-of select="@dl_id"/>')</xsl:attribute>
                    </input>
                </td>
                <td class="bg_cell" colspan="3">
                    Shared     
                </td>
                 

              </tr>
          </xsl:if>


          <tr>
            <td colspan="1">
                <input type="checkbox">
                    <xsl:attribute name="name">auto_delete_cb<xsl:value-of select="@dl_id"/></xsl:attribute>
                    <xsl:if test="@auto_delete='true'">
                    <xsl:attribute name="checked"></xsl:attribute>
                    </xsl:if>       
                    <xsl:attribute name="onclick">javascript:dlmanager.ToggleAutodelete('<xsl:value-of select="@dl_id"/>')</xsl:attribute>
                </input>
            </td>
            <td class="bg_cell" colspan="3">
                Auto delete     
            </td>
            


          </tr>


      </table>       
        </div>
    </xsl:template>

    <xsl:template name="admincontrols">
        <div class="controls">
            <table>
                <tbody>
                    <tr>
                        <td>
                            <div style="margin-top:3px;">
                                Drop single
                            </div>
                        </td>
                        <td>
                            <div class="deleteimg" title="Delete download" style="cursor:pointer">
                                <xsl:attribute name="onclick">
                                    javascript:removeDownload('<xsl:value-of select="@dl_id"/>')
                                </xsl:attribute>  
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <span style="/*float:left;*/ text-align:left;">Mark to drop</span>
                            <input type="checkbox">
                                <xsl:attribute name="name"><xsl:value-of select="@dl_id"/></xsl:attribute>  
                                <xsl:attribute name="class">chk_downloads_drop</xsl:attribute>  
                                <xsl:if test="@is_shared = 'true'">
                                    <xsl:attribute name="class">chk_downloads_drop chk_downloads_shared</xsl:attribute>  
                                </xsl:if>
                            </input>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </xsl:template>


  <xsl:template name="download_content">
    <!-- <tr id=<xsl:value-of select="@dl_id"> > -->
    <xsl:element name="tr">
        <xsl:attribute name="id">dlrow_<xsl:value-of select="@dl_id"/></xsl:attribute> 
        
        <td>
          <div>
        <xsl:value-of select="@user"/>
          </div>
          <xsl:if test="@owner != 'true'">
        <!-- <div class="dlsort_down" title="Sort downloads from this IP down" style="cursor:pointer" onmouseover="document.body.style.cursor='default'">
          <xsl:attribute name="onclick">javascript:dlmanager.SortBy('<xsl:value-of select="@user"/>')</xsl:attribute>
        </div> -->
          </xsl:if>
        </td>  
        <td>
         <div class="downloadData" style=" min-width: 200px;">
          <table>
        <tr>
          <td><u>Source:</u></td>   
          <td style="white-space: no-wrap;"><xsl:value-of select="@dl_name"/><br></br></td>
        </tr><tr>
          <td><u>Window:</u></td>
          <td style=""><xsl:value-of select="@detwindow"/><br></br></td>
        </tr><tr>
          <td><u>Format:</u></td>
          <td style=""><xsl:value-of select="@format"/><br></br></td>
        </tr>
          </table>
         </div>
        </td>
        <td><xsl:value-of select="@startdate"/></td>
        <td>    
          <xsl:if test="@status='Queue'">   
        <div class="progress_container" id="progress_container">
        <div class="progressQueue">Queue</div></div>
        <div style="font-size:11px">
          <xsl:attribute name="id">fcount<xsl:value-of select="@dl_id"/></xsl:attribute>
        </div>        
          </xsl:if>
          <xsl:if test="@status='ERROR'">   
        <div class="progress_container" id="progress_container">
            <div class="progressQueue"><font color="#FF0000">Error!</font></div>
        </div>  
          </xsl:if>
          <xsl:if test="@status='Finalizing'"> 
        <div class="progress_container" id="progress_container">
            <div class="progress" id="progress">Finalizing file...</div>
        </div>      
          </xsl:if>
          <xsl:if test="@status='Ready'"> 
        <div class="progress_container" id="progress_container">
            <div class="progressReady" id="progressReady">Complete (
            <xsl:if test="@filesize='0.1'"> &#60;1mb)</xsl:if> 
            <xsl:if test="@filesize!='0.1'"><xsl:value-of select="@filesize"/>mb)</xsl:if>
            </div>
        </div>      
          </xsl:if>
        <xsl:if test="@status='Preparing'"> 
        <div class="progress_container" id="progress_container">
            <div class="progress" style="width:0%">
            <xsl:attribute name="id">progress<xsl:value-of select="@dl_id"/></xsl:attribute>
            </div>
        </div>
        <div style="font-size:11px">
          <xsl:attribute name="id">fcount<xsl:value-of select="@dl_id"/></xsl:attribute>
        </div>
          </xsl:if>
        </td>   
        <td>
            <xsl:choose>
                <xsl:when test="../@isadmin != 'true'">
                    <xsl:call-template name="buttoncontainer"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:call-template name="admincontrols" />
                </xsl:otherwise>
            </xsl:choose>
        </td>
    </xsl:element>
  </xsl:template>


</xsl:stylesheet>
