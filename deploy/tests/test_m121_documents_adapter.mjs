import test from 'node:test';
import assert from 'node:assert/strict';
import { prepareHandoff, consumeHandoff } from '../../custom/chinaplanetdocuments/js/editor-context.mjs';
import { wireContextLauncher } from '../../custom/chinaplanetdocuments/js/launch-context.mjs';

const sample = () => ({ version: 1, entity: 1, customerId: 7, projectId: 9, shipmentId: 3,
  shipmentRef: 'TEST-S3', root: '/erp', returnto: 'shipment' });
function browser() {
  const data = new Map();
  return { location: { origin: 'https://erp.test', pathname: '/editor/', search: '', hash: '' },
    sessionStorage: { getItem: k => data.get(k) ?? null, setItem: (k,v) => data.set(k,v), removeItem: k => data.delete(k) },
    crypto: { randomUUID: () => '12345678-1234-4234-9234-123456789abc' },
    now: () => 1000, authenticatedStaff: true,
    history: { replaceState: () => {} }, data };
}
test('same-origin transfer uses an opaque fragment, consumes once, preserves canonical return', () => {
  const env = browser();
  const target = prepareHandoff('https://erp.test/editor/', sample(), env);
  assert.equal(target, 'https://erp.test/editor/#cpd_context=12345678-1234-4234-9234-123456789abc');
  assert.ok(!target.includes('TEST-S3') && !target.includes('projectId'));
  env.location.hash = new URL(target).hash;
  let cleaned;
  env.history.replaceState = (_state,_title,url) => { cleaned = url; };
  const value = consumeHandoff(env);
  assert.equal(value.context.shipmentRef, 'TEST-S3');
  assert.equal(value.context.projectId, 9);
  assert.equal(value.returnPath, '/erp/custom/chinaplanetlogistics/card.php?id=3');
  assert.equal(cleaned, '/editor/');
  assert.equal(env.data.size, 0);
  assert.equal(consumeHandoff(env), null);
});
for (const target of ['https://docs.other.test/', 'http://erp.test/', 'https://erp.test:444/editor/', '//evil.test/',
  'javascript:alert(1)', 'https://u:p@erp.test/editor/', 'https://erp.test/editor/?token=x', 'https://erp.test/editor/#x']) {
  test(`no disclosure or storage for disallowed destination ${target}`, () => {
    const env = browser();
    assert.equal(prepareHandoff(target, sample(), env), null);
    assert.equal(env.data.size, 0);
  });
}
test('receiver waits for authenticated staff; unrelated fragments remain unchanged', () => {
  const env = browser();
  env.location.hash = new URL(prepareHandoff('https://erp.test/editor/', sample(), env)).hash;
  env.authenticatedStaff = false;
  assert.equal(consumeHandoff(env), null);
  assert.equal(env.data.size, 1);
  env.authenticatedStaff = true;
  assert.ok(consumeHandoff(env));
  env.location.hash = '#invoice-tab';
  env.history.replaceState = () => assert.fail('unrelated fragment modified');
  assert.equal(consumeHandoff(env), null);
});
test('expired context is removed and cannot be replayed', () => {
  const env = browser();
  env.location.hash = new URL(prepareHandoff('https://erp.test/editor/', sample(), env)).hash;
  env.now = () => 122000;
  assert.equal(consumeHandoff(env), null);
  assert.equal(env.data.size, 0);
});
test('overlapping launches invalidate the older handle', () => {
  const env = browser();
  const first = prepareHandoff('https://erp.test/editor/', sample(), env);
  env.crypto.randomUUID = () => '22345678-1234-4234-9234-123456789abc';
  const second = prepareHandoff('https://erp.test/editor/', sample(), env);
  env.location.hash = new URL(first).hash;
  assert.equal(consumeHandoff(env), null);
  env.location.hash = new URL(second).hash;
  assert.ok(consumeHandoff(env));
});
for (const [key,value] of [['customerId',0],['projectId',-1],['shipmentId',3.5],['returnto','https://evil.test'],
  ['root','//evil.test'],['root','/erp/../x'],['shipmentRef',''],['version',2],['draftId',55]]) {
  test(`reject invalid or extra field ${key}`, () => {
    const env = browser();
    assert.equal(prepareHandoff('https://erp.test/editor/', {...sample(), [key]:value}, env), null);
    assert.equal(env.data.size, 0);
  });
}
test('customer-only and project-only contexts return to validated native records', () => {
  for (const [patch,path] of [
    [{shipmentId:0,shipmentRef:'',returnto:'project'}, '/erp/projet/card.php?id=9'],
    [{shipmentId:0,shipmentRef:'',projectId:0,returnto:'customer'}, '/erp/societe/card.php?id=7'],
    [{shipmentId:0,shipmentRef:'',returnto:'shipment'}, '/erp/custom/chinaplanetdocuments/index.php?socid=7&projectid=9'],
  ]) {
    const env = browser();
    env.location.hash = new URL(prepareHandoff('https://erp.test/editor/', {...sample(),...patch}, env)).hash;
    assert.equal(consumeHandoff(env).returnPath, path);
  }
});
test('malformed storage and storage failure fail closed', () => {
  const env = browser();
  const target = prepareHandoff('https://erp.test/editor/', sample(), env);
  env.location.hash = new URL(target).hash;
  for (const key of env.data.keys()) env.data.set(key, '{bad JSON');
  assert.equal(consumeHandoff(env), null);
  assert.equal(env.data.size, 0);
  env.sessionStorage.setItem = () => { throw new Error('disabled'); };
  assert.equal(prepareHandoff('https://erp.test/editor/', sample(), env), null);
});
for (const [field,value] of [['createdAt',2000],['expiresAt',9999999],['origin','https://other.test']]) {
  test(`receiver rejects tampered ${field}`, () => {
    const env=browser();
    env.location.hash=new URL(prepareHandoff('https://erp.test/editor/',sample(),env)).hash;
    for (const [key,raw] of env.data) env.data.set(key,JSON.stringify({...JSON.parse(raw),[field]:value}));
    assert.equal(consumeHandoff(env),null);
    assert.equal(env.data.size,0);
  });
}
function launcher(url='https://erp.test/editor/', content=JSON.stringify(sample())) {
  const env=browser(); let handler;
  env.document={querySelector:()=>({href:url,addEventListener:(_type,fn)=>{handler=fn;}}),getElementById:()=>({textContent:content})};
  const navigated=[];
  env.location.assign=url=>navigated.push(url);
  wireContextLauncher(env);
  const event={button:0,preventDefault:()=>{event.prevented=true;}};
  return {env,event,navigated,click:()=>handler?.(event)};
}
test('enabled local launcher transfers in same tab; existing anchor is fallback', () => {
  const state=launcher();state.click();
  assert.equal(state.event.prevented,true);
  assert.equal(state.navigated.length,1);
  state.env.location.hash=new URL(state.navigated[0]).hash;
  assert.equal(consumeHandoff(state.env).context.shipmentId,3);
});
test('cross-origin launcher never writes context, navigates, or cancels original private link', () => {
  const state=launcher('https://docs.other.test/');state.click();
  assert.equal(state.env.data.size,0);
  assert.equal(state.event.prevented,undefined);
  assert.deepEqual(state.navigated,[]);
});
for (const modifier of ['ctrlKey','metaKey','shiftKey','altKey']) {
  test(`modified click ${modifier} does not disclose context`,()=>{
    const state=launcher();state.event[modifier]=true;state.click();
    assert.equal(state.env.data.size,0);assert.deepEqual(state.navigated,[]);
  });
}
test('corrupt payload and disabled storage preserve original private link', () => {
  const corrupt=launcher(undefined,'bad JSON');corrupt.click();
  assert.equal(corrupt.event.prevented,undefined);
  const blocked=launcher();blocked.env.sessionStorage.setItem=()=>{throw Error('disabled');};blocked.click();
  assert.equal(blocked.event.prevented,undefined);
  assert.deepEqual(blocked.navigated,[]);
});
