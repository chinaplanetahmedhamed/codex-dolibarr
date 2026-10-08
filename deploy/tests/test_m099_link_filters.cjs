const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('custom/chinaplanetoperations/js/card_operation.js','utf8');
const edit=source.slice(source.indexOf('async function editLinks()'),source.indexOf('function privateUpload()'));
const selects={},calls=[];let deferred=false;const pending=[];let errorOutput;
function node(tag,cls,text){return {tag,cls,text,children:[],attributes:{},value:'',append(...items){this.children.push(...items)},replaceChildren(...items){this.children=items},get firstChild(){return this.children[0]},setAttribute(k,v){this.attributes[k]=v;if(k==='aria-label'&&tag==='select')selects[v]=this},focus(){}};}
const scope={node,tr:(en)=>en,btn:(label,fn)=>({label,onclick:fn}),open(){},dialogEpoch:1,state:{context:{version:1}},body:node('div'),message:()=>errorOutput={textContent:''},dialog:{close(){}},mutate(){},request:async(action,args)=>{calls.push(args);if(deferred&&args.type!=='customer')return new Promise((resolve,reject)=>pending.push({args,resolve,reject}));return {options:args.type==='customer'?[{id:10,label:'A'},{id:20,label:'B'}]:args.type==='project'?[{id:101,label:'project'}]:[{id:201,label:'shipment'}]}}};
vm.createContext(scope);vm.runInContext(edit,scope);
(async()=>{
await scope.editLinks();const customer=selects.Customer,project=selects['Project (optional)'],shipment=selects['Shipment (optional)'];
customer.value='10';await customer.onchange();
assert.equal(calls.at(-2).type,'project');assert.equal(calls.at(-2).customer_id,10);assert.equal(calls.at(-1).type,'shipment');assert.equal(calls.at(-1).customer_id,10);
project.value='101';await project.onchange();assert.equal(calls.at(-1).customer_id,10);assert.equal(calls.at(-1).project_id,101);
shipment.value='201';await shipment.onchange();assert.equal(customer.disabled,true);assert.equal(project.disabled,true);
shipment.value='0';await shipment.onchange();customer.value='20';await customer.onchange();assert.equal(calls.at(-1).customer_id,20);assert.equal(calls.at(-1).project_id,0);assert.equal(project.value,0);assert.equal(shipment.value,0);
// Delayed A -> B: neither old successes nor old failures may replace B or clear its busy state.
deferred=true;customer.value='10';const a=customer.onchange();customer.value='20';const b=customer.onchange();
assert.equal(pending.length,4);assert.equal(shipment.children.length,1);assert.equal(project.children.length,1);
pending[0].reject(new Error('obsolete A error'));pending[1].resolve({options:[{id:999,label:'obsolete A'}]});await a;
assert.equal(errorOutput.textContent,'');assert.equal(shipment.attributes['aria-busy'],'true');assert.equal(project.attributes['aria-busy'],'true');assert.equal(shipment.children.length,1);
pending[2].resolve({options:[{id:202,label:'B project'}]});pending[3].resolve({options:[{id:302,label:'B shipment'}]});await b;
assert.equal(project.children[1].text,'B project');assert.equal(shipment.children[1].text,'B shipment');assert.equal(shipment.attributes['aria-busy'],'false');
// A arrives after B: latest result remains and latest error is not erased by A.
pending.length=0;customer.value='10';const lateA=customer.onchange();customer.value='20';const fastB=customer.onchange();
pending[2].resolve({options:[{id:203,label:'latest B project'}]});pending[3].reject(new Error('current B error'));await fastB;
assert.equal(errorOutput.textContent,'current B error');pending[0].resolve({options:[{id:998,label:'late A project'}]});pending[1].resolve({options:[{id:999,label:'late A shipment'}]});await lateA;
assert.equal(project.children[1].text,'latest B project');assert.equal(errorOutput.textContent,'current B error');assert.equal(shipment.children.length,1);
console.log('M099_LINK_FILTERS=PASS customer change, project change, shipment authority, stale selections');
})().catch(e=>{console.error(e);process.exit(1)});
