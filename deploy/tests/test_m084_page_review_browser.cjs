/** Focused order form and dashboard toolbar checks. Saves/restores the exact personal layout. */
const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require(process.env.M084_PLAYWRIGHT||'playwright');
const root=fs.realpathSync(process.env.M084_QA_ROOT||''),out=process.env.M084_OUTPUT;
const qa=JSON.parse(fs.readFileSync(root+'/state.json'));
assert(path.basename(root)==='m084-qa'&&/^m084_qa_[a-f0-9]{10}$/.test(qa.database)&&qa.port===8094&&qa.outbound_disabled);
const BASE='http://127.0.0.1:8094',rows=[];
(async()=>{const b=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});try{
 const c=await b.newContext();await c.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());const p=await c.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));p.on('dialog',async d=>{assert.equal(d.type(),'beforeunload');await d.accept();});
 await p.goto(BASE);await p.fill('[name=username]','m084_admin');await p.fill('[name=password]',JSON.parse(fs.readFileSync(root+'/qa-credentials.json')).password);await Promise.all([p.waitForNavigation(),p.locator('[name=username]').evaluate(e=>e.form.submit())]);
 for(const lang of ['ar_SA','en_US'])for(const width of [320,390,768,1087,1359,1440]){
  await p.setViewportSize({width,height:1000});await p.goto(BASE+'/custom/chinaplanetoperations/operations/edit.php?fk_soc=101&lang='+lang);
  assert.equal(await p.locator('#cpo-guided-operation-form').count(),0,'chooser must not include form');
  const services=await p.locator('.cpo-service-grid small').evaluateAll(items=>items.map(e=>({size:parseFloat(getComputedStyle(e).fontSize),color:getComputedStyle(e).color})));
  assert(services.length===12&&services.every(x=>x.size>=14&&x.color==='rgb(70, 86, 108)'),'service description contrast');
  await p.screenshot({path:out+'/chooser-'+lang+'-'+width+'.png'});
  await p.locator('.cpo-service-grid a[href*="service_type=order"]').click();
  assert.equal(await p.locator('.cpo-service-grid').count(),0,'selected service must have dedicated form');
  assert.equal(await p.locator('#cpo-customer').inputValue(),'101','customer lost from chooser');
  assert.equal(await p.locator('.cpo-change-service').count(),1);
  assert.equal(await p.locator('input[name="service_data[product_summary]"],input[name="service_data[quantity]"],input[name="service_data[target_price]"],input[name="service_data[order_value]"],input[name="service_data[currency]"]').count(),0,'single-product fields still shown');
  assert.equal(await p.locator('#cpo-supplier option[value="301"]').count(),1);
  assert.equal(await p.locator('#cpo-supplier option[value="302"],#cpo-supplier option[value="401"],#cpo-supplier option[value="101"]').count(),0);
  assert.equal(await p.locator('select[name=waiting_on]').inputValue(),'internal');
  assert.equal(await p.locator('select[name=waiting_on]').locator('..').locator('span').textContent(),lang==='ar_SA'?'في انتظار إجراء من مَن؟':'Waiting for action from whom?');
  await p.selectOption('#cpo-order','301');assert((await p.locator('#cpo-native-order').getAttribute('href')).includes('commande/card.php?id=301'));assert(await p.locator('#cpo-native-order').isVisible());
  await p.selectOption('#cpo-customer','102');assert(await p.locator('#cpo-native-order').isHidden());await p.selectOption('#cpo-customer','101');
  const menu=await p.locator('#id-top .cpcc-icon-menu-item').evaluateAll(items=>items.map(e=>({name:e.querySelector('a.tmenuimage').getAttribute('aria-label'),focus:e.querySelector('a.tmenuimage').tabIndex,hidden:getComputedStyle(e.querySelector('a.tmenulabel')).display==='none'})));
  assert(menu.length>=8&&menu.every(x=>x.name),'native menu accessible names');
  if(await p.locator('body').evaluate(e=>e.classList.contains('cpcc-native-compact')))assert(menu.every(x=>x.focus===0&&x.hidden),'compact menu still clips duplicate labels');
  const form=p.locator('#cpo-guided-operation-form');assert.equal(await form.getAttribute('id'),'cpo-guided-operation-form');assert.equal(await form.locator('[name=lang]').inputValue(),lang);
  const measures=await p.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,small:[...document.querySelectorAll('.cpo-service-grid small')].map(e=>({size:parseFloat(getComputedStyle(e).fontSize),color:getComputedStyle(e).color})),labels:[...document.querySelectorAll('.cpo-field>span,.cpo-field>label')].map(e=>({text:e.textContent.trim(),size:parseFloat(getComputedStyle(e).fontSize)})),fields:[...document.querySelectorAll('.cpo-field input,.cpo-field select,.cpo-field textarea')].map(e=>parseFloat(getComputedStyle(e).fontSize)),teamBeforeOwner:!!(document.querySelector('#cpo-team').compareDocumentPosition(document.querySelector('#cpo-owner'))&Node.DOCUMENT_POSITION_FOLLOWING)}));
  assert(!measures.overflow,'overflow '+lang+width);assert(measures.small.every(x=>x.size>=14&&x.color==='rgb(70, 86, 108)'),'description size/contrast '+JSON.stringify(measures.small));assert(measures.labels.every(x=>x.size>=15),'label size');assert(measures.fields.every(x=>x>=16),'input size');assert(measures.teamBeforeOwner);
  if(lang==='en_US')assert(!(await p.locator('.cpo-guided-page').innerText()).match(/[\u0600-\u06ff]/),'Arabic static text on English form');
  assert.equal(await p.locator('.cpo-form-actions button').textContent(),lang==='ar_SA'?'حفظ':'Save');
  assert(!(await p.locator('.cpo-guided-page').innerText()).includes('CargoX'),'technical integration notice');
  await p.selectOption('#cpo-team','1');assert(await p.locator('#cpo-owner option[value="2"]').evaluate(e=>!e.hidden),'team owner unavailable');
  await p.selectOption('#cpo-customer','101');assert(await p.locator('#cpo-project option[value="101"]').evaluate(e=>!e.hidden));assert(await p.locator('#cpo-project option[value="102"]').evaluate(e=>e.hidden));assert(await p.locator('#cpo-project option[value="0"]').evaluate(e=>!e.hidden));
  let axe=[];if(process.env.M084_AXE){await p.addScriptTag({path:process.env.M084_AXE});axe=await p.evaluate(async()=>{const r=await window.axe.run('.cpo-guided-page',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}});return r.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}));});assert.deepEqual(axe,[],'form accessibility '+JSON.stringify(axe));}
  await p.screenshot({path:out+'/order-'+lang+'-'+width+'.png',fullPage:true});rows.push({page:'order',lang,width,measures,axe,pass:true});
  await p.goto(BASE+'/custom/chinaplanetcustomercenter/admin_dashboard.php?lang='+lang);
  assert(await p.locator('#cpad-customize').evaluate(e=>{const r=e.getBoundingClientRect(),grid=document.querySelector('#cpad-widget-grid').getBoundingClientRect();return r.height>=44&&r.top>=0&&r.bottom<innerHeight&&r.bottom<=grid.top;}),'customization not obvious before widgets');await p.screenshot({path:out+'/dashboard-'+lang+'-'+width+'.png'});rows.push({page:'dashboard',lang,width,topEntry:true,pass:true});
 }
 for(const lang of ['ar_SA','en_US'])for(const width of [390,1359]){
  await p.setViewportSize({width,height:1000});await p.goto(BASE+'/custom/chinaplanetoperations/workspace.php?view=customer&socid=101&lang='+lang);
  assert.equal(await p.locator('.cpw-unavailable').count(),0,'removed warning remains');
  const section=p.locator('#customer-operations');assert(await section.locator('.cpw-action-card').count()>0,'operation fixture absent');
  assert(await section.locator('.cpw-next strong').evaluateAll(items=>items.every(e=>getComputedStyle(e).color==='rgb(23, 32, 51)'&&parseFloat(getComputedStyle(e).fontSize)>=16)));
  assert(await section.locator('.cpw-next span').evaluateAll(items=>items.every(e=>getComputedStyle(e).color==='rgb(70, 86, 108)'&&parseFloat(getComputedStyle(e).fontSize)>=14)));
  assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'workspace overflow');
  await section.scrollIntoViewIfNeeded();await p.screenshot({path:out+'/customer-operations-'+lang+'-'+width+'.png'});
  if(process.env.M084_AXE){await p.addScriptTag({path:process.env.M084_AXE});const violations=await p.evaluate(async()=>{const r=await axe.run('#customer-operations',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}});return r.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}));});assert.deepEqual(violations,[],'customer operation card accessibility');}
  rows.push({page:'customer_operations',lang,width,contrast:true,pass:true});
 }
 for(const lang of ['ar_SA','en_US']){
  await p.goto(BASE+'/custom/chinaplanetoperations/operations/edit.php?id=105&lang='+lang);
  const nativeAdd=p.locator('a[href*="fournisseur=1"]');assert.equal(await nativeAdd.getAttribute('target'),'_blank');assert.equal(await nativeAdd.getAttribute('rel'),'noopener');
  const back=new URL(new URL(await nativeAdd.getAttribute('href'),BASE).searchParams.get('backtopage'),BASE);assert.equal(back.searchParams.get('id'),'105');assert.equal(back.searchParams.get('lang'),lang);assert.equal(back.searchParams.get('fk_soc'),'101');
  await p.fill('input[name=title]','Unsaved supplier refresh test');await p.selectOption('#cpo-supplier','301');const before=await p.locator('input[name=request_hash]').inputValue();
  await p.click('#cpo-refresh-suppliers');await p.waitForFunction(()=>document.querySelector('#cpo-supplier-refresh-status').textContent.includes(document.documentElement.lang.startsWith('ar')?'تم تحديث':'updated'));
  assert.equal(await p.locator('input[name=title]').inputValue(),'Unsaved supplier refresh test');assert.equal(await p.locator('#cpo-supplier').inputValue(),'301');assert.equal(await p.locator('input[name=request_hash]').inputValue(),before);
  assert.equal(await p.locator('input[name=title]').locator('..').locator('span').textContent(),lang==='ar_SA'?'عنوان العملية *':'Operation title *');
  rows.push({test:'supplier_refresh_preserves_form_and_native_return',lang,pass:true});
 }
 // Back up the persisted data before any write; restore even when a test assertion fails.
 await p.setViewportSize({width:1440,height:1000});await p.goto(BASE+'/custom/chinaplanetcustomercenter/admin_dashboard.php?lang=en_US');
 const readLayout=()=>p.locator('#cpad-layout-data').evaluate(e=>JSON.parse(e.textContent).layout);
 const initial=await readLayout();fs.writeFileSync(out+'/personal-layout-before.json',JSON.stringify(initial,null,2),{mode:0o600});
 const visibleState=()=>p.evaluate(()=>[...document.querySelectorAll('#cpad-widget-grid>[data-cpad-widget]')].map(e=>({key:e.dataset.cpadWidget,size:e.dataset.size,visible:e.dataset.visible})));
 const initialView=await visibleState();
 try{
  await p.click('#cpad-customize');await p.locator('#cpad-widget-grid>[data-cpad-widget]').first().locator('.cpad-drag-handle').press('ArrowDown');const edited=await visibleState();assert.notDeepEqual(edited,initialView);
  const response=p.waitForResponse(r=>r.url().includes('/ajax/dashboard_layout.php')&&r.request().method()==='POST');await p.click('#cpad-save-layout');assert((await (await response).json()).success);await p.reload();assert.deepEqual(await visibleState(),edited);rows.push({test:'reorder_save_reload',pass:true});
 }finally{
  const restored=await p.evaluate(async saved=>{const config=JSON.parse(document.querySelector('#cpad-layout-data').textContent);const body=new URLSearchParams({action:saved.length?'save':'reset',token:config.token,layout:JSON.stringify(saved)});return(await fetch(config.endpoint,{method:'POST',body})).json();},initial);assert(restored.success,'layout restore failed');await p.reload();assert.deepEqual(await readLayout(),initial,'persisted personal layout differs after restore');assert.deepEqual(await visibleState(),initialView,'rendered personal layout differs after restore');fs.writeFileSync(out+'/personal-layout-after.json',JSON.stringify(await readLayout(),null,2),{mode:0o600});
 }
 assert.deepEqual(errors,[]);fs.writeFileSync(out+'/presentation-results.json',JSON.stringify({status:'PASS',checks:rows.length,rows,personalLayoutRestored:true,idAttributeCorrect:true},null,2));console.log('M084_PAGE_REVIEW_PASS',rows.length,'PERSONAL_LAYOUT_RESTORED');
}finally{await b.close();}})().catch(e=>{console.error(e);process.exit(1)});
