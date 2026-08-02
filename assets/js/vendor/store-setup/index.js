import { cacheRefs, doSaveStep, setAutosaveStatus, showToast, log } from './ui.js';
import { save, flushOfflineQueue } from './autosave.js';
import { queueItem, getQueue, clearQueue } from './offline.js';
import { getSessionUuid, setSessionUuid } from './session.js';

// Entry point - orchestration
let sessionUuid = null;
let currentStep = 1;
let debounceTimer = null;
let lastSavedAt = null;
let lastSavedInterval = null;

function qs(sel){ return document.querySelector(sel); }

function setProgress(stepCount, current){
  const prog = Math.round((current-1)/(stepCount-1)*100);
  const cont = qs('#vmp-wizard-progress');
  if (cont) cont.innerHTML = `<div class="bar" style="width:${prog}%"></div>`;
  const label = qs('#vmp-wizard-progress-label'); if (label) label.textContent = `Step ${current} of ${stepCount}`;
}

function startLastSavedClock(ts){
  if (!ts) return;
  lastSavedAt = new Date(ts + 'Z').getTime();
  if (lastSavedInterval) clearInterval(lastSavedInterval);
  lastSavedInterval = setInterval(()=>{ const diff=Math.floor((Date.now()-lastSavedAt)/1000); qs('#vmp-last-saved').textContent = diff<60?`منذ ${diff} ثانية`:`منذ ${Math.floor(diff/60)} دقيقة`; }, 60*1000);
}

async function init(){
  cacheRefs();
  sessionUuid = getSessionUuid();
  bindUi();
  if (sessionUuid) {
    const r = await fetch(window.VMP_StoreSetup.restBase + '/store-setup/state?session_uuid=' + encodeURIComponent(sessionUuid), { headers: {'X-WP-Nonce': window.VMP_StoreSetup.nonce}, credentials: 'same-origin' });
    const json = await r.json().catch(()=>null);
    if (r.status === 200 && json && json.success){
      // restore state
      currentStep = json.session.current_step || 1; setProgress(5, currentStep); startLastSavedClock(json.session.last_activity_at);
    } else {
      // session invalid
      setSessionUuid(null);
      sessionUuid = null;
    }
  }
  if (!sessionUuid){
    // start new
    const res = await fetch(window.VMP_StoreSetup.restBase + '/store-setup/start', { method:'POST', headers:{'X-WP-Nonce': window.VMP_StoreSetup.nonce, 'Content-Type':'application/json'}, credentials:'same-origin' });
    const json = await res.json().catch(()=>null);
    if (res.status === 201 && json && json.success){ sessionUuid = json.session.session_uuid; setSessionUuid(sessionUuid); }
  }
  // flush offline queue if online
  if (navigator.onLine) { await flushOfflineQueue(); }
}

function bindUi(){
  document.addEventListener('click', (e)=>{
    const t = e.target;
    if (t.matches('[data-action="prev"]')) { changeStep(-1); }
    if (t.matches('[data-action="next"]')) { changeStep(1); }
  });

  // delegated input listener for autosave (event delegation)
  document.addEventListener('input', (e)=>{
    const el = e.target;
    if (el.closest('.step')) {
      scheduleSaveForCurrentStep();
    }
  });

  const finish = qs('#finish_btn'); if (finish) finish.addEventListener('click', onFinish);
}

function changeStep(delta){
  currentStep = Math.min(5, Math.max(1, currentStep + delta));
  setProgress(5, currentStep);
}

function scheduleSaveForCurrentStep(){
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(()=> saveCurrentStep(), 400);
}

async function saveCurrentStep(){
  const step = currentStep;
  const payload = collectStepData(step);
  const res = await save({ step, data: payload }, sessionUuid);
  if (res && res.ok) {
    lastSavedAt = Date.now(); qs('#vmp-last-saved').textContent = 'الآن';
  }
}

function collectStepData(step){
  // lightweight - reuse UI.collectStepData logic from previous implementation if needed
  const out = {};
  if (step===1) out.store = { store_name: document.getElementById('store_name')?.value||'', description: document.getElementById('store_description')?.value||'' };
  if (step===2) out.branding = { brand_color: document.getElementById('brand_color')?.value||'' };
  if (step===3) out.contact = { phone: document.getElementById('contact_phone')?.value||'', email: document.getElementById('contact_email')?.value||'', address: document.getElementById('contact_address')?.value||'' };
  if (step===4) out.policies = { shipping: document.getElementById('policy_shipping')?.value||'', returns: document.getElementById('policy_returns')?.value||'', privacy: document.getElementById('policy_privacy')?.value||'' };
  if (step===5) out.social = { facebook: document.getElementById('social_facebook')?.value||'', instagram: document.getElementById('social_instagram')?.value||'', x: document.getElementById('social_x')?.value||'', website: document.getElementById('social_website')?.value||'' };
  return out;
}

async function onFinish(){
  if (!confirm(window.wp && wp.i18n ? wp.i18n.__('Are you sure to finish?', 'vmp') : 'Confirm')) return;
  const res = await fetch(window.VMP_StoreSetup.restBase + '/store-setup/finish', { method:'POST', headers:{'X-WP-Nonce': window.VMP_StoreSetup.nonce, 'X-Session-UUID': sessionUuid}, credentials:'same-origin' });
  const json = await res.json().catch(()=>null);
  if (res.status === 200 && json && json.success){ window.location.href = '/vendor/store/status'; }
  else { alert(window.wp && wp.i18n ? wp.i18n.__('Finish failed','vmp') : 'Finish failed'); }
}

// initialize
init();

export {};
