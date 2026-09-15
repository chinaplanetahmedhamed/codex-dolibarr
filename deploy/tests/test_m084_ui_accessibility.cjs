/** Isolated local UI regression. No business writes, outbound requests or live targets. */
const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require(process.env.M084_PLAYWRIGHT||'playwright');
const root=fs.realpathSync(process.env.M084_QA_ROOT||''),out=process.env.M084_OUTPUT||root;
const state=JSON.parse(fs.readFileSync(root+'/state.json'));
assert(path.basename(root)==='m084-qa'&&/^m084_qa_[a-f0-9]{10}$/.test(state.database)&&state.port===8094&&state.outbound_disabled);
const creds=JSON.parse(fs.readFileSync(root+'/qa-credentials.json')),BASE='http://127.0.0.1:8094';
(async()=>{
 const b=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'}),results=[];
 try{
 const c=await b.newContext();await c.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());const p=await c.newPage();
 await p.goto(BASE);await p.fill('[name=username]','m084_admin');await p.fill('[name=password]',creds.password);await Promise.all([p.waitForNavigation(),p.locator('[name=username]').evaluate(e=>e.form.submit())]);
 for(const lang of ['en_US','ar_SA']){
  for(const width of [320,390,768,820,1024,1440]){
   await p.setViewportSize({width,height:844});
   await p.goto(BASE+'/custom/chinaplanetcustomercenter/projects.php?status=active&lang='+lang);
   assert(await p.locator('#cpcc-project-customer option[value="101"]').textContent()==='M084 Customer A');
   assert(!(await p.locator('#cpcc-project-customer').textContent()).includes('M084 ENTITY2'));
   assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'projects overflow '+width);
   const toggle=p.locator('.cpad-nav-toggle');if(await toggle.isVisible())await toggle.click();
   for(const a of await p.locator('.cpad-nav-links>a').all()){
    await a.focus();assert(await a.evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth;}),'navigation clipped');
   }
   results.push({test:'tablet_and_phone_navigation',lang,width,pass:true});
  }
  await p.goto(BASE+'/custom/chinaplanetcustomercenter/index.php?lang='+lang);
  assert((await p.locator('html').getAttribute('lang')).startsWith(lang.slice(0,2)),'redirect language');
  await p.goto(BASE+'/custom/chinaplanetcustomercenter/invoices.php?lang='+lang);
  await p.selectOption('#cpcc-customer-select','101');await Promise.all([p.waitForNavigation(),p.locator('#cpcc-customer-select').evaluate(e=>e.form.submit())]);
  assert(p.url().includes('lang='+lang),'selector language');assert((await p.locator('body').innerText()).includes('M084-INV-A'));
  await p.goto(BASE+'/custom/chinaplanetcustomercenter/admin_dashboard.php?lang='+lang);await p.setViewportSize({width:390,height:844});
  const config=JSON.parse(await p.locator('#cpad-layout-data').textContent());
  if(!config.layout.length)assert.equal(await p.locator('#cpad-widget-grid>[data-cpad-widget]').first().getAttribute('data-cpad-widget'),'attention_center');
  assert(await p.locator('.cpad-environment').isVisible(),'environment hidden');
  await p.click('#cpad-customize');
  const button=p.locator('.cpad-resize-widget').first();assert(await button.evaluate(e=>{const r=e.getBoundingClientRect();return r.width>=44&&r.height>=44}));
  await p.click('#cpad-widget-library-button');assert(await p.locator('#cpad-widget-library').evaluate(e=>e.open));assert(await p.locator('#cpad-widget-library-close').evaluate(e=>e===document.activeElement));
  await p.keyboard.press('Shift+Tab');assert(await p.locator('#cpad-widget-library').evaluate(e=>e.contains(document.activeElement)),'focus escaped dialog');
  await p.keyboard.press('Escape');assert(!await p.locator('#cpad-widget-library').evaluate(e=>e.open));assert(await p.locator('#cpad-widget-library-button').evaluate(e=>e===document.activeElement));
  await p.goto(BASE+'/custom/chinaplanetlogistics/list.php?lang='+lang);await p.waitForTimeout(120);
  const cards=await p.locator('.cpl-shipment-list tbody tr').evaluateAll(rows=>rows.map(row=>{const cells=[...row.querySelectorAll('td')],rect=row.getBoundingClientRect();return{contained:cells.every(e=>e.getBoundingClientRect().bottom<=rect.bottom+1),noOverlap:cells.every(e=>{const r=e.getBoundingClientRect();return cells.every(f=>f===e||Math.min(r.bottom,f.getBoundingClientRect().bottom)-Math.max(r.top,f.getBoundingClientRect().top)<1)}),status:row.querySelector('[data-column=status]')?.textContent,eta:row.querySelector('[data-column=eta]')?.textContent}}));
  assert(cards.length===5&&cards.every(r=>r.contained&&r.noOverlap&&r.status&&r.eta),'mobile card overlap/missing context');
  await p.screenshot({path:out+'/final-shipment-list-'+lang+'-390.png',fullPage:true});
  await p.goto(BASE+'/custom/chinaplanetlogistics/card.php?id=101&lang='+lang);
  assert(await p.locator('label[for="cpl-staff-upload"]').textContent());
  assert(await p.locator('#cpl-staff-upload').getAttribute('aria-describedby')==='cpl-upload-limit');
  results.push({test:'language_dialog_mobile_cards_upload_name',lang,pass:true});
 }
 fs.writeFileSync(out+'/accessibility-interaction-results.json',JSON.stringify({status:'PASS',results},null,2));console.log('M084_UI_ACCESSIBILITY_PASS',results.length);
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1)});
