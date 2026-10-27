/* Personal browser sound preferences; no requests or extra data access. */
(function(scope,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else scope.CPBoardsSounds=api;})(typeof window==='object'?window:globalThis,function(){
'use strict';
const tones=['chime','soft','ping','bell'];
const validTone=t=>tones.includes(t),boardId=id=>Number.isSafeInteger(Number(id))&&Number(id)>0?String(Number(id)):null;
function normalize(raw){
 const p={muted:true,tone:'chime',boards:{}};
 if(!raw||typeof raw!=='object')return p;
 if(typeof raw.muted==='boolean')p.muted=raw.muted;if(validTone(raw.tone))p.tone=raw.tone;
 if(raw.boards&&typeof raw.boards==='object')for(const k of Object.keys(raw.boards).slice(-200))if(boardId(k)&&String(Number(k))===k&&(validTone(raw.boards[k])||raw.boards[k]==='silent'))p.boards[k]=raw.boards[k];
 return p;
}
function changed(before,after){
 if(!before?.board||!after?.board||Number(before.board.rowid)!==Number(after.board.rowid))return false;
 if(Boolean(before.board.completed)!==Boolean(after.board.completed))return true;
 const lists=new Set((before.lists||[]).map(l=>Number(l.rowid)));
 if((after.lists||[]).some(l=>!Number(l.archived)&&!lists.has(Number(l.rowid))))return true;
 const cards=new Map((before.cards||[]).map(c=>[Number(c.fk_task),c]));
 return (after.cards||[]).some(c=>{if(Number(c.archived))return false;const old=cards.get(Number(c.fk_task));return !old||Number(old.fk_list)!==Number(c.fk_list)||Number(old.position)!==Number(c.position);});
}
function createController(o){
 const now=o.now||Date.now;let p=normalize(),ready=false,persisted=true,baseline=null,lastRead=0,lastCue=-Infinity,last=Promise.resolve(),unlocking=null;
 const seen=new Set();
 try{p=normalize(JSON.parse(o.storage.getItem(o.key)||'null'));}catch{persisted=false;}
 function settings(){return {...p,boards:{...p.boards},ready,persisted};}
 function update(){if(o.onChange)o.onChange(settings());}
 function persist(){try{o.storage.setItem(o.key,JSON.stringify(p));persisted=true;}catch{persisted=false;}update();}
 function toneFor(id){return p.boards[boardId(id)]||p.tone;}
 async function unlock(){
  if(unlocking)return unlocking;
  let timer;unlocking=(async()=>{try{const attempt=o.unlock(),deadline=new Promise((_,reject)=>{timer=setTimeout(()=>reject(Error('Audio unlock timed out')),500);});if(await Promise.race([attempt,deadline])===false)throw Error('Audio unavailable');ready=true;return true;}catch{ready=false;return false;}finally{clearTimeout(timer);unlocking=null;update();}})();return unlocking;
 }
 function cue(id,force=false,tone){
  const selected=tone||toneFor(id);if(!ready||(!force&&p.muted)||selected==='silent'||!validTone(selected))return false;
  if(!force&&now()-lastCue<700)return false;lastCue=now();
  try{last=Promise.resolve(o.play(selected)).catch(()=>{ready=false;update();});}catch{ready=false;update();return false;}return true;
 }
 function seed(data){baseline=data;lastRead=now();}
 return {
  settings,toneFor,configure(next){if(validTone(next?.tone))p.tone=next.tone;persist();},
  setBoardTone(id,tone){const k=boardId(id);if(!k)return;if(tone===null)delete p.boards[k];else if(validTone(tone)||tone==='silent')p.boards[k]=tone;else return;
   while(Object.keys(p.boards).length>200)delete p.boards[Object.keys(p.boards)[0]];persist();},
  async toggle(){if(!p.muted&&ready){p.muted=true;persist();return true;}if(!await unlock())return false;p.muted=false;persist();return true;},
  async preview(tone){if(!validTone(tone)||!await unlock())return false;return cue(0,true,tone);},
  seed,disconnected(){baseline=null;lastRead=0;},
  observe(data){const old=baseline,gap=now()-lastRead;seed(data);if(!old||gap>10000||!changed(old,data))return false;return cue(data.board.rowid);},
  committed(action,payload={},result={}){
   if(!['move_card','add_card','copy_card','create_board','add_list','complete_board','reopen_completed_board'].includes(action))return false;
   const identity=JSON.stringify([action,payload.board,payload.version,payload.task,payload.list,payload.position,payload.request,result.id]);
   if(seen.has(identity))return false;seen.add(identity);if(seen.size>100)seen.delete(seen.values().next().value);
   return cue(action==='create_board'?result.id:payload.board);
  },
  settle(){return Promise.race([last,new Promise(resolve=>setTimeout(resolve,500))]);}
 };
}
function createPlayer(win){
 let ctx=null;
 async function unlock(){const Audio=win.AudioContext||win.webkitAudioContext;if(!Audio)return false;if(!ctx||ctx.state==='closed')ctx=new Audio();if(ctx.state!=='running')await ctx.resume();return ctx.state==='running';}
 function play(tone){
  if(!ctx||ctx.state!=='running')throw Error('Audio needs a user gesture');
  const notes={chime:[[660,0,.15],[880,.10,.20]],soft:[[440,0,.24],[554,.07,.25]],ping:[[1046,0,.18]],bell:[[784,0,.30],[1568,0,.22],[2352,0,.13]]}[tone];if(!notes)return Promise.resolve();
  const start=ctx.currentTime+.01;let end=start;
  for(const [frequency,offset,duration] of notes){const osc=ctx.createOscillator(),gain=ctx.createGain();osc.type='sine';osc.frequency.value=frequency;gain.gain.setValueAtTime(0,start+offset);gain.gain.linearRampToValueAtTime(.055/notes.length,start+offset+.008);gain.gain.exponentialRampToValueAtTime(.0001,start+offset+duration);osc.connect(gain);gain.connect(ctx.destination);osc.start(start+offset);osc.stop(start+offset+duration+.01);osc.onended=()=>{osc.disconnect();gain.disconnect();};end=Math.max(end,start+offset+duration+.02);}
  return new Promise(resolve=>win.setTimeout(resolve,Math.min(450,Math.ceil((end-ctx.currentTime)*1000))));
 }
 return {unlock,play};
}
return {tones,createController,createPlayer,changed};
});
