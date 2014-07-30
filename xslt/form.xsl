<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
 <xsl:template match="@*|node()">
   <xsl:copy>
     <xsl:apply-templates select="@*|node()"/>
   </xsl:copy>
 </xsl:template>

 <xsl:template match="/result">
  <div>
    <xsl:if test="//Error">
	<span class="error"><xsl:value-of select="//Error"/></span>
    </xsl:if>
    
    <xsl:if test="not(//Error)">
	<xsl:apply-templates select="*"/>
    </xsl:if>	
  </div>
 </xsl:template>

</xsl:stylesheet>
