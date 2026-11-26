/* Marks the page as JavaScript-enabled before it is drawn (the CSS uses html.js). External file so the
   Content-Security-Policy can stay strict (script-src self) without any inline-script hash. */
document.documentElement.classList.add("js");
/* logo splash on the home page, once per browser session */
try{if(/(^|\/)(index\.html)?$/.test(location.pathname)&&!sessionStorage.bvsplash){sessionStorage.bvsplash=1;document.documentElement.classList.add("splash")}}catch(e){}
