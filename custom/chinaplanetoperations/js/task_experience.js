(function () {
  'use strict';
  function init() {
    var messages = document.getElementById('cpt-messages');
    var ui = messages ? messages.dataset : {};
    function el(tag, className, text) { var node = document.createElement(tag); if(className)node.className=className; if(text!==undefined)node.textContent=text; return node; }
    function button(text, label) { var b=el('button','cpt-text-button',text);b.type='button';if(label)b.setAttribute('aria-label',label);return b; }
    async function post(action, payload, token, options) {
      var form=new FormData();form.set('action',action);form.set('payload',JSON.stringify(payload));form.set('token',token);
      Object.keys(options||{}).forEach(function(key){form.set(key,String(options[key]));});
      var response=await fetch('personal_action.php',{method:'POST',body:form,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      var result;try{result=await response.json();}catch(e){throw new Error(ui.failed);}
      if(!response.ok||!result.ok)throw new Error(result.message||ui.failed);return result;
    }
    document.querySelectorAll('[data-details-editor]').forEach(function(editor){
      var initial=JSON.parse(editor.querySelector('[data-details-value]').textContent), words=editor.querySelector('[data-extra-ui]').dataset;
      var readonly=editor.dataset.readonly==='1', labels=editor.querySelector('[data-extra-labels]'), fields=editor.querySelector('[data-extra-fields]');
      function labelRow(data){var row=el('div','cpt-label-row');var name=el('input'),color=el('input');name.value=data.name||'';name.placeholder=words.name;name.setAttribute('aria-label',words.name);name.maxLength=70;name.required=true;name.dataset.labelName='';color.type='color';color.value=data.color||'#16a34a';color.dataset.labelColor='';color.setAttribute('aria-label',words.value);[name,color].forEach(function(n){n.disabled=readonly;row.appendChild(n);});if(!readonly){var remove=button('×',words.remove);remove.onclick=function(){row.remove();};row.appendChild(remove);}labels.appendChild(row);}
      function fieldRow(data){var row=el('div','cpt-custom-row'),name=el('input'),type=el('select'),value=el('input');name.value=data.name||'';name.placeholder=words.name;name.setAttribute('aria-label',words.name);name.maxLength=70;name.required=true;name.dataset.fieldName='';type.dataset.fieldType='';type.setAttribute('aria-label',words.value);['text','number','date','checkbox'].forEach(function(t){var o=el('option','',words[t]);o.value=t;type.appendChild(o);});type.value=data.type||'text';value.dataset.fieldValue='';value.setAttribute('aria-label',words.value);value.maxLength=1000;
        function setType(){value.type=type.value==='checkbox'?'checkbox':type.value==='date'?'date':type.value==='number'?'number':'text';if(type.value==='number')value.step='any';}
        setType();if(type.value==='checkbox')value.checked=String(data.value)==='1';else value.value=data.value||'';type.onchange=setType;[name,type,value].forEach(function(n){n.disabled=readonly;row.appendChild(n);});if(!readonly){var remove=button('×',words.remove);remove.onclick=function(){row.remove();};row.appendChild(remove);}fields.appendChild(row);
      }
      (initial.labels||[]).forEach(labelRow);(initial.fields||[]).forEach(fieldRow);
      var addLabel=editor.querySelector('[data-add-label]'),addField=editor.querySelector('[data-add-field]');if(addLabel)addLabel.onclick=function(){if(labels.children.length<20)labelRow({});};if(addField)addField.onclick=function(){if(fields.children.length<30)fieldRow({});};
      editor.readDetails=function(){return {start_date:editor.querySelector('[data-extra="start_date"]').value,start_time:editor.querySelector('[data-extra="start_time"]').value,cover_color:editor.querySelector('[data-color-enabled]').checked?editor.querySelector('[data-extra="cover_color"]').value:'',notes:editor.querySelector('[data-extra="notes"]').value,labels:Array.from(labels.children).map(function(row){return{name:row.querySelector('[data-label-name]').value,color:row.querySelector('[data-label-color]').value};}),fields:Array.from(fields.children).map(function(row){var type=row.querySelector('[data-field-type]').value,value=row.querySelector('[data-field-value]');return{name:row.querySelector('[data-field-name]').value,type:type,value:type==='checkbox'?(value.checked?'1':'0'):value.value};})};};
    });
    document.querySelectorAll('[data-task-details-form]').forEach(function(form){form.addEventListener('submit',async function(event){event.preventDefault();var editor=form.querySelector('[data-details-editor]'),status=form.querySelector('output'),submit=form.querySelector('[type="submit"]');submit.disabled=true;status.textContent=ui.saving;try{var deadline=editor.querySelector('[data-task-deadline]'),options={task_id:form.dataset.taskId,version:editor.dataset.version};if(deadline){options.task_version=deadline.dataset.taskVersion;options.date_deadline=deadline.value;}var result=await post(deadline?'schedule':'details',editor.readDetails(),form.elements.token.value,options);editor.dataset.version=result.details.version;status.textContent=result.message;if(document.body.classList.contains('cpo-task-embed'))window.parent.postMessage({type:'cpo-task-dirty'},window.location.origin);window.location.reload();}catch(e){status.textContent=e.message;}finally{submit.disabled=false;}});});
    var cancelOpen=document.querySelector('[data-task-cancel-open]'),cancelDialog=document.querySelector('[data-task-cancel-dialog]');
    if(cancelOpen&&cancelDialog){cancelOpen.addEventListener('click',function(){cancelDialog.showModal();cancelDialog.querySelector('[data-task-cancel-close]').focus();});cancelDialog.querySelector('[data-task-cancel-close]').addEventListener('click',function(){cancelDialog.close();});cancelDialog.addEventListener('close',function(){cancelOpen.focus();});}
    document.querySelectorAll('[data-task-dialog-open]').forEach(function(open){
      var dialog=document.querySelector('[data-task-dialog="'+open.dataset.taskDialogOpen+'"]');if(!dialog)return;
      open.addEventListener('click',function(){dialog.showModal();var focus=dialog.querySelector(open.dataset.taskDialogOpen==='rename'?'input[name="title"]':'[data-task-dialog-close]');focus.focus();if(focus.select)focus.select();});
      dialog.querySelector('[data-task-dialog-close]').addEventListener('click',function(){dialog.close();});
      dialog.addEventListener('close',function(){open.focus();});
    });
    var editOpen=document.querySelector('[data-task-edit]'),editPanel=document.getElementById('task-edit');
    if(editOpen&&editPanel)editOpen.addEventListener('click',function(event){event.preventDefault();editPanel.open=true;editPanel.scrollIntoView({block:'start'});editPanel.querySelector('input[name="title"]').focus();});
    var boardPage=document.querySelector('.cpt-board-page'),board=document.querySelector('.cpo-task-kanban');
    if(boardPage&&board){
      function fit(){board.style.setProperty('--board-top',Math.max(80,board.getBoundingClientRect().top)+'px');}fit();window.addEventListener('resize',fit);
      if(boardPage.dataset.personal==='1'){
        var token=boardPage.dataset.boardToken,version=Number(boardPage.dataset.boardVersion),busy=false;
        var columnsDialog=document.querySelector('[data-columns-dialog]'),rows=columnsDialog.querySelector('[data-column-rows]');
        function updateButtons(){Array.from(rows.children).forEach(function(row,index){row.querySelector('[data-column-up]').disabled=index===0;row.querySelector('[data-column-down]').disabled=index===rows.children.length-1;});}
        function wireRow(row){row.querySelector('[data-column-up]').onclick=function(){if(row.previousElementSibling)rows.insertBefore(row,row.previousElementSibling);updateButtons();};row.querySelector('[data-column-down]').onclick=function(){if(row.nextElementSibling)rows.insertBefore(row.nextElementSibling,row);updateButtons();};}
        Array.from(rows.children).forEach(wireRow);updateButtons();document.querySelector('[data-personal-columns]').onclick=function(){columnsDialog.showModal();};
        columnsDialog.querySelector('[data-column-add]').onclick=function(){if(rows.children.length>=30)return;var row=rows.firstElementChild.cloneNode(true);row.dataset.columnId='0';row.querySelector('[data-column-name]').value='';row.querySelector('[data-column-color]').value='#64748b';rows.appendChild(row);wireRow(row);updateButtons();row.querySelector('input').focus();};
        columnsDialog.querySelector('form').onsubmit=async function(event){event.preventDefault();var output=columnsDialog.querySelector('output'),submit=columnsDialog.querySelector('[type="submit"]');submit.disabled=true;output.textContent=ui.saving;try{await post('columns',Array.from(rows.children).map(function(row){return{id:Number(row.dataset.columnId),name:row.querySelector('[data-column-name]').value,color:row.querySelector('[data-column-color]').value};}),token,{version:version});window.location.reload();}catch(e){output.textContent=e.message;submit.disabled=false;}};
        var moveDialog=document.querySelector('[data-personal-move-dialog]'),moveTask=0;
        var live=document.querySelector('[data-task-live]');
        async function move(taskId,columnId,beforeId){if(busy)return;busy=true;try{var result=await post('move',{column:Number(columnId),before:Number(beforeId||0)},token,{version:version,task_id:taskId});version=result.board.version;var card=board.querySelector('[data-task-id="'+taskId+'"]'),list=board.querySelector('[data-stage-id="'+columnId+'"] .cpo-task-list'),before=beforeId?list.querySelector('[data-task-id="'+beforeId+'"]'):null;list.insertBefore(card,before);board.querySelectorAll('[data-stage-id]').forEach(function(column){column.querySelector('header>span').textContent=column.querySelectorAll('.cpo-task-card').length;});if(live)live.textContent=result.message;return true;}catch(e){if(live)live.textContent=e.message;moveDialog.querySelector('output').textContent=e.message;return false;}finally{busy=false;}}
        board.querySelectorAll('[data-personal-move]').forEach(function(b){b.onclick=function(){moveTask=Number(b.dataset.personalMove);moveDialog.querySelector('select').value=b.closest('[data-stage-id]').dataset.stageId;moveDialog.showModal();};});
        moveDialog.querySelector('form').onsubmit=async function(e){e.preventDefault();if(await move(moveTask,moveDialog.querySelector('select').value))moveDialog.close();};
        function clearTarget(){board.querySelectorAll('.cpt-drop-target').forEach(function(node){node.classList.remove('cpt-drop-target');});}
        board.querySelectorAll('.cpo-task-card').forEach(function(card){
          var handle=card.querySelector('.cpo-drag-handle');
          if(!handle)return;
          handle.onclick=function(){card.querySelector('[data-personal-move]').click();};
          window.cpKanbanPointer(card,{
            handle:'.cpo-drag-handle',
            disabled:function(){return busy;},
            move:function(event,target){
              clearTarget();
              var column=target&&target.closest('[data-stage-id]');
              if(column&&board.contains(column)){
                column.classList.add('cpt-drop-target');
                var list=column.querySelector('.cpo-task-list'),bounds=list.getBoundingClientRect();
                if(event.clientY<bounds.top+40)list.scrollTop-=12;
                else if(event.clientY>bounds.bottom-40)list.scrollTop+=12;
              }
              var bounds=board.getBoundingClientRect();
              if(event.clientX<bounds.left+40)board.scrollLeft-=14;
              else if(event.clientX>bounds.right-40)board.scrollLeft+=14;
            },
            drop:function(event,target){
              var column=target&&target.closest('[data-stage-id]');
              if(!column||!board.contains(column))return;
              var at=target.closest('.cpo-task-card'),before=0;
              if(at===card)return;
              if(at){
                if(event.clientY<at.getBoundingClientRect().top+at.getBoundingClientRect().height/2)before=Number(at.dataset.taskId);
                else {var next=at.nextElementSibling;while(next===card)next=next.nextElementSibling;before=next?Number(next.dataset.taskId):0;}
              }
              move(Number(card.dataset.taskId),Number(column.dataset.stageId),before);
            },
            end:clearTarget
          });
        });
      }
    }
    document.querySelectorAll('[data-close-dialog]').forEach(function(b){b.onclick=function(){b.closest('dialog').close();};});
    var composer=document.querySelector('[data-task-composer]');
    if(composer){
      var memberData=composer.querySelector('[data-composer-members]');if(memberData){var members=JSON.parse(memberData.textContent),owner=composer.elements.fk_user_assign;function membersForTeam(){var zero=owner.options[0].cloneNode(true);owner.replaceChildren(zero);(members[composer.elements.fk_usergroup.value]||[]).forEach(function(person){var option=el('option','',[person.firstname,person.lastname].filter(Boolean).join(' ')||person.login);option.value=person.rowid;owner.appendChild(option);});}membersForTeam();composer.elements.fk_usergroup.onchange=membersForTeam;}
      var fileInput=composer.elements.files,fileList=composer.querySelector('[data-create-files]'),files=[],taskId=0,taskVersion=0,uploadIndex=0,coverSet=false,pendingCover=null;
      function showFiles(){fileList.replaceChildren();files.forEach(function(file){fileList.appendChild(el('li','',file.name+' · '+Math.ceil(file.size/1024)+' KB'));});}
      fileInput.onchange=function(){files=Array.from(fileInput.files);showFiles();};
      var drop=composer.querySelector('.cpt-file-drop');['dragenter','dragover'].forEach(function(t){drop.addEventListener(t,function(e){e.preventDefault();drop.classList.add('is-dragging');});});drop.addEventListener('dragleave',function(){drop.classList.remove('is-dragging');});composer.addEventListener('paste',function(e){if(drop.closest('[hidden]'))return;var pasted=Array.from((e.clipboardData&&e.clipboardData.files)||[]);if(pasted.length){e.preventDefault();files=files.concat(pasted);showFiles();}});drop.addEventListener('drop',function(e){e.preventDefault();drop.classList.remove('is-dragging');files=files.concat(Array.from(e.dataTransfer.files));showFiles();});
      async function asset(action,values,file){var data=new FormData();data.set('token',composer.elements.token.value);data.set('task_id',taskId);data.set('version',taskVersion);data.set('action',action);Object.keys(values||{}).forEach(function(key){data.set(key,values[key]);});if(file)data.set('task_file',file,file.name);var response=await fetch('asset.php',{method:'POST',body:data,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});var payload;try{payload=await response.json();}catch(invalid){throw new Error(response.status===413?ui.tooLarge:ui.uploadResponse);}if(!response.ok||!payload.ok)throw new Error(response.status===413?ui.tooLarge:(payload.message||ui.failed));taskVersion=payload.version;return payload;}
      composer.onsubmit=async function(event){event.preventDefault();var submit=composer.querySelector('[type="submit"]'),status=composer.querySelector('[data-compose-status]'),link=composer.querySelector('[data-created-link]');submit.disabled=true;status.textContent=ui.saving;
        try{
          if(files.length>25||files.reduce(function(total,f){return total+f.size;},0)>500*1024*1024||files.some(function(f){return !/\.(jpg|jpeg|png|webp|pdf|docx|xlsx|csv|txt)$/i.test(f.name)||f.size>200*1024*1024;}))throw new Error(ui.uploadFailed);
          if(!taskId){var task={};['title','description','priority','date_deadline','fk_usergroup','fk_user_assign'].forEach(function(key){task[key]=composer.elements[key].value;});var result=await post('compose',{column:composer.elements.personal_column?Number(composer.elements.personal_column.value):0,task:task,details:composer.querySelector('[data-details-editor]').readDetails(),items:composer.elements.checklist.value.split(/\r?\n/),comment:composer.elements.comment.value},composer.elements.token.value,{request_id:composer.dataset.request});taskId=result.taskId;taskVersion=result.version;link.href='card.php?id='+taskId;link.hidden=false;composer.querySelectorAll('input,textarea,select,button').forEach(function(control){control.disabled=true;});}
          while(uploadIndex<files.length||pendingCover!==null){if(pendingCover!==null){if(composer.elements.first_image_cover.checked)await asset('cover',{asset_id:pendingCover});else await asset('cover_clear',{});pendingCover=null;coverSet=true;continue;}status.textContent=ui.uploading+' '+(uploadIndex+1)+' / '+files.length;var uploaded=await asset('upload',{},files[uploadIndex]);if(!coverSet&&/\.(jpg|jpeg|png|webp)$/i.test(files[uploadIndex].name))pendingCover=uploaded.assetId;fileList.children[uploadIndex].textContent='✓ '+files[uploadIndex].name;uploadIndex++;}
          window.location.href='card.php?id='+taskId;
        }catch(e){status.textContent=(taskId?ui.uploadFailed+' ':'')+(e.message||ui.failed);submit.disabled=!!taskId;}
      };
    }
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
