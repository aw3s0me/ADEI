<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
 <xsl:output method="html" encoding="utf-8"/>

 <xsl:template match="/result/alarms/Value">
    <tr>
	<td><xsl:value-of select="@severity"/></td>
	<td><xsl:value-of select="@name"/></td>
	<td><xsl:value-of select="@in"/></td>
	<td><xsl:value-of select="@description"/></td>
    </tr>
 </xsl:template>

 <xsl:template match="alarms">
  <div>
    <xsl:if test="/result/@title">
	<h1>List of alarms for: <xsl:value-of select="/result/@title"/></h1>
    </xsl:if>

    <xsl:if test="not(//Error)">
      <table>
	<tr>
	    <th>Severity</th>
	    <th>Alarm</th>
	    <th>Arrived</th>
	    <th>Description</th>
	</tr>
	<xsl:apply-templates select="Value"/>
     </table>
    </xsl:if>
  </div>
 </xsl:template>


 <xsl:template match="/result/data/Value">
    <tr>
	<td>
	    <xsl:if test="@uid">
		<xsl:value-of select="@uid"/>
	    </xsl:if>
	    <xsl:if test="not(@uid)">
		<xsl:value-of select="@id"/>
	    </xsl:if>
	</td>
	<td>
	    <xsl:if test="@write=1">
		<xsl:element name="input">
		    <xsl:attribute name="name">control_id_<xsl:value-of select="@id"/></xsl:attribute>
		    <xsl:attribute name="id">control_id_<xsl:value-of select="@id"/></xsl:attribute>
		    <xsl:attribute name="value">
			<xsl:value-of select="@value"/>
		    </xsl:attribute>
		</xsl:element>
	    </xsl:if>
	    <xsl:if test="not(@write=1)">
		<xsl:value-of select="@value"/>
	    </xsl:if>
	</td>
	<td><xsl:value-of select="@timestamp"/></td>
	<td><xsl:value-of select="@verified"/></td>
	<td><xsl:value-of select="@name"/></td>
    </tr>
 </xsl:template>

 <xsl:template match="data">
  <div>
    <xsl:if test="/result/@title">
	<h1>Control Values for: <xsl:value-of select="/result/@title"/></h1>
    </xsl:if>
    
    <xsl:if test="not(//Error)">
     <xsl:if test="Value/@write=1">
      <form id="slowcontrol_channels" action="javascript:slowcontrol.Set('slowcontrol_channels')">
       <table>
	<tr>
	    <th>Channel</th>
	    <th>Value</th>
	    <th>Timestamp</th>
	    <th>Verified</th>
	    <th>Description</th>
	</tr>
	<xsl:apply-templates select="Value"/>
       </table>
       <input type="submit" value="Set Controls"></input>
      </form>
     </xsl:if>
     <xsl:if test="not(Value/@write=1)">
      <table>
	<tr>
	    <th>Channel</th>
	    <th>Value</th>
	    <th>Timestamp</th>
	    <th>Verified</th>
	    <th>Description</th>
	</tr>
	<xsl:apply-templates select="Value"/>
      </table>
     </xsl:if>
    </xsl:if>
    <br/><br/>
  </div>
 </xsl:template>

 <xsl:template match="/result">
  <div>
    <xsl:if test="//Error">
	<span class="error"><xsl:value-of select="//Error"/></span>
    </xsl:if>

    <xsl:if test="/result/@status">
        <script type="text/javascript">
	    adei.SetStatus('<xsl:value-of select="/result/@status"/>');
	</script>
    </xsl:if>

    <xsl:apply-templates select="data|alarms"/>
  </div>
 </xsl:template>

</xsl:stylesheet>
