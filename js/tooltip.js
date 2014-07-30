function TOOLTIP(divid)
{ 
  this.isInit = false;
  this.div;
  this.divWidth;
  this.divHeight;
  this.xincr=10,yincr=10;
  this.animateToolTip = false;
  this.isAnimated = false/*true*/; 
  this.aniSpeed = 10; 	 
  this.Init(divid);  
  this.visible = false;
  
}  
    
  TOOLTIP.prototype.SetHTML = function(strHTML){
    html = strHTML;
    this.div.innerHTML=html;
  }

  TOOLTIP.prototype.Hide = function(){
    this.div.style.display='none';   
    this.div.style.height= "0px";
    this.div.style.width= "0px";
    this.visible = false;   
    if(this.det)this.SetHTML("Loading...");
  }
  
  TOOLTIP.prototype.Show = function(e,dl_id,det){
    this.det = det;
    e = e ? e : event;    
    if(this.visible != false) {
      this.Hide();      
      return;  
    }
    if(this.isInit != true) return;    

    var height,width,newPosx,newPosy, coordX, coordY;
    if(typeof( document.documentElement.clientWidth ) == 'number' ){
      width = document.body.clientWidth;
      height = document.body.clientHeight;
    }else{
      width = parseInt(window.innerWidth);
      height = parseInt(window.innerHeight);
    }

    var positions = getPosition(e);
     
    coordX=positions.x;
    coordY=positions.y;   
    
    if((coordX > this.divWidth+20) )
      newPosx = coordX-this.divWidth-20;
    else
      newPosx = coordX-this.divWidth+60;      
    
    if(this.divHeight + 40 < coordY)
      newPosy= coordY-this.divHeight-40;
    else
      newPosy = coordY+40;
    

    this.div.style.display='block';
    this.div.style.top= newPosy + "px";
    this.div.style.left= newPosx + "px"
    this.div.style.height= this.divHeight + "px";
    this.div.style.width= this.divWidth + "px";
    if(!this.det)this.div.style.backgroundImage = "url(tmp/downloads/images/"+dl_id+".png)";	
    this.div.focus(); 
    if(this.det)adei.UpdateDIV(this.div, "services/download.php?target=dlmanager_details&dl_id="+dl_id, "downloaddetails", false);      
    if(this.animateToolTip) {
      this.div.style.height= "0px";
      this.div.style.width= "0px";
      tooltipAnimate(this.div.id,this.divHeight,this.divWidth); 
    }
    this.visible = true;
  }

  function getPosition(e) {
    e = e || window.event;
    var positions = {x:0, y:0};
    if (e.pageX || e.pageY) {
        positions.x = e.pageX;
        positions.y = e.pageY;
    } 
    else {
        var de = document.documentElement;
        var b = document.body;
        positions.x = e.clientX + 
            (de.scrollLeft || b.scrollLeft) - (de.clientLeft || 0);
        positions.y = e.clientY + 
            (de.scrollTop || b.scrollTop) - (de.clientTop || 0);
    }
    return positions;
  }

  TOOLTIP.prototype.Init = function(id){
   this.div = document.getElementById(id);
   if(this.div==null) return;
   
   if((this.div.style.width=="" || this.div.style.height=="")){
     alert("Error: Downloadmanager graph div needs width and height");
     return;
    }
     
   this.divWidth = parseInt(this.div.style.width);
   this.divHeight= parseInt(this.div.style.height);
   if(this.div.style.visibility!="visible")this.div.style.visibility="visible";
   if(this.div.style.display!="none")this.div.style.display="none";
   if(this.div.style.position!="absolute")this.div.style.position="absolute";
   
   if(this.isAnimated && this.aniSpeed > 0){
    xincr = parseInt(divWidth/this.aniSpeed);
    yincr = parseInt(divHeight/this.aniSpeed);    
    this.animateToolTip = true;
   }
        
   this.isInit = true; 
   
  } 

  /*  
  function tooltipAnimate(a,aheight,awidth){ 
    a = document.getElementById(a);         
    var tw = parseInt(a.style.width)+xincr ;
    var th = parseInt(a.style.height)+yincr; 
    
    if(tw <= awidth){
      a.style.width = tw+"px";
    }
    else{
      a.style.width = awidth+"px";
    }
    
    if(th <= aheight){
      a.style.height = th+"px";
    }
    else{
      a.style.height = aheight+"px";
    }
    
    if(!((tw > awidth) && (th > aheight)))      
    setTimeout( "tooltipAnimate('"+a.id+"',"+aheight+","+awidth+")",1);
  }
   */  
