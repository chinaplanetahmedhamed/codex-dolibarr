const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const src=fs.readFileSync('custom/chinaplanetoperations/js/boards.js','utf8');
const a=src.indexOf('async function api('),b=src.indexOf('\nasync function load(',a);assert(a>=0&&b>a);
let events=[],settles=0,success=true;
const c={cfg:{token:'fixture',board:7,customer:0,mode:'boards'},liveSync:null,archiveGallery:false,search:{value:''},URLSearchParams,boardSounds:{committed:(...x)=>events.push(x),settle:async()=>settles++,received:()=>{}},fetch:async()=>({ok:success,status:success?200:403,headers:{get:()=>null},json:async()=>success?{ok:true,data:{id:12}}:{ok:false,error:'Denied'}})};
vm.createContext(c);vm.runInContext(src.slice(a,b),c);
(async()=>{await vm.runInContext("api('move_card',{board:7,task:100})",c);assert.equal(events.length,1,'successful saves must reach notification controller');assert.equal(events[0][0],'move_card');assert.equal(settles,0,'card saves must not await audio');await vm.runInContext("api('create_board',{request:'fixture'})",c);assert.equal(settles,1,'creation waits for its bounded cue before navigation');success=false;await assert.rejects(vm.runInContext("api('move_card',{board:7})",c));assert.equal(events.length,2,'failed mutation must remain silent');success=true;await vm.runInContext("api('workspace')",c);assert.equal(events.length,2,'poll reads do not emit action sound');
assert(src.includes('boardSounds?.observe(data)'),'remote authorized snapshots must reach audio diff');assert(src.includes('boardSounds?.seed(state)'),'manual load must seed without notification');assert(src.includes('boardSounds?.disconnected()'),'reconnect/denied must reset baseline');
const sounds=require('../../../custom/chinaplanetoperations/js/boards_sounds.js'),live=require('../../../custom/chinaplanetoperations/js/boards_live_runtime.js');
let clock=1000,heard=[],snapshot={board:{rowid:7},lists:[{rowid:10},{rowid:11}],cards:[{fk_task:100,fk_list:10,position:1024}]};
const real=sounds.createController({storage:{getItem:()=>null,setItem:()=>{}},key:'fixture',now:()=>clock,unlock:async()=>true,play:t=>heard.push(t)});
await real.toggle();real.seed(snapshot);c.boardSounds=real;c.fetch=async()=>({ok:true,headers:{get:()=>null},json:async()=>({ok:true,data:snapshot})});
const sync=live.createSync({now:()=>clock,setTimeout:()=>1,clearTimeout:()=>{},read:()=>vm.runInContext("api('workspace')",c),blocked:()=>false,apply:d=>real.observe(d),status:v=>{if(v==='offline')real.disconnected();}});
sync.seed(snapshot);sync.start();for(let i=0;i<9;i++){clock+=2000;await sync.check();}
snapshot={...snapshot,cards:[{fk_task:100,fk_list:11,position:1024}]};clock+=2000;await sync.check();assert.equal(heard.length,1,'first remote move after healthy unchanged polling must sound');
clock+=20000;snapshot={...snapshot,cards:[{fk_task:100,fk_list:10,position:1024}]};await sync.check();assert.equal(heard.length,1,'long hidden/reconnect catchup must stay silent');
clock+=2000;snapshot={...snapshot,cards:[{fk_task:100,fk_list:11,position:1024}]};await sync.check();assert.equal(heard.length,2,'current remote changes resume after catchup');sync.stop();
console.log('SOUND_INTEGRATION_PASS successful/failed API boundaries, bounded creation navigation, read silence, seed/diff/reconnect hooks, healthy idle polling and catchup');})().catch(e=>{console.error(e);process.exit(1)});
