<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
<xsl:template match="result">
  <div class = "emailform">
  <div class="emailheading"><table class="headingtable"><tr><td><h1><xsl:value-of select="heading"/></h1></td></tr></table>
  </div>
  <div class="emailcontent">
  <table>
  	<xsl:for-each select="value">
  		<xsl:if test="string(@name)!='Message'">
		  	<tr><td><xsl:value-of select='@name'/></td><td><input type='text' style="width:100%"><xsl:attribute name="id"><xsl:value-of select='@id'/></xsl:attribute></input></td></tr>
  		</xsl:if>
  		<xsl:if test="string(@name)='Message'">
  		  	<tr><td><xsl:value-of select='@name'/></td><td><textarea rows='5' style="width:100%"><xsl:attribute name="id"><xsl:value-of select='@id'/></xsl:attribute></textarea></td></tr>
  		</xsl:if>
  	</xsl:for-each>
  		<tr><td><button><xsl:attribute name="onclick">javascript:graph_control.sendEmail();</xsl:attribute>Send e-mail</button></td><td><button><xsl:attribute name="onclick">javascript:graph_control.closediv('emailform');</xsl:attribute>Cancel</button></td></tr>
  </table>
  </div>  
  </div>
</xsl:template>
</xsl:stylesheet>
	 