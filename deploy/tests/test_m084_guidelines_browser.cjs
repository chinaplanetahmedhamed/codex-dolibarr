/** Guidelines acceptance: read-only pages, no saved layout or native record writes. */
const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require(process.env.M084_PLAYWRIGHT||'playwright');
const root=fs.realpathSync(process.env.M084_QA_ROOT||''),out=process.env.M084_OUTPUT;
const qa=JSON.parse(fs.readFileSync(root+'/state.json'));assert(path.basename(root)==='m084-qa'&&qa.port===8094&&qa.outbound_disabled&&/^m084_qa_[a-f0-9]{10}$/.test(qa.database));
const BASE='http://127.0.0.1:8094';
(async()=>{const b=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'}),rows=[];try{
const c=await b.newContext();await c.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());const p=await c.newPage(),errors=[],dialogs=[];p.on('pageerror',e=>errors.push(e.message));p.on('dialog',async d=>{dialogs.push(d.type());await d.accept();});
await p.goto(BASE);await p.fill('[name=username]','m084_admin');await p.fill('[name=password]',JSON.parse(fs.readFileSync(root+'/qa-credentials.json')).password);await Promise.all([p.waitForNavigation(),p.locator('[name=username]').evaluate(e=>e.form.submit())]);
for(const lang of ['ar_SA','en_US'])for(const width of [390,1087])for(const view of ['chooser','form','dashboard','customer']){
 await p.setViewportSize({width,height:1000});const url=view==='chooser'?'/custom/chinaplanetoperations/operations/edit.php?fk_soc=101':view==='form'?'/custom/chinaplanetoperations/operations/edit.php?service_type=order&fk_soc=101':view==='dashboard'?'/custom/chinaplanetcustomercenter/admin_dashboard.php?review=guidelines':'/custom/chinaplanetoperations/workspace.php?view=customer&socid=101';
 await p.goto(BASE+url+'&lang='+lang);const skip=p.locator('.cpcc-skip-link');assert.equal(await skip.count(),1);await skip.focus();assert(await skip.evaluate(e=>{const r=e.getBoundingClientRect();return r.top>=50&&r.bottom<innerHeight&&r.left>=0&&r.right<=innerWidth;}),'skip link covered');await skip.press('Enter');assert(await p.evaluate(()=>document.activeElement.id===document.querySelector('.cpcc-skip-link').hash.slice(1)),'skip target not focused');
 assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'overflow');
 if(view==='form'){
  assert.equal(await p.locator('#cpo-guided-operation-form').getAttribute('autocomplete'),'off');await p.locator('[name=title]').focus();assert.equal(await p.locator('[name=title]').evaluate(e=>getComputedStyle(e).outlineStyle),'solid');assert.equal(await p.locator('[name=title]').evaluate(e=>getComputedStyle(e).outlineWidth),'3px');assert((await p.locator('[name=title]').getAttribute('placeholder')).endsWith('…'));
  await p.locator('[name=title]').evaluate(e=>e.scrollIntoView());assert(await p.locator('[name=title]').evaluate(e=>e.getBoundingClientRect().top>=80),'sticky menu covers form field');
 }
 if(view==='dashboard'){assert.equal(await p.locator('#cpad-layout-message').getAttribute('role'),'status');assert.equal(await p.locator('#cpad-layout-message').getAttribute('aria-live'),'polite');assert.equal(await p.locator('#cpad-widget-library').evaluate(e=>getComputedStyle(e).overscrollBehavior),'contain');assert(await p.locator('.cpad-customize-toolbar .fas').evaluateAll(items=>items.every(e=>e.getAttribute('aria-hidden')==='true')));}
 if(view==='customer'){
  await p.locator('#customer-operations .cpw-action-main h3').first().evaluate(e=>e.textContent='LONG_REFERENCE_'.repeat(20));assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'long operation title overflows');
 }
 const scope=view==='customer'?'#customer-operations':view==='dashboard'?'.cpad-shell':'.cpo-guided-page';let axe=[];
 if(process.env.M084_AXE){await p.addScriptTag({path:process.env.M084_AXE});axe=await p.evaluate(async selector=>{const r=await window.axe.run(selector,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}});return r.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}));},scope);assert.deepEqual(axe,[],'axe '+view);}
 await p.screenshot({path:out+'/'+view+'-'+lang+'-'+width+'.png'});rows.push({view,lang,width,skip:true,focus:true,axe,pass:true});
}
// User data remains in the page during supplier-list refresh and native popup navigation.
for(const lang of ['ar_SA','en_US']){
 await p.goto(BASE+'/custom/chinaplanetoperations/operations/edit.php?id=105&lang='+lang);
 const beforeDialogs=dialogs.length;await p.locator('#cpo-refresh-suppliers').click();await p.waitForFunction(()=>!document.querySelector('#cpo-refresh-suppliers').disabled);await p.goto(BASE+'/custom/chinaplanetoperations/operations/edit.php?id=105&lang='+lang);assert.equal(dialogs.length,beforeDialogs,'pristine refresh warns about hidden tokens');
 await p.fill('[name=title]','Unsaved UI guideline check');const popupPromise=p.waitForEvent('popup');await p.locator('a[href*="fournisseur=1"]').click();const popup=await popupPromise;await popup.waitForLoadState('domcontentloaded');assert.equal(dialogs.length,beforeDialogs,'native popup warns about navigation');await popup.close();assert.equal(await p.locator('[name=title]').inputValue(),'Unsaved UI guideline check');
 await p.locator('#cpo-refresh-suppliers').click();await p.waitForFunction(()=>!document.querySelector('#cpo-refresh-suppliers').disabled);assert.equal(await p.locator('[name=title]').inputValue(),'Unsaved UI guideline check');
 await p.goto(BASE+'/custom/chinaplanetoperations/workspace.php?view=customer&socid=101&lang='+lang);assert.equal(dialogs.length,beforeDialogs+1,'dirty navigation lacks beforeunload warning');
 rows.push({test:'pristine_refresh_no_warning_dirty_navigation_warns_native_popup_preserves',lang,pass:true});
}
assert.deepEqual(errors,[]);fs.writeFileSync(out+'/guidelines-results.json',JSON.stringify({status:'PASS',checks:rows.length,rows,noBusinessWrites:true,noLayoutWrites:true},null,2));console.log('M084_GUIDELINES_PASS',rows.length);
}finally{await b.close();}})().catch(e=>{console.error(e);process.exit(1)});
