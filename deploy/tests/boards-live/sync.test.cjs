const assert=require('node:assert/strict'),fs=require('node:fs');
const path=require('node:path').resolve(__dirname,'../../../custom/chinaplanetoperations/js/boards_live_runtime.js');
const runtime=fs.existsSync(path)?require(path):{};
assert.equal(typeof runtime.createSync,'function','trial must expose the real sync controller');
let checks=0;
function rig(){let now=0,id=0,blocked=false,visible=true,current=true;const jobs=new Map(),responses=[],applied=[],statuses=[];let revoked=0;
 const opt={interval:2000,timeout:8000,now:()=>now,setTimeout:(fn,ms)=>{jobs.set(++id,{fn,ms});return id},clearTimeout:k=>jobs.delete(k),read:()=>responses.shift()(),blocked:()=>blocked,visible:()=>visible,current:()=>current,apply:d=>applied.push(d),status:s=>statuses.push(s),revoke:()=>revoked++};
 const sync=runtime.createSync(opt);return {sync,jobs,responses,applied,statuses,get revoked(){return revoked},block:v=>blocked=v,hide:v=>visible=!v,leave:()=>current=false,time:v=>now=v};}
(async()=>{
 const a=rig();a.sync.seed({v:1});a.responses.push(async()=>({v:2}));a.sync.start();await a.sync.check();assert.equal(a.applied[0].v,2);checks++;
 a.responses.push(async()=>({v:2}));await a.sync.check();assert.equal(a.applied.length,1);checks++;
 a.block(true);a.responses.push(async()=>({v:3}));await a.sync.check();assert.equal(a.applied.length,1);assert.equal(a.sync.diagnostics().pending,true);checks++;
 a.block(false);a.sync.flush();assert.equal(a.applied.at(-1).v,3);checks++;
 let resolve;a.responses.push(()=>new Promise(r=>resolve=r));const pending=a.sync.check();a.sync.invalidate();resolve({v:4});await pending;assert.equal(a.applied.at(-1).v,3);checks++;
 let finish;a.responses.push(()=>new Promise(r=>finish=r));const first=a.sync.check();await a.sync.check();finish({v:5});await first;assert.equal(a.applied.at(-1).v,5);checks++;
 a.responses.push(async()=>{throw new Error('offline')});await a.sync.check();assert.equal(a.sync.diagnostics().failures,1);assert.equal(a.applied.at(-1).v,5);assert([...a.jobs.values()].some(j=>j.ms===4000));checks++;
 a.responses.push(async()=>({v:6}));await a.sync.check();assert.equal(a.sync.diagnostics().failures,0);assert.equal(a.applied.at(-1).v,6);checks++;
 a.hide(true);const before=a.sync.diagnostics().checks;await a.sync.check();assert.equal(a.sync.diagnostics().checks,before);checks++;
 a.hide(false);a.block(true);a.responses.push(async()=>({v:7}));await a.sync.check();a.responses.push(async()=>{const e=new Error('denied');e.status=403;throw e});await a.sync.check();assert.equal(a.revoked,1);assert.equal(a.sync.diagnostics().pending,false);assert.equal(a.sync.diagnostics().running,false);a.block(false);a.sync.flush();assert.equal(a.applied.at(-1).v,6);checks++;
 const b=rig();b.sync.seed({v:1});b.sync.start();b.leave();await b.sync.check();assert.equal(b.sync.diagnostics().running,false);checks++;
 const c=rig();c.sync.start();c.sync.stop();assert.equal(c.jobs.size,0);checks++;
 console.log('SYNC_PASS assertions='+checks+' (real controller, deterministic transport/time boundaries)');
})().catch(e=>{console.error(e);process.exitCode=1});
