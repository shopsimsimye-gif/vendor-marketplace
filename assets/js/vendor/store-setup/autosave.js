import { request } from './api.js';
import { setAutosaveStatus, log, flushOfflineQueue } from './ui.js';

let abortController = null;
let pending = null;

function genIdempotencyKey(sessionUuid, step){
  return 'idemp|' + sessionUuid + '|' + step + '|' + Date.now();
}

async function save(payload, sessionUuid){
  // payload: { step, data }
  if (!navigator.onLine) {
    log('offline - queueing payload');
    // store offline queue
    const arr = JSON.parse(localStorage.getItem('vmp_offline_queue') || '[]');
    arr.push(payload);
    localStorage.setItem('vmp_offline_queue', JSON.stringify(arr));
    setAutosaveStatus('error', window.wp && wp.i18n ? wp.i18n.__('Offline. Will sync when online', 'vmp') : 'Offline');
    return { queued: true };
  }

  // abort previous
  if (abortController) { abortController.abort(); }
  abortController = new AbortController();

  const idemp = genIdempotencyKey(sessionUuid, payload.step);
  setAutosaveStatus('saving');
  log('autosave:start', payload.step);
  try {
    const res = await fetch(REST_BASE + '/store-setup/step/' + payload.step, {
      method: 'POST',
      headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json', 'X-Session-UUID': sessionUuid, 'X-Idempotency-Key': idemp },
      body: JSON.stringify(payload.data),
      credentials: 'same-origin',
      signal: abortController.signal
    });
    const json = await res.json().catch(()=>null);
    if (res.ok && json && json.success) {
      log('autosave:finished', payload.step);
      setAutosaveStatus('saved');
      return { ok: true, session: json.session };
    }
    const message = mapError(res.status, json);
    setAutosaveStatus('error', message);
    log('autosave:error', res.status, json);
    return { ok: false, status: res.status, json };
  } catch (e) {
    if (e.name === 'AbortError') { log('autosave:aborted'); setAutosaveStatus('idle'); return { aborted: true }; }
    log('autosave:exception', e);
    setAutosaveStatus('error', window.wp && wp.i18n ? wp.i18n.__('Network error', 'vmp') : 'Network error');
    return { ok: false, error: e };
  }
}

function mapError(status, json){
  if (!status) return window.wp && wp.i18n ? wp.i18n.__('Unknown error', 'vmp') : 'Unknown error';
  switch(status){
    case 401: return window.wp && wp.i18n ? wp.i18n.__('Please login.', 'vmp') : 'Please login.';
    case 403: return window.wp && wp.i18n ? wp.i18n.__('You do not have permission.', 'vmp') : 'Permission denied.';
    case 404: return window.wp && wp.i18n ? wp.i18n.__('Session not found.', 'vmp') : 'Session not found.';
    case 409: return window.wp && wp.i18n ? wp.i18n.__('Conflict detected, try again.', 'vmp') : 'Conflict';
    case 422: return json && json.errors ? JSON.stringify(json.errors) : (window.wp && wp.i18n ? wp.i18n.__('Invalid data', 'vmp') : 'Invalid');
    case 500: return window.wp && wp.i18n ? wp.i18n.__('Server error', 'vmp') : 'Server error';
    default: return (json && json.error) || (window.wp && wp.i18n ? wp.i18n.__('Request error', 'vmp') : 'Request error');
  }
}

export { save, flushOfflineQueue };
