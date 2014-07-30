<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>
<xsl:template match="result">
  <div class = "emailform">
  	<div class="emailheading"><table class="headingtable"><tr><td><h1>List of Sensors</h1></td></tr></table>
  </div>
  	<div class="emailcontent">
	  <table>
	  <tr><td>ID</td><td>Name</td></tr>
		<xsl:for-each select="value/item">
	  		<tr><td><xsl:value-of select="itemid"/></td><td><xsl:value-of select="itemname"/></td></tr>
		</xsl:for-each>
	  		<tr><td><button><xsl:attribute name="onclick">javascript:graph_control.closediv('sensordiv');</xsl:attribute>Close</button></td></tr>
	  </table>
	 </div>  
  </div>
</xsl:template>
</xsl:stylesheet>
	 