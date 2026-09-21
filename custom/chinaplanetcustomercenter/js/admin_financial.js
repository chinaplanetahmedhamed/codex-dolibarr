(function(){
  'use strict';
  function init(){
    var type=document.getElementById('cpcc-entry-type');
    var output=document.getElementById('cpcc-direction-output');
    if(!type||!output)return;
    function update(){
      var credit=type.value==='customer_receipt'||type.value==='opening_balance';
      output.textContent=credit?'Credit (+)':'Debit (−)';
      output.className='cpcc-direction '+(credit?'credit':'debit');
    }
    type.addEventListener('change',update);update();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
