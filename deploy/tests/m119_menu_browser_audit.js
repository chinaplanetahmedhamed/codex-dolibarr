// Read-only authenticated browser audit. Does not submit any form or change native values.
async function auditOfficeMenus(page, cases) {
  const results = [];
  let lastUrl = null;
  for (const test of cases) {
    await page.setViewportSize({width:test.width,height:900});
    const url = 'https://backup-erp.visiontrading.biz/custom/chinaplanetofficeexpenses/' +
      (test.view === 'users' ? 'users.php?' : 'workspace.php?view='+test.view+'&')+
      'lang='+test.lang+'&office='+test.office;
    if (url !== lastUrl) { await page.goto(url,{waitUntil:'domcontentloaded'}); lastUrl=url; }
    await page.locator('.office-expenses-app .select2-container').first().waitFor();
    const result = await page.evaluate(({lang,office,view}) => {
      const root=document.querySelector('.office-expenses-app');
      const links=[...root.querySelectorAll('nav a')];
      return {lang,office,view,width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+2,
        wrongLinks:links.filter(a=>{const u=new URL(a.href);return u.searchParams.get('lang')!==lang||u.searchParams.get('office')!==office}).length,
        current:root.querySelectorAll('nav a[aria-current=page]').length,
        popups:[],errors:[]};
    },test);
    const mobile = page.locator('.workspace-mobile-menu, .staff-mobile-menu');
    if (await mobile.count() && await mobile.first().isVisible()) {
      await mobile.first().locator('summary').evaluate(e=>e.click());
      result.mobileLinks=await mobile.first().locator('nav a:visible').count();
      await mobile.first().locator('summary').evaluate(e=>e.click());
    }
    async function checkSelects(scope) {
      const ids = await page.locator(scope+' select').evaluateAll(list=>list.filter(s=>!s.disabled&&s.id&&window.jQuery(s).data('select2')&&window.jQuery(s).next('.select2').is(':visible')).map(s=>s.id));
      for (const id of ids) {
        await page.locator('#'+id+' + .select2').evaluate(e=>e.scrollIntoView({block:'center',behavior:'instant'}));
        await page.waitForFunction(id=>{const e=document.querySelector('#'+id+' + .select2'),r=e.getBoundingClientRect(),d=e.closest('dialog[open]'),b=d?d.getBoundingClientRect():{top:0,bottom:innerHeight};return r.top>=Math.max(0,b.top)&&r.bottom<=Math.min(innerHeight,b.bottom);},id,{polling:50,timeout:5000});
        const popup=await page.evaluate(async id=>{
          const s=document.getElementById(id),q=window.jQuery(s);q.select2('open');
          await Promise.resolve();
          const d=document.querySelector('.select2-dropdown'),c=q.next('.select2')[0],dialog=s.closest('dialog[open]');
          if(!d)return {id,error:'not-open'};
          const r=d.getBoundingClientRect(),control=c.getBoundingClientRect(),b=dialog?dialog.getBoundingClientRect():{left:0,right:innerWidth,top:0,bottom:innerHeight};
          const style=getComputedStyle(d),option=d.querySelector('[role=option]');
          const out={id,width:r.width,control:control.width,font:style.fontSize,direction:style.direction,
            clipped:r.left<b.left-2||r.right>b.right+2||r.top<b.top-2||r.bottom>b.bottom+2,
            rect:{top:r.top,bottom:r.bottom,left:r.left,right:r.right},bounds:b.toJSON?b.toJSON():b,widthMismatch:Math.abs(r.width-control.width)>2,misaligned:Math.abs(r.left-control.left)>2,optionFont:option?getComputedStyle(option).fontSize:null};
          q.select2('close');return out;
        },id);
        result.popups.push(popup);
      }
    }
    await checkSelects('.office-expenses-app main');
    const add=page.locator('[data-workspace-open], [data-receipt-open]').first();
    if(await add.count()) {
      await add.evaluate(e=>e.click());await page.locator('dialog[open]').waitFor();
      await checkSelects('dialog[open]');
      await page.locator('dialog[open] [data-workspace-close], dialog[open] [data-receipt-close]').first().evaluate(e=>e.click());
    }
    result.errors=result.popups.filter(p=>p.error||p.clipped||p.widthMismatch||p.misaligned||p.font!=='14px'||p.direction!==(test.lang==='ar'?'rtl':'ltr'));
    results.push(result);
  }
  return {cases:results.length,popups:results.reduce((n,r)=>n+r.popups.length,0),failures:results.filter(r=>r.overflow||r.wrongLinks||!r.current||r.errors.length),results};
}
