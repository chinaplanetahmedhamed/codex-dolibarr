/* Unit test: pointer gesture dispatch; no browser automation or synthetic ERP mutation. */
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const classes=()=>({add(){},remove(){}});let drop=0,started=0;
const listeners={},title={contains:n=>n===title,setPointerCapture(){},hasPointerCapture(){return true},releasePointerCapture(){}},card={dataset:{},classList:classes(),querySelector:s=>s==='.cb-card-title'?title:null,addEventListener:(type,fn)=>listeners[type]=fn,setPointerCapture(){},hasPointerCapture(){return true},releasePointerCapture(){}};
const target={};const ctx={window:{getSelection:()=>({removeAllRanges(){}})},document:{documentElement:{classList:classes()},elementFromPoint:()=>target},setTimeout:fn=>fn(),Math};vm.createContext(ctx);vm.runInContext(fs.readFileSync('custom/chinaplanetcustomercenter/js/kanban_pointer.js','utf8'),ctx);
ctx.window.cpKanbanPointer(card,{handle:'.cb-card-title',start:()=>started++,drop:(e,t)=>{assert.strictEqual(t,target);drop++;}});
const event=(x,y,type='mouse',node=title)=>({isPrimary:true,button:0,pointerId:1,clientX:x,clientY:y,pointerType:type,target:node,preventDefault(){}});
listeners.pointerdown(event(10,10));listeners.pointerup(event(10,10));assert.equal(drop,0,'click must not move');
listeners.pointerdown(event(10,10));listeners.pointermove(event(30,30));listeners.pointerup(event(30,30));assert.equal(drop,1,'title drag reaches drop');
listeners.pointerdown(event(10,10,'touch'));listeners.pointermove(event(30,30,'touch'));listeners.pointerup(event(30,30,'touch'));assert.equal(drop,2,'touch title drag reaches drop');
listeners.pointerdown(event(10,10));listeners.pointermove(event(30,30));listeners.pointercancel(event(30,30));assert.equal(drop,2,'cancel never saves');
assert.equal(started,3);const source=fs.readFileSync('custom/chinaplanetoperations/js/boards.js','utf8');assert(source.includes("handle:'.cb-card-title'"));assert(source.includes('personalListMenu(list)'));assert(source.includes('personalSaveColumns(ids.map'));console.log('M097_POINTER_UNIT=PASS (mouse, touch, click, cancel and personal control wiring)');
