<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>

 <xsl:template match="@*|node()">
   <xsl:copy>
     <xsl:apply-templates select="@*|node()"/>
   </xsl:copy>
 </xsl:template>

 <xsl:template match="img">
    <xsl:copy>
        <xsl:attribute name="src"><xsl:text>services/getimage.php?id=</xsl:text><xsl:value-of select="@id"/></xsl:attribute>
    </xsl:copy>
 </xsl:template>

 <xsl:template match="info">
    <div>
	<xsl:for-each select="*">
	    <b><xsl:value-of select="@title"/></b>: <xsl:value-of select="@value"/><br/>
	</xsl:for-each>
    </div>
 </xsl:template>

 <xsl:template match="/result">
  <div style="white-space:nowrap;">
    <xsl:if test="//Error">
	<span class="error"><xsl:value-of select="//Error"/></span>
    </xsl:if>
    <xsl:if test="not(//Error)">
	<xsl:apply-templates select="*"/>
    </xsl:if>	
  </div>
 </xsl:template>

</xsl:stylesheet>
