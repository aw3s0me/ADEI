<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>

 <xsl:template match="Value">
    <tr>
	<td>
	    <xsl:element name="span">
		<xsl:attribute name="style">color: <xsl:value-of select="@color"/></xsl:attribute>
		<xsl:value-of select="@id"/>
	    </xsl:element>
	</td>
	<td><xsl:value-of select="@name"/></td>
	<td>
	    <xsl:if test="value">
		<xsl:value-of select="value/@min"/> to <xsl:value-of select="value/@max"/>
		<xsl:if test="value/@mean">(<xsl:value-of select="value/@mean"/>)</xsl:if>
	    </xsl:if>
	    <xsl:if test="mean">
		<xsl:value-of select="mean/@min"/> to <xsl:value-of select="mean/@max"/>
	    </xsl:if>
	    <xsl:if test="approximation">
		approximation
	    </xsl:if>
	</td>
    </tr>
 </xsl:template>

 
 <xsl:template match="Group">
    <div>
	<xsl:if test="count(../Group) &gt; 1">
	    <xsl:if test="@title">
		<h4>Group: <xsl:value-of select="@title"/></h4>
	    </xsl:if>
	</xsl:if>
	<table>
	    <tr>
		<th>ID</th>
		<th>Name</th>
		<xsl:if test="results/Value/mean"><th>Mean</th></xsl:if>
		<xsl:if test="not(results/Value/mean)"><th>Value</th></xsl:if>
	    </tr>
	    <xsl:apply-templates select="results/Value"/>
	</table>
    </div>
 </xsl:template>

 <xsl:template match="/result">
  <div>
    <xsl:if test="//Error">
	<span class="error"><xsl:value-of select="//Error"/></span>
    </xsl:if>
    

    <xsl:if test="not(//Error)">
	<xsl:if test="not(//Value)">
	    Nothing is selected
	</xsl:if>

	<xsl:apply-templates select="Group"/>
    </xsl:if>	
  </div>
 </xsl:template>
</xsl:stylesheet>
