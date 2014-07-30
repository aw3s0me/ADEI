<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="html" encoding="utf-8"/>  

<xsl:template match="/">
 <html>
  <body>    
  <div style="text-align:center">    
    <xsl:if test="groups/error!=''">
      <span style="color:red; text-align:center;"> Error:  <xsl:value-of select="groups/error"/></span>     
    </xsl:if>
    <xsl:if test="result/Error!=''">
      <span style="color:red; text-align:center;"><xsl:value-of select="result/Error"/></span>     
    </xsl:if>
    <table border="1">
     <tr bgcolor="darkgrey">
      <th width="306px">Window start</th>
      <th width="306px">Window end</th>
     </tr>
     <tr>
      <td><xsl:value-of select="groups/window/from"/></td>
      <td><xsl:value-of select="groups/window/to"/></td>     
     </tr>
    </table>
  </div>
   <div style="text-align:center">    
    <table border="1">
     <tr bgcolor="darkgrey">
      <th width="306px">File format</th>
      <th width="306px">File size</th>
     </tr>
     <tr>
      <td><xsl:value-of select="groups/data/format"/></td>
      <td><xsl:value-of select="groups/data/size"/></td>     
     </tr>
    </table>
  </div>
  <xsl:for-each select="/groups/group">
  <div style="text-align:center">
    </div>
  <table border="1">
     <tr bgcolor="darkgrey">
        <th>ID</th>
	<th width="100%">Group: <xsl:value-of select="gname"/></th>
     </tr>   
    <xsl:for-each select="item">
      <tr>
        <td><xsl:value-of select="itemid"/></td>  
	<td><xsl:value-of select="itemname"/></td> 
      </tr>
    </xsl:for-each> 
  </table>  
  </xsl:for-each>  
  </body>
 </html>
</xsl:template>

</xsl:stylesheet>
