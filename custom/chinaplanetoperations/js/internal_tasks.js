(function () {
  'use strict';
  function cpoInternalTaskInit() {
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
  var fittedPage = page || document.querySelector('.cpo-stage-admin');
  function fitLocalPage() { if (!fittedPage) return; var rect=fittedPage.getBoundingClientRect();var left=Math.max(0,rect.left);var shell=fittedPage.closest('#id-right');var shellLeft=shell?Math.max(0,shell.getBoundingClientRect().left):left;var gutter=Math.max(0,left-shellLeft);var style=getComputedStyle(fittedPage);var padding=(parseFloat(style.paddingLeft)||0)+(parseFloat(style.paddingRight)||0);var available=Math.max(280,window.innerWidth-left-gutter-padding-8);fittedPage.style.width=available+'px';fittedPage.style.maxWidth=available+'px'; }
  fitLocalPage(); window.addEventListener('resize',fitLocalPage);
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

  var taskUiSource = document.getElementById('cpo-task-ui-messages');
  var taskUi = taskUiSource ? taskUiSource.dataset : {};
  [].slice.call(document.querySelectorAll('[style*="--cpo-stage-color"]')).forEach(function(node){var color=getComputedStyle(node).getPropertyValue('--cpo-stage-color').trim();if(/^#[0-9a-f]{6}$/i.test(color)){var r=parseInt(color.slice(1,3),16),g=parseInt(color.slice(3,5),16),b=parseInt(color.slice(5,7),16);var luminance=(0.2126*r+0.7152*g+0.0722*b)/255;node.style.setProperty('--cpo-stage-foreground',luminance>0.56?'#102A43':'#FFFFFF');}});
  var live = document.querySelector('[data-task-live]');
  function announce(message) { if (live) live.textContent = message || ''; }
  function formActionUrl(form) {
    var raw = form.getAttribute('action') || '';
    var endpoint = new URL(raw || window.location.href, window.location.href);
    if (endpoint.origin !== window.location.origin) throw new Error(taskUi.failed || '');
    return endpoint.toString();
  }
  function postForm(form, onSuccess) {
    if (!window.fetch || !window.FormData) return false;
    var submit = form.querySelector('button[type="submit"]'); var status = form.querySelector('.cpo-dialog-status');
    form.setAttribute('aria-busy', 'true'); if (submit) submit.disabled = true; if (status) status.value = taskUi.moving || '';
    var endpoint; try { endpoint = formActionUrl(form); } catch (endpointError) { form.setAttribute('aria-busy', 'false'); if (submit) submit.disabled = false; if (status) status.value = endpointError.message || taskUi.failed || ''; announce(endpointError.message || taskUi.failed); return true; }
    fetch(endpoint, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (response) { return response.text().then(function (body) { var payload; try { payload = JSON.parse(body); } catch (invalidJson) { throw new Error(taskUi.failed || ''); } return { response: response, payload: payload }; }); })
      .then(function (result) {
        if (!result.response.ok || !result.payload.ok) throw new Error(result.payload.message || taskUi.failed || '');
        if (status) status.value = result.payload.message || taskUi.moved || ''; announce(taskUi.moved || result.payload.message); onSuccess(result.payload);
      })
      .catch(function (error) { form.setAttribute('aria-busy', 'false'); if (submit) submit.disabled = false; if (status) status.value = error.message || taskUi.failed || ''; announce(error.message || taskUi.failed); });
    return true;
  }

  var newDialog = document.querySelector('[data-new-task-dialog]');
  if (newDialog) {
    var newOpen = document.querySelector('[data-new-task-open]'); var newClose = newDialog.querySelector('[data-new-task-close]'); var newCancel = newDialog.querySelector('[data-new-task-cancel]');
    var newForm = newDialog.querySelector('form'); var teamSelect = newDialog.querySelector('[data-new-task-team]'); var assigneeSelect = newDialog.querySelector('[data-new-task-assignee]'); var memberState = newDialog.querySelector('[data-new-task-members-state]');
    var members = {}; var memberSource = document.getElementById('cpo-team-members'); try { members = memberSource ? JSON.parse(memberSource.textContent) : {}; } catch (ignoreMembers) {}
    function fillMembers() { var rows = members[String(teamSelect.value)] || []; assigneeSelect.innerHTML = '<option value="0">' + (assigneeSelect.dataset.unassigned || '—') + '</option>'; rows.forEach(function (person) { var option=document.createElement('option'); option.value=person.rowid; option.textContent=((person.firstname||'')+' '+(person.lastname||'')).trim()||person.login; assigneeSelect.appendChild(option); }); assigneeSelect.disabled=!teamSelect.value; if(memberState)memberState.textContent=!teamSelect.value?(taskUi.selectTeam||''):(rows.length?'':(taskUi.noMembers||'')); }
    if (assigneeSelect.options.length) assigneeSelect.dataset.unassigned = assigneeSelect.options[0].textContent;
    teamSelect.addEventListener('change', fillMembers);
    function openNew() { newDialog.showModal(); fillMembers(); setTimeout(function(){teamSelect.focus();},0); }
    function closeNew() { newDialog.close(); }
    if(newOpen)newOpen.addEventListener('click',openNew); if(newClose)newClose.addEventListener('click',closeNew); if(newCancel)newCancel.addEventListener('click',closeNew);
    newDialog.addEventListener('close',function(){if(newOpen)newOpen.focus();});
    var createRetrySource=document.getElementById('cpo-create-retry');if(createRetrySource){try{var createRetry=JSON.parse(createRetrySource.textContent);Object.keys(createRetry).forEach(function(name){var field=newForm.elements[name];if(!field||!Object.prototype.hasOwnProperty.call(createRetry,name))return;field.value=createRetry[name]===null?'':String(createRetry[name]);});fillMembers();if(createRetry.fk_user_assign!==undefined)assigneeSelect.value=String(createRetry.fk_user_assign||0);openNew();}catch(ignoreCreateRetry){}}
    newForm.addEventListener('submit',function(event){if(!newForm.checkValidity())return;event.preventDefault();postForm(newForm,function(){newDialog.close();window.location.reload();});});
  }

  var moveDialog=document.querySelector('[data-move-dialog]');
  if(moveDialog){
    var moveForm=moveDialog.querySelector('form');var moveTarget=moveDialog.querySelector('[data-move-target]');var waitingFields=moveDialog.querySelector('[data-move-waiting]');var returnField=moveDialog.querySelector('[data-move-return]');var moveTrigger=null;var sourceStatus='';
    function configureMove(){var option=moveTarget.options[moveTarget.selectedIndex];var behavior=option?option.dataset.behavior:'';var needsWaiting=behavior==='waiting';var needsReturn=sourceStatus==='review'&&behavior==='work';waitingFields.hidden=!needsWaiting;returnField.hidden=!needsReturn;[].slice.call(waitingFields.querySelectorAll('input,select,textarea')).forEach(function(field){field.required=needsWaiting;});returnField.querySelector('textarea').required=needsReturn;}
    function openMove(taskId,version,status,targets,preset,trigger){moveTrigger=trigger||null;sourceStatus=status;moveForm.elements.task_id.value=taskId;moveForm.elements.version.value=version;moveTarget.innerHTML='';targets.forEach(function(target){var option=document.createElement('option');option.value=target.id;option.textContent=target.label;option.dataset.behavior=target.behavior;if(String(target.id)===String(preset))option.selected=true;moveTarget.appendChild(option);});configureMove();moveDialog.showModal();moveTarget.focus();}
    [].slice.call(document.querySelectorAll('[data-move-task]')).forEach(function(button){button.addEventListener('click',function(){var targets=[];try{targets=JSON.parse(button.dataset.moveTargets||'[]');}catch(ignoreTargets){}if(targets.length)openMove(button.dataset.moveTask,button.dataset.moveVersion,button.dataset.moveStatus,targets,'',button);});});
    moveTarget.addEventListener('change',configureMove);['[data-move-close]','[data-move-cancel]'].forEach(function(selector){var button=moveDialog.querySelector(selector);if(button)button.addEventListener('click',function(){moveDialog.close();});});
    moveDialog.addEventListener('close',function(){if(moveTrigger)moveTrigger.focus();});
    moveForm.addEventListener('submit',function(event){if(!moveForm.checkValidity())return;event.preventDefault();var id=moveForm.elements.task_id.value;sessionStorage.setItem('cpo-task-focus-id',id);postForm(moveForm,function(){moveDialog.close();window.location.reload();});});
    if(board&&page&&page.dataset.adminDrag==='1'){
      var dragged=null;var dragAllowed=[];[].slice.call(board.querySelectorAll('.cpo-task-card[draggable]')).forEach(function(card){var handle=card.querySelector('.cpo-drag-handle');if(handle){handle.addEventListener('pointerdown',function(){card.dataset.dragReady='1';});handle.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();var moveButton=card.querySelector('[data-move-task]');if(moveButton)moveButton.click();}});}card.addEventListener('dragstart',function(event){if(card.dataset.dragReady!=='1'){event.preventDefault();return;}dragged=card;var moveButton=card.querySelector('[data-move-task]');try{dragAllowed=JSON.parse(moveButton?moveButton.dataset.moveTargets:'[]');}catch(ignoreDragTargets){dragAllowed=[];}var ids=dragAllowed.map(function(target){return String(target.id);});[].slice.call(board.querySelectorAll('[data-stage-id]')).forEach(function(column){column.classList.toggle('is-drop-target',ids.indexOf(column.dataset.stageId)!==-1);});event.dataTransfer.effectAllowed='move';});card.addEventListener('dragend',function(){card.dataset.dragReady='';dragged=null;[].slice.call(board.querySelectorAll('.is-drop-target')).forEach(function(column){column.classList.remove('is-drop-target');});});});
      [].slice.call(board.querySelectorAll('[data-stage-id]')).forEach(function(column){column.addEventListener('dragover',function(event){if(column.classList.contains('is-drop-target')){event.preventDefault();event.dataTransfer.dropEffect='move';}});column.addEventListener('drop',function(event){event.preventDefault();if(!dragged||!column.classList.contains('is-drop-target'))return;var button=dragged.querySelector('[data-move-task]');openMove(button.dataset.moveTask,button.dataset.moveVersion,button.dataset.moveStatus,dragAllowed,column.dataset.stageId,dragged.querySelector('.cpo-drag-handle'));});});
    }
  }
  window.addEventListener('pageshow',function(){var focusId=sessionStorage.getItem('cpo-task-focus-id');if(focusId){var card=document.getElementById('cpo-task-'+focusId);if(card){var focusable=card.querySelector('a,button');if(focusable)focusable.focus();}sessionStorage.removeItem('cpo-task-focus-id');}});

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
      var endpoint;try{endpoint=formActionUrl(form);}catch(endpointError){ready(endpointError.message||messages.failed||'');return;}
      request.open('POST', endpoint, true); request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');request.setRequestHeader('Accept','application/json');
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
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cpoInternalTaskInit, { once: true });
  else cpoInternalTaskInit();
}());
