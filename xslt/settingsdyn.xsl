<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
<xsl:template match="result">
  <div class = "settingspage">
	<div class = 'settingsheading'>
		<table class="headingtable"><tr><td><div class="btn"><xsl:attribute name="onclick">javascript:settingmodule.HandleBack("<xsl:value-of select='history'/>");</xsl:attribute>Back</div></td><td><h1><xsl:value-of select="heading"/></h1></td></tr></table>
	</div>
	<div class = 'settingscontent'>
		<ul class='ilist'>
			<xsl:if test="string(pageid) = 'source'">
				<xsl:for-each select="Value">
					<li><xsl:attribute name="onclick">javascript:settingmodule.sendGetRequest("p_id=sourceselect","<xsl:value-of select='@value'/>");</xsl:attribute><xsl:value-of select='@name' /></li>	
				</xsl:for-each>
			</xsl:if>
			<xsl:if test="string(pageid) = 'control'">
				<xsl:for-each select="Value">
					<li><xsl:attribute name="onclick">javascript:settingmodule.sendGetRequest("p_id=controlsaggr","<xsl:value-of select='@value'/>");</xsl:attribute><xsl:value-of select='@name' /></li>	
				</xsl:for-each>
			</xsl:if>
		</ul>
	</div>
  </div>
</xsl:template>
</xsl:stylesheet>
	