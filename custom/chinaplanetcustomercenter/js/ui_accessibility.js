/* Custom module integration with the native Dolibarr theme; no core modifications. */
(function () {
 'use strict';
 function init() {
  document.body.classList.add('cpcc-ui');
  var ar=/^ar\b/i.test(document.documentElement.lang), lang=new URL(location.href).searchParams.get('lang');
  var text=function(en,arabic){return ar?arabic:en;};
  // All language propagation is same-origin navigation and GET filters only.
  // Do not modify POST destinations, tokens, account preferences or external URLs.
  if(['ar_SA','en_US'].indexOf(lang)!==-1){
   document.querySelectorAll('a[href]').forEach(function(a){
    var url=new URL(a.href,location.href);
    if(url.origin!==location.origin||!url.pathname.endsWith('.php')||/logout|document\.php|download/i.test(url.pathname)||url.searchParams.has('action'))return;
    if(!url.searchParams.has('lang')){url.searchParams.set('lang',lang);a.href=url.href;}
   });
   document.querySelectorAll('form').forEach(function(form){
    if((form.method||'get').toLowerCase()!=='get'||form.querySelector('[name="lang"]')||new URL(form.action,location.href).origin!==location.origin)return;
    var input=document.createElement('input');input.type='hidden';input.name='lang';input.value=lang;form.appendChild(input);
   });
  }
  document.querySelectorAll('.cpcc-dashboard,.cpcc-admin-overview').forEach(function(e){e.dir=ar?'rtl':'ltr';});
  document.querySelectorAll('#id-top a').forEach(function(e){
   if(e.querySelector('.fa-print'))e.setAttribute('aria-label',text('Print page','طباعة الصفحة'));
   if(e.classList.contains('help'))e.setAttribute('aria-label',text('Help documentation','دليل المساعدة'));
  });
  document.querySelectorAll('.cpad-head,.cpad-section-heading,.cpcc-portal-shell').forEach(function(e){e.setAttribute('role','region');e.setAttribute('aria-label',e.classList.contains('cpcc-portal-shell')?text('Customer portal','بوابة العميل'):(e.classList.contains('cpad-head')?text('Company and environment','الشركة وبيئة العمل'):text('Current section','القسم الحالي')));});
  document.querySelectorAll('.cpl-doc-panel').forEach(function(e){var heading=e.querySelector('h2');if(heading){e.setAttribute('role','region');e.setAttribute('aria-label',heading.textContent.trim());}});
  var menu=document.querySelector('#mainmenua_menu');
  if(menu){menu.setAttribute('aria-label',text('Toggle main menu','فتح أو إغلاق القائمة الرئيسية'));}
  document.querySelectorAll('.menuhider a').forEach(function(e){if(!e.textContent.trim())e.setAttribute('aria-label',text('Toggle navigation','فتح أو إغلاق التنقل'));});
  // Core emits UL role=navigation, leaving LI without list semantics.
  document.querySelectorAll('ul.tmenu[role="navigation"]').forEach(function(ul){ul.removeAttribute('role');var wrap=document.createElement('nav');wrap.setAttribute('aria-label',text('ERP navigation','قائمة النظام'));ul.parentNode.insertBefore(wrap,ul);wrap.appendChild(ul);});
  var main=document.querySelector('main');
  if(!main){main=document.querySelector('.cpad-shell');if(main){main.setAttribute('role','main');main.setAttribute('aria-label',text('Operations dashboard','لوحة العمليات'));}}
  function focusScrollableData(){
  document.querySelectorAll('.cpcc-table-wrap,.div-table-responsive,.cpad-table-scroll,.cpl-timeline').forEach(function(e){
   if(e.scrollWidth<=e.clientWidth+1)return;
   e.tabIndex=0;
   if(!e.hasAttribute('aria-label'))e.setAttribute('aria-label',text('Scrollable data — use arrow keys','بيانات قابلة للتمرير — استخدم مفاتيح الأسهم'));
   if(e.tagName!=='OL')e.setAttribute('role','region');
  });
  }
  focusScrollableData();
  var resizeTimer;window.addEventListener('resize',function(){clearTimeout(resizeTimer);resizeTimer=setTimeout(focusScrollableData,100);});
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
}());
