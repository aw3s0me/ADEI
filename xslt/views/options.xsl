<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
 <xsl:template match="@*|node()">
   <xsl:copy>
     <xsl:apply-templates select="@*|node()"/>
   </xsl:copy>
 </xsl:template>


 <xsl:template match="Group">
    <xsl:apply-templates select="@*|*"/>
 </xsl:template>


 <xsl:template match="@label"></xsl:template>
 <xsl:template match="@id"><xsl:attribute name="id">view_<xsl:value-of select="."/></xsl:attribute></xsl:template>
 <xsl:template match="options//@id"><xsl:attribute name="id"><xsl:value-of select="."/></xsl:attribute></xsl:template> 


 <xsl:template match="options">
    <xsl:for-each select="Value">
        <xsl:element name="option">
            <xsl:apply-templates select="@*"/>
            <xsl:value-of select="@label"/>
        </xsl:element>
    </xsl:for-each>
 </xsl:template>

 <xsl:template name="onchange_tmpl">
        <xsl:attribute name="onchange">
            <xsl:text>javascript:</xsl:text>
            <xsl:if test="/result/@object">
                <xsl:value-of select="/result/@object"/>
                <xsl:text>.</xsl:text>
            </xsl:if>
            <xsl:text>OptionsUpdater('view_</xsl:text><xsl:value-of select="@id"/><xsl:text>', ((typeof this.checked != "undefined")?this.checked:this.value))</xsl:text>
        </xsl:attribute>
 </xsl:template>

 <xsl:template match="checkbox">
    <xsl:element name="input">
	<xsl:attribute name="type">checkbox</xsl:attribute>
    	<xsl:call-template name="onchange_tmpl"/>
	<xsl:apply-templates select="*|@*"/>
    </xsl:element>
    <xsl:if test="@label">
        <xsl:value-of select="@label"/>
    </xsl:if>
 </xsl:template>

 <xsl:template match="input|select">
    <xsl:if test="@label">
        <xsl:value-of select="@label"/>:
    </xsl:if>
    <xsl:copy>
	<xsl:call-template name="onchange_tmpl"/>
        <xsl:apply-templates select="*|@*"/>
    </xsl:copy>
   <br/>
 </xsl:template>

 <xsl:template match="/result">
  <div style="white-space:nowrap;">
    <xsl:if test="*"><hr style="margin: 20px;"/></xsl:if>
    <xsl:if test="//Error">
	<span class="error"><xsl:value-of select="//Error"/></span>
    </xsl:if>
    
    <xsl:if test="not(//Error)">
	<xsl:apply-templates select="*"/>
    </xsl:if>	
    <xsl:if test="*"><hr style="margin: 20px;" /></xsl:if>
  </div>
 </xsl:template>

</xsl:stylesheet>
