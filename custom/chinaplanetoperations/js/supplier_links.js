/* Staff-only supplier relationships. The owning PHP service validates every context and write. */
(function () {
    'use strict';
    const node = (tag, cls, text) => { const n=document.createElement(tag);n.className=cls||'';if(text!==undefined)n.textContent=text;return n; };
    const button = (text, click) => { const n=node('button','csl-button',text);n.type='button';n.onclick=click;return n; };
    const link = (text, path) => { const n=node('a','csl-button',text);n.href=path;return n; };
    function client(cfg) {
        const t=key=>cfg.supplierLinksLabels?.[key]||key;
        async function api(action, values={}, write=false, signal) {
            const params=new URLSearchParams({token:cfg.token,action,...values});
            const response=await fetch('supplier_links_api.php'+(write?'':'?'+params),write?{method:'POST',body:params,signal}:{cache:'no-store',signal});
            let result;try{result=await response.json();}catch(e){throw new Error(t('Error'));}
            if(!response.ok||!result.ok){const error=new Error(result.error||t('Error'));error.status=response.status;throw error;}
            return result.data;
        }
        const notify=()=>{window.parent.postMessage({type:'cpo-task-changed'},location.origin);};
        return {t,api,notify};
    }
    function mount(cfg, scope, host) {
        const {t,api,notify}=client(cfg),panel=node('section','csl-panel'),heading=node('header','csl-heading'),rows=node('div','csl-links'),message=node('output','csl-status');
        message.setAttribute('role','status');heading.append(node('h2','',t('Title')),node('small','',t('Private')));panel.append(heading,rows,message);host.append(panel);
        let state, busy=false;
        async function mutate(action,supplier) {
            if(busy)return;busy=true;panel.setAttribute('aria-busy','true');panel.querySelectorAll('button').forEach(b=>b.disabled=true);
            try{state=await api(action,{...scope,project_id:state.project_id,version:state.version,supplier},true);render();notify();}
            catch(e){message.textContent=e.message;throw e;}
            finally{busy=false;panel.removeAttribute('aria-busy');panel.querySelectorAll('button').forEach(b=>b.disabled=false);}
        }
        function render() {
            rows.replaceChildren();message.textContent='';
            for(const supplier of state.suppliers) {
                const row=node('div','csl-row'),name=link(supplier.nom,'/societe/card.php?socid='+Number(supplier.rowid));name.dir='auto';row.append(name);
                if(supplier.board_id)row.append(link(t('Board'),'boards.php?id='+Number(supplier.board_id)));
                if(supplier.board_deleted&&state.can_edit)row.append(link(t('Restore'),'boards.php?view=deleted'));
                if(state.can_edit)row.append(button(t('Remove'),async()=>{if(confirm(t('UnlinkConfirm')))try{await mutate('unlink',supplier.rowid);}catch(e){/* persistent message */}}));
                rows.append(row);
            }
            if(!state.suppliers.length)rows.append(node('p','csl-muted',t('Empty')));
            if(state.can_edit)rows.append(button(t('Link'),picker));
        }
        function picker() {
            const focus=document.activeElement,dialog=node('dialog','csl-dialog'),head=node('header','csl-heading'),body=node('div','csl-picker'),input=node('input','csl-search'),list=node('div','csl-options'),status=node('output','csl-status'),more=button(t('More'),()=>load(page+1));
            const close=button(t('Close'),()=>dialog.close());head.append(node('h2','',t('Link')),close);dialog.setAttribute('aria-label',t('Link'));dialog.dir=document.documentElement.dir||'auto';
            const label=node('label','',t('Search'));label.append(input);input.type='search';input.maxLength=100;input.autocomplete='off';
            status.setAttribute('role','status');body.append(node('p','csl-muted',t('LinkHelp')),label,list,status,more);dialog.append(head,body);document.body.append(dialog);
            let page=0,sequence=0,timer,controller,pending=false;
            async function load(next) {
                if(pending&&next>1)return;
                controller?.abort();controller=new AbortController();const current=++sequence;pending=true;more.disabled=true;status.textContent=t('Loading');
                if(next===1)list.replaceChildren();
                try {
                    const data=await api('options',{...scope,project_id:state.project_id,q:input.value,page:next},false,controller.signal);
                    if(current!==sequence||!dialog.open)return;
                    for(const supplier of data.suppliers) {
                        const selected=state.suppliers.some(s=>Number(s.rowid)===Number(supplier.rowid));
                        const option=button(supplier.nom+(selected?' · '+t('Linked'):''),async()=>{option.disabled=true;try{await mutate('link',supplier.rowid);dialog.close();}catch(e){status.textContent=e.message;option.disabled=false;}});
                        option.classList.add('csl-option');option.dir='auto';option.disabled=selected;list.append(option);
                    }
                    page=next;more.hidden=!data.has_more;more.textContent=t('More');more.onclick=()=>load(page+1);status.textContent=list.children.length?'':t('None');
                }catch(e){if(e.name!=='AbortError'&&current===sequence){status.textContent=e.message;more.hidden=false;more.textContent=t('Retry');more.onclick=()=>load(next);}}
                finally{if(current===sequence){pending=false;more.disabled=false;}}
            }
            input.oninput=()=>{clearTimeout(timer);sequence++;controller?.abort();more.hidden=true;list.replaceChildren();status.textContent=t('Loading');timer=setTimeout(()=>load(1),250);};
            dialog.addEventListener('close',()=>{sequence++;clearTimeout(timer);controller?.abort();dialog.remove();focus?.focus();},{once:true});dialog.showModal();input.focus();load(1);
        }
        api('links',scope).then(data=>{if(!panel.isConnected)return;state=data;render();}).catch(e=>{if(scope.task_id&&e.status===403)panel.remove();else message.textContent=e.message;});
        return panel;
    }
    function open(cfg, scope) {
        const {t}=client(cfg),dialog=node('dialog','csl-dialog'),head=node('header','csl-heading'),body=node('div');
        const focus=document.activeElement;head.append(node('h2','',t('Title')),button(t('Close'),()=>dialog.close()));dialog.append(head,body);dialog.setAttribute('aria-label',t('Title'));document.body.append(dialog);
        dialog.addEventListener('close',()=>{dialog.remove();focus?.focus();},{once:true});const panel=mount(cfg,scope,body);panel.querySelector('.csl-heading').remove();body.prepend(node('p','csl-muted',t('Private')));dialog.showModal();head.querySelector('button').focus();
    }
    async function board(cfg,id,host,openCard) {
        const {t,api}=client(cfg),panel=node('section','csl-panel'),status=node('output','csl-status');status.setAttribute('role','status');panel.append(status);host.append(panel);
        try {
            const data=await api('board',{board:id});if(!panel.isConnected)return;
            const cards=node('div','csl-links');cards.append(node('h2','',t('Cards')));
            for(const card of data.cards)cards.append(button(card.title,()=>openCard(card.rowid)));
            if(!data.cards.length)cards.append(node('p','csl-muted',t('NoCards')));
            const orders=node('div','csl-links');orders.append(node('h2','',t('Orders')));
            for(const order of data.orders)orders.append(link(order.ref+' · '+t(Number(order.fk_statut)===0?'NewDraft':'ExistingOrder'),'/fourn/commande/card.php?id='+Number(order.rowid)));
            if(!data.orders.length)orders.append(node('p','csl-muted',t('NoOrders')));
            if(data.can_create_order) {
                const request=Array.from(crypto.getRandomValues(new Uint8Array(24)),b=>b.toString(16).padStart(2,'0')).join('');
                orders.append(button(t('Draft'),async e=>{if(!confirm(t('DraftConfirm')))return;const b=e.currentTarget;b.disabled=true;try{const result=await api('draft',{board:id,request},true);location.href='/fourn/commande/card.php?id='+Number(result.id);}catch(error){status.textContent=error.message;b.disabled=false;}}));
            }
            panel.prepend(cards,orders);
        }catch(e){status.textContent=e.message;}
    }
    window.CPOSupplierLinks={open,board};
    const cardConfig=document.getElementById('cbo-card-config');
    if(cardConfig){const cfg=JSON.parse(cardConfig.textContent);if(cfg.supplierLinksEnabled){const host=document.querySelector('.cbo-card-content');if(host){const slot=node('div','csl-card-slot');host.querySelector('.cbo-card-toolbar')?.after(slot);if(!slot.isConnected)host.prepend(slot);mount(cfg,{task_id:cfg.id},slot);}}}
})();
