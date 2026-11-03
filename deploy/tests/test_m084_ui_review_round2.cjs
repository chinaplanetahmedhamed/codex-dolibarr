/** Exact regressions from independent UI review of 68940d5. Local QA reads only. */
const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require(process.env.M084_PLAYWRIGHT||'playwright');
const root=fs.realpathSync(process.env.M084_QA_ROOT||''),out=process.env.M084_OUTPUT||root;
const state=JSON.parse(fs.readFileSync(root+'/state.json'));
assert(path.basename(root)==='m084-qa'&&/^m084_qa_[a-f0-9]{10}$/.test(state.database)&&state.port===8094&&state.outbound_disabled);
const creds=JSON.parse(fs.readFileSync(root+'/qa-credentials.json')),BASE='http://127.0.0.1:8094';
const ar=/[\u0600-\u06ff]/,latin=/[A-Za-z]/;
(async()=>{const b=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'}),rows=[];
try{
 for(const role of ['admin','staff','customer_a'].filter(role=>!process.env.M084_TEST_ROLE||process.env.M084_TEST_ROLE===role)){
  const c=await b.newContext();await c.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());const p=await c.newPage();const errors=[];p.on('pageerror',e=>errors.push(e.message));
  await p.goto(BASE);await p.fill('[name=username]','m084_'+role);await p.fill('[name=password]',creds.password);await Promise.all([p.waitForNavigation(),p.locator('[name=username]').evaluate(e=>e.form.submit())]);
  for(const lang of ['en_US','ar_SA']){
   if(role==='admin')for(const width of [320,390,768,820,1024,1440])for(const group of [0,1]){
    await p.setViewportSize({width,height:900});await p.goto(BASE+'/custom/chinaplanetcustomercenter/projects.php?status=active&lang='+lang);
    const overflow=await p.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,items:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(-12).map(e=>({tag:e.tagName,cls:e.className,width:e.getBoundingClientRect().width}))}));assert(overflow.scroll<=width,'page overflow '+lang+' '+width+' '+JSON.stringify(overflow));
    const header=await p.locator('.cpad-head').evaluate(e=>{const title=e.querySelector('h1').getBoundingClientRect(),badge=e.querySelector('.cpad-environment').getBoundingClientRect();return{overlap:Math.min(title.right,badge.right)>Math.max(title.left,badge.left)&&Math.min(title.bottom,badge.bottom)>Math.max(title.top,badge.top),badgeVisible:badge.width>0&&badge.height>0,titleHeight:title.height,badgeTop:badge.top}});
    assert(!header.overlap&&header.badgeVisible,'company title overlaps or hides environment '+width);
    if(width<=1024){
     const native=await p.locator('.cpcc-icon-menu-item').evaluateAll(items=>items.map(item=>{const icon=item.querySelector('a.tmenuimage'),label=item.querySelector('a.tmenulabel');return{name:icon.getAttribute('aria-label'),tabindex:icon.tabIndex,labelHidden:getComputedStyle(label).display==='none'};}));
     assert(native.length>=8&&native.every(x=>x.name&&x.tabindex===0&&x.labelHidden),'native compact navigation names/focus');
    }
    const toggle=p.locator('.cpad-nav-toggle');if(await toggle.isVisible())await toggle.press('Enter');
    const button=p.locator('.cpad-nav-group-toggle').nth(group);await button.press('Enter');assert.equal(await button.getAttribute('aria-expanded'),'true');
    const dropdown=p.locator('.cpad-nav-group.is-open .cpad-nav-dropdown');
    assert(await dropdown.evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth;}),'OPEN dropdown clipped '+lang+' '+width+' '+group);
    const links=[];
    for(const link of await dropdown.locator('a').all()){
     await link.scrollIntoViewIfNeeded();await link.focus();
     const hit=await link.evaluate(e=>{const r=e.getBoundingClientRect(),hit=document.elementFromPoint((r.left+r.right)/2,(r.top+r.bottom)/2);return{text:e.textContent.trim(),left:r.left,right:r.right,focused:e===document.activeElement,reachable:r.left>=0&&r.right<=innerWidth&&(hit===e||e.contains(hit))};});
     assert(hit.focused&&hit.reachable,'dropdown link not reachable '+JSON.stringify(hit));links.push(hit);
    }
    if(width===768&&lang==='en_US')await p.screenshot({path:out+'/dropdown-'+lang+'-'+width+'-'+group+'.png'});
    await button.press('Escape');assert.equal(await button.getAttribute('aria-expanded'),'false');
    rows.push({test:'open_dropdown_and_header',role,lang,width,group,header,links,pass:true});
   }
   for(const width of [390,1440]){
    await p.setViewportSize({width,height:900});await p.goto(BASE+'/custom/chinaplanetcustomercenter/projects.php?status=active&lang='+lang);
    const cards=await p.locator('.cpcc-project-view>span').allTextContents();assert.equal(cards.length,2);
    for(const text of cards)assert(lang==='ar_SA'?ar.test(text)&&!latin.test(text):latin.test(text)&&!ar.test(text),'project view not localized '+text);
    const headers=await p.locator('.cpcc-projects-page table th').allTextContents();assert(headers.length=== (role==='customer_a'?5:8));
    for(const text of headers)assert(lang==='ar_SA'?ar.test(text)&&!latin.test(text):latin.test(text)&&!ar.test(text),'project heading not localized '+text);
    assert((await p.locator('.cpcc-project-view-cards').getAttribute('aria-label'))===(lang==='ar_SA'?'طرق عرض المشروعات':'Project views'));
    if(role!=='customer_a')assert.equal(await p.locator('input[type=submit]').last().getAttribute('value'),lang==='ar_SA'?'تصفية':'Filter');
    else{assert.equal(await p.locator('.cpk-dialog-close').getAttribute('aria-label'),lang==='ar_SA'?'إغلاق تفاصيل المشروع':'Close project details');assert.equal(await p.locator('.cpk-dialog-frame').getAttribute('title'),lang==='ar_SA'?'تفاصيل بطاقة المشروع':'Project card details');}
    assert((await p.locator('.cpcc-projects-page').innerText()).includes('M084-PA'),'project fixture missing');
    if(process.env.M084_AXE){await p.addScriptTag({path:process.env.M084_AXE});const violations=await p.evaluate(async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa','best-practice']}});return r.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}));});assert.deepEqual(violations,[],'projects axe');}
    await p.screenshot({path:out+'/'+role+'-projects-'+lang+'-'+width+'.png',fullPage:false});
    await p.goto(BASE+'/custom/chinaplanetlogistics/card.php?id=101&lang='+lang);
    // The command-center H1 is the shipment reference, not a translated label.
    const selectors=['.cpl-command-center h2','#cpcc-gallery-title','#cpcc-documents-title','.cpcc-customer-uploaded-section h3'];
    if(role!=='customer_a')selectors.push('.cpad-section-heading h2','#shipment-files-manager h2','label[for=cpl-staff-upload]','#shipment-files-manager button.button-save');
    const labels=[];
    for(const selector of selectors)for(const label of await p.locator(selector).allTextContents()){
     assert(!(ar.test(label)&&latin.test(label)),'mixed language shipment control '+label);assert(lang==='ar_SA'?ar.test(label):!ar.test(label),'wrong language shipment control '+label);labels.push(label);
    }
    assert(labels.length>=3,'shipment labels not found '+role+' '+lang+' '+JSON.stringify(await p.locator('h1,h2,h3').allTextContents()));
    if(process.env.M084_AXE){await p.addScriptTag({path:process.env.M084_AXE});const violations=await p.evaluate(async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa','best-practice']}});return r.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}));});assert.deepEqual(violations,[],'shipment axe');}
    await p.screenshot({path:out+'/'+role+'-shipment-'+lang+'-'+width+'.png',fullPage:false});
    rows.push({test:'projects_and_shipment_localization',role,lang,width,projectCards:cards,projectHeaders:headers,shipmentLabels:labels,pass:true});
   }
  }
  assert.deepEqual(errors,[],'JavaScript errors');await c.close();
 }
 fs.writeFileSync(out+'/round2-results.json',JSON.stringify({status:'PASS',checks:rows.length,rows},null,2));console.log('M084_UI_REVIEW_ROUND2_PASS',rows.length);
}finally{await b.close();}})().catch(e=>{console.error(e);process.exit(1)});
