const assert=require('node:assert/strict');
const g=require('../../../custom/chinaplanetoperations/js/boards_geometry.js');
let count=0;function test(label,fn){fn();count++;console.log('PASS '+label);}
const viewport={left:0,top:0,width:820,height:819};
test('a long menu uses full space above instead of cramped 200px below',()=>{const p=g.placement({left:100,right:140,top:560,bottom:600},{width:280,height:400},viewport);assert.equal(p.direction,'above');assert.equal(p.maxHeight,544);assert.equal(p.top,152);});
test('a menu that fits below stays below',()=>{const p=g.placement({left:100,top:200,bottom:244},{width:280,height:300},viewport);assert.equal(p.direction,'below');assert.equal(p.top,252);});
test('oversized menu chooses larger side and remains reachable',()=>{const p=g.placement({left:790,top:480,bottom:524},{width:280,height:900},viewport);assert.equal(p.direction,'above');assert.ok(p.left+280<=812);assert.ok(p.top>=8);assert.ok(p.maxHeight<900);});
test('visual viewport offset and narrow width are respected',()=>{const p=g.placement({left:450,top:400,bottom:444},{width:280,height:200},{left:80,top:300,width:230,height:300});assert.ok(p.left>=88);assert.ok(p.left+p.width<=302);assert.ok(p.top>=308);assert.ok(p.top+Math.min(200,p.maxHeight)<=592);});
test('natural-height frame maps parent clip to visible child coordinates',()=>{const p=g.frameViewport({left:0,top:0,width:390,height:844},{left:10,top:-600,right:380,bottom:1400},{left:10,top:68,right:380,bottom:832});assert.deepEqual(p,{left:0,top:668,width:370,height:764});});
test('frame fitting grows and shrinks with content without compounding viewport height',()=>{const f={style:{height:'600px'}};let height=1802.4;const doc={body:{getBoundingClientRect:()=>({height})}};assert.equal(g.fitFrame(f,doc),1803);height=750;assert.equal(g.fitFrame(f,doc),750);assert.equal(f.style.height,'750px');assert.equal(g.fitFrame(f,doc),750);});
test('closing a card restores the board position and a replacement card focus',()=>{let focus=null,scroll=null;const fresh={focus:value=>focus=value};const root={querySelector:()=>fresh};g.restoreBoardPosition({task:5,x:0,y:810,focus:{isConnected:false}},root,{scrollTo:(x,y)=>scroll=[x,y]});assert.deepEqual(focus,{preventScroll:true});assert.deepEqual(scroll,[0,810]);});

console.log('GEOMETRY_PASS '+count+' cases');
