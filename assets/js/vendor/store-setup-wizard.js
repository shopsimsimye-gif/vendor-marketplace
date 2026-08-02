// ES6 module for Store Setup Wizard (vanilla JS)
const REST_BASE = window.VMP_StoreSetup.restBase;
const NONCE = window.VMP_StoreSetup.nonce;
const PLUGIN_URL = window.VMP_StoreSetup.pluginUrl;
const DEBUG = !!window.VMP_StoreSetup.debug;

const steps = [
  { id: 1, title: 'معلومات المتجر', key: 'store' },
  { id: 2, title: 'العلامة التجارية', key: 'branding' },
  { id: 3, title: 'الاتصال', key: 'contact' },
  { id: 4, title: 'السياسات', key: 'policies' },
  { id: 5, title: 'وسائل التواصل', key: 'social' },
];

let session = null;
let sessionUuid = null;
let currentStep = 1;
let debounceTimer = null;
let saveAbortController = null;
let savePendingPayload = null; // latest payload waiting to be sent
let lastSavedAt = null;
let lastSavedInterval = null;
let offlineQueue = [];

function log(...args){ if(DEBUG) console.debug('[VMP.Wizard]', ...args); }
function qs(sel, ctx=document) { return ctx.querySelector(sel); }
function ce(tag, attrs={}, txt='') { const el = document.createElement(tag); for(const k in attrs) el.setAttribute(k, attrs[k]); if (txt) el.textContent = txt; return el; }

function setAutosaveStatus(state, msg=''){
  const dot = qs('#vmp-autosave-indicator');
  const label = qs('#vmp-autosave-label');
  const lastEl = qs('#vmp-last-saved');
  if (!dot || !label) return;
  switch(state){
    case 'idle': dot.textContent='○'; label.textContent='غير محفوظ'; lastEl.textContent=''; break;
    case 'saving': dot.textContent='🟡'; label.textContent='يتم الحفظ...'; if(msg) lastEl.textContent = msg; break;
    case 'saved': dot.textContent='🟢'; label.textContent='تم الحفظ'; if(msg) lastEl.textContent = msg; break;
    case 'error': dot.textContent='🔴'; label.textContent='خطأ بالحفظ'; if(msg) lastEl.textContent = msg; break;
  }
}

function timeAgoString(date){
  if(!date) return '';
  const diff = Math.floor((Date.now() - date)/1000);
  if(diff < 60) return `منذ ${diff} ثانية`;
  if(diff < 3600) return `منذ ${Math.floor(diff/60)} دقيقة`;
  if(diff < 86400) return `منذ ${Math.floor(diff/3600)} ساعة`;
  return `منذ ${Math.floor(diff/86400)} يوم`;
}

function updateLastSavedClock(){
  if(!lastSavedAt) return;
  qs('#vmp-last-saved').textContent = timeAgoString(lastSavedAt);
}

async function api(path, method='GET', body=null, headers={}){
  const url = REST_BASE + path;
  const opts = { method, credentials: 'same-origin', headers: Object.assign({'X-WP-Nonce': NONCE}, headers) };
  if (body) { opts.body = typeof body === 'string' ? body : JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
  const res = await fetch(url, opts);
  const contentType = res.headers.get('content-type') || '';
  let json = null;
  if (contentType.includes('application/json')) json = await res.json().catch(()=>null);
  return { status: res.status, json };
}

async function ensureSession(){
  sessionUuid = localStorage.getItem('vmp_store_setup_uuid');
  if (sessionUuid) {
    const {status, json} = await api('/store-setup/state?session_uuid=' + encodeURIComponent(sessionUuid));
    if (status === 200 && json && json.success) { session = json.session; currentStep = session.current_step || 1; startLastSavedClock(session.last_activity_at); renderWizard(); setProgress(); return; }
    // if 404 or expired remove
    if (status === 404 || (json && json.error === 'not_found')) { localStorage.removeItem('vmp_store_setup_uuid'); sessionUuid = null; }
  }
  const { status, json } = await api('/store-setup/start', 'POST', {});
  if (status === 201 && json && json.success) { session = json.session; sessionUuid = session.session_uuid; localStorage.setItem('vmp_store_setup_uuid', sessionUuid); currentStep = session.current_step || 1; renderWizard(); setProgress(); startLastSavedClock(session.last_activity_at); showToast('تم إنشاء جلسة الإعداد'); }
}

function renderWizard(){
  const root = qs('#vmp-wizard-main'); root.innerHTML = '';
  // header progress small label
  const progLabel = ce('div',{id:'vmp-wizard-progress-label','class':'vmp-progress-label'}, `Step ${currentStep} of ${steps.length}`);
  root.appendChild(progLabel);

  steps.forEach(s => {
    const stepEl = ce('section',{class:'step', 'data-step':s.id});
    if (s.id === currentStep) stepEl.classList.add('active');
    const h = ce('h2',{}, s.title);
    stepEl.appendChild(h);
    const container = ce('div',{class:'step-content'});
    if (s.id === 1) {
      container.appendChild(ce('label',{class:'label'}, 'اسم المتجر'));
      const name = ce('input',{class:'input', id:'store_name', name:'store_name', placeholder:'مثال: متجر أحمد'});
      container.appendChild(name);
      container.appendChild(ce('label',{class:'label'}, 'وصف المتجر'));
      const desc = ce('textarea',{class:'input', id:'store_description', name:'store_description', rows:4});
      container.appendChild(desc);
      container.appendChild(ce('label',{class:'label'}, 'معاينة الـ slug'));
      const slugPreview = ce('div',{id:'slug_preview'}, ''); container.appendChild(slugPreview);
    }
    if (s.id === 2) {
      container.appendChild(ce('label',{class:'label'}, 'الشعار (Placeholder)'));
      container.appendChild(ce('div',{class:'input'}, 'مكان رفع الشعار سيُضاف لاحقًا'));
      container.appendChild(ce('label',{class:'label'}, 'بانر (Placeholder)'));
      container.appendChild(ce('div',{class:'input'}, 'مكان رفع البانر سيُضاف لاحقًا'));
      container.appendChild(ce('label',{class:'label'}, 'لون العلامة'));
      container.appendChild(ce('input',{class:'input', id:'brand_color', name:'brand_color', placeholder:'#RRGGBB'}));
    }
    if (s.id === 3) {
      container.appendChild(ce('label',{class:'label'}, 'الهاتف'));
      container.appendChild(ce('input',{class:'input', id:'contact_phone', name:'contact_phone'}));
      container.appendChild(ce('label',{class:'label'}, 'البريد'));
      container.appendChild(ce('input',{class:'input', id:'contact_email', name:'contact_email'}));
      container.appendChild(ce('label',{class:'label'}, 'العنوان'));
      container.appendChild(ce('input',{class:'input', id:'contact_address', name:'contact_address'}));
    }
    if (s.id === 4) {
      container.appendChild(ce('label',{class:'label'}, 'سياسة الشحن'));
      container.appendChild(ce('textarea',{class:'input', id:'policy_shipping', rows:4}));
      container.appendChild(ce('label',{class:'label'}, 'سياسة الإرجاع'));
      container.appendChild(ce('textarea',{class:'input', id:'policy_returns', rows:4}));
      container.appendChild(ce('label',{class:'label'}, 'سياسة الخصوصية'));
      container.appendChild(ce('textarea',{class:'input', id:'policy_privacy', rows:4}));
    }
    if (s.id === 5) {
      container.appendChild(ce('label',{class:'label'}, 'Facebook'));
      container.appendChild(ce('input',{class:'input', id:'social_facebook'}));
      container.appendChild(ce('label',{class:'label'}, 'Instagram'));
      container.appendChild(ce('input',{class:'input', id:'social_instagram'}));
      container.appendChild(ce('label',{class:'label'}, 'X'));
      container.appendChild(ce('input',{class:'input', id:'social_x'}));
      container.appendChild(ce('label',{class:'label'}, 'Website'));
      container.appendChild(ce('input',{class:'input', id:'social_website'}));
    }

    stepEl.appendChild(container);
    const actions = ce('div',{class:'row-actions'});
    if (s.id > 1) actions.appendChild(ce('button',{class:'button secondary', id:`prev_${s.id}`}, 'السابق'));
    if (s.id < steps.length) actions.appendChild(ce('button',{class:'button', id:`next_${s.id}`}, 'التالي'));
    if (s.id === steps.length) actions.appendChild(ce('button',{class:'button', id:'finish_btn'}, 'إنهاء'));
    stepEl.appendChild(actions);

    root.appendChild(stepEl);
  });

  bindEvents();
}

function getStepData(step) {
  if (step === 1) return { store: { store_name: qs('#store_name')?.value || '', description: qs('#store_description')?.value || '', store_slug: qs('#slug_preview')?.textContent || '' } };
  if (step === 2) return { branding: { brand_color: qs('#brand_color')?.value || '' } };
  if (step === 3) return { contact: { phone: qs('#contact_phone')?.value || '', email: qs('#contact_email')?.value || '', address: qs('#contact_address')?.value || '' } };
  if (step === 4) return { policies: { shipping: qs('#policy_shipping')?.value || '', returns: qs('#policy_returns')?.value || '', privacy: qs('#policy_privacy')?.value || '' } };
  if (step === 5) return { social: { facebook: qs('#social_facebook')?.value || '', instagram: qs('#social_instagram')?.value || '', x: qs('#social_x')?.value || '', website: qs('#social_website')?.value || '' } };
  return {};
}

function scheduleSave(step){
  // latest-wins queue: store latest payload and abort previous save
  savePendingPayload = { step, payload: getStepData(step) };
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(()=> doSaveLatest(), 400);
}

async function doSaveLatest(){
  if (!savePendingPayload) return;
  if (!navigator.onLine) {
    // offline -> queue in localStorage
    log('Offline: queueing payload');
    queueOffline(savePendingPayload);
    setAutosaveStatus('error', 'لا يوجد اتصال. سيتم المزامنة عند العودة.');
    savePendingPayload = null;
    return;
  }

  // abort previous
  if (saveAbortController) {
    log('Aborting previous save');
    saveAbortController.abort();
  }
  saveAbortController = new AbortController();
  const { step, payload } = savePendingPayload;
  savePendingPayload = null;

  // idempotency key
  const idempKey = 'save-' + sessionUuid + '-' + step + '-' + Date.now();
  setAutosaveStatus('saving');
  log('Autosave started', { step });

  try {
    const res = await fetch(REST_BASE + '/store-setup/step/' + step, {
      method: 'POST',
      headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json', 'X-Session-UUID': sessionUuid, 'X-Idempotency-Key': idempKey },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
      signal: saveAbortController.signal
    });
    const json = await res.json().catch(()=>null);
    if (res.ok && json && json.success) {
      session = json.session;
      lastSavedAt = Date.now();
      startLastSavedClock();
      setAutosaveStatus('saved', timeAgoString(lastSavedAt));
      log('Autosave finished', { step });
    } else {
      // handle categorized errors
      const message = mapError(res.status, json);
      setAutosaveStatus('error', message);
      log('Autosave failed', { step, status: res.status, json });
    }
  } catch (e) {
    if (e.name === 'AbortError') { log('Autosave cancelled'); setAutosaveStatus('idle'); return; }
    log('Autosave exception', e);
    setAutosaveStatus('error', 'خطأ في الشبكة');
  }
}

function queueOffline(item){
  try {
    const key = 'vmp_offline_queue';
    const arr = JSON.parse(localStorage.getItem(key) || '[]');
    arr.push(item);
    localStorage.setItem(key, JSON.stringify(arr));
  } catch(e){ log('queueOffline failed', e); }
}

async function flushOfflineQueue(){
  const key = 'vmp_offline_queue';
  const arr = JSON.parse(localStorage.getItem(key) || '[]');
  if (!arr.length) return;
  log('Flushing offline queue', arr.length);
  for(const item of arr){
    savePendingPayload = item;
    await doSaveLatest();
  }
  localStorage.removeItem(key);
  showToast('✓ تمت المزامنة');
}

function startLastSavedClock(initialIso){
  if (initialIso) lastSavedAt = new Date(initialIso + 'Z').getTime();
  if (lastSavedInterval) clearInterval(lastSavedInterval);
  if (lastSavedAt) {
    updateLastSavedClock();
    lastSavedInterval = setInterval(updateLastSavedClock, 60 * 1000);
  }
}

function mapError(status, json){
  if (!status) return 'خطأ غير معروف';
  switch(status){
    case 401: return 'يرجى تسجيل الدخول.';
    case 403: return 'ليست لديك صلاحية.';
    case 404: return 'الجلسة غير موجودة.';
    case 409: return 'حدث تعارض، حاول مرة أخرى.';
    case 422: return (json && json.errors) ? JSON.stringify(json.errors) : 'بيانات غير صحيحة.';
    case 500: return 'خطأ داخلي بالخادم.';
    default: return (json && json.error) ? json.error : 'خطأ في الطلب.';
  }
}

function bindEvents(){
  steps.forEach(s=>{
    const prev = qs(`#prev_${s.id}`);
    const next = qs(`#next_${s.id}`);
    if (prev) prev.addEventListener('click', ()=> switchStep(s.id-1));
    if (next) next.addEventListener('click', ()=> { if (validateStep(s.id)) { scheduleSave(s.id); switchStep(s.id+1); } });
  });
  const finish = qs('#finish_btn'); if (finish) finish.addEventListener('click', onFinish);

  // Bind input listeners for autosave
  ['#store_name','#store_description','#brand_color','#contact_phone','#contact_email','#contact_address','#policy_shipping','#policy_returns','#policy_privacy','#social_facebook','#social_instagram','#social_x','#social_website'].forEach(sel => {
    const el = qs(sel); if (el) el.addEventListener('input', ()=> scheduleSave(currentStep));
  });

  // slug preview from name
  const nameInput = qs('#store_name'); if (nameInput) nameInput.addEventListener('input', ()=>{
    const slug = nameInput.value.toLowerCase().trim().replace(/[^a-z0-9\s\-]/gi,'').replace(/\s+/g,'-').replace(/\-+/g,'-');
    qs('#slug_preview').textContent = slug || '';
  });

  // offline/online
  window.addEventListener('online', ()=>{ log('Back online'); setAutosaveStatus('idle'); flushOfflineQueue(); showToast('متصل مجدداً'); });
  window.addEventListener('offline', ()=>{ log('Offline'); setAutosaveStatus('error','لا يوجد اتصال. سيتم المزامنة عند العودة.'); showToast('لا يوجد اتصال بالإنترنت'); });

  // session overlay button
  const startBtn = qs('#vmp-start-new-session'); if (startBtn) startBtn.addEventListener('click', async ()=>{
    const {status,json} = await api('/store-setup/start', 'POST', {});
    if (status === 201 && json && json.success) {
      localStorage.setItem('vmp_store_setup_uuid', json.session.session_uuid);
      location.reload();
    } else {
      alert('فشل بدء جلسة جديدة');
    }
  });
}

function validateStep(step){
  if (step === 1) {
    const name = qs('#store_name')?.value || '';
    if (!name.trim()) { alert('اسم المتجر مطلوب'); return false; }
  }
  if (step === 3) {
    const phone = qs('#contact_phone')?.value || '';
    if (!phone.trim()) { alert('الهاتف مطلوب'); return false; }
  }
  return true;
}

function switchStep(next){
  if (next < 1 || next > steps.length) return;
  const cur = qs(`.step.active`); if (cur) cur.classList.remove('active');
  const newEl = qs(`.step[data-step="${next}"]`);
  if (newEl) newEl.classList.add('active');
  currentStep = next; setProgress();
}

function setProgress() {
  const prog = Math.round((currentStep-1)/(steps.length-1)*100);
  const cont = qs('#vmp-wizard-progress');
  cont.innerHTML = `<div class="bar" style="width:${prog}%"></div>`;
  const label = qs('#vmp-wizard-progress-label'); if (label) label.textContent = `Step ${currentStep} of ${steps.length}`;
}

function showToast(msg) {
  let t = document.getElementById('vmp-toast');
  if (!t) { t = document.createElement('div'); t.id='vmp-toast'; t.className='toast'; document.body.appendChild(t); }
  t.textContent = msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 2500);
}

async function onFinish(){
  if (!confirm('هل أنت متأكد من إكمال إعداد المتجر؟')) return;
  setAutosaveStatus('saving');
  try {
    const res = await api('/store-setup/finish', 'POST', {}, {'X-Session-UUID': sessionUuid});
    if (res.status === 200 && res.json && res.json.success) {
      setAutosaveStatus('saved', 'تم الانتهاء');
      showToast('اكتمل إعداد المتجر، قيد مراجعة المشرف');
      // go to status page
      setTimeout(()=>{ window.location.href = '/vendor/store/status'; }, 1200);
    } else {
      const message = mapError(res.status, res.json);
      setAutosaveStatus('error', message);
      alert('فشل الإنهاء: ' + message);
    }
  } catch(e){ setAutosaveStatus('error','خطأ في الشبكة'); console.error(e); }
}

// init on load
window.addEventListener('DOMContentLoaded', async ()=>{
  renderWizard();
  setProgress();
  bindEvents();
  await ensureSession();
  // if session expired show overlay
  if (session && session.status === 'expired') {
    qs('#vmp-session-overlay').style.display = 'flex';
  }
});

export {};
