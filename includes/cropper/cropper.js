/**
 * Image Cropper (v. 1.2.0 - 2006-10-30 )
 * Copyright (c) 2006 David Spurr (http://www.defusion.org.uk/)
 * 
 * The image cropper provides a way to draw a crop area on an image and capture
 * the coordinates of the drawn crop area.
 * 
 * Features include:
 * 		- Based on Prototype and Scriptaculous
 * 		- Image editing package styling, the crop area functions and looks 
 * 		  like those found in popular image editing software
 * 		- Dynamic inclusion of required styles
 * 		- Drag to draw areas
 * 		- Shift drag to draw/resize areas as squares
 * 		- Selection area can be moved 
 * 		- Seleciton area can be resized using resize handles
 * 		- Allows dimension ratio limited crop areas
 * 		- Allows minimum dimension crop areas
 * 		- Allows maximum dimesion crop areas
 * 		- If both min & max dimension options set to the same value for a single axis,then the cropper will not 
 * 		  display the resize handles as appropriate (when min & max dimensions are passed for both axes this
 * 		  results in a 'fixed size' crop area)
 * 		- Allows dynamic preview of resultant crop ( if minimum width & height are provided ), this is
 * 		  implemented as a subclass so can be excluded when not required
 * 		- Movement of selection area by arrow keys ( shift + arrow key will move selection area by
 * 		  10 pixels )
 *		- All operations stay within bounds of image
 * 		- All functionality & display compatible with most popular browsers supported by Prototype:
 * 			PC:	IE 7, 6 & 5.5, Firefox 1.5, Opera 8.5 (see known issues) & 9.0b
 * 			MAC: Camino 1.0, Firefox 1.5, Safari 2.0
 * 
 * Requires:
 * 		- Prototype v. 1.5.0_rc0 > (as packaged with Scriptaculous 1.6.1)
 * 		- Scriptaculous v. 1.6.1 > modules: builder, dragdrop 
 * 		
 * Known issues:
 * 		- Safari animated gifs, only one of each will animate, this seems to be a known Safari issue
 * 
 * 		- After drawing an area and then clicking to start a new drag in IE 5.5 the rendered height 
 *        appears as the last height until the user drags, this appears to be the related to the error 
 *        that the forceReRender() method fixes for IE 6, i.e. IE 5.5 is not redrawing the box properly.
 * 
 * 		- Lack of CSS opacity support in Opera before version 9 mean we disable those style rules, these 
 * 		  could be fixed by using PNGs with transparency if Opera 8.5 support is high priority for you
 * 
 * 		- Marching ants keep reloading in IE <6 (not tested in IE7), it is a known issue in IE and I have 
 *        found no viable workarounds that can be included in the release. If this really is an issue for you
 *        either try this post: http://mir.aculo.us/articles/2005/08/28/internet-explorer-and-ajax-image-caching-woes
 *        or uncomment the 'FIX MARCHING ANTS IN IE' rules in the CSS file
 *		
 *		- Styling & borders on image, any CSS styling applied directly to the image itself (floats, borders, padding, margin, etc.) will 
 *		  cause problems with the cropper. The use of a wrapper element to apply these styles to is recommended.
 * 
 * 		- overflow: auto or overflow: scroll on parent will cause cropper to burst out of parent in IE and Opera (maybe Mac browsers too)
 *		  I'm not sure why yet.
 * 
 * Usage:
 * 		See Cropper.Img & Cropper.ImgWithPreview for usage details
 * 
 * Changelog:
 * v1.2.0 - 2006-10-30
 * 		+ Added id to the preview image element using 'imgCrop_[originalImageID]'
 *      * #00001 - Fixed bug: Doesn't account for scroll offsets
 *      * #00009 - Fixed bug: Placing the cropper inside differently positioned elements causes incorrect co-ordinates and display
 *      * #00013 - Fixed bug: I-bar cursor appears on drag plane
 *      * #00014 - Fixed bug: If ID for image tag is not found in document script throws error
 *      * Fixed bug with drag start co-ordinates if wrapper element has moved in browser (e.g. dragged to a new position)
 *      * Fixed bug with drag start co-ordinates if image contained in a wrapper with scrolling - this may be buggy if image 
 * 		  has other ancestors with scrolling applied (except the body)
 *      * #00015 - Fixed bug: When cropper removed and then reapplied onEndCrop callback gets called multiple times, solution suggestion from Bill Smith
 *      * Various speed increases & code cleanup which meant improved performance in Mac - which allowed removal of different overlay methods for
 *        IE and all other browsers, which led to a fix for:
 * 		* #00010 - Fixed bug: Select area doesn't adhere to image size when image resized using img attributes
 *      - #00006 - Removed default behaviour of automatically setting a ratio when both min width & height passed, the ratioDimensions must be passed in
 * 		+ #00005 - Added ability to set maximum crop dimensions, if both min & max set as the same value then we'll get a fixed cropper size on the axes as appropriate
 *        and the resize handles will not be displayed as appropriate
 * 		* Switched keydown for keypress for moving select area with cursor keys (makes for nicer action) - doesn't appear to work in Safari
 * 
 * v1.1.3 - 2006-08-21
 * 		* Fixed wrong cursor on western handle in CSS
 * 		+ #00008 & #00003 - Added feature: Allow to set dimensions & position for cropper on load
 *      * #00002 - Fixed bug: Pressing 'remove cropper' twice removes image in IE
 * 
 * v1.1.2 - 2006-06-09
 * 		* Fixed bugs with ratios when GCD is low (patch submitted by Andy Skelton)
 * 
 * v1.1.1 - 2006-06-03
 * 		* Fixed bug with rendering issues fix in IE 5.5
 * 		* Fixed bug with endCrop callback issues once cropper had been removed & reset in IE
 * 
 * v1.1.0 - 2006-06-02
 * 		* Fixed bug with IE constantly trying to reload select area background image
 * 		* Applied more robust fix to Safari & IE rendering issues
 * 		+ Added method to reset parameters - useful for when dynamically changing img cropper attached to
 * 		+ Added method to remove cropper from image
 * 
 * v1.0.0 - 2006-05-18 
 * 		+ Initial verison
 * 
 * 
 * Copyright (c) 2006, David Spurr (http://www.defusion.org.uk/)
 * All rights reserved.
 * 
 * 
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 
 *     * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 *     * Neither the name of the David Spurr nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * http://www.opensource.org/licenses/bsd-license.php
 * 
 * See scriptaculous.js for full scriptaculous licence
 */
 
/**
 * Extend the Draggable class to allow us to pass the rendering
 * down to the Cropper object.
 */
var CropDraggable = Class.create();

Object.extend( Object.extend( CropDraggable.prototype, Draggable.prototype), {
	
	initialize: function(element) {
		this.options = Object.extend(
			{
				/**
				 * The draw method to defer drawing to
				 */
				drawMethod: function() {}
			}, 
			arguments[1] || {}
		);

		this.element = $(element);

		this.handle = this.element;

		this.delta    = this.currentDelta();
		this.dragging = false;   

		this.eventMouseDown = this.initDrag.bindAsEventListener(this);
		Event.observe(this.handle, "mousedown", this.eventMouseDown);

		Draggables.register(this);
	},
	
	/**
	 * Defers the drawing of the draggable to the supplied method
	 */
	draw: function(point) {
		var pos = Position.cumulativeOffset(this.element);
		var d = this.currentDelta();
		pos[0] -= d[0]; 
		pos[1] -= d[1];
				
		var p = [0,1].map(function(i) { 
			return (point[i]-pos[i]-this.offset[i]) 
		}.bind(this));
				
		this.options.drawMethod( p );
	}
	
});


/**
 * The Cropper object, this will attach itself to the provided image by wrapping it with 
 * the generated xHTML structure required by the cropper.
 * 
 * Usage:
 * 	@param obj Image element to attach to
 * 	@param obj Optional options:
 * 		- ratioDim obj 
 * 			The pixel dimensions to apply as a restrictive ratio, with properties x & y
 * 
 * 		- minWidth int 
 * 			The minimum width for the select area in pixels
 * 
 * 		- minHeight	int 
 * 			The mimimum height for the select area in pixels
 * 
 * 		- maxWidth int
 * 			The maximum width for the select areas in pixels (if both minWidth & maxWidth set to same the width of the cropper will be fixed)
 * 
 * 		- maxHeight int
 *			The maximum height for the select areas in pixels (if both minHeight & maxHeight set to same the height of the cropper will be fixed)
 * 
 * 		- displayOnInit int 
 * 			Whether to display the select area on initialisation, only used when providing minimum width & height or ratio
 * 
 * 		- onEndCrop func
 * 			The callback function to provide the crop details to on end of a crop (see below)
 * 
 * 		- captureKeys boolean
 * 			Whether to capture the keys for moving the select area, as these can cause some problems at the moment
 * 
 * 		- onloadCoords obj
 * 			A coordinates object with properties x1, y1, x2 & y2; for the coordinates of the select area to display onload
 * 	
 *----------------------------------------------
 * 
 * The callback function provided via the onEndCrop option should accept the following parameters:
 * 		- coords obj
 * 			The coordinates object with properties x1, y1, x2 & y2; for the coordinates of the select area
 * 
 * 		- dimensions obj
 * 			The dimensions object with properites width & height; for the dimensions of the select area
 * 		
 *
 * 		Example:
 * 			function onEndCrop( coords, dimensions ) {
 *				$( 'x1' ).value 	= coords.x1;
 *				$( 'y1' ).value 	= coords.y1;
 *				$( 'x2' ).value 	= coords.x2;
 *				$( 'y2' ).value 	= coords.y2;
 *				$( 'width' ).value 	= dimensions.width;
 *				$( 'height' ).value	= dimensions.height;
 *			}
 * 
 */
var Cropper = {};
Cropper.Img = Class.create();
Cropper.Img.prototype = {
	
	/**
	 * Initialises the class
	 * 
	 * @access public
	 * @param obj Image element to attach to
	 * @param obj Options
	 * @return void
	 */
	initialize: function(element, options) {
		this.options = Object.extend(
			{
				/**
				 * @var obj
				 * The pixel dimensions to apply as a restrictive ratio
				 */
				ratioDim: { x: 0, y: 0 },
				/**
				 * @var int
				 * The minimum pixel width, also used as restrictive ratio if min height passed too
				 */
				minWidth:		0,
				/**
				 * @var int
				 * The minimum pixel height, also used as restrictive ratio if min width passed too
				 */
				minHeight:		0,
				/**
				 * @var boolean
				 * Whether to display the select area on initialisation, only used when providing minimum width & height or ratio
				 */
				displayOnInit:	false,
				/**
				 * @var function
				 * The call back function to pass the final values to
				 */
				onEndCrop: Prototype.emptyFunction,
				/**
				 * @var function
				 * The call back function to be called if crop is canceled
				 */
				onCancelCrop: Prototype.emptyFunction,
				/**
				 * @var function
				 * The call back function to call on double click
				 */
				onDblClick: Prototype.emptyFunction,
				/**
				 * @var function
				 * The call back function to call on double click within selection
				 */
				onDblSelClick: Prototype.emptyFunction,
				/**
				 * @var function
				 * The call back function on mouse wheel scrolling
				 */
				 onMouseScroll: null,
				/**
				 * @var function
				 * The call back function on mouse moving
				 */
				 onMouseMove: null,
				 

				 onTouchStart: null,
				 onTouchMove: null,
				 onTouchEnd: null,
				 onGestureStart: null,
				 onGestureMove: null,
				 onGestureEnd: null,
				 onOrientationChange: null,
          
				/**
				 * @var function
				 * The call back function to pass the final values to
				 */
				onApplyClick: null,
				/**
				 * @var function
				 * The call back to be called on click of save button
				 */
				 onSaveClick: null,
				/**
				 * @var function
				 * The call back function to handle click events
				 */
				onClick: null,
				/**
				 * @var array
				 * Additional buttons in crop area
				 */
				extraButtons: new Array(),
				/**
				 * @var boolean
				 * Whether to capture key presses or not
				 */
				captureKeys: true,
				/**
				 * @var obj Coordinate object x1, y1, x2, y2
				 * The coordinates to optionally display the select area at onload
				 */
				onloadCoords: null,
				/**
				 * @var obj tooltips for misc. buttons
				 */
				tooltips: null,
				/**
				 * @var int
				 * The maximum width for the select areas in pixels (if both minWidth & maxWidth set to same the width of the cropper will be fixed)
				 */
				maxWidth: 0,
				/**
				 * @var int
				 * The maximum height for the select areas in pixels (if both minHeight & maxHeight set to same the height of the cropper will be fixed)
				 */
				maxHeight: 0,
				/**
				 * @var obj Coordinate object left, top, right, bottom
				 * The non-selectable margins
				 */
				margins: { left: 0, top: 0, right: 0, bottom: 0 },
				/**
				 * @var int
				 * If width is below this value, we are selecting whole horizontal range
				 */
				allWidth: 0,
				/**
				 * @var int
				 * If height is below this value, we are selecting whole vertical range
				 */
				allHeight: 0,
				/**
				 * @var boolean
				 * Whether to monitor reloading of picture
				 */
				monitorImage: true,
				/**
				 * @var boolean
				 * Allow vertical cropping or not
				 */
				verticalCrop: true,
				/**
				 * @var boolean
				 * Whether the image is already loaded
				 */
				imageReady: false,
				/**
				 * @var int
				 * Maximal duration of the click event in ms (after dragging is started)
				 */
				clickDuration: 200

			}, 
			options || {}
		);

		this.initialized = false;
		/**
		 * @var obj
		 * The img node to attach to
		 */
		this.img			= $( element );
		/**
		 * @var obj
		 * The x & y coordinates of the click point
		 */
		this.clickCoords	= { x: 0, y: 0 };
		/**
		 * @var boolean
		 * Whether the user is dragging
		 */
		this.dragging		= false;
		/**
		 * @var boolean
		 * Whether the user is resizing
		 */
		this.resizing		= false;
		/**
		 * @var boolean
		 * Whether the selected area is present on the screen
		 */
		this.selected		= false;
		/**
		 * @var boolean
		 * Whether the selected area is present on the screen
		 */
		this.altered		= false;
		/**
		 * @var boolean
		 * Whether the user is on a webKit browser
		 */
		this.isWebKit 		= /Konqueror|Safari|KHTML/.test( navigator.userAgent );
		/**
		 * @var boolean
		 * Whether the user is on IE
		 */
		this.isIE 			= /MSIE/.test( navigator.userAgent );
		/**
		 * @var boolean
		 * Whether the user is on Opera below version 9
		 */
		this.isOpera8		= /Opera\s[1-8]/.test( navigator.userAgent );
		/**
		 * @var int
		 * The x ratio 
		 */
		this.ratioX			= 0;
		/**
		 * @var int
		 * The y ratio
		 */
		this.ratioY			= 0;
		/**
		 * @var boolean
		 * Whether we've attached sucessfully
		 */
		this.attached		= false;
		/**
		 * @var boolean
		 * Whether we've got a fixed width (if minWidth EQ or GT maxWidth then we have a fixed width
		 * in the case of minWidth > maxWidth maxWidth wins as the fixed width)
		 */
		this.fixedWidth		= ( this.options.maxWidth > 0 && ( this.options.minWidth >= this.options.maxWidth ) );
		/**
		 * @var boolean
		 * Whether we've got a fixed height (if minHeight EQ or GT maxHeight then we have a fixed height
		 * in the case of minHeight > maxHeight maxHeight wins as the fixed height)
		 */
		this.fixedHeight	= ( this.options.maxHeight > 0 && ( this.options.minHeight >= this.options.maxHeight ) );
		
		
		/**
		 * scroll constants
		 */
		this.SCROLL_EVENT = 1;
		
		// quit if the image element doesn't exist
		if( typeof this.img == 'undefined' ) return;
/*				
		var script_found = false;
		$A( document.getElementsByTagName( 'script' ) ).each( 
			function(s) {
				if( s.src.match( /cropper\.js/ ) ) {
					script_found = true;
					var path 	= s.src.replace( /cropper\.js(.*)?/, '' );
					// '<link rel="stylesheet" type="text/css" href="' + path + 'cropper.css" media="screen" />';
//					var path 	= "includes/cropper/"
					var path = "/zweb/includes/cropper/";
*/					
					var path = "includes/cropper/";
//					alert(path);
					var style 		= document.createElement( 'link' );
					style.rel 		= 'stylesheet';
					style.type 		= 'text/css';
					style.href 		= path + 'cropper.css';
					style.media 	= 'screen';
					document.getElementsByTagName( 'head' )[0].appendChild( style );
/*				}
	    		}
		);   
		
		if (!script_found) return;
*/
	
		// calculate the ratio when neccessary
		if( this.options.ratioDim.x > 0 && this.options.ratioDim.y > 0 ) {
			var gcd = this.getGCD( this.options.ratioDim.x, this.options.ratioDim.y );
			this.ratioX = this.options.ratioDim.x / gcd;
			this.ratioY = this.options.ratioDim.y / gcd;
			// dump( 'RATIO : ' + this.ratioX + ':' + this.ratioY + '\n' );
		}
							
		// initialise sub classes
		this.subInitialize();

		// only load the event observers etc. once the image is loaded
		// this is done after the subInitialize() call just in case the sub class does anything
		// that will affect the result of the call to onLoad()
		if( this.options.imageReady || this.img.complete || this.isWebKit ) {
		    this.onLoad(); // for some reason Safari seems to support img.complete but returns 'undefined' on the this.img object
		    if (this.options.monitorImage) Event.observe( this.img, 'load', this.onLoad.bindAsEventListener( this) );
		} else Event.observe( this.img, 'load', this.onLoad.bindAsEventListener( this) );
	
	
		// we need to detect browser version for certain fixes
		this.fix_gecko_mouse_position = false;
		
		if ( Prototype.Browser.Gecko) {
		    var res = /rv:(\d+(\.\d+)?)/.exec(navigator.userAgent);
		    if (res) {
			if (10*parseFloat(res[1]) < 19) {
			    /* My Firefox3 reporting Gecko18, strange
			    builds on Gentoo. According to 
			        https://developer.mozilla.org/en/DOM_improvements_in_Firefox_3
			    postMessage only have arrived at Firefox3, so checking it availability.
			    
			    [ Danger: Check if this function is not comming with newer versions of 
			    Prototype and etc. ] */
			    if(!window.postMessage) this.fix_gecko_mouse_position = true;
			}
		    }
		}
		
		this.initialized = true;
	},
	
	/**
	 * Updates tooltips
	 *
	 * @access public
	 * @param object Associative array of tooltips
	 * @return void
	 */
	setTooltip : function (opts) {
	    if (!this.options.tooltips) this.options.tooltips = new Object();
	    
	    for (var i in opts) {
		this.options.tooltips[i] = opts[i];
	    }
	    
	    if ((this.applyButton)&&(opts.apply)) {
		this.applyButton.setAttribute('title', opts.apply);
	    }
	    if ((this.saveButton)&&(opts.save)) {
		this.saveButton.setAttribute('title', opts.save);
	    }
	},
	
	/**
	 * Alter margins
	 *
	 * @access public
	 * @param int left margin
	 * @param int top margin
	 * @param int right margin
	 * @param int bottom margin
	 * @return void
	 */
	setMargins : function(l,t,r,b) {
	    this.options.margins = { left: l, top: r, right: r, bottom: b };
	},
	
	/**
	 * The Euclidean algorithm used to find the greatest common divisor
	 * 
	 * @acces private
	 * @param int Value 1
	 * @param int Value 2
	 * @return int
	 */
	getGCD : function( a , b ) {
		if( b == 0 ) return a;
		return this.getGCD(b, a % b );
	},
	
	/**
	 * Attaches the cropper to the image once it has loaded
	 * 
	 * @access private
	 * @return void
	 */
	onLoad: function( ) {
		/*
		 * Build the container and all related elements, will result in the following
		 *
		 * <div class="imgCrop_wrap">
		 * 		<img ... this.img ... />
		 * 		<div class="imgCrop_dragArea">
		 * 			<!-- the inner spans are only required for IE to stop it making the divs 1px high/wide -->
		 * 			<div class="imgCrop_overlay imageCrop_north"><span></span></div>
		 * 			<div class="imgCrop_overlay imageCrop_east"><span></span></div>
		 * 			<div class="imgCrop_overlay imageCrop_south"><span></span></div>
		 * 			<div class="imgCrop_overlay imageCrop_west"><span></span></div>
		 * 			<div class="imgCrop_selArea">
		 * 				<!-- marquees -->
		 * 				<!-- the inner spans are only required for IE to stop it making the divs 1px high/wide -->
		 * 				<div class="imgCrop_marqueeHoriz imgCrop_marqueeNorth"><span></span></div>
		 * 				<div class="imgCrop_marqueeVert imgCrop_marqueeEast"><span></span></div>
		 * 				<div class="imgCrop_marqueeHoriz imgCrop_marqueeSouth"><span></span></div>
		 * 				<div class="imgCrop_marqueeVert imgCrop_marqueeWest"><span></span></div>			
		 * 				<!-- handles -->
		 * 				<div class="imgCrop_handle imgCrop_handleN"></div>
		 * 				<div class="imgCrop_handle imgCrop_handleNE"></div>
		 * 				<div class="imgCrop_handle imgCrop_handleE"></div>
		 * 				<div class="imgCrop_handle imgCrop_handleSE"></div>
		 * 				<div class="imgCrop_handle imgCrop_handleS"></div>
		 * 				<div class="imgCrop_handle imgCrop_handleSW"></div>
		 * 				<div class="imgCrop_handle imgCrop_handleW"></div>
		 * 				<div class="imgCrop_handle imgCrop_handleNW"></div>
		 * 				<div class="imgCrop_clickArea"></div>
		 * 			</div>	
		 * 			<div class="imgCrop_clickArea"></div>
		 * 		</div>	
		 * </div>
		 */

		if (this.attached) {
		    if (this.monitorImage) {
			this.setParams();
		    }
		    return;
		}
		 
		var cNamePrefix = 'imgCrop_';
		
		// get the point to insert the container
		var insertPoint = this.img.parentNode;
		
		// apply an extra class to the wrapper to fix Opera below version 9
		var fixOperaClass = '';
		if( this.isOpera8 ) fixOperaClass = ' opera8';
		this.imgWrap = Builder.node( 'div', { 'class': cNamePrefix + 'wrap' + fixOperaClass } );
		
		this.applyLinked = 0;
		this.saveLinked = 0;
		this.buttonsLinked = 0;
		
		this.applyButton = Builder.node( 'div', { 'class': cNamePrefix + 'apply'} );
		if ((this.applyButton.parentNode)&&(this.options.tooltips)&&(this.options.tooltips.apply)) {
		    this.applyButton.setAttribute('title', this.options.tooltips.apply);
	        }
		this.saveButton = Builder.node( 'div', { 'class': cNamePrefix + 'save'} );
		if ((this.saveButton.parentNode)&&(this.options.tooltips)&&(this.options.tooltips.save)) {
		    this.saveButton.setAttribute('title', this.options.tooltips.save);
		}
		
		this.buttons = new Array();
		for (var i = 0; i < this.options.extraButtons.length; i++) {
		    var info = this.options.extraButtons[i];
		    if (info.css) this.buttons[i] = Builder.node( 'div', { 'class': info.css} );
		    else this.buttons[i] = Builder.node( 'div', { 'class': cNamePrefix + info.name} );
		    if ((this.buttons[i].parentNode)&&(info.tooltip)) {
			this.buttons[i].setAttribute('title', info.tooltip);
		    }
		}
		
		
		this.north		= Builder.node( 'div', { 'class': cNamePrefix + 'overlay ' + cNamePrefix + 'north' }, [Builder.node( 'span' )] );
		this.north_east		= Builder.node( 'div', { 'class': cNamePrefix + 'overlay ' + cNamePrefix + 'north' }, [Builder.node( 'span' )] );
		this.north_west		= Builder.node( 'div', { 'class': cNamePrefix + 'overlay ' + cNamePrefix + 'north' }, [Builder.node( 'span' )] );
		this.east		= Builder.node( 'div', { 'class': cNamePrefix + 'overlay ' + cNamePrefix + 'east' } , [Builder.node( 'span' )] );
		this.south		= Builder.node( 'div', { 'class': cNamePrefix + 'overlay ' + cNamePrefix + 'south' }, [Builder.node( 'span' )] );
		this.south_east		= Builder.node( 'div', { 'class': cNamePrefix + 'overlay ' + cNamePrefix + 'south' }, [Builder.node( 'span' )] );
		this.south_west		= Builder.node( 'div', { 'class': cNamePrefix + 'overlay ' + cNamePrefix + 'south' }, [Builder.node( 'span' )] );
		this.west		= Builder.node( 'div', { 'class': cNamePrefix + 'overlay ' + cNamePrefix + 'west' } , [Builder.node( 'span' )] );
		
		var overlays	= [ this.north_west, this.north, this.north_east, this.east, this.south_east, this.south, this.south_west, this.west ];

		this.dragArea	= Builder.node( 'div', { 'class': cNamePrefix + 'dragArea' }, overlays );
						
		this.handleN	= Builder.node( 'div', { 'class': cNamePrefix + 'handle ' + cNamePrefix + 'handleN' } );
		this.handleNE	= Builder.node( 'div', { 'class': cNamePrefix + 'handle ' + cNamePrefix + 'handleNE' } );
		this.handleE	= Builder.node( 'div', { 'class': cNamePrefix + 'handle ' + cNamePrefix + 'handleE' } );
		this.handleSE	= Builder.node( 'div', { 'class': cNamePrefix + 'handle ' + cNamePrefix + 'handleSE' } );
		this.handleS	= Builder.node( 'div', { 'class': cNamePrefix + 'handle ' + cNamePrefix + 'handleS' } );
		this.handleSW	= Builder.node( 'div', { 'class': cNamePrefix + 'handle ' + cNamePrefix + 'handleSW' } );
		this.handleW	= Builder.node( 'div', { 'class': cNamePrefix + 'handle ' + cNamePrefix + 'handleW' } );
		this.handleNW	= Builder.node( 'div', { 'class': cNamePrefix + 'handle ' + cNamePrefix + 'handleNW' } );
		
		if (Prototype.Browser.IE) {
		    this.selArea	= Builder.node( 'div', { 'class': cNamePrefix + 'selArea' },
			[
				Builder.node( 'div', { 'class': cNamePrefix + 'marqueeHoriz ' + cNamePrefix + 'marqueeNorth' }, [Builder.node( 'span' )] ),
				Builder.node( 'div', { 'class': cNamePrefix + 'marqueeVert ' + cNamePrefix + 'marqueeEast' }  , [Builder.node( 'span' )] ),
				Builder.node( 'div', { 'class': cNamePrefix + 'marqueeHoriz ' + cNamePrefix + 'marqueeSouth' }, [Builder.node( 'span' )] ),
				Builder.node( 'div', { 'class': cNamePrefix + 'marqueeVert ' + cNamePrefix + 'marqueeWest' }  , [Builder.node( 'span' )] ),
				Builder.node( 'div', { 'class': cNamePrefix + 'clickArea' } )
			]
		    );

		    this.handleArea = Builder.node( 'div', { 'class': cNamePrefix + 'handleArea' },
			[
				this.handleN,
				this.handleNE,
				this.handleE,
				this.handleSE,
				this.handleS,
				this.handleSW,
				this.handleW,
				this.handleNW
			]
		    );				

		    this.dragArea.appendChild( this.handleArea );
		    
		    this.eventArea = this.handleArea;
		} else {
		    this.selArea	= Builder.node( 'div', { 'class': cNamePrefix + 'selArea' },
			[
				Builder.node( 'div', { 'class': cNamePrefix + 'marqueeHoriz ' + cNamePrefix + 'marqueeNorth' }, [Builder.node( 'span' )] ),
				Builder.node( 'div', { 'class': cNamePrefix + 'marqueeVert ' + cNamePrefix + 'marqueeEast' }  , [Builder.node( 'span' )] ),
				Builder.node( 'div', { 'class': cNamePrefix + 'marqueeHoriz ' + cNamePrefix + 'marqueeSouth' }, [Builder.node( 'span' )] ),
				Builder.node( 'div', { 'class': cNamePrefix + 'marqueeVert ' + cNamePrefix + 'marqueeWest' }  , [Builder.node( 'span' )] ),
				this.handleN,
				this.handleNE,
				this.handleE,
				this.handleSE,
				this.handleS,
				this.handleSW,
				this.handleW,
				this.handleNW,
				Builder.node( 'div', { 'class': cNamePrefix + 'clickArea' } )
			]
		    );

		    this.eventArea = this.selArea;
		}
		
		this.imgWrap.appendChild( this.img );
		this.imgWrap.appendChild( this.dragArea );
		this.dragArea.appendChild( this.selArea );
		this.dragArea.appendChild( Builder.node( 'div', { 'class': cNamePrefix + 'clickArea' } ) );

		insertPoint.appendChild( this.imgWrap );


		if (Prototype.Browser.IE) {
		    $( this.applyButton ).hide();
		    this.dragArea.appendChild(this.applyButton);
		    $( this.saveButton ).hide();
		    this.dragArea.appendChild(this.saveButton);
		    
		    for (var i = 0; i < this.buttons.length; i++) {
			$( this.buttons[i] ).hide();
			this.dragArea.appendChild( this.buttons[i] );
		    }
		}

		// add event observers
		this.startDragBind 	= this.startDrag.bindAsEventListener( this );
		Event.observe( this.dragArea, 'mousedown', this.startDragBind );
		
		this.onDragBind 	= this.onDrag.bindAsEventListener( this );
		//Event.observe( this.dragArea, 'mousemove', this.onDragBind );
		Event.observe( document, 'mousemove', this.onDragBind );

		if (this.options.onMouseMove) {
		    this.onMouseMoveBind 	= this.onMouseMove.bindAsEventListener( this );
		    Event.observe( this.dragArea, 'mousemove', this.onMouseMoveBind );
		    this.onMouseOutBind 	= this.onMouseOut.bindAsEventListener( this );
		    Event.observe( document, 'mouseout', this.onMouseOutBind );
		}


    if(this.options.onTouchStart){
      this.onTouchStartBind = this.onTouchStart.bindAsEventListener(this);
      Event.observe(this.dragArea,'touchstart',this.onTouchStartBind);
    }
    if(this.options.onTouchMove){
      this.onTouchMoveBind = this.onTouchMove.bindAsEventListener(this);
      Event.observe(this.dragArea,'touchmove',this.onTouchMoveBind);
    }
    if(this.options.onTouchEnd){
      this.onTouchEndBind = this.onTouchEnd.bindAsEventListener(this);
      Event.observe(this.dragArea, 'touchend',this.onTouchEndBind);
    }
    if(this.options.onGestureStart){
      this.onGestureStartBind = this.onGestureStart.bindAsEventListener(this);
      Event.observe(this.dragArea,'gesturestart',this.onGestureStartBind);
    }
    if(this.options.onGestureMove){
      this.onGestureMoveBind = this.onGestureMove.bindAsEventListener(this);
      Event.observe(this.dragArea,'gesturechange',this.onGestureMoveBind);
    }
    if(this.options.onGestureEnd){
      this.onGestureEndBind = this.onGestureEnd.bindAsEventListener(this);
      Event.observe(this.dragArea,'gestureend',this.onGestureEndBind);
    }
    if(this.options.onOrientationChange){
      this.onOrientationChangeBind = this.onOrientationChange.bindAsEventListener(this);
      Event.observe(this.dragArea,'orientationchange',this.onOrientationChangeBind);
    }


		this.endCropBind 	= this.endCrop.bindAsEventListener( this );
		Event.observe( document, 'mouseup', this.endCropBind );
//		Event.observe( this.imgWrap, 'mouseup', this.endCropBind );
		
		this.dblClickBind	= this.dblClick.bindAsEventListener( this );
		Event.observe( this.dragArea, 'dblclick', this.dblClickBind );

		this.dblSelClickBind	= this.dblSelClick.bindAsEventListener( this );
		Event.observe( this.eventArea, 'dblclick', this.dblSelClickBind );

/*		if (Ext) {
		    Ext.EventManager.addListener(this.dragArea, "mousewheel", this.mouseScrollExt, this, {
			    stopEvent: true,
			    preventDefault: true
		    });
		} else {*/
		    this.mouseScrollBind	= this.mouseScroll.bindAsEventListener( this );
		    Event.observe( this.dragArea, 'DOMMouseScroll', this.mouseScrollBind );
		    Event.observe( this.dragArea, 'mousewheel', this.mouseScrollBind );
/*		}*/
		
		this.applyClickBind	= this.applyClick.bindAsEventListener( this );
		Event.observe( this.applyButton, 'mousedown', this.applyClickBind );
//		Event.observe( this.applyButton, 'click', this.applyClickBind );

		this.saveClickBind	= this.saveClick.bindAsEventListener( this );
		Event.observe( this.saveButton, 'mousedown', this.saveClickBind );

		this.buttonClickBind = new Array();
		for (var i = 0; i < this.options.extraButtons.length; i++) {
		    this.buttonClickBind[i] = this.buttonClick(this,i).bindAsEventListener( this );
	    	    Event.observe( this.buttons[i], 'mousedown', this.buttonClickBind[i] );
		}
		
		this.resizeBind		= this.startResize.bindAsEventListener( this );
		this.handles = [ this.handleN, this.handleNE, this.handleE, this.handleSE, this.handleS, this.handleSW, this.handleW, this.handleNW ];
		this.registerHandles( true );
		
		if( this.options.captureKeys ) {
			this.keysBind = this.handleKeys.bindAsEventListener( this );
			Event.observe( document, 'keypress', this.keysBind );
		}

		// attach the dragable to the select area
		new CropDraggable( this.eventArea, { drawMethod: this.moveArea.bindAsEventListener( this ) } );
		
		this.setParams();
	},
	
	/**
	 * Manages adding or removing the handle event handler and hiding or displaying them as appropriate
	 * 
	 * @access private
	 * @param boolean registration true = add, false = remove
	 * @return void
	 */
	registerHandles: function( registration ) {	
		for( var i = 0; i < this.handles.length; i++ ) {
			var handle = $( this.handles[i] );
			
			if( registration ) {
				var hideHandle	= false;	// whether to hide the handle
				
				// disable handles asappropriate if we've got fixed dimensions
				// if both dimensions are fixed we don't need to do much
				if( this.fixedWidth && this.fixedHeight ) hideHandle = true;
				else if( this.fixedWidth || this.fixedHeight ) {
					// if one of the dimensions is fixed then just hide those handles
					var isCornerHandle	= handle.className.match( /([S|N][E|W])$/ )
					var isWidthHandle 	= handle.className.match( /(E|W)$/ );
					var isHeightHandle 	= handle.className.match( /(N|S)$/ );
					if( isCornerHandle ) hideHandle = true;
					else if( this.fixedWidth && isWidthHandle ) hideHandle = true;
					else if( this.fixedHeight && isHeightHandle ) hideHandle = true;
				}
				if( hideHandle ) handle.hide();
				else Event.observe( handle, 'mousedown', this.resizeBind );
			} else {
				handle.show();
				Event.stopObserving( handle, 'mousedown', this.resizeBind );
			}
		}
	},
		
	/**
	 * Sets up all the cropper parameters, this can be used to reset the cropper when dynamically
	 * changing the images
	 * 
	 * @access private
	 * @return void
	 */
	setParams: function() {
		/**
		 * @var int
		 * The image width
		 */
		this.imgW = this.img.width;
		/**
		 * @var int
		 * The image height
		 */
		this.imgH = this.img.height;

		$( this.north ).setStyle( { height: 0 } );
		$( this.north_east ).setStyle( { height: 0 } );
		$( this.north_west ).setStyle( { height: 0 } );
		$( this.east ).setStyle( { width: 0, height: 0 } );
		$( this.south ).setStyle( { height: 0 } );
		$( this.south_east ).setStyle( { height: 0 } );
		$( this.south_west ).setStyle( { height: 0 } );
		$( this.west ).setStyle( { width: 0, height: 0 } );
		
		// resize the container to fit the image
		$( this.imgWrap ).setStyle( { 'width': this.imgW + 'px', 'height': this.imgH + 'px' } );
		
		// hide the select area
		$( this.selArea ).hide();
		if (this.handleArea) $( this.handleArea).hide();
		
		// hide apply button
		this.unlinkButtons();
		
		this.selected = false;
		this.altered = false;
						
		// setup the starting position of the select area
		var startCoords = { x1: this.options.margins.left, y1: this.options.margins.top, x2: this.options.margins.left, y2: this.options.margins.top };
		var validCoordsSet = false;
		
		// display the select area 
		if( this.options.onloadCoords != null ) {
			// if we've being given some coordinates to 
			startCoords = this.cloneCoords( this.options.onloadCoords );
			validCoordsSet = true;
		} else if( this.options.ratioDim.x > 0 && this.options.ratioDim.y > 0 ) {
			// if there is a ratio limit applied and the then set it to initial ratio
			startCoords.x1 = Math.ceil( ( this.imgW - this.options.ratioDim.x ) / 2 );
			startCoords.y1 = Math.ceil( ( this.imgH - this.options.ratioDim.y ) / 2 );
			startCoords.x2 = startCoords.x1 + this.options.ratioDim.x;
			startCoords.y2 = startCoords.y1 + this.options.ratioDim.y;
			validCoordsSet = true;
		}
		
		this.setAreaCoords( startCoords, false, false, 1 );
		
		if( this.options.displayOnInit && validCoordsSet ) {
			this.selArea.show();
			if (this.handleArea) this.handleArea.show();
			this.drawArea();
			this.endCrop();
		}
		
		this.attached = true;
	},
	/**
	 * Sets cropping mode
	 * 
	 * @access public
	 * @param boolean vertical - enable/disable vertical cropping
	 * @return void
	 */
	setCropMode: function(vertical) {
	    this.options.verticalCrop = vertical;
	},
	
	/**
	 * Removes the cropper
	 * 
	 * @access public
	 * @return void
	 */
	remove: function() {
		if( this.attached ) {
			this.attached = false;
			
			// remove the elements we inserted
			this.imgWrap.parentNode.insertBefore( this.img, this.imgWrap );
			this.imgWrap.parentNode.removeChild( this.imgWrap );
			
			// remove the event observers
			Event.stopObserving( this.dragArea, 'mousedown', this.startDragBind );
			Event.stopObserving( document, 'mousemove', this.onDragBind );
			if (this.options.onMouseMove) {
			    Event.stopObserving( document, 'mousemove', this.onMouseMoveBind );		
			    Event.stopObserving( document, 'mouseout', this.onMouseOutBind );		
			}


            if(this.options.onTouchStart){
                Event.stopObserving(this.dragArea,'touchstart',this.onTouchStartBind);
            }
            if(this.options.onTouchMove){
              Event.stopObserving(this.dragArea,'touchmove',this.onTouchMoveBind);
            }
            if(this.options.onTouchEnd){
              Event.stopObserving(this.dragArea,'touchend',this.onTouchEndBind);
            }
            
            if(this.options.onGestureStart){
              Event.stopObserving(this.dragArea,'gesturestart',this.onGestureStartBind);
            }
            if(this.options.onGestureMove){
              Event.stopObserving(this.dragArea,'gesturemove',this.onGestureMoveBind);
            }
            if(this.options.onGestureEnd){
              Event.stopObserving(this.dragArea,'gestureend',this.onGestureEndBind);
            }
            if(this.options.onOrientationChange){
              Event.stopObserving(this.dragArea,'orientationchange', this.onOrientationChange);
            }

 
			Event.stopObserving( document, 'mouseup', this.endCropBind );
//			Event.stopObserving( this.imgWrap, 'mouseup', this.endCropBind );
			Event.stopObserving( this.dragArea, 'dblclick', this.dblClickBind );
			Event.stopObserving( this.eventArea, 'dblclick', this.dblSelClickBind );
/*			if (Ext) {
			    Ext.EventManager.removeListener(this.dragArea, "mousewheel", this.mouseScrollExt);
			} else {*/
			    Event.stopObserving( this.dragArea, 'DOMMouseScroll', this.mouseScrollBind );
			    Event.stopObserving( this.dragArea, 'mousewheel', this.mouseScrollBind );
/*			}*/
			Event.stopObserving( this.applyButton, 'mousedown', this.applyClickBind );
			Event.stopObserving( this.saveButton, 'mousedown', this.saveClickBind );
		
			for (var i = 0; i < this.buttonClickBind.length; i++) {
			    Event.stopObserving( this.buttons[i], 'mousedown', this.buttonClickBind[i] );
			}

			//buttons
			
			this.registerHandles( false );
			if( this.options.captureKeys ) Event.stopObserving( document, 'keypress', this.keysBind );
		}
	},
	
	linkButtons: function() {
		    if ((this.options.onApplyClick)&&(!this.applyLinked)) {
			if (Prototype.Browser.IE) {
			    $( this.applyButton ).show();
			} else {
			    this.dragArea.appendChild(this.applyButton);
			}
			this.applyLinked = true;
		    }
		    if ((this.options.onSaveClick)&&(!this.saveLinked)) {
			if (Prototype.Browser.IE) {
			    $( this.saveButton ).show();
			} else {
			    this.dragArea.appendChild(this.saveButton);
			}
			this.saveLinked = true;
		    }
		    
		    if (!this.buttonsLinked) {
		        if (Prototype.Browser.IE) {
			    for (var i = 0; i < this.buttons.length; i++) {
				$( this.buttons[i] ).show();
			    }
			} else {
			    for (var i = 0; i < this.buttons.length; i++) {
				this.dragArea.appendChild(this.buttons[i]);
			    }
			}
			this.buttonsLinked = true;
		    }

	},
	
	unlinkButtons: function() {
		// hide apply button
		if (this.applyLinked) {
		    if (Prototype.Browser.IE) {
			$( this.applyButton ).hide();
		    } else {
			this.dragArea.removeChild(this.applyButton);
		    }
		    this.applyLinked = false;
		}

		if (this.saveLinked) {
		    if (Prototype.Browser.IE) {
			$( this.saveButton ).hide();
		    } else {
		        this.dragArea.removeChild(this.saveButton);
		    }
		    this.saveLinked = false;
		}
		
		if (this.buttonsLinked) {
		    if (Prototype.Browser.IE) {
			for (var i = 0; i < this.buttons.length; i++) {
			    $( this.buttons[i] ).hide();
			}
		    } else {
			for (var i = 0; i < this.buttons.length; i++) {
			    this.dragArea.removeChild(this.buttons[i]);
			}
		    }
		    this.buttonsLinked = false;
		}
	},
	
	/**
	 * Resets the cropper, can be used either after being removed or any time you wish
	 * 
	 * @access public
	 * @return void
	 */
	reset: function() {
		if( !this.attached ) this.onLoad();
		else this.setParams();
		this.endCrop();
	},
	
	/**
	 * Resets the cropper selection
	 * 
	 * @access public
	 * @return void
	 */
	clear: function() {
		$( this.north ).setStyle( { height: 0 } );
		$( this.north_east ).setStyle( { height: 0 } );
		$( this.north_west ).setStyle( { height: 0 } );
		$( this.east ).setStyle( { width: 0, height: 0 } );
		$( this.south ).setStyle( { height: 0 } );
		$( this.south_east ).setStyle( { height: 0 } );
		$( this.south_west ).setStyle( { height: 0 } );
		$( this.west ).setStyle( { width: 0, height: 0 } );

		// hide the select area
		$( this.selArea ).hide();
		if (this.handleArea) $( this.handleArea).hide();

		this.unlinkButtons();
		//
		this.selected = false;
		this.altered = false;
		
        	this.setAreaCoords( { x1: this.options.margins.left, y1: this.options.margins.top, x2: this.options.margins.left, y2: this.options.margins.top }, false, false, null );
		this.drawArea();
	},
	
	/**
	 * Handles the key functionality, currently just using arrow keys to move, if the user
	 * presses shift then the area will move by 10 pixels
	 */
	handleKeys: function( e ) {
		var dir = { x: 0, y: 0 }; // direction to move it in & the amount in pixels
		if( !this.dragging ) {
			
			// catch the arrow keys
			switch( e.keyCode ) {
				case( 37 ) : // left
					dir.x = -1;
					break;
				case( 38 ) : // up
					dir.y = -1;
					break;
				case( 39 ) : // right
					dir.x = 1;
					break
				case( 40 ) : // down
					dir.y = 1;
					break;
			}
			
			if( dir.x != 0 || dir.y != 0 ) {
				// if shift is pressed then move by 10 pixels
				if( e.shiftKey ) {
					dir.x *= 10;
					dir.y *= 10;
				}
				
				this.moveArea( [ this.areaCoords.x1 + dir.x, this.areaCoords.y1 + dir.y ] );
				Event.stop( e ); 
			}
		}
	},
	
	/**
	 * Calculates the width from the areaCoords
	 * 
	 * @access private
	 * @return int
	 */
	calcW: function() {
		return (this.areaCoords.x2 - this.areaCoords.x1)
	},
	
	/**
	 * Calculates the height from the areaCoords
	 * 
	 * @access private
	 * @return int
	 */
	calcH: function() {
		return (this.areaCoords.y2 - this.areaCoords.y1)
	},
	
	/**
	 * Moves the select area to the supplied point (assumes the point is x1 & y1 of the select area)
	 * 
	 * @access public
	 * @param array Point for x1 & y1 to move select area to
	 * @return void
	 */
	moveArea: function( point ) {
		// dump( 'moveArea        : ' + point[0] + ',' + point[1] + ',' + ( point[0] + ( this.areaCoords.x2 - this.areaCoords.x1 ) ) + ',' + ( point[1] + ( this.areaCoords.y2 - this.areaCoords.y1 ) ) + '\n' );
		this.altered = true;
		this.setAreaCoords( 
			{
				x1: point[0], 
				y1: point[1],
				x2: point[0] + this.calcW(),
				y2: point[1] + this.calcH()
			},
			true,
			false
		);
		this.drawArea();
	},

	/**
	 * Clones a co-ordinates object, stops problems with handling them by reference
	 * 
	 * @access private
	 * @param obj Coordinate object x1, y1, x2, y2
	 * @return obj Coordinate object x1, y1, x2, y2
	 */
	cloneCoords: function( coords ) {
		return { x1: coords.x1, y1: coords.y1, x2: coords.x2, y2: coords.y2 };
	},

	/**
	 * Sets the select coords to those provided but ensures they don't go
	 * outside the bounding box
	 * 
	 * @access private
	 * @param obj Coordinates x1, y1, x2, y2
	 * @param boolean Whether this is a move
	 * @param boolean Whether to apply squaring
	 * @param obj Direction of mouse along both axis x, y ( -1 = negative, 1 = positive ) only required when moving etc.
	 * @param string The current resize handle || null
	 * @return void
	 */
	setAreaCoords: function( coords, moving, square, direction, resizeHandle ) {
		// dump( 'setAreaCoords (in) : ' + coords.x1 + ',' + coords.y1 + ',' + coords.x2 + ',' + coords.y2 );
		if( moving ) {
			// if moving
			var targW = coords.x2 - coords.x1;
			var targH = coords.y2 - coords.y1;
			
			// ensure we're within the bounds
			if( coords.x1 < this.options.margins.left ) {
				coords.x1 = this.options.margins.left;
				coords.x2 = this.options.margins.left + targW;
			}
			if( coords.y1 < this.options.margins.top ) {
				coords.y1 = this.options.margins.top;
				coords.y2 = this.options.margins.top + targH;
			}
			if( coords.x2 > (this.imgW - this.options.margins.right) ) {
				coords.x2 = this.imgW - this.options.margins.right;
				coords.x1 = coords.x2 - targW;
			}
			if( coords.y2 > (this.imgH - this.options.margins.bottom) ) {
				coords.y2 = this.imgH - this.options.margins.bottom;
				coords.y1 = coords.y2 - targH;
			}			
		} else {
			// ensure we're within the bounds
			if( coords.x1 < this.options.margins.left ) coords.x1 = this.options.margins.left;
			if( coords.y1 < this.options.margins.top ) coords.y1 = this.options.margins.top;
			if( coords.x2 > (this.imgW - this.options.margins.right) ) coords.x2 = this.imgW - this.options.margins.right;
			if( coords.y2 > (this.imgH - this.options.margins.bottom) ) coords.y2 = this.imgH - this.options.margins.bottom;
			
			// This is passed as null in onload
			if( direction != null ) {
								
				// apply the ratio or squaring where appropriate
				if( this.ratioX > 0 ) this.applyRatio( coords, { x: this.ratioX, y: this.ratioY }, direction, resizeHandle );
				else if( square ) this.applyRatio( coords, { x: 1, y: 1 }, direction, resizeHandle );
										
				var mins = [ this.options.minWidth, this.options.minHeight ]; // minimum dimensions [x,y]			
				var maxs = [ this.options.maxWidth, this.options.maxHeight ]; // maximum dimensions [x,y]
		
				// apply dimensions where appropriate
				if( mins[0] > 0 || mins[1] > 0 || maxs[0] > 0 || maxs[1] > 0) {
				
					var coordsTransX 	= { a1: coords.x1, a2: coords.x2 };
					var coordsTransY 	= { a1: coords.y1, a2: coords.y2 };
					var boundsX			= { min: 0, max: this.imgW };
					var boundsY			= { min: 0, max: this.imgH };
					
					// handle squaring properly on single axis minimum dimensions
					if( (mins[0] != 0 || mins[1] != 0) && square ) {
						if( mins[0] > 0 ) mins[1] = mins[0];
						else if( mins[1] > 0 ) mins[0] = mins[1];
					}
					
					if( (maxs[0] != 0 || maxs[0] != 0) && square ) {
						// if we have a max x value & it is less than the max y value then we set the y max to the max x (so we don't go over the minimum maximum of one of the axes - if that makes sense)
						if( maxs[0] > 0 && maxs[0] <= maxs[1] ) maxs[1] = maxs[0];
						else if( maxs[1] > 0 && maxs[1] <= maxs[0] ) maxs[0] = maxs[1];
					}
					
					if( mins[0] > 0 ) this.applyDimRestriction( coordsTransX, mins[0], direction.x, boundsX, 'min' );
					if( mins[1] > 1 ) this.applyDimRestriction( coordsTransY, mins[1], direction.y, boundsY, 'min' );
					
					if( maxs[0] > 0 ) this.applyDimRestriction( coordsTransX, maxs[0], direction.x, boundsX, 'max' );
					if( maxs[1] > 1 ) this.applyDimRestriction( coordsTransY, maxs[1], direction.y, boundsY, 'max' );
					
					coords = { x1: coordsTransX.a1, y1: coordsTransY.a1, x2: coordsTransX.a2, y2: coordsTransY.a2 };
				}
				
			}
		}
		
//		dump( 'setAreaCoords (out) : ' + coords.x1 + ',' + coords.y1 + ',' + coords.x2 + ',' + coords.y2 + '\n' );
		this.areaCoords = coords;
	},
	
	/**
	 * Applies the supplied dimension restriction to the supplied coordinates along a single axis
	 * 
	 * @access private
	 * @param obj Single axis coordinates, a1, a2 (e.g. for the x axis a1 = x1 & a2 = x2)
	 * @param int The restriction value
	 * @param int The direction ( -1 = negative, 1 = positive )
	 * @param obj The bounds of the image ( for this axis )
	 * @param string The dimension restriction type ( 'min' | 'max' )
	 * @return void
	 */
	applyDimRestriction: function( coords, val, direction, bounds, type ) {
		var check;
		if( type == 'min' ) check = ( ( coords.a2 - coords.a1 ) < val );
		else check = ( ( coords.a2 - coords.a1 ) > val );
		if( check ) {
			if( direction == 1 ) coords.a2 = coords.a1 + val;
			else coords.a1 = coords.a2 - val;
			
			// make sure we're still in the bounds (not too pretty for the user, but needed)
			if( coords.a1 < bounds.min ) {
				coords.a1 = bounds.min;
				coords.a2 = val;
			} else if( coords.a2 > bounds.max ) {
				coords.a1 = bounds.max - val;
				coords.a2 = bounds.max;
			}
		}
	},
	
	/**
	 * Applies the supplied ratio to the supplied coordinates
	 * 
	 * @access private
	 * @param obj Coordinates, x1, y1, x2, y2
	 * @param obj Ratio, x, y
	 * @param obj Direction of mouse, x & y : -1 == negative 1 == positive
	 * @param string The current resize handle || null
	 * @return void
	 */
	applyRatio : function( coords, ratio, direction, resizeHandle ) {
		// dump( 'direction.y : ' + direction.y + '\n');
		var newCoords;
		if( resizeHandle == 'N' || resizeHandle == 'S' ) {
			// dump( 'north south \n');
			// if moving on either the lone north & south handles apply the ratio on the y axis
			newCoords = this.applyRatioToAxis( 
				{ a1: coords.y1, b1: coords.x1, a2: coords.y2, b2: coords.x2 },
				{ a: ratio.y, b: ratio.x },
				{ a: direction.y, b: direction.x },
				{ min: 0, max: this.imgW }
			);
			coords.x1 = newCoords.b1;
			coords.y1 = newCoords.a1;
			coords.x2 = newCoords.b2;
			coords.y2 = newCoords.a2;
		} else {
			// otherwise deal with it as if we're applying the ratio on the x axis
			newCoords = this.applyRatioToAxis( 
				{ a1: coords.x1, b1: coords.y1, a2: coords.x2, b2: coords.y2 },
				{ a: ratio.x, b: ratio.y },
				{ a: direction.x, b: direction.y },
				{ min: 0, max: this.imgH }
			);
			coords.x1 = newCoords.a1;
			coords.y1 = newCoords.b1;
			coords.x2 = newCoords.a2;
			coords.y2 = newCoords.b2;
		}
		
	},
	
	/**
	 * Applies the provided ratio to the provided coordinates based on provided direction & bounds,
	 * use to encapsulate functionality to make it easy to apply to either axis. This is probably
	 * quite hard to visualise so see the x axis example within applyRatio()
	 * 
	 * Example in parameter details & comments is for requesting applying ratio to x axis.
	 * 
	 * @access private
	 * @param obj Coords object (a1, b1, a2, b2) where a = x & b = y in example
	 * @param obj Ratio object (a, b) where a = x & b = y in example
	 * @param obj Direction object (a, b) where a = x & b = y in example
	 * @param obj Bounds (min, max)
	 * @return obj Coords object (a1, b1, a2, b2) where a = x & b = y in example
	 */
	applyRatioToAxis: function( coords, ratio, direction, bounds ) {
		var newCoords = Object.extend( coords, {} );
		var calcDimA = newCoords.a2 - newCoords.a1;			// calculate dimension a (e.g. width)
		var targDimB = Math.floor( calcDimA * ratio.b / ratio.a );	// the target dimension b (e.g. height)
		var targB;											// to hold target b (e.g. y value)
		var targDimA;                                		// to hold target dimension a (e.g. width)
		var calcDimB = null;								// to hold calculated dimension b (e.g. height)
		
		// dump( 'newCoords[0]: ' + newCoords.a1 + ',' + newCoords.b1 + ','+ newCoords.a2 + ',' + newCoords.b2 + '\n');
				
		if( direction.b == 1 ) {							// if travelling in a positive direction
			// make sure we're not going out of bounds
			targB = newCoords.b1 + targDimB;
			if( targB > bounds.max ) {
				targB = bounds.max;
				calcDimB = targB - newCoords.b1;			// calcuate dimension b (e.g. height)
			}
			
			newCoords.b2 = targB;
		} else {											// if travelling in a negative direction
			// make sure we're not going out of bounds
			targB = newCoords.b2 - targDimB;
			if( targB < bounds.min ) {
				targB = bounds.min;
				calcDimB = targB + newCoords.b2;			// calcuate dimension b (e.g. height)
			}
			newCoords.b1 = targB;
		}
		
		// dump( 'newCoords[1]: ' + newCoords.a1 + ',' + newCoords.b1 + ','+ newCoords.a2 + ',' + newCoords.b2 + '\n');
			
		// apply the calculated dimensions
		if( calcDimB != null ) {
			targDimA = Math.floor( calcDimB * ratio.a / ratio.b );
			
			if( direction.a == 1 ) newCoords.a2 = newCoords.a1 + targDimA;
			else newCoords.a1 = newCoords.a1 = newCoords.a2 - targDimA;
		}
		
		// dump( 'newCoords[2]: ' + newCoords.a1 + ',' + newCoords.b1 + ','+ newCoords.a2 + ',' + newCoords.b2 + '\n');
			
		return newCoords;
	},
	
	/**
	 * Draws the select area
	 * 
	 * @access private
	 * @return void
	 */
	drawArea: function( ) {	
		/*
		 * NOTE: I'm not using the Element.setStyle() shortcut as they make it 
		 * quite sluggish on Mac based browsers
		 */
		// dump( 'drawArea        : ' + this.areaCoords.x1 + ',' + this.areaCoords.y1 + ',' + this.areaCoords.x2 + ',' + this.areaCoords.y2 + '\n' );
		var areaWidth     = this.calcW();
		var areaHeight    = this.calcH();

		//alert(this.options.verticalCrop);
		if (!this.options.verticalCrop) {
		    areaHeight = 0;
		    this.areaCoords.y2 = this.areaCoords.y1;
		}
	
		/*
		 * Calculate all the style strings before we use them, allows reuse & produces quicker
		 * rendering (especially noticable in Mac based browsers)
		 */
		var px = 'px';
		var params = [
			this.areaCoords.x1 + px, 	// the left of the selArea
			this.areaCoords.y1 + px,		// the top of the selArea
			areaWidth + px,					// width of the selArea
			areaHeight + px,					// height of the selArea
			this.areaCoords.x2 + px,		// bottom of the selArea
			this.areaCoords.y2 + px,		// right of the selArea
			(this.img.width - this.areaCoords.x2) + px,	// right edge of selArea
			(this.img.height - this.areaCoords.y2) + px	// bottom edge of selArea
		];
				
		// do the select area
		var areaStyle				= this.selArea.style;
		areaStyle.left				= params[0];
		areaStyle.top				= params[1];
		areaStyle.width				= params[2];
		areaStyle.height			= params[3];

		if (this.handleArea) {		
		    var handleStyle			= this.handleArea.style;
		    handleStyle.left			= params[0];
		    handleStyle.top			= params[1];
		    handleStyle.width			= params[2];
		    handleStyle.height			= params[3];
		}
			  	
		// position the north, east, south & west handles
		var horizHandlePos = Math.ceil( (areaWidth - 6) / 2 ) + px;
		var vertHandlePos = Math.ceil( (areaHeight - 6) / 2 ) + px;
		
		this.handleN.style.left 	= horizHandlePos;
		this.handleE.style.top 		= vertHandlePos;
		this.handleS.style.left 	= horizHandlePos;
		this.handleW.style.top		= vertHandlePos;
		
		if ((areaWidth < this.options.allWidth)&&(areaHeight<this.options.allHeight)) {
		    this.unlinkButtons();
		    
		    this.east.className = "imgCrop_overlay";
		    this.west.className = "imgCrop_overlay";
		    this.north.className = "imgCrop_overlay";
		    this.south.className = "imgCrop_overlay";
		} else if (areaWidth < this.options.allWidth) {
		    this.linkButtons();
		    
		    this.east.className = "imgCrop_selArea";
		    this.west.className = "imgCrop_selArea";
		    this.north.className = "imgCrop_overlay";
		    this.south.className = "imgCrop_overlay";
		} else if (areaHeight < this.options.allHeight) {
		    this.linkButtons();
		    
		    this.east.className = "imgCrop_overlay";
		    this.west.className = "imgCrop_overlay";
		    this.north.className = "imgCrop_selArea";
		    this.south.className = "imgCrop_selArea";
		} else {
		    this.linkButtons();
		    
		    this.east.className = "imgCrop_overlay";
		    this.west.className = "imgCrop_overlay";
		    this.north.className = "imgCrop_overlay";
		    this.south.className = "imgCrop_overlay";
		}
		
		// draw the four overlays
		var northStyle			= this.north.style;
		northStyle.height 		= params[1];
		northStyle.left			= params[0];
		northStyle.width		= params[2];
		
		var neStyle			= this.north_east.style;
		neStyle.height			= params[1];
		neStyle.width			= params[0];

		var nwStyle			= this.north_west.style;
		nwStyle.height			= params[1];
		nwStyle.left			= params[4];
		nwStyle.width			= params[6];
		
		var eastStyle 			= this.east.style;
		eastStyle.top			= params[1];
		eastStyle.height		= params[3];
		eastStyle.left			= params[4];
	        eastStyle.width			= params[6];
	   
	   	var southStyle 			= this.south.style;
	   	southStyle.top			= params[5];
	   	southStyle.height		= params[7];
		southStyle.left			= params[0];
		southStyle.width		= params[2];

		var seStyle			= this.south_east.style;
	   	seStyle.top			= params[5];
	   	seStyle.height			= params[7];
		seStyle.width			= params[0];

		var swStyle			= this.south_west.style;
	   	swStyle.top			= params[5];
	   	swStyle.height			= params[7];
		swStyle.left			= params[4];
		swStyle.width			= params[6];

		var westStyle       		= this.west.style;
		westStyle.top			= params[1];
		westStyle.height		= params[3];
	   	westStyle.width			= params[0];

		
		var buttonWidth = 0;
		var buttonHeight = 0;
		
		if (this.applyButton.parentNode) {
		    var applyStyle = this.applyButton.style;
		    applyStyle.left = params[4];
		    applyStyle.top = params[5];

		    buttonWidth += 1 + this.applyButton.offsetWidth;
		    buttonHeight += 1 + this.applyButton.offsetHeight;
		}

		if (this.saveButton.parentNode) {
		    var saveStyle = this.saveButton.style;
		    saveStyle.left = (this.areaCoords.x2 - buttonWidth) + px;
		    saveStyle.top = params[5];

		    buttonWidth += 1 + this.saveButton.offsetWidth;
		}
		
		for (i = 0; i < this.buttons.length; i++) {
    		    if (this.buttons[i].parentNode) {
			var buttonStyle = this.buttons[i].style;
			
			if ((typeof this.options.extraButtons[i].vertical != "undefined")&&(this.options.extraButtons[i].vertical)) {
			    buttonStyle.left = params[4];
			    buttonStyle.top = (this.areaCoords.y2 - buttonHeight) + px;
			    buttonHeight += 1 + this.buttons[i].offsetWidth;
			} else {
			    buttonStyle.left = (this.areaCoords.x2 - buttonWidth) + px;
			    buttonStyle.top = params[5];
			    buttonWidth += 1 + this.buttons[i].offsetWidth;
			}
		    }
		}
	   	
		// call the draw method on sub classes
		this.subDrawArea();
		
		this.forceReRender();
	},
	
	/**
	 * Force the re-rendering of the selArea element which fixes rendering issues in Safari 
	 * & IE PC, especially evident when re-sizing perfectly vertical using any of the south handles
	 * 
	 * @access private
	 * @return void
	 */
	forceReRender: function() {
		if( this.isIE || this.isWebKit) {
			var n = document.createTextNode(' ');
			var d,el,fixEL,i;
		
			if( this.isIE ) fixEl = this.selArea;
			else if( this.isWebKit ) {
				fixEl = document.getElementsByClassName( 'imgCrop_marqueeSouth', this.imgWrap )[0];
				/* we have to be a bit more forceful for Safari, otherwise the the marquee &
				 * the south handles still don't move
				 */ 
				d = Builder.node( 'div', '' );
				d.style.visibility = 'hidden';
				
				var classList = ['SE','S','SW'];
				for( i = 0; i < classList.length; i++ ) {
					el = document.getElementsByClassName( 'imgCrop_handle' + classList[i], this.selArea )[0];
					if( el.childNodes.length ) el.removeChild( el.childNodes[0] );
					el.appendChild(d);
				}
			}
			fixEl.appendChild(n);
			fixEl.removeChild(n);
		}
	},
	
	/**
	 * Starts the resize
	 * 
	 * @access private
	 * @param obj Event
	 * @return void
	 */
	startResize: function( e ) {
		this.startCoords = this.cloneCoords( this.areaCoords );
		
		this.altered = true;
		this.resizing = true;

		this.resizeHandle = Event.element( e ).classNames().toString().replace(/([^N|NE|E|SE|S|SW|W|NW])+/, '');
		// dump( 'this.resizeHandle : ' + this.resizeHandle + '\n' );
		Event.stop( e );
	},
	
  onTouchStart: function(e){
    this.Prevent(e);
    var pos = this.getCurTouchPos(e);
      this.options.onTouchStart(e,pos);
  },
  onTouchMove: function(e){
    this.Prevent(e);
    var xpos = this.getCurTouchPos(e);
      this.options.onTouchMove(e,xpos);
  },
  onTouchEnd: function(e){
    this.options.onTouchEnd(e);
  },
  onGestureStart: function(e){
    this.options.onGestureStart(e);
  },
  onGestureMove: function(e){
    this.Prevent(e); 
    var scale = e.scale;
    this.options.onGestureMove(e,scale);
  },
  onGestureEnd: function(e){
    var scale = e.scale;
    var angle = e.rotation;
    this.options.onGestureEnd(e,scale,angle);
  },
  
  onOrientationChange: function(e){
    this.options.onOrientationChange(e);
  },
  
  Prevent: function(e){
    e.preventDefault();
  },
  
  /** Gets touchposition
   * returns an Object.
   */
  getCurTouchPos: function(e){
    var curpos = new Object();
    var el = this.imgWrap, wrapOffsets = Position.cumulativeOffset(el);

    curpos.x = e.targetTouches[0].clientX - wrapOffsets[0];
    curpos.y = e.targetTouches[0].clientY - wrapOffsets[1];

    
    return curpos;

    
  },
/**
	 * Starts the drag
	 * 
	 * @access private
	 * @param obj Event
	 * @return void
	 */
	startDrag: function( e ) {	
	    if (!this.dragging) {
		var curtime = new Date();
		this.start_time = curtime.getTime();
	    }
		
	    this.selArea.show();
	    if (this.handleArea) this.handleArea.show();
	    this.clickCoords = this.getCurPos( e );

	    this.setAreaCoords( { x1: this.clickCoords.x, y1: this.clickCoords.y, x2: this.clickCoords.x, y2: this.clickCoords.y }, false, false, null );

	    this.altered = true;
    	    this.dragging = true;

	    if (this.options.onClick)
		this.dragged = false;
	    else
    		this.onDrag( e ); // incase the user just clicks once after already making a selection
    	    Event.stop( e );
	},
	
	/**
	 * Gets the current cursor position relative to the image
	 * 
	 * @access private
	 * @param obj Event
	 * @return obj x,y pixels of the cursor
	 */
	getCurPos: function( e ) {
		// get the offsets for the wrapper within the document
		var el = this.imgWrap, wrapOffsets = Position.cumulativeOffset( el );
		
		// remove any scrolling that is applied to the wrapper (this may be buggy) - don't count the scroll on the body as that won't affect us
/* DS: Causes problems in Opera9 (IE, Seamonkey are OK)
		while( el.nodeName != 'BODY' ) {
			wrapOffsets[1] -= el.scrollTop  || 0;
			wrapOffsets[0] -= el.scrollLeft || 0;
			el = el.parentNode;
		}
*/
		return curPos = { 
			x: Event.pointerX(e) - wrapOffsets[0],
			y: Event.pointerY(e) - wrapOffsets[1]
		}
	},
  	
  	/**
  	 * Performs the drag for both resize & inital draw dragging
  	 * 
  	 * @access private
	 * @param obj Event
	 * @return void
	 */
  	onDrag: function( e ) {
  		if( this.dragging || this.resizing ) {	
			this.dragged = true;
  		
  			var resizeHandle = null;
  			var curPos = this.getCurPos( e );			
			var newCoords = this.cloneCoords( this.areaCoords );
  			var direction = { x: 1, y: 1 };
  	  					
		    if( this.dragging ) {
		    	if( curPos.x < this.clickCoords.x ) direction.x = -1;
		    	if( curPos.y < this.clickCoords.y ) direction.y = -1;
		    	
				this.transformCoords( curPos.x, this.clickCoords.x, newCoords, 'x' );
				this.transformCoords( curPos.y, this.clickCoords.y, newCoords, 'y' );
		    } else if( this.resizing ) {
				resizeHandle = this.resizeHandle;			
				// do x movements first
				if( resizeHandle.match(/E/) ) {
					// if we're moving an east handle
					this.transformCoords( curPos.x, this.startCoords.x1, newCoords, 'x' );	
					if( curPos.x < this.startCoords.x1 ) direction.x = -1;
				} else if( resizeHandle.match(/W/) ) {
					// if we're moving an west handle
					this.transformCoords( curPos.x, this.startCoords.x2, newCoords, 'x' );
					if( curPos.x < this.startCoords.x2 ) direction.x = -1;
				}
									
				// do y movements second
				if( resizeHandle.match(/N/) ) {
					// if we're moving an north handle	
					this.transformCoords( curPos.y, this.startCoords.y2, newCoords, 'y' );
					if( curPos.y < this.startCoords.y2 ) direction.y = -1;
				} else if( resizeHandle.match(/S/) ) {
					// if we're moving an south handle
					this.transformCoords( curPos.y, this.startCoords.y1, newCoords, 'y' );	
					if( curPos.y < this.startCoords.y1 ) direction.y = -1;
				}	
							
			}
		
			this.setAreaCoords( newCoords, false, e.shiftKey, direction, resizeHandle );
			this.drawArea();

			if (this.options.onMouseMove) {
			    var pos = this.getMousePosition(e);
			    if ((pos.x > 0)&&(pos.y > 0)) {
				this.options.onMouseMove(pos, this.dragging, this.resizing);
			    }
			}

			Event.stop( e ); // stop the default event (selecting images & text) in Safari & IE PC
		}
	},

  	onMouseMove: function( e ) {
	    var pos = this.getMousePosition(e);
	    if ((pos.x>0)&&(pos.y>0)) {
	        this.options.onMouseMove(pos, this.dragging, this.resizing);
	    }
	},

  	onMouseOut: function( e ) {
	    this.options.onMouseMove(undefined, this.dragging, this.resizing);
	},
	
	/**
	 * Applies the appropriate transform to supplied co-ordinates, on the
	 * defined axis, depending on the relationship of the supplied values
	 * 
	 * @access private
	 * @param int Current value of pointer
	 * @param int Base value to compare current pointer val to
	 * @param obj Coordinates to apply transformation on x1, x2, y1, y2
	 * @param string Axis to apply transformation on 'x' || 'y'
	 * @return void
	 */
	transformCoords : function( curVal, baseVal, coords, axis ) {
		var newVals = [ curVal, baseVal ];
		if( curVal > baseVal ) newVals.reverse();
		coords[ axis + '1' ] = newVals[0];
		coords[ axis + '2' ] = newVals[1];		
	},
	
	/**
	 * Ends the crop & passes the values of the select area on to the appropriate 
	 * callback function on completion of a crop
	 * 
	 * @access private
	 * @return void
	 */
	endCrop : function(e) {
		var click = false;
		var w, h;
		
		if ((this.dragging)&&(this.start_time)) {
		    var curtime = new Date();
		    var duration = curtime.getTime() - this.start_time;
		    
		    if (duration < this.options.clickDuration) click = true;
		} 

		
		this.dragging = false;
		this.resizing = false;
		
		w = this.calcW();
		h = this.calcH();
		
		if (click) {
		    if ((w>this.options.allWidth)||(h>this.options.allHeight)) click = false;
		}
		
		if (click) {
		    this.justClick(e);
//		    setTimeout(this.postponeClick(this, e), 100);
		} else if ((w>this.options.allWidth)||(h>this.options.allHeight)) {
		    if (!this.dragged)	this.onDrag( e );
		    
		    if (this.altered) {
			this.options.onEndCrop(
			    this.areaCoords,
			    {
				width: w, 
				height: h 
			    }
			);
			this.altered = false;
		    }
		    this.selected = true;
		} else {
		    this.clear();
		}
	},
	

        getMousePosition : function (ev, event_type) {
		if ((this.fix_gecko_mouse_position)&&(event_type == this.SCROLL_EVENT)) {
			/* 
			   This is due to the bug in Gecko engine
			    Should be fixed in Gecko 1.9
			    Info: https://bugzilla.mozilla.org/show_bug.cgi?id=352179
			*/
		    var curpos = new Object();
		    curpos.x = ev.target.offsetLeft + (ev.clientX-8)/15; // + document.body.marginLeft
		    curpos.y = ev.target.offsetTop + (ev.clientY-8)/15;
		} else {
		    var curpos = this.getCurPos( ev );
		}

		return curpos;
	},

	justClick : function(e) {
/*
		    var x = this.areaCoords.x1;
		    var y = this.areaCoords.y1;
*/
		    var curpos = this.getMousePosition (e);


		    if (this.selected) {
			this.options.onCancelCrop();
			this.clear();
		    } else if (this.options.onClick) {
			this.options.onClick(e, curpos);
			/*
			    This prevents global double clicks from occuring,
			    the problem is somewhere drawarea, code:
				var swStyle			= this.south_west.style;
			  	swStyle.top			= params[5];
			       	swStyle.height			= params[7];
			    but there is no bug, probably just dragArea somehow
			    modified, so event is not occured. Not sure.
			    
			    this.clear();
			*/
		    }
	
	},
	
	/**
	 * Passes the values of the select area on to the appropriate 
	 * callback function on double click in cropping area
	 * 
	 * @access private
	 * @return void
	 */
	dblSelClick : function(ev) {
		this.dragging = false;
		this.resizing = false;
		
		var w = this.calcW();
		var h = this.calcH();
		
		if ((w > this.options.allWidth)&&(h > this.options.allHeight)) {
		    var curpos = this.getMousePosition (ev);
		    
		    this.options.onDblSelClick(
			this.areaCoords,
			{
				width: w, 
				height: h 
			},
			curpos
		    );
		}

		Event.stop( ev );
	},
	
	dblClick : function(ev) {
	    var curpos = this.getMousePosition (ev);
	    this.options.onDblClick(ev, curpos);
	},
    
	mouseScroll : function(ev) {
	    if (this.options.onMouseScroll) {
		this.dragging = false;
		this.resizing = false;
		
		var delta = domGetScrollEventDelta(ev);
		var curpos = this.getMousePosition (ev, this.SCROLL_EVENT);
		this.options.onMouseScroll(delta, curpos);
	    }

	},
/*
	mouseScrollExt: function(e) {
		this.options.onMouseScroll(-e.getWheelDelta(),
			this.areaCoords.x1,
			this.areaCoords.y1
		);
	},
*/

	/**
	 * Passes the values of the select area on to the appropriate 
	 * callback function on a click on apply button
	 * 
	 * @access private
	 * @return void
	 */
	applyClick : function(ev) {
		this.dragging = false;
		this.resizing = false;
		
		this.options.onApplyClick(
			this.areaCoords,
			{
				width: this.calcW(), 
				height: this.calcH() 
			}
		);
		//ev.cancelBubble = true;
		Event.stop( ev );
	},
	
	/**
	 * Calls save callback
	 * 
	 * @access private
	 * @return void
	 */
	saveClick : function(ev) {
		this.dragging = false;
		this.resizing = false;
		
		this.options.onSaveClick(
			this.areaCoords,
			{
				width: this.calcW(), 
				height: this.calcH() 
			}
		);
		//ev.cancelBubble = true;
		Event.stop( ev );
	},

	/**
	 * Calls button callback
	 * 
	 * @access private
	 * @return void
	 */
	buttonClick : function (self, id) {
	    return function(ev) {
		self.dragging = false;
		self.resizing = false;

		self.options.extraButtons[id].onClick(
			self.areaCoords,
			{
				width: self.calcW(), 
				height: self.calcH() 
			}
		);

		if ((typeof self.options.extraButtons[id].keep_selection != "undefined")&&(!self.options.extraButtons[id].keep_selection)) {
		    this.options.onCancelCrop();
		    this.clear();
		}

		//ev.cancelBubble = true;
		Event.stop( ev );
	    }
	},
	
	/**
	 * Abstract method called on the end of initialization
	 * 
	 * @access private
	 * @abstract
	 * @return void
	 */
	subInitialize: function() {},
	
	/**
	 * Abstract method called on the end of drawArea()
	 * 
	 * @access private
	 * @abstract
	 * @return void
	 */
	subDrawArea: function() {}
};




/**
 * Extend the Cropper.Img class to allow for presentation of a preview image of the resulting crop,
 * the option for displayOnInit is always overridden to true when displaying a preview image
 * 
 * Usage:
 * 	@param obj Image element to attach to
 * 	@param obj Optional options:
 * 		- see Cropper.Img for base options
 * 		
 * 		- previewWrap obj
 * 			HTML element that will be used as a container for the preview image		
 */
Cropper.ImgWithPreview = Class.create();

Object.extend( Object.extend( Cropper.ImgWithPreview.prototype, Cropper.Img.prototype ), {
	
	/**
	 * Implements the abstract method from Cropper.Img to initialize preview image settings.
	 * Will only attach a preview image is the previewWrap element is defined and the minWidth
	 * & minHeight options are set.
	 * 
	 * @see Croper.Img.subInitialize
	 */
	subInitialize: function() {
		/**
		 * Whether or not we've attached a preview image
		 * @var boolean
		 */
		this.hasPreviewImg = false;
		if( typeof(this.options.previewWrap) != 'undefined' 
			&& this.options.minWidth > 0 
			&& this.options.minHeight > 0
		) {
			/**
			 * The preview image wrapper element
			 * @var obj HTML element
			 */
			this.previewWrap 	= $( this.options.previewWrap );
			/**
			 * The preview image element
			 * @var obj HTML IMG element
			 */
			this.previewImg 	= this.img.cloneNode( false );
			// set the ID of the preview image to be unique
			this.previewImg.id	= 'imgCrop_' + this.previewImg.id;
			
						
			// set the displayOnInit option to true so we display the select area at the same time as the thumbnail
			this.options.displayOnInit = true;

			this.hasPreviewImg 	= true;
			
			this.previewWrap.addClassName( 'imgCrop_previewWrap' );
			
			this.previewWrap.setStyle(
			 { 
			 	width: this.options.minWidth + 'px',
			 	height: this.options.minHeight + 'px'
			 }
			);
			
			this.previewWrap.appendChild( this.previewImg );
		}
	},
	
	/**
	 * Implements the abstract method from Cropper.Img to draw the preview image
	 * 
	 * @see Croper.Img.subDrawArea
	 */
	subDrawArea: function() {
		if( this.hasPreviewImg ) {
			// get the ratio of the select area to the src image
			var calcWidth = this.calcW();
			var calcHeight = this.calcH();
			// ratios for the dimensions of the preview image
			var dimRatio = { 
				x: this.imgW / calcWidth, 
				y: this.imgH / calcHeight 
			}; 
			//ratios for the positions within the preview
			var posRatio = { 
				x: calcWidth / this.options.minWidth, 
				y: calcHeight / this.options.minHeight 
			};
			
			// setting the positions in an obj before apply styles for rendering speed increase
			var calcPos	= {
				w: Math.ceil( this.options.minWidth * dimRatio.x ) + 'px',
				h: Math.ceil( this.options.minHeight * dimRatio.y ) + 'px',
				x: '-' + Math.ceil( this.areaCoords.x1 / posRatio.x )  + 'px',
				y: '-' + Math.ceil( this.areaCoords.y1 / posRatio.y ) + 'px'
			}
			
			var previewStyle 	= this.previewImg.style;
			previewStyle.width 	= calcPos.w;
			previewStyle.height	= calcPos.h;
			previewStyle.left	= calcPos.x;
			previewStyle.top	= calcPos.y;
		}
	}
	
});
