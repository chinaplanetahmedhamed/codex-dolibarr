const assert=require('node:assert/strict'),g=require('../../../custom/chinaplanetoperations/js/boards_geometry.js');
let height=1800,modal=false,scheduled=new Map(),sequence=0,notifications=0,disconnections=0,ro;
const frameEvents={},dialogEvents={},docEvents={};
const doc={body:{isConnected:true,classList:{contains:x=>x==='cpo-board-card'},getBoundingClientRect:()=>({height})},documentElement:{classList:{add:()=>{}},style:{setProperty:()=>{}}},addEventListener:(k,v)=>docEvents[k]=v,removeEventListener:k=>delete docEvents[k],querySelector:()=>modal?{}:null};
const frame={style:{height:'600px'},contentDocument:doc,addEventListener:(k,v)=>frameEvents[k]=v,contentWindow:{innerWidth:390,innerHeight:700,frameElement:null,Event:class{constructor(type){this.type=type;}},dispatchEvent:()=>notifications++}};
const dialog={scrollTop:50,addEventListener:(k,v)=>dialogEvents[k]=v};
const win={ResizeObserver:class{constructor(fn){ro=fn;}observe(){}disconnect(){disconnections++;}},requestAnimationFrame:fn=>{const id=++sequence;scheduled.set(id,fn);return id;},cancelAnimationFrame:id=>scheduled.delete(id),addEventListener:()=>{}};
function flush(){for(const [id,fn] of [...scheduled]){scheduled.delete(id);fn();}}
const binding=g.bindFrame(frame,dialog,win);frameEvents.load();flush();assert.equal(frame.style.height,'1800px');assert.equal(notifications,1);
ro();flush();assert.equal(notifications,1,'unchanged resize must not close a just-opened menu');
modal=true;height=300;ro();flush();assert.equal(frame.style.height,'1800px','native child dialog must not collapse the host');
modal=false;docEvents.toggle();flush();assert.equal(frame.style.height,'320px');
binding.reset();assert.equal(frame.style.height,'600px');assert.equal(dialog.scrollTop,0);assert.equal(disconnections,1);assert.deepEqual(Object.keys(docEvents),[]);
frame.contentDocument={body:{classList:{contains:()=>false}}};frameEvents.load();flush();assert.equal(frame.style.height,'600px','login/error documents retain the normal fallback');
binding.disconnect();assert.equal(scheduled.size,0);
console.log('FRAME_LIFECYCLE_PASS grow/shrink, stable menu notifications, modal freeze, teardown and auth fallback');
