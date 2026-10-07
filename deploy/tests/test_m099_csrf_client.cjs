const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(process.argv[2]||require('node:path').resolve(__dirname,'../../custom/chinaplanetoperations/js/card_operation.js'),'utf8');
const request=source.slice(source.indexOf('async function request('),source.indexOf('function notify()'));
const url=source.slice(source.indexOf('function documentUrl('),source.indexOf('function recordLink('));
let calls=[];const scope={FormData,URLSearchParams,URL,cfg:{id:117,token:'synthetic-csrf-token'},location:{href:'https://staging.example/tasks/card.php?id=117',origin:'https://staging.example'},tr:(x)=>x,renderDenied:()=>{},fetch:async(path,options)=>{calls.push({path,options});return {ok:true,status:200,json:async()=>({ok:true,data:{context:'ok'}})};}};
vm.createContext(scope);vm.runInContext(request+url,scope);
(async()=>{
for(const action of ['context','options','versions']){await scope.request(action);const p=new URL(calls.at(-1).path,scope.location.href);assert.equal(p.searchParams.get('token'),scope.cfg.token);assert.equal(p.searchParams.get('task_id'),'117');}
await scope.request('rename',{document_id:7},true);assert.equal(calls.at(-1).options.method,'POST');assert.equal(calls.at(-1).options.body.get('token'),scope.cfg.token);
for(const action of ['download','preview']){const p=new URL(scope.documentUrl('context_api.php?action='+action+'&task_id=117&document_id=7'));assert.equal(p.origin,scope.location.origin);assert.equal(p.searchParams.get('token'),scope.cfg.token);assert.equal(p.searchParams.get('document_id'),'7');}
assert.throws(()=>scope.documentUrl('https://foreign.example/context_api.php'),/Invalid document URL/);
console.log('M099_CSRF_CLIENT=PASS assertions=15');
})().catch(e=>{console.error(e);process.exit(1)});
