import {spawnSync} from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {afterEach,expect,it,vi} from 'vitest';
import {DolibarrClient,dolibarrClient} from '/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m121-editor-contract-integration/server/_core/dolibarr';
import {appRouter} from '/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m121-editor-contract-integration/server/routers';
import {resolveErpContext} from '/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m121-editor-contract-integration/server/erpContext';
import {selectionFromContext,selectionContextForExport,erpReturnPath} from '/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m121-editor-contract-integration/client/src/lib/erpContext';
const root=process.env.M121_ERP_INTEGRATION_ROOT!;
function php(file:string,input?:unknown){const r=spawnSync(process.env.PHP_BINARY??'php',[path.join(root,'deploy/tests',file)],{input:input===undefined?undefined:JSON.stringify(input),encoding:'utf8'});expect(r.status,r.stderr||r.stdout).toBe(0);expect(r.stderr).toBe('');return JSON.parse(r.stdout);}
afterEach(()=>{vi.restoreAllMocks();vi.unstubAllGlobals();vi.unstubAllEnvs();});
async function setup(){
 const finance=php('m121_local_journey_finance.php');const context=finance.context;const uploads:any[]=[];const evidence:any[]=[];const calls:string[]=[];
 vi.stubEnv('ERP_CONTEXT_ADAPTER_ENABLED','1');vi.stubEnv('DOLIBARR_API_URL','http://127.0.0.1:18778/api/index.php');
 vi.stubGlobal('fetch',vi.fn(async (url:any,init:any)=>{
  const endpoint=new URL(String(url));expect(endpoint.origin).toBe('http://127.0.0.1:18778');calls.push(endpoint.pathname);
  let data:any;
  if(endpoint.pathname.endsWith('/users/info'))data={id:'19',socid:'0',statut:'1'}; // native staff lookup boundary mocked
  else if(endpoint.pathname.endsWith('/thirdparties/7'))data={...finance.customerRecord,name:'Synthetic journey customer'}; // native customer lookup boundary mocked
  else if(endpoint.pathname.endsWith('/projects/11'))data=finance.projectRecord; // native project lookup boundary mocked
  else if(endpoint.pathname.includes('/chinaplanetlogisticsapi/shipments/'))data=php('m121_local_journey_documents.php',{action:'read',context});
  else if(endpoint.pathname.endsWith('/chinaplanetlogisticsapi/upload')){const payload=JSON.parse(init.body);uploads.push(payload);const written=php('m121_local_journey_documents.php',{action:'upload',context,payload});evidence.push(written);data=written.receipt;}
  else throw new Error('Unexpected network request in synthetic test: '+endpoint.pathname);
  return new Response(JSON.stringify(data),{status:200,headers:{'Content-Type':'application/json'}});
 }));
 const transport=new DolibarrClient('http://127.0.0.1:18778/api/index.php');
 vi.spyOn(dolibarrClient,'getShipment').mockImplementation((ref,key)=>transport.getShipment(ref,key));vi.spyOn(dolibarrClient,'uploadDocument').mockImplementation((p,key)=>transport.uploadDocument(p,key));
 const actor={id:19,role:'staff',dolibarrToken:'SYNTHETIC-JOURNEY-TOKEN'};
 const caller=appRouter.createCaller({user:actor as any,req:{} as any,res:{} as any});
 const resolved=await resolveErpContext(context,actor,transport);
 const selection=selectionFromContext(resolved,{customerId:null,shipmentRef:'',touched:false});
 const exportContext=selectionContextForExport(resolved,selection!.customerId,selection!.shipmentRef);
 const input={targetRef:selection!.shipmentRef,customerId:selection!.customerId,editorContext:exportContext,modulepart:'chinaplanetlogistics' as const,documents:[{filename:'INVOICE.pdf',base64:Buffer.from('%PDF-1.4\n% SYNTHETIC M121 JOURNEY\n%%EOF\n').toString('base64')}],syncOptions:{syncDocuments:true,syncCustomer:false,syncProducts:false,syncLogistics:false}};
 return {finance,context,uploads,evidence,calls,caller,input,resolved};
}
it('seeded native deposit -> actual supplier attribution/views -> seeded shipment projection -> actual private atomic document',async()=>{
 const s=await setup();const response=await s.caller.dolibarr.syncSelected(s.input);
 expect(JSON.stringify(s.resolved)).not.toContain('SYNTHETIC_PRIVATE_CANARY');expect(s.finance.checks).toBe(8);expect(s.finance.native_posting_performed).toBe(false);
 expect(s.finance.phases[1].data).toMatchObject({total:60,paid:0,remaining:60});
 expect(s.finance.phases[3].supplier).toMatchObject({total:60,paid:40,remaining:20});
 expect(s.finance.phases[3].customer.currencies.USD).toMatchObject({received_cash:100,paid_cash:40,reserved:0,available_to_allocate:60});
 expect(s.finance.phases[4].data).toMatchObject({sourceId:17,status:2,statusLabel:'On Vessel'});
 expect(erpReturnPath(s.context)).toBe('/erp/custom/chinaplanetlogistics/card.php?id=17');
 expect(s.uploads).toHaveLength(1);expect(s.uploads[0]).toMatchObject({overwrite:0,publish_to_customer:0,ref:'SYN-M121-INTEGRATED'});
 expect((response.results.documents as any[])[0]).toMatchObject({filename:'SHIP-17-INVOICE-2.pdf',customer_visible:false,publication_id:null});
 expect(s.evidence[0]).toMatchObject({originalPreserved:true,fingerprintPreserved:true,returnedBytesMatch:true,mode:'0640',stagingNames:0,fileCount:2,shipmentStatus:2});
 fs.writeFileSync(path.join(root,'outputs/M121/local-assembly-2026-11-03/assembled-journey.json'),JSON.stringify({finance:s.finance,context:s.context,resolved:s.resolved,privateDocument:s.evidence[0],transport:'fetch fully mocked; no HTTP/socket/remote service; actual editor client/router and PHP API/atomic filesystem',calls:s.calls,limits:['native cash/order/invoice/shipment seeded','native customer/project/staff lookup and permissions synthetic','no actual document PDF generation, saved Docs draft or native session','private storage source contract only; no published-customer document','no financial posting/deployment/nativeMySQL']},null,2)+'\n');
});
it('mismatched export customer is rejected before private upload',async()=>{
 const s=await setup();await expect(s.caller.dolibarr.syncSelected({...s.input,customerId:8})).rejects.toMatchObject({code:'BAD_REQUEST'});expect(s.uploads).toHaveLength(0);expect(s.evidence).toHaveLength(0);
});
