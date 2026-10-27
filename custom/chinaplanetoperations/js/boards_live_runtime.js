/* Boards: authorized polling and dedicated pointer handles. */
(function(scope,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else scope.CPBoardsLive=api;})(typeof window==='object'?window:globalThis,function(){
'use strict';
function createSync(o){
 const clock=o.now||Date.now,later=o.setTimeout||setTimeout,cancel=o.clearTimeout||clearTimeout,interval=o.interval||2000;
 let timer=null,request=null,running=false,active=false,denied=false,generation=0,stamp='',pending=null,failures=0;
 const stats={checks:0,applied:0,lastAppliedAt:null,lastRoundTripMs:null};
 function status(value){if(o.status)o.status(value,{...stats,failures,pending:!!pending});}
 function schedule(ms){if(timer!==null)cancel(timer);timer=null;if(running)timer=later(()=>{timer=null;check();},ms);}
 function flush(){if(!running||denied||!pending||o.blocked())return false;const next=pending;pending=null;if(next.stamp===stamp)return false;o.apply(next.data);stamp=next.stamp;stats.applied++;stats.lastAppliedAt=clock();status('live');return true;}
 function invalidate(){generation++;pending=null;if(request)request.abort();}
 function seed(data){invalidate();stamp=JSON.stringify(data);}
 function stop(){running=false;invalidate();if(timer!==null)cancel(timer);timer=null;}
 async function check(){
  if(!running||active||denied)return;
  if(o.current&&!o.current()){stop();return;}
  if(o.visible&&!o.visible()){schedule(10000);return;}
  active=true;const epoch=generation,started=clock(),controller=new AbortController();request=controller;const timeout=later(()=>controller.abort(),o.timeout||8000);stats.checks++;
  try{
   const data=await o.read(controller.signal);if(epoch!==generation||!running)return;
   failures=0;stats.lastRoundTripMs=clock()-started;const next=JSON.stringify(data);
   if(next!==stamp){pending={stamp:next,data};flush();}else pending=null;
   status(pending?'waiting':'live');
  }catch(error){
   if(epoch!==generation||!running)return;
   if(error.status===401||error.status===403){denied=true;stop();if(o.revoke)o.revoke();status('denied');}
   else{failures++;status('offline');}
  }finally{cancel(timeout);request=null;active=false;if(running)schedule(Math.min(30000,interval*Math.pow(2,Math.min(failures,4))));}
 }
 return {start(){if(denied)return;running=true;schedule(interval);status('live');},stop,seed,invalidate,flush,check,wake(){if(running&&!denied)schedule(0);},diagnostics(){return {...stats,failures,pending:!!pending,running,active,transport:'polling',intervalMs:interval};}};
}
function installPointer(win){
 const doc=win.document;let owner=null,frame=0;
 function dispatch(active){doc.dispatchEvent(new win.CustomEvent('cp-boards-live-drag',{detail:{active}}));}
 function edge(value,min,max){const band=40;if(value<min+band)return -Math.ceil(12*Math.min(1,(min+band-value)/band));if(value>max-band)return Math.ceil(12*Math.min(1,(value-max+band)/band));return 0;}
 function scrollTick(){
  if(!owner||!owner.started)return;const e=owner.last,hit=doc.elementFromPoint(e.clientX,e.clientY),lanes=owner.card.closest('.cb-lanes');
  if(lanes){const b=lanes.getBoundingClientRect();lanes.scrollBy(edge(e.clientX,b.left,b.right),edge(e.clientY,b.top,b.bottom));}
  const stack=hit&&hit.closest('.cb-list');if(stack&&stack.scrollHeight>stack.clientHeight){const b=stack.getBoundingClientRect();stack.scrollBy(0,edge(e.clientY,b.top,b.bottom));}
  win.scrollBy(0,edge(e.clientY,0,win.innerHeight));if(owner&&owner.options.move)owner.options.move(e,doc.elementFromPoint(e.clientX,e.clientY));frame=win.requestAnimationFrame(scrollTick);
 }
 function finish(event,drop){
  if(!owner||event&&event.pointerId!==undefined&&event.pointerId!==owner.id)return;const old=owner;owner=null;win.cancelAnimationFrame(frame);frame=0;
  try{if(old.started&&drop&&old.options.drop)old.options.drop(event,doc.elementFromPoint(event.clientX,event.clientY));}
  finally{
   old.card.classList.remove('kanban-pointer-dragging');doc.documentElement.classList.remove('kanban-pointer-active');old.handle.setAttribute('aria-pressed','false');
   if(old.options.end)old.options.end(event);if(old.started){old.card.dataset.kanbanSuppressClick='1';win.setTimeout(()=>{delete old.card.dataset.kanbanSuppressClick;},0);}
   if(old.handle.hasPointerCapture(old.id))old.handle.releasePointerCapture(old.id);dispatch(false);
  }
 }
 doc.addEventListener('keydown',e=>{if(e.key==='Escape'&&owner){e.preventDefault();finish(null,false);}});
 win.addEventListener('blur',()=>finish(null,false));doc.addEventListener('visibilitychange',()=>{if(doc.hidden)finish(null,false);});
 win.cpBoardsLivePointer=function(card,options){
  const handle=card.querySelector(options.handle);if(!handle)return;
  handle.addEventListener('contextmenu',e=>e.preventDefault());
  handle.addEventListener('pointerdown',e=>{
   if(owner||!e.isPrimary||e.button!==0||options.disabled&&options.disabled())return;
   owner={card,handle,options,id:e.pointerId,x:e.clientX,y:e.clientY,started:false,last:e};handle.setPointerCapture(e.pointerId);handle.setAttribute('aria-pressed','true');dispatch(true);
  });
  handle.addEventListener('pointermove',e=>{
   if(!owner||owner.card!==card||e.pointerId!==owner.id)return;owner.last=e;
   if(!owner.started){if(Math.hypot(e.clientX-owner.x,e.clientY-owner.y)<7)return;owner.started=true;card.classList.add('kanban-pointer-dragging');doc.documentElement.classList.add('kanban-pointer-active');if(win.getSelection)win.getSelection().removeAllRanges();if(options.start)options.start(e);frame=win.requestAnimationFrame(scrollTick);}
   e.preventDefault();if(options.move)options.move(e,doc.elementFromPoint(e.clientX,e.clientY));
  });
  handle.addEventListener('pointerup',e=>finish(e,true));handle.addEventListener('pointercancel',e=>finish(e,false));handle.addEventListener('lostpointercapture',e=>finish(e,false));
  card.addEventListener('click',e=>{if(card.dataset.kanbanSuppressClick==='1'){e.preventDefault();e.stopImmediatePropagation();}},true);
 };
}
return {createSync,installPointer};
});
