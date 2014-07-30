<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
<xsl:template match="result">
  <div class = "settingspage">
  <div class="settingsheading"><table class="headingtable"><tr><td><div class="btn"><xsl:attribute name="onclick">javascript:settingmodule.goTo("<xsl:value-of select='history'/>");</xsl:attribute>Back</div></td><td><h1><xsl:value-of select="heading"/></h1></td></tr></table>
  </div>
  <div class = "settingscontent">
  <xsl:if test="string(page)='controlssearch'">
		<table>
		<tr>
		<td><input type="text" id="searchfield"></input></td><td><button><xsl:attribute name="onclick">javascript:settingmodule.Search();</xsl:attribute>Search</button></td>
		</tr>
		</table>
  </xsl:if>
  <xsl:if test="string(page) = 'searchresults'">
  	<xsl:if test="count(//Value)=1">
	<xsl:if test="//Value[@certain]">
	    <script type="text/javascript">
	        adei.SetConfiguration(htmlEntityDecode('<xsl:value-of select="//Value/@props"/>'));
	    	adei.OpenModule('graph');
	    </script>
	</xsl:if>
      </xsl:if>
  	<ul class='ilist'>
  	<xsl:for-each select='module/results/Value'>
  		<li><xsl:attribute name='onclick'>javascript:settingmodule.goTo('<xsl:value-of select="@props"/>',true);</xsl:attribute><xsl:value-of select='@title'/></li>
  	</xsl:for-each>
  	</ul>
  </xsl:if>
  <xsl:if test="string(page)!='controlssearch'">
  	<ul class='ilist'>
	<xsl:for-each select="value">
		<li><xsl:attribute name="onclick">javascript:settingmodule.sendGetRequest("<xsl:value-of select='@page'/>","<xsl:value-of select='@value'/>");</xsl:attribute><xsl:value-of select='@name' /></li>
	</xsl:for-each>
	</ul>
  </xsl:if>
  </div>
  </div>
</xsl:template>
</xsl:stylesheet>
	 