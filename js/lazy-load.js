!function(e){var t={};function n(r){if(t[r])return t[r].exports;var i=t[r]={i:r,l:!1,exports:{}};return e[r].call(i.exports,i,i.exports,n),i.l=!0,i.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var i in e)n.d(r,i,function(t){return e[t]}.bind(null,i));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="",n(n.s=2)}([function(e,t){e.exports=function(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r},e.exports.default=e.exports,e.exports.__esModule=!0},function(e,t,n){var r=n(3),i=n(4),o=n(5),a=n(6);e.exports=function(e){return r(e)||i(e)||o(e)||a()},e.exports.default=e.exports,e.exports.__esModule=!0},function(e,t,n){"use strict";n.r(t);var r=n(1),i=n.n(r),o={density:window.devicePixelRatio?window.devicePixelRatio:"auto",images:[],debounce:null,config:CLDLB||{},_init:function(){var e=this;i()(document.images).forEach((function(t){t.dataset.src&&(t.originalWidth=t.dataset.width,e.images.push(t))})),window.addEventListener("resize",(function(t){e._debounceBuild()})),window.addEventListener("scroll",(function(t){e._debounceBuild()})),this._build()},_debounceBuild:function(){var e=this;this.debounce&&clearTimeout(this.debounce),this.debounce=setTimeout((function(){e._build()}),100)},_getDensity:function(){var e=CLDLB.dpr?CLDLB.dpr.replace("X",""):"off";if("off"===e)return 1;var t=this.density;return CLDLB.dpr_precise||"auto"===t||(t=t>Math.ceil(e)?e:t),t},_build:function(){var e=this;this.images.forEach((function(t){e.buildSize(t)}))},_shouldRebuild:function(e){var t=this.scaleSize(e.originalWidth,e.width,this.config.pixel_step),n=e.getBoundingClientRect(),r="auto"!==this.density?this._getDensity():1,i=window.innerHeight+parseInt(this.config.lazy_threshold,10);return n.top<i&&(t>e.naturalWidth/r||!e.cld_loaded)},_shouldPlacehold:function(e){var t=this.scaleSize(e.originalWidth,e.width,this.config.pixel_step),n=e.getBoundingClientRect(),r="auto"!==this.density?this._getDensity():1,i=window.innerHeight+parseInt(this.config.lazy_threshold,10);return!e.cld_loaded&&n.top<2*i&&(t>e.naturalWidth/r||!e.cld_placehold)},getResponsiveSteps:function(e){return Math.ceil(e.dataset.breakpoints?e.originalWidth/e.dataset.breakpoints:this.responsiveStep)},getQuality:function(){var e="q_auto";switch(navigator&&navigator.connection?navigator.connection.effectiveType:"none"){case"none":break;case"4g":e="q_auto:good";break;case"3g":e="q_auto:eco";break;case"2g":case"slow-2g":e="q_auto:low";break;default:e="q_auto:best"}return e},scaleSize:function(e,t,n){var r=e-n*Math.floor((e-t)/n);return(r>e||this.config.max_width<r)&&(r=e),r},buildSize:function(e){this._shouldRebuild(e)?e.dataset.srcset?e.srcset=e.dataset.srcset:e.src=this.getSizeURL(e):this._shouldPlacehold(e)&&(e.src=this.getPlaceholderURL(e))},getSizeURL:function(e){if(e.cld_loaded=!0,e.dataset.srcset)return e.srcset=e.dataset.srcset,delete e.dataset.srcset,"";var t=this.scaleSize(e.originalWidth,e.width,this.config.pixel_step),n=this._getDensity(),r="w_"+t;return 1!==n&&(r+=",dpr_"+n),e.dataset.src.replace("--size--",r).replace(/q_auto(?!:)/gi,this.getQuality())},getPlaceholderURL:function(e){return e.cld_placehold=!0,e.dataset.placeholder.replace("/--size--","/")}};window.addEventListener("load",(function(){o._init()}))},function(e,t,n){var r=n(0);e.exports=function(e){if(Array.isArray(e))return r(e)},e.exports.default=e.exports,e.exports.__esModule=!0},function(e,t){e.exports=function(e){if("undefined"!=typeof Symbol&&null!=e[Symbol.iterator]||null!=e["@@iterator"])return Array.from(e)},e.exports.default=e.exports,e.exports.__esModule=!0},function(e,t,n){var r=n(0);e.exports=function(e,t){if(e){if("string"==typeof e)return r(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);return"Object"===n&&e.constructor&&(n=e.constructor.name),"Map"===n||"Set"===n?Array.from(e):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?r(e,t):void 0}},e.exports.default=e.exports,e.exports.__esModule=!0},function(e,t){e.exports=function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")},e.exports.default=e.exports,e.exports.__esModule=!0}]);