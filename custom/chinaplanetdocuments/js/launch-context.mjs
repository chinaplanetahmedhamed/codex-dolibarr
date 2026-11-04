import { prepareHandoff } from './editor-context.mjs';

/** Progressive enhancement; failures retain the original private launch link. */
export function wireContextLauncher(env) {
  const anchor = env.document.querySelector('[data-cpd-editor]');
  const source = env.document.getElementById('cpd-editor-context');
  if (!anchor || !source) return;
  let context;
  try { context = JSON.parse(source.textContent); } catch { return; }
  anchor.addEventListener('click', event => {
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const target = prepareHandoff(anchor.href, context, {
      location: env.location, sessionStorage: env.sessionStorage, crypto: env.crypto,
      now: env.now ?? (() => Date.now()),
    });
    if (!target) return;
    event.preventDefault();
    env.location.assign(target);
  });
}

if (typeof window !== 'undefined') wireContextLauncher(window);
