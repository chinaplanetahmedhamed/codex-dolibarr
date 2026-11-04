// Portable same-origin adapter. Context is a selection hint, never authority.
const KEY = 'chinaplanetdocuments:context:v1';
const TTL = 120000;
const HANDLE = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const FIELDS = ['version','entity','customerId','projectId','shipmentId','shipmentRef','root','returnto'];
function valid(context) {
  if (!context || typeof context !== 'object' || Array.isArray(context)
    || Object.keys(context).length !== FIELDS.length || !FIELDS.every(key => Object.hasOwn(context, key))) return false;
  if (context.version !== 1 || !Number.isSafeInteger(context.entity) || context.entity < 1) return false;
  if (!['customerId','projectId','shipmentId'].every(key => Number.isSafeInteger(context[key]) && context[key] >= 0)) return false;
  if (context.customerId < 1 || typeof context.shipmentRef !== 'string' || context.shipmentRef.length > 128
    || /[\x00-\x1f\x7f]/.test(context.shipmentRef) || (context.shipmentId > 0) !== (context.shipmentRef.length > 0)) return false;
  return typeof context.root === 'string' && /^(?:\/[A-Za-z0-9_-]+)*$/.test(context.root)
    && ['workspace','customer','project','shipment'].includes(context.returnto);
}
function returnPath(context) {
  const targets = {customer:['customerId','/societe/card.php'], project:['projectId','/projet/card.php'], shipment:['shipmentId','/custom/chinaplanetlogistics/card.php']};
  const target = targets[context.returnto];
  if (target && context[target[0]] > 0) return context.root + target[1] + '?id=' + context[target[0]];
  const query = new URLSearchParams();
  for (const [field,key] of [['customerId','socid'],['projectId','projectid'],['shipmentId','shipmentid']]) {
    if (context[field] > 0) query.set(key, String(context[field]));
  }
  return context.root + '/custom/chinaplanetdocuments/index.php' + (query.size ? '?' + query : '');
}

/** Called only for a configured, same-origin editor supporting this adapter. */
export function prepareHandoff(editorUrl, context, env) {
  try {
    if (!valid(context) || typeof editorUrl !== 'string' || !/^https?:\/\//i.test(editorUrl)) return null;
    const target = new URL(editorUrl);
    if (target.origin !== env.location.origin || target.username || target.password || target.search || target.hash) return null;
    const handle = env.crypto.randomUUID();
    if (!HANDLE.test(handle)) return null;
    const createdAt = env.now();
    if (!Number.isSafeInteger(createdAt)) return null;
    env.sessionStorage.setItem(KEY, JSON.stringify({handle, origin: target.origin, createdAt, expiresAt: createdAt + TTL, context}));
    target.hash = 'cpd_context=' + handle;
    return target.href;
  } catch { return null; }
}

/** Receiver hook: invoke only after existing editor staff authentication. */
export function consumeHandoff(env) {
  if (env.authenticatedStaff !== true) return null;
  const match = /^#cpd_context=([0-9a-f-]+)$/i.exec(env.location.hash);
  if (!match || !HANDLE.test(match[1])) return null;
  try {
    const raw = env.sessionStorage.getItem(KEY);
    // The opaque pointer must not stay in browser history after intake.
    env.history.replaceState(null, '', env.location.pathname + env.location.search);
    if (!raw) return null;
    let packet;
    try { packet = JSON.parse(raw); }
    catch { env.sessionStorage.removeItem(KEY); return null; }
    if (packet.handle !== match[1]) return null;
    env.sessionStorage.removeItem(KEY);
    const now = env.now();
    if (packet.origin !== env.location.origin || !Number.isSafeInteger(packet.createdAt) || !Number.isSafeInteger(packet.expiresAt)
      || packet.createdAt > now || packet.expiresAt !== packet.createdAt + TTL || now >= packet.expiresAt
      || !valid(packet.context)) return null;
    return { context: Object.freeze({...packet.context}), returnPath: returnPath(packet.context) };
  } catch { return null; }
}
