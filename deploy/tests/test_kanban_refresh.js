const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const listeners = {};
const saved = new Map();
const boardScroller = {scrollLeft: 31};
const board = {
  dataset: {kanbanRevision: 'old', kanbanRevisionUrl: '/custom/revision.php'},
  querySelector: () => boardScroller,
};
let editing = false;
let visible = 'visible';
let reloads = 0;
let interval;
let fetchedUrl;
const document = {
  readyState: 'complete',
  get visibilityState() { return visible; },
  get activeElement() { return editing ? {matches: () => true} : {matches: () => false}; },
  documentElement: {classList: {contains: () => false}},
  querySelector: selector => selector.startsWith('[data-kanban') ? board : null,
  addEventListener: (name, callback) => { listeners[name] = callback; },
};
const window = {
  location: {
    href: 'https://erp.example/custom/board.php?view=my',
    pathname: '/custom/board.php', search: '?view=my',
    reload: () => { reloads++; },
  },
  scrollX: 12, scrollY: 42, scrollTo: () => {},
};
const context = {
  document, window, URL, sessionStorage: {
    getItem: key => saved.get(key),
    setItem: (key, value) => saved.set(key, value),
    removeItem: key => saved.delete(key),
  },
  setInterval: callback => { interval = callback; },
  setTimeout: callback => callback(),
  fetch: async url => { fetchedUrl = url; return {ok: true, json: async () => ({revision: 'new'})}; },
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../../custom/chinaplanetcustomercenter/js/kanban_refresh.js'), 'utf8'), context);
const settle = () => new Promise(resolve => setImmediate(resolve));
(async () => {
  editing = true;
  interval();
  await settle();
  assert.equal(reloads, 0, 'an open editor must not be interrupted');
  assert.equal(new URL(fetchedUrl).search, '?view=my', 'the current board view must be polled');
  editing = false;
  interval();
  assert.equal(reloads, 1, 'a saved remote change must appear automatically');
  assert.deepEqual(JSON.parse(saved.values().next().value), {pageX: 12, pageY: 42, boardX: 31});
  visible = 'hidden';
  interval();
  assert.equal(reloads, 1, 'hidden tabs must not reload');
  console.log('KANBAN_REFRESH=PASS');
})().catch(error => { console.error(error); process.exitCode = 1; });
