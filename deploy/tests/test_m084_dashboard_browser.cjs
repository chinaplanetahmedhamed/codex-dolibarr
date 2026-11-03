const {chromium}=require(process.env.M084_PLAYWRIGHT || 'playwright');
const fs=require('fs');const path=require('path');
const qaRoot=fs.realpathSync(process.env.M084_QA_ROOT || '');
if(path.basename(qaRoot)!=='m084-qa')throw Error('ISOLATED_M084_QA_REQUIRED');
const qaState=JSON.parse(fs.readFileSync(qaRoot+'/state.json'));
if(!/^m084_qa_[a-f0-9]{10}$/.test(qaState.database)||qaState.port!==8094||!qaState.outbound_disabled)throw Error('QA_SCOPE_INVALID');

const OUT=process.env.M084_OUTPUT || qaRoot;
const creds=JSON.parse(fs.readFileSync(qaRoot+'/qa-credentials.json'));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 const results=[];
 for(const lang of ['en_US','ar_SA']){
  const context=await browser.newContext({viewport:{width:1440,height:1000},locale:lang==='ar_SA'?'ar-SA':'en-US'});
  await context.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());
  const page=await context.newPage();
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8094/custom/chinaplanetcustomercenter/admin_dashboard.php?lang='+lang);
  await page.locator('input[name=username]').fill('m084_admin');await page.locator('input[name=password]').fill(creds.password);
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded',timeout:15000}),page.locator('input[name=username]').evaluate(e=>e.form.submit())]);
  console.log('UI_LOGIN',lang,page.url(),await page.title());
  if(await page.locator('input[name=username]').count())throw new Error('QA browser login failed');
  const paths={dashboard:'/custom/chinaplanetcustomercenter/admin_dashboard.php',projects:'/custom/chinaplanetcustomercenter/projects.php?status=active',shipment:'/custom/chinaplanetlogistics/card.php?id=101',invoices:'/custom/chinaplanetcustomercenter/invoices.php'};
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:width===390?844:1000});
   for(const [name,path] of Object.entries(paths)){
    await page.goto('http://127.0.0.1:8094'+path+(path.includes('?')?'&':'?')+'lang='+lang);await page.waitForTimeout(250);
    const row=await page.evaluate(()=>({title:document.title,overflow:document.documentElement.scrollWidth>innerWidth,width:innerWidth,main:!!document.querySelector('main,.cpad-widget-grid'),projectRows:[...document.querySelectorAll('a')].filter(a=>a.textContent==='M084-PA').length,dates:[...document.querySelectorAll('.cpl-command-center time')].map(e=>e.textContent),direction:document.querySelector('.cpl-command-center')?.dir,widgets:document.querySelectorAll('[data-cpad-widget]').length,selector:!!document.querySelector('#cpcc-customer-select')}));
    row.page=name;row.lang=lang;row.errors=errors.splice(0);console.log('UI_VIEW',name,lang,width,row.title,row.widgets);
    if(name==='dashboard'&&!row.widgets){fs.writeFileSync(OUT+'/ui-debug.html',await page.content());throw new Error('Dashboard not rendered');}
    if(row.overflow)throw new Error('Overflow: '+name+' '+width+' '+lang);
    if(name==='shipment'&&row.dates.some(v=>/\d{4} \/ \d/.test(v)))throw new Error('Duplicate date');
    if(name==='projects'&&!row.projectRows)throw new Error('Missing project');
    if(name==='dashboard'){
     await page.locator('#cpad-customize').click();
     const first=page.locator('[data-cpad-widget]').first();
     const key=await first.getAttribute('data-cpad-widget');
     const size=await first.getAttribute('data-size');
     await first.locator('.cpad-resize-widget').click();
     if(await first.getAttribute('data-size')===size)throw new Error('Resize did not change');
     await first.locator('.cpad-hide-widget').click();
     if(await first.getAttribute('data-visible')!=='0')throw new Error('Hide failed');
     await page.locator('#cpad-widget-library-button').click();
     await page.locator('#cpad-widget-library-close').click();
     await page.locator('#cpad-cancel-layout').click();await page.waitForLoadState('load');
     await page.waitForTimeout(200);
     row.controls='resize/hide/library/cancel';
    }
    if(name==='invoices'){
     await page.selectOption('#cpcc-customer-select','101');
     await Promise.all([page.waitForURL(/socid=101/),page.locator('#cpcc-customer-select').evaluate(e=>e.form.submit())]);
     if(!(await page.locator('body').innerText()).includes('M084-INV-A'))throw new Error('Selector did not load customer invoices');
     row.selectorSubmit=true;
    }
    await page.screenshot({path:OUT+'/'+name+'-'+lang+'-'+width+'.png',fullPage:false});results.push(row);
   }
  }
  await context.close();
 }
 fs.writeFileSync(OUT+'/ui-results.json',JSON.stringify(results,null,2));
 await browser.close();console.log(JSON.stringify({status:'PASS',views:results.length,jsErrors:results.flatMap(r=>r.errors)}));
})().catch(e=>{console.error(e);process.exit(1)});
