!function(e){var t={};function i(r){if(t[r])return t[r].exports;var n=t[r]={i:r,l:!1,exports:{}};return e[r].call(n.exports,n,n.exports,i),n.l=!0,n.exports}i.m=e,i.c=t,i.d=function(e,t,r){i.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},i.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},i.t=function(e,t){if(1&t&&(e=i(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(i.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var n in e)i.d(r,n,function(t){return e[t]}.bind(null,n));return r},i.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return i.d(t,"a",t),t},i.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},i.p="",i(i.s=161)}({14:function(e,t,i){var r=i(9);e.exports=function(e,t){if(e){if("string"==typeof e)return r(e,t);var i=Object.prototype.toString.call(e).slice(8,-1);return"Object"===i&&e.constructor&&(i=e.constructor.name),"Map"===i||"Set"===i?Array.from(e):"Arguments"===i||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(i)?r(e,t):void 0}},e.exports.__esModule=!0,e.exports.default=e.exports},161:function(e,t,i){"use strict";i.r(t);var r=i(8),n=i.n(r),o={deviceDensity:window.devicePixelRatio?window.devicePixelRatio:"auto",density:null,config:CLDLB||{},lazyThreshold:0,enabled:!1,sizeBands:[],iObserver:null,pObserver:null,rObserver:null,aboveFold:!0,bind:function(e){var t=this;e.CLDbound=!0,this.enabled||this._init();var i=e.dataset.size.split(" ");e.originalWidth=i[0],e.originalHeight=i[1],this.pObserver?(this.aboveFold&&this.inInitialView(e)?this.buildImage(e):(this.pObserver.observe(e),this.iObserver.observe(e)),e.addEventListener("error",(function(i){e.srcset="",e.src='data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="rgba(0,0,0,0.1)"/><text x="50%" y="50%" fill="red" text-anchor="middle" dominant-baseline="middle">%26%23x26A0%3B︎</text></svg>',t.rObserver.unobserve(e)}))):this.setupFallback(e)},buildImage:function(e){e.dataset.srcset?(e.cld_loaded=!0,e.srcset=e.dataset.srcset):(e.src=this.getSizeURL(e),e.dataset.responsive&&this.rObserver.observe(e))},inInitialView:function(e){var t=e.getBoundingClientRect();return this.aboveFold=t.top<window.innerHeight+this.lazyThreshold,this.aboveFold},setupFallback:function(e){var t=this,i=[];this.sizeBands.forEach((function(r){if(r<=e.originalWidth){var n=t.getSizeURL(e,r,!0)+" ".concat(r,"w");-1===i.indexOf(n)&&i.push(n)}})),e.srcset=i.join(","),e.sizes="(max-width: ".concat(e.originalWidth,"px) 100vw, ").concat(e.originalWidth,"px")},_init:function(){this.enabled=!0,this._calcThreshold(),this._getDensity();for(var e=parseInt(this.config.max_width),t=parseInt(this.config.min_width),i=parseInt(this.config.pixel_step);e-i>=t;)e-=i,this.sizeBands.push(e);"undefined"!=typeof IntersectionObserver&&this._setupObservers(),this.enabled=!0},_setupObservers:function(){var e=this,t={rootMargin:this.lazyThreshold+"px 0px "+this.lazyThreshold+"px 0px"},i={rootMargin:2*this.lazyThreshold+"px 0px "+2*this.lazyThreshold+"px 0px"};this.rObserver=new ResizeObserver((function(t,i){t.forEach((function(t){t.target.cld_loaded&&t.contentRect.width>=t.target.cld_loaded&&(t.target.src=e.getSizeURL(t.target))}))})),this.iObserver=new IntersectionObserver((function(t,i){t.forEach((function(t){t.isIntersecting&&(e.buildImage(t.target),i.unobserve(t.target))}))}),t),this.pObserver=new IntersectionObserver((function(t,i){t.forEach((function(t){t.isIntersecting&&(t.intersectionRatio<.5&&(t.target.src=e.getPlaceholderURL(t.target)),i.unobserve(t.target))}))}),i)},_calcThreshold:function(){var e=this.config.lazy_threshold.replace(/[^0-9]/g,""),t=0;switch(this.config.lazy_threshold.replace(/[0-9]/g,"").toLowerCase()){case"em":t=parseFloat(getComputedStyle(document.body).fontSize)*e;break;case"rem":t=parseFloat(getComputedStyle(document.documentElement).fontSize)*e;break;case"vh":t=window.innerHeight/e*100;break;default:t=e}this.lazyThreshold=parseInt(t,10)},_getDensity:function(){var e=this.config.dpr?this.config.dpr.replace("X",""):"off";if("off"===e)return this.density=1,1;var t=this.deviceDensity;"max"!==e&&"auto"!==t&&(e=parseFloat(e),t=t>Math.ceil(e)?e:t),this.density=t},scaleWidth:function(e,t){var i=parseInt(this.config.max_width);if(!t)for(t=e.width;-1===this.sizeBands.indexOf(t)&&t<i;)t++;return t>i&&(t=i),e.originalWidth<t&&(t=e.originalWidth),t},scaleSize:function(e,t,i){var r=(e.originalWidth/e.originalHeight).toFixed(3),n=(e.width/e.height).toFixed(3),o=this.scaleWidth(e,t),s=[];e.width!==e.originalWidth&&s.push(r===n?"c_scale":"c_fill,g_auto");var a=Math.round(o/n);return s.push("w_"+o),s.push("h_"+a),i&&1!==this.density&&s.push("dpr_"+this.density),e.cld_loaded=o,{transformation:s.join(","),nameExtension:o+"x"+a}},getSizeURL:function(e,t){var i=this.scaleSize(e,t,!0);return[this.config.base_url,"image",e.dataset.delivery,"upload"===e.dataset.delivery?i.transformation:"",e.dataset.transformations,"v"+e.dataset.version,e.dataset.publicId+"?_i=AA"].filter(this.empty).join("/")},getPlaceholderURL:function(e){e.cld_placehold=!0;this.scaleSize(e,null,!1);return[this.config.base_url,"image",e.dataset.delivery,this.config.placeholder,e.dataset.publicId].filter(this.empty).join("/")},empty:function(e){return void 0!==e&&0!==e.length}};window.CLDBind=function(e){e.CLDbound||o.bind(e)},window.addEventListener("load",(function(){n()(document.querySelectorAll("img[data-public-id]")).forEach((function(e){CLDBind(e)}))}))},26:function(e,t,i){var r=i(9);e.exports=function(e){if(Array.isArray(e))return r(e)},e.exports.__esModule=!0,e.exports.default=e.exports},27:function(e,t){e.exports=function(e){if("undefined"!=typeof Symbol&&null!=e[Symbol.iterator]||null!=e["@@iterator"])return Array.from(e)},e.exports.__esModule=!0,e.exports.default=e.exports},28:function(e,t){e.exports=function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")},e.exports.__esModule=!0,e.exports.default=e.exports},8:function(e,t,i){var r=i(26),n=i(27),o=i(14),s=i(28);e.exports=function(e){return r(e)||n(e)||o(e)||s()},e.exports.__esModule=!0,e.exports.default=e.exports},9:function(e,t){e.exports=function(e,t){(null==t||t>e.length)&&(t=e.length);for(var i=0,r=new Array(t);i<t;i++)r[i]=e[i];return r},e.exports.__esModule=!0,e.exports.default=e.exports}});