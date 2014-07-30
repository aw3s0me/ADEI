<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>

 <xsl:template name="content">
    <xsl:copy>
	<xsl:for-each select="@*|node()">
	    <xsl:call-template name="content"/>
	</xsl:for-each>
    </xsl:copy>
 </xsl:template>

 <xsl:template match="Content|description">
    <xsl:for-each select="@*|node()">
	<xsl:call-template name="content"/>
    </xsl:for-each>
 </xsl:template>

 <xsl:template match="Value">
    <xsl:element name="a">
	<xsl:attribute name="href">
	    javascript:adei.SetConfiguration('<xsl:value-of select="@props"/>')
	</xsl:attribute>
	<xsl:value-of select="@title"/>
    </xsl:element>

    <xsl:if test="description">
	<div><xsl:apply-templates select="description"/></div>
    </xsl:if>
    <br/>
 </xsl:template>

 <xsl:template match="module">
    <div>
	<xsl:if test="@title">
	    <h4><xsl:value-of select="@title"/></h4>
	</xsl:if>
	<xsl:if test="not(@title) and results">
	    <h4><xsl:value-of select="@name"/></h4>
	</xsl:if>
    
	<xsl:if test="@description">
	    <div><xsl:value-of select="description"/></div>
	</xsl:if>
	
	<xsl:if test="results">
	    <div>
		<xsl:apply-templates select="results/Value"/>
	    </div>
	</xsl:if>
	
	<xsl:if test="Content">
		<xsl:apply-templates select="Content"/>
<!--
	    <xsl:copy>
		<xsl:call-template name="content" select="Content/@*|Content/node()"/>
		<xsl:apply-templates select="content/@*|content/node()"/>
	    </xsl:copy>
-->
	</xsl:if>
    </div>
 </xsl:template>

 <xsl:template match="/result">
  <div>
    <xsl:if test="//Error">
	<span class="error"><xsl:value-of select="//Error"/></span>
    </xsl:if>
    

    <xsl:if test="not(//Error)">
	<xsl:if test="not(//Value|//Content)">
	    Nothing is found
	</xsl:if>

	<xsl:apply-templates select="module"/>
    </xsl:if>	
    <xsl:if test="not(//Error)">
      <xsl:if test="count(//Value)=1">
	<xsl:if test="//Value[@certain]">
	    <script type="text/javascript">
	        adei.SetConfiguration(htmlEntityDecode('<xsl:value-of select="//Value/@props"/>'));
	    </script>
	</xsl:if>
      </xsl:if>
    </xsl:if>
  </div>
 </xsl:template>
</xsl:stylesheet>
