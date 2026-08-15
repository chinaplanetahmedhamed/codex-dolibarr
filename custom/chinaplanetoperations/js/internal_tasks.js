(function () {
  'use strict';
  function randomRequestId() {
    var bytes = new Uint8Array(24);
    window.crypto.getRandomValues(bytes);
    return [].map.call(bytes, function (value) { return value.toString(16).padStart(2, '0'); }).join('');
  }
  [].slice.call(document.querySelectorAll('form[action="action.php"]')).forEach(function (form) {
    var action = form.querySelector('input[name="action"]');
    if (action && action.value === 'create' && !form.querySelector('input[name="request_id"]')) {
      var request = document.createElement('input');
      request.type = 'hidden'; request.name = 'request_id'; request.value = randomRequestId(); form.appendChild(request);
    }
  });
  var retrySource = document.getElementById('cpo-task-retry');
  if (retrySource) {
    try {
      var retry = JSON.parse(retrySource.textContent);
      [].slice.call(document.querySelectorAll('form[action="action.php"]')).forEach(function (form) {
        var action = form.querySelector('[name="action"]'); var target = form.querySelector('[name="target"]');
        if (!action || action.value !== retry.action || (retry.target && target && target.value !== retry.target)) return;
        Object.keys(retry).forEach(function (name) { var field = form.elements[name]; if (!field || !Object.prototype.hasOwnProperty.call(retry, name)) return; if (field.type === 'checkbox' || field.type === 'radio') field.checked = String(field.value) === String(retry[name]); else field.value = retry[name] === null ? '' : String(retry[name]); });
      });
    } catch (ignore) {}
  }
  if(document.body.classList.contains('cpo-task-embed')){
    [].slice.call(document.querySelectorAll('form')).forEach(function(form){if(!form.querySelector('[name="embed"]')){var embedded=document.createElement('input');embedded.type='hidden';embedded.name='embed';embedded.value='1';form.appendChild(embedded);}});
    var embeddedBack=document.querySelector('.cpo-task-detail .cpo-hero a.button');if(embeddedBack)embeddedBack.addEventListener('click',function(event){event.preventDefault();window.parent.postMessage({type:'cpo-task-close'},window.location.origin);});
    if(document.querySelector('.cpo-embed-message.is-success'))setTimeout(function(){window.parent.postMessage({type:'cpo-task-mutated'},window.location.origin);},900);
  }
  var page = document.querySelector('.cpo-task-page');
  var board = document.querySelector('.cpo-task-kanban');
  if (page && board) {
    var stages = [].slice.call(board.querySelectorAll('[data-stage]'));
    var index = 0;
    var label = document.querySelector('[data-stage-label]');
    var previous = document.querySelector('[data-stage-prev]');
    var next = document.querySelector('[data-stage-next]');
    var navigator = document.querySelector('.cpo-stage-nav');
    var rtl = page.dataset.direction === 'rtl';
    var template = navigator ? navigator.dataset.stageTemplate : '__NAME__ — __CURRENT__ / __TOTAL__';
    if (previous) previous.textContent = rtl ? '›' : '‹';
    if (next) next.textContent = rtl ? '‹' : '›';
    function show(target) {
      index = Math.max(0, Math.min(stages.length - 1, target));
      stages.forEach(function (stage, stageIndex) { stage.classList.toggle('is-phone-active', stageIndex === index); });
      if (label) label.textContent = template.replace('__NAME__', stages[index].dataset.stageName).replace('__CURRENT__', String(index + 1)).replace('__TOTAL__', String(stages.length));
      if (previous) previous.disabled = index === 0;
      if (next) next.disabled = index === stages.length - 1;
    }
    if (previous) previous.addEventListener('click', function () { show(index - 1); });
    if (next) next.addEventListener('click', function () { show(index + 1); });
    var drawer = document.querySelector('[data-task-drawer]'); var drawerFrame = drawer ? drawer.querySelector('iframe') : null; var drawerClose = drawer ? drawer.querySelector('[data-task-drawer-close]') : null; var drawerTrigger = null;
    [].slice.call(board.querySelectorAll('.cpo-task-card h3 a')).forEach(function (link) {
      link.addEventListener('click', function (event) { sessionStorage.setItem('cpo-task-focus', link.getAttribute('href')); if (!drawer || !drawerFrame || typeof drawer.showModal !== 'function') return; event.preventDefault(); drawerTrigger=link;var embeddedUrl=new URL(link.href,window.location.href);embeddedUrl.searchParams.set('embed','1');drawerFrame.src=embeddedUrl.toString(); drawer.showModal(); if(drawerClose)drawerClose.focus(); });
    });
    if(drawerClose)drawerClose.addEventListener('click',function(){drawer.close();});
    if(drawer)drawer.addEventListener('close',function(){drawerFrame.src='about:blank';if(drawerTrigger)drawerTrigger.focus();});
    window.addEventListener('message',function(event){if(event.origin!==window.location.origin||!drawerFrame||event.source!==drawerFrame.contentWindow||!event.data)return;if(event.data.type==='cpo-task-close'&&drawer&&drawer.open)drawer.close();if(event.data.type==='cpo-task-mutated'){if(drawer&&drawer.open)drawer.close();window.location.reload();}});
    window.addEventListener('pageshow', function () {
      var href = sessionStorage.getItem('cpo-task-focus');
      var returnLink = href ? board.querySelector('a[href="' + href + '"]') : null;
      if (returnLink) { returnLink.focus(); sessionStorage.removeItem('cpo-task-focus'); }
    });
    show(0);
  }

  var uiMessageSource=document.getElementById('cpo-upload-messages');var uiMessages=uiMessageSource?uiMessageSource.dataset:{};var confirmIndex=0;
  [].slice.call(document.querySelectorAll('.cpo-task-lifecycle form,.cpo-task-transition form')).forEach(function(form){form.addEventListener('submit',function(event){if(form.dataset.confirmed==='1')return;event.preventDefault();var submit=form.querySelector('button[type="submit"],button:not([type])');var confirm=document.createElement('dialog');var headingId='cpo-action-confirm-'+(++confirmIndex);confirm.className='cpo-action-confirm';confirm.setAttribute('aria-labelledby',headingId);confirm.innerHTML='<h2></h2><p></p><div><button type="button" data-confirm></button><button type="button" data-cancel></button></div>';confirm.querySelector('h2').id=headingId;confirm.querySelector('h2').textContent=submit?submit.textContent.trim():'';confirm.querySelector('p').textContent=(submit?submit.textContent.trim():'')+'?';confirm.querySelector('[data-confirm]').textContent=submit?submit.textContent.trim():'';confirm.querySelector('[data-cancel]').textContent=uiMessages.cancel||'×';document.body.appendChild(confirm);confirm.querySelector('[data-cancel]').addEventListener('click',function(){confirm.close();});confirm.querySelector('[data-confirm]').addEventListener('click',function(){form.dataset.confirmed='1';confirm.close();form.requestSubmit();});confirm.addEventListener('close',function(){confirm.remove();if(submit)submit.focus();});confirm.showModal();confirm.querySelector('[data-cancel]').focus();});});

  [].slice.call(document.querySelectorAll('form[action="asset.php"][enctype="multipart/form-data"]')).forEach(function (form) {
    var file = form.querySelector('input[type="file"]');
    var submit = form.querySelector('button[type="submit"],button:not([type])');
    if (!file || !submit || !window.FormData || !window.XMLHttpRequest) return;
    var messages=uiMessages;var status = document.createElement('output');
    status.className = 'cpo-upload-status'; status.setAttribute('aria-live', 'polite'); form.appendChild(status);
    file.addEventListener('change',function(){status.value=file.files&&file.files.length===1?(messages.pending||submit.textContent.trim()):'';});
    form.addEventListener('submit', function (event) {
      if (!file.files || file.files.length !== 1) return;
      event.preventDefault(); submit.disabled = true; file.disabled = true; form.setAttribute('aria-busy','true'); status.value = messages.uploading||submit.textContent.trim();
      var request = new XMLHttpRequest();
      function ready(message){form.setAttribute('aria-busy','false');submit.disabled=false;file.disabled=false;status.value=message;}
      request.open('POST', form.action, true); request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      request.timeout=60000;
      request.upload.addEventListener('progress', function (progress) {
        if (progress.lengthComputable) status.value = submit.textContent.trim() + ' ' + Math.round(progress.loaded / progress.total * 100) + '%';
      });
      request.addEventListener('load', function () {
        var payload = null; try { payload = JSON.parse(request.responseText); } catch (ignore) {}
        if (request.status >= 200 && request.status < 300 && payload && payload.ok) { form.setAttribute('aria-busy','false');status.value=messages.success||payload.message;setTimeout(function(){if(document.body.classList.contains('cpo-task-embed'))window.parent.postMessage({type:'cpo-task-mutated'},window.location.origin);else window.location.reload();},900);return; }
        ready((payload&&payload.message?payload.message:(messages.failed||''))+' — '+(messages.retry||''));
      });
      ['error','abort','timeout'].forEach(function(kind){request.addEventListener(kind,function(){ready((messages.failed||'')+' — '+(messages.retry||''));});});
      request.send(new FormData(form));
    });
  });
}());
