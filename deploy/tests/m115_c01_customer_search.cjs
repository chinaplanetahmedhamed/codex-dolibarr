// Catches customer-option typing accidentally becoming a board mutation POST.
// Runs the real wsEnable handler and transport; only DOM/network/timer boundaries are doubled.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(__dirname+'/../../custom/chinaplanetoperations/js/boards.js','utf8');
const requests=[],timers=[],nodes=[];
class Node {
 constructor(tag,text=''){this.tag=tag;this.textContent=text;this.children=[];this.listeners={};this.value='';}
 append(...children){this.children.push(...children)}
 prepend(...children){this.children.unshift(...children)}
 replaceChildren(...children){this.children=children}
 setAttribute(){}
 addEventListener(name,handler){this.listeners[name]=handler}
 focus(){}
}
const select=new Node('select'),submit=new Node('button'),error=new Node('p');
const form={isConnected:true,prepend(){},querySelector(s){return {'[name=customer]':select,'[type=submit]':submit,'.cb-error':error}[s]}};
const ctx={URLSearchParams,cfg:{token:'synthetic-token',board:0,customer:0,mode:'customers'},search:{value:'unrelated gallery text'},archiveGallery:false,
 dialog:{querySelector(){return form},addEventListener(){}},wsText:(ar,en)=>en,formModal(){},
 el(tag,cls,text){const n=new Node(tag,text);nodes.push(n);return n},
 setTimeout(fn){timers.push(fn);return timers.length},clearTimeout(){},
 fetch:async(url,options)=>{requests.push({url,options});const get=options.method!=='POST';return {ok:get,status:get?200:403,json:async()=>get?{ok:true,data:{customers:new URL(url,'https://erp.invalid/').searchParams.get('q')==='حسن & Noor'? [{rowid:222,nom:'حسن & Noor'}]:[]}}:{ok:false,error:'Board access denied.'}}}
};
vm.createContext(ctx);
vm.runInContext(source.slice(source.indexOf('async function api('),source.indexOf('async function load(')),ctx);
vm.runInContext(source.slice(source.indexOf('async function wsEnable('),source.indexOf('function renderCustomerSpaces(')),ctx);
(async()=>{
 await ctx.wsEnable();
 const input=nodes.find(n=>n.type==='search');input.value='حسن & Noor';input.listeners.input();await timers.shift()();
 assert.equal(requests[1].options.method||'GET','GET','typing customer search must never invoke a mutation');
 const u=new URL(requests[1].url,'https://erp.invalid/');
 assert.equal(u.searchParams.get('action'),'customer_options');assert.equal(u.searchParams.get('q'),'حسن & Noor','use dialog query, preserving Arabic and ampersand');
 assert.equal(select.children[1].value,222);assert.equal(select.children[1].textContent,'حسن & Noor');assert.equal(error.textContent,'');assert.equal(submit.disabled,false);
 await assert.rejects(()=>ctx.api('disable_customer_workspace',{customer:222}),/Board access denied\./);
 assert.equal(requests.length,3);assert.equal(requests.at(-1).options.method,'POST');
 const p=requests.at(-1);const body=new URLSearchParams(p.options.body);assert.equal(body.get('token'),'synthetic-token');assert.equal(body.get('action'),'disable_customer_workspace');assert.deepEqual(JSON.parse(body.get('payload')),{customer:222});console.log('PASS real customer-search input emits GET with its own query; options render; mutations remain CSRF-protected POST.');
})().catch(e=>{console.error(e);process.exitCode=1});
