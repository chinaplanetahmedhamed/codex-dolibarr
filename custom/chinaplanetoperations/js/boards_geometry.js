/* Shared visible-area geometry. No requests, storage, permissions or record edits. */
(function(global,factory){'use strict';const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else global.CPBoardsGeometry=api;})(typeof window!=='undefined'?window:globalThis,function(){
'use strict';
function placement(anchor,box,view,gap=8){
 const width=Math.max(0,Math.min(box.width,view.width-gap*2));
 const above=Math.max(0,anchor.top-view.top-gap*2),below=Math.max(0,view.top+view.height-anchor.bottom-gap*2);
 const flip=box.height>below&&above>below,maxHeight=flip?above:below;
 const left=Math.max(view.left+gap,Math.min(anchor.left,view.left+view.width-width-gap));
 const top=flip?Math.max(view.top+gap,anchor.top-gap-Math.min(box.height,maxHeight)):Math.min(view.top+view.height-gap,Math.max(view.top+gap,anchor.bottom+gap));
 return {left,top,width,maxHeight,direction:flip?'above':'below'};
}
function frameViewport(outer,frame,clip){
 const left=Math.max(outer.left,frame.left,clip.left),top=Math.max(outer.top,frame.top,clip.top);
 const right=Math.min(outer.left+outer.width,frame.right,clip.right),bottom=Math.min(outer.top+outer.height,frame.bottom,clip.bottom);
 return {left:Math.max(0,left-frame.left),top:Math.max(0,top-frame.top),width:Math.max(0,right-left),height:Math.max(0,bottom-top)};
}
function viewport(win){
 const vv=win.visualViewport;let view={left:vv?.offsetLeft||0,top:vv?.offsetTop||0,width:vv?.width||win.innerWidth,height:vv?.height||win.innerHeight};
 try{
  const frame=win.frameElement,dialog=frame?.closest('#cb-card-dialog');
  if(frame&&dialog){const p=win.parent,pv=p.visualViewport,r=dialog.getBoundingClientRect(),header=dialog.querySelector(':scope > header');
   const outer={left:pv?.offsetLeft||0,top:pv?.offsetTop||0,width:pv?.width||p.innerWidth,height:pv?.height||p.innerHeight};
   const clip={left:r.left+dialog.clientLeft,top:r.top+dialog.clientTop+(header?.getBoundingClientRect().height||0),right:r.right-dialog.clientLeft,bottom:r.bottom-dialog.clientTop};
   view=frameViewport(outer,frame.getBoundingClientRect(),clip);
  }
 }catch(e){/* Standalone/other-origin frames retain their own native viewport. */}
 return view;
}
function position(panel,trigger,win){
 const v=viewport(win);panel.style.maxWidth=Math.max(0,v.width-16)+'px';panel.style.maxHeight='none';
 const result=placement(trigger.getBoundingClientRect(),panel.getBoundingClientRect(),v);
 panel.style.left=result.left+'px';panel.style.top=result.top+'px';panel.style.maxHeight=result.maxHeight+'px';panel.dataset.direction=result.direction;return result;
}
function fitFrame(frame,doc){
 const height=Math.max(320,Math.ceil(doc.body.getBoundingClientRect().height));
 if(frame.style.height!==height+'px')frame.style.height=height+'px';return height;
}
function restoreBoardPosition(origin,root,win){if(!origin)return;const task=Number(origin.task),target=origin.focus?.isConnected?origin.focus:(Number.isSafeInteger(task)&&task>0?root.querySelector('.cb-card[data-task="'+task+'"] .cb-card-title'):null);target?.focus({preventScroll:true});win.scrollTo(origin.x,origin.y);}
function bindFrame(frame,dialog,win){
 let observer=null,doc=null,tick=0,lastView='';
 function notify(){
  if(!doc?.body?.isConnected)return;
  try {const v=viewport(frame.contentWindow),signature=JSON.stringify(v);for(const key of ['left','top','width','height'])doc.documentElement.style.setProperty('--cbo-visible-'+key,v[key]+'px');
  if(signature!==lastView){lastView=signature;frame.contentWindow.dispatchEvent(new frame.contentWindow.Event('cpo-card-viewport-change'));}}catch(e){/* A navigation may temporarily detach the same-origin document. */}
 }
 function disconnect(){observer?.disconnect();observer=null;if(tick)win.cancelAnimationFrame(tick);tick=0;if(doc){doc.removeEventListener('load',schedule,true);doc.removeEventListener('toggle',schedule,true);}doc=null;lastView='';}
 function update(){tick=0;if(doc?.body?.isConnected){if(!doc.querySelector('dialog[open]'))fitFrame(frame,doc);notify();}}
 function schedule(){if(!tick)tick=win.requestAnimationFrame(update);}
 frame.addEventListener('load',()=>{
  disconnect();try{const next=frame.contentDocument;if(!next?.body?.classList.contains('cpo-board-card'))return;doc=next;doc.documentElement.classList.add('cbo-natural-height');
   if(win.ResizeObserver){observer=new win.ResizeObserver(schedule);observer.observe(doc.body);}
   doc.addEventListener('load',schedule,true);doc.addEventListener('toggle',schedule,true);schedule();
  }catch(e){/* Authentication/errors and other origins keep the ordinary frame fallback. */}
 });
 dialog.addEventListener('scroll',notify,{passive:true});win.addEventListener('resize',schedule);win.visualViewport?.addEventListener('resize',schedule);win.visualViewport?.addEventListener('scroll',notify);
 return {disconnect,reset(){disconnect();frame.style.height='600px';dialog.scrollTop=0;}};
}
return {placement,frameViewport,viewport,position,fitFrame,restoreBoardPosition,bindFrame};
});
