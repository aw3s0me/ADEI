<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
 <xsl:template match="Value">
    <tr>
	<td><xsl:value-of select="@severity"/></td>
	<td><xsl:value-of select="@name"/></td>
	<td><xsl:value-of select="@in"/></td>
	<td><xsl:if test="not(@out) or @out=''">Active</xsl:if><xsl:value-of select="@out"/></td>
	<td><xsl:value-of select="@count"/></td>
	<td><xsl:value-of select="@description"/></td>
    </tr>
 </xsl:template>

 <xsl:template match="/result">
  <div>
    <xsl:if test="@title">
	<h1>List of alarms for: <xsl:value-of select="@title"/></h1>
    </xsl:if>
    
    <xsl:if test="//Error">
	<span class="error"><xsl:value-of select="//Error"/></span>
    </xsl:if>
    
    <xsl:if test="not(//Error)">
      <table>
	<tr>
	    <th>Severity</th>
	    <th>Alarm</th>
	    <th>First Seen</th>
	    <th>Last Seen</th>
	    <th>Count</th>
	    <th>Description</th>
	</tr>
	<xsl:apply-templates select="alarms/Value"/>
     </table>
    </xsl:if>
  </div>
 </xsl:template>
</xsl:stylesheet>
