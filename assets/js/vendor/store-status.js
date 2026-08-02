// store-status.js: renders timeline and status for the user's store setup
const REST_BASE = window.VMP_StoreSetup.restBase;
const NONCE = window.VMP_StoreSetup.nonce;

function qs(sel, ctx=document) { return ctx.querySelector(sel); }
function ce(tag, attrs={}, txt='') { const el = document.createElement(tag); for(const k in attrs) el.setAttribute(k, attrs[k]); if (txt) el.textContent = txt; return el; }

async function api(path, method='GET', body=null, headers={}){
  const url = REST_BASE + path;
  const opts = { method, credentials: 'same-origin', headers: Object.assign({'X-WP-Nonce': NONCE}, headers) };
  if (body) { opts.body = typeof body === 'string' ? body : JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
  const res = await fetch(url, opts);
  return res.json();
}

function timeAgo(iso){
  if (!iso) return '';
  const then = new Date(iso + 'Z'); // ensure UTC
  const diff = Math.floor((Date.now() - then.getTime())/1000);
  if (diff < 60) return `منذ ${diff} ثانية`;
  if (diff < 3600) return `منذ ${Math.floor(diff/60)} دقيقة`;
  if (diff < 86400) return `منذ ${Math.floor(diff/3600)} ساعة`;
  return `منذ ${Math.floor(diff/86400)} يوم`;
}

function mapStatusLabel(status){
  const map = {
    'draft': 'مسودة',
    'submitted': 'مُقدّم',
    'store_setup': 'إعداد المتجر',
    'in_progress': 'قيد الإعداد',
    'completed': 'مكتمل',
    'store_setup_completed': 'تم إكمال إعداد المتجر',
    'admin_review': 'قيد مراجعة الإدارة',
    'active': 'نشط',
    'expired': 'منتهي'
  };
  return map[status] || status;
}

function renderTimeline(session, request){
  const timeline = qs('#vmp-status-timeline'); timeline.innerHTML = '';
  const steps = [
    {key: 'account_created', label: 'تم إنشاء الحساب', done: true},
    {key: 'application_approved', label: 'تمت الموافقة على الطلب', done: (request && (request.status === 'store_setup' || request.status === 'store_setup_completed' || request.status === 'active'))},
    {key: 'store_setup', label: 'إعداد المتجر', done: (session && session.current_step && session.current_step > 0 && session.status !== 'draft')},
    {key: 'admin_review', label: 'مراجعة الإدارة', done: (request && (request.status === 'store_setup_completed' || request.status === 'active'))},
    {key: 'vendor_activated', label: 'تفعيل البائع', done: (request && request.status === 'active')}
  ];

  steps.forEach(s=>{
    const el = ce('div', {class:'timeline-item'}, `${s.done ? '✓' : '○'} ${s.label}`);
    timeline.appendChild(el);
  });
}

async function renderStatus(){
  // try session_uuid from localStorage
  let uuid = localStorage.getItem('vmp_store_setup_uuid');
  let session = null;
  let request = null;
  if (uuid) {
    const res = await api('/store-setup/state?session_uuid=' + encodeURIComponent(uuid));
    if (res && res.success) session = res.session;
    else {
      // check if session expired or not found
      if (res && res.error === 'not_found') { uuid = null; localStorage.removeItem('vmp_store_setup_uuid'); }
    }
  }

  // If no session, attempt to find any session by API? We'll show message
  const msg = qs('#vmp-status-message'); msg.innerHTML = '';
  if (!session) {
    msg.appendChild(ce('p',{}, 'لم يتم العثور على جلسة إعداد نشطة.')); 
    const btn = ce('button',{class:'button'}, 'بدء جلسة جديدة');
    btn.addEventListener('click', async ()=>{
      const r = await api('/store-setup/start', 'POST', {});
      if (r && r.success) { localStorage.setItem('vmp_store_setup_uuid', r.session.session_uuid); window.location.href = '/vendor/store/setup'; }
    });
    msg.appendChild(btn);
    return;
  }

  // fetch vendor request details if available
  if (session.vendor_request_id) {
    const rr = await api('/vendor-request/get?id=' + session.vendor_request_id).catch(()=>null);
    if (rr && rr.success) request = rr.request;
  }

  // status box
  const statusBox = ce('div',{class:'status-box'});
  statusBox.appendChild(ce('h2',{}, 'تم إرسال إعداد المتجر'));
  const stLabel = mapStatusLabel(session.status || (request && request.status) || 'in_progress');
  statusBox.appendChild(ce('p',{}, `الحالة الحالية: ${stLabel}`));
  statusBox.appendChild(ce('p',{}, `آخر تحديث: ${timeAgo(session.last_activity_at)}`));
  qs('#vmp-status-message').appendChild(statusBox);

  // show requested changes if any (placeholder: request has changes array)
  if (request && request.requested_changes) {
    const note = ce('div', {class:'note'}, 'يرجى تعديل العناصر المطلوبة');
    qs('#vmp-status-message').appendChild(note);
  }

  renderTimeline(session, request);

  // actions
  const actions = qs('#vmp-status-actions'); actions.innerHTML = '';
  const resumeBtn = ce('button',{class:'button'}, 'استكمال التعديل');
  resumeBtn.addEventListener('click', ()=>{ window.location.href = '/vendor/store/setup'; });
  actions.appendChild(resumeBtn);
}

window.addEventListener('DOMContentLoaded', ()=>{ renderStatus(); });

export {};
