const assert=require('node:assert/strict'),{installPointer}=require('../../../custom/chinaplanetoperations/js/boards_live_runtime.js');
class Node{
 constructor(){this.events={};this.classes=new Set();this.dataset={};this.attrs={};this.capture=new Set();this.classList={add:(...v)=>v.forEach(x=>this.classes.add(x)),remove:(...v)=>v.forEach(x=>this.classes.delete(x))};this.scrolls=[];}
 addEventListener(type,fn){(this.events[type]??=[]).push(fn)}
 emit(type,event={}){for(const f of this.events[type]||[])f(event)}
 setAttribute(k,v){this.attrs[k]=v}setPointerCapture(id){this.capture.add(id)}hasPointerCapture(id){return this.capture.has(id)}releasePointerCapture(id){this.capture.delete(id)}
 getBoundingClientRect(){return {left:0,right:300,top:10,bottom:200}}scrollBy(x,y){this.scrolls.push([x,y])}
}
const document=new Node(),html=new Node(),lane=new Node(),list=new Node();list.scrollHeight=900;list.clientHeight=190;const hit={closest:s=>s==='.cb-list'?list:null};document.documentElement=html;document.elementFromPoint=()=>hit;document.dispatchEvent=e=>document.emit(e.type,e);
let frame=0;const frames=new Map(),win=new Node();Object.assign(win,{document,innerHeight:600,CustomEvent:class{constructor(type,options){this.type=type;this.detail=options.detail}},getSelection:()=>({removeAllRanges(){}}),requestAnimationFrame:fn=>{frames.set(++frame,fn);return frame},cancelAnimationFrame:id=>frames.delete(id),setTimeout:fn=>fn()});
installPointer(win);const card=new Node(),handle=new Node();card.querySelector=()=>handle;card.closest=s=>s==='.cb-lanes'?lane:null;
let disabled=false,drops=0,moves=0;win.cpBoardsLivePointer(card,{handle:'.grip',disabled:()=>disabled,drop:()=>drops++,move:()=>moves++});
const event=(x=150,y=100)=>({pointerId:9,pointerType:'touch',isPrimary:true,button:0,clientX:x,clientY:y,preventDefault(){this.prevented=true}});
let checks=0;assert.equal(card.events.pointerdown,undefined);checks++;
handle.emit('pointerdown',event());assert(handle.capture.has(9));assert.equal(handle.attrs['aria-pressed'],'true');checks++;
handle.emit('pointermove',event(153,102));assert(!card.classes.has('kanban-pointer-dragging'));checks++;
handle.emit('pointermove',event(295,195));assert(card.classes.has('kanban-pointer-dragging'));assert(moves>0);checks++;
const job=frames.entries().next().value;frames.delete(job[0]);job[1]();assert(lane.scrolls.some(([x])=>x>0));assert(list.scrolls.some(([,y])=>y>0));checks++;
document.emit('keydown',{key:'Escape',preventDefault(){}});assert.equal(drops,0);assert.equal(handle.capture.size,0);assert(!card.classes.has('kanban-pointer-dragging'));checks++;
handle.emit('pointerdown',event());handle.emit('pointermove',event(170,120));handle.emit('pointercancel',event(170,120));assert.equal(drops,0);checks++;
handle.emit('pointerdown',event());handle.emit('pointermove',event(170,120));handle.emit('pointerup',event(170,120));assert.equal(drops,1);checks++;
handle.emit('lostpointercapture',event());assert.equal(drops,1);checks++;
disabled=true;handle.emit('pointerdown',event());assert.equal(handle.capture.size,0);checks++;
disabled=false;const secondary=event();secondary.isPrimary=false;handle.emit('pointerdown',secondary);assert.equal(handle.capture.size,0);checks++;
handle.emit('pointerdown',event());handle.emit('pointermove',event(170,120));win.emit('blur');assert.equal(drops,1);assert.equal(handle.capture.size,0);checks++;
console.log('POINTER_PASS assertions='+checks+' synthetic touch-pointer/DOM boundary harness; not physical-device evidence');
