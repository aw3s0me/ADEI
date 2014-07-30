<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
<xsl:template match="result">
  <div class = "settingspage">
  <div class="settingsheading"><table class="headingtable"><tr><td><div class="btn"><xsl:attribute name="onclick">javascript:settingmodule.goTo("<xsl:value-of select='history'/>");</xsl:attribute>Back</div></td><td><h1><xsl:value-of select="heading"/></h1></td></tr></table>
  </div>
  <div class = "settingscontent">
 	<xsl:if test="string(page)='p_id=sourcetimecustom'">
  		<table>
  		<tr>
  		<td><button><xsl:attribute name="onclick">javascript:settingmodule.CreateSpinningWheel("startdate");</xsl:attribute>Start Date</button></td>
  		<td><button><xsl:attribute name="onclick">javascript:settingmodule.CreateSpinningWheel("starttime");</xsl:attribute>Start Time</button></td>
  		</tr>
  		<tr>
  		<td><input type="text" id="startdate" readonly='readonly'></input></td><td><input type="text" id="starttime" readonly='readonly'></input></td>
  		</tr>
  		<tr>
  		<td><button><xsl:attribute name="onclick">javascript:settingmodule.CreateSpinningWheel("enddate");</xsl:attribute>End Date</button></td>
  		<td><button><xsl:attribute name="onclick">javascript:settingmodule.CreateSpinningWheel("endtime");</xsl:attribute>End Time</button></td>
  		</tr>
  		<tr>
  		<td><input type="text" id="enddate" readonly='readonly'></input></td><td><input type="text" id="endtime" readonly='readonly'></input></td>
  		</tr>
  		<tr><td><button><xsl:attribute name="onclick">javascript:settingmodule.ProcessWindow();</xsl:attribute>Update Window</button></td></tr>
  		</table>
	</xsl:if>
	<xsl:if test="string(page)='p_id=sourcetimewindow'">
		<ul class='ilist'>
		<xsl:for-each select="value">
			<li><xsl:attribute name="onclick">javascript:settingmodule.updateTime("<xsl:value-of select='@window'/>");</xsl:attribute><xsl:value-of select="@name"/></li>
		</xsl:for-each>
		</ul>
	</xsl:if>
	<xsl:if test="string(page)='controlssearch'">
		<table>
		<tr>
		<td><input type="text" id="searchfield"></input></td><td><button><xsl:attribute name="onclick">javascript:settingmodule.Search();</xsl:attribute>Search</button></td>
		</tr>
		</table>
		<div id="searchresults">
		</div>
	</xsl:if>
	<xsl:if test="string(page)='controlsaggr'">
		<ul class='ilist'>
		<xsl:for-each select="Value">
			<li><xsl:attribute name="onclick">javascript:settingmodule.sendGetRequest("p_id=controlsaggr","<xsl:value-of select='@value'/>");</xsl:attribute><xsl:value-of select='@name' />
			</li>	
		</xsl:for-each>
		</ul>
	</xsl:if>
  </div>
  </div>
</xsl:template>
</xsl:stylesheet>


