/* Admin review JS - extended for Health Card, Activity lazy load, search and bulk actions */
(function(){
  const root = VMP_Admin_Settings.restRoot.replace(/\/$/, '');
  const nonce = VMP_Admin_Settings.nonce;

  function apiFetch(path, opts={}){
    opts.headers = Object.assign({'X-WP-Nonce': nonce, 'Content-Type': 'application/json'}, opts.headers || {});
    return fetch(root + path, opts).then(r=>{
      if (!r.ok) throw r;
      return r.json();
    });
  }

  function el(tag, cls, html){ const d = document.createElement(tag); if (cls) d.className = cls; if (html!==undefined) d.innerHTML = html; return d; }
  function escapeHtml(s){ if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  // --- Requests table ---
  async function loadRequests(q, status){
    const container = document.getElementById('vmp-requests-table');
    container.innerHTML = 'Loading...';
    const qs = [];
    if (q) qs.push('q=' + encodeURIComponent(q));
    if (status) qs.push('status=' + encodeURIComponent(status));
    try{
      const res = await apiFetch('/admin/requests' + (qs.length ? '?' + qs.join('&') : ''));
      renderTable(res.data || []);
    }catch(e){ console.error(e); container.innerHTML = '<div class="vmp-error">Failed to load requests</div>'; }
  }

  function renderTable(items){
    const container = document.getElementById('vmp-requests-table');
    const table = document.createElement('table');
    table.className = 'vmp-table';
    table.innerHTML = `
      <thead><tr><th><input id="vmp-select-all" type="checkbox" /></th><th>ID</th><th>Vendor</th><th>Store</th><th>Status</th><th>Submitted</th><th>Last</th><th>Actions</th></tr></thead>
      <tbody></tbody>
    `;
    const tbody = table.querySelector('tbody');
    items.forEach(it=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td><input class="vmp-select" data-id="${it.id}" type="checkbox" /></td><td>${it.id}</td><td>${escapeHtml(it.vendor_name || it.vendor_id || '')}</td><td>${escapeHtml(it.store_name)}</td><td>${escapeHtml(it.status)}</td><td>${escapeHtml(it.submitted_at)}</td><td>${escapeHtml(it.last_activity)}</td><td></td>`;
      const actions = tr.querySelector('td:last-child');
      const btn = el('button','vmp-btn','View');
      btn.addEventListener('click', ()=>openDetail(it.id));
      actions.appendChild(btn);
      tbody.appendChild(tr);
    });
    container.innerHTML = '';
    container.appendChild(table);

    // select all behavior
    const selAll = document.getElementById('vmp-select-all');
    selAll.addEventListener('change', function(){ document.querySelectorAll('.vmp-select').forEach(cb=>cb.checked = selAll.checked); });
  }

  // --- Health card ---
  async function loadHealth(id){
    const container = document.getElementById('vmp-health-content');
    container.innerHTML = 'Loading...';
    try{
      const res = await apiFetch('/admin/requests/' + id + '/health');
      const d = res.data;
      container.innerHTML = `<div class="vmp-health-percent">${d.percent_complete}%</div>`;
      if (d.warnings && d.warnings.length){
        const ul = el('ul','vmp-health-warnings'); d.warnings.forEach(w=> ul.appendChild(el('li','',escapeHtml(w)))); container.appendChild(ul);
      }
      container.appendChild(el('div','',`<strong>Previous requests:</strong> ${d.previous_requests}`));
      container.appendChild(el('div','',`<strong>Last activity:</strong> ${escapeHtml(d.last_activity)}`));
    }catch(e){ console.error(e); container.innerHTML = '<div class="vmp-error">Failed to load health</div>'; }
  }

  // --- Activity log lazy loading ---
  let activityPage = 1; let activityPerPage = 20; let activityRequestId = null;
  async function loadActivity(id, reset = false){
    if (reset) { activityPage = 1; document.getElementById('vmp-activity-items').innerHTML = ''; }
    activityRequestId = id;
    const itemsContainer = document.getElementById('vmp-activity-items');
    try{
      const res = await apiFetch('/admin/requests/' + id + '/activity?page=' + activityPage + '&per_page=' + activityPerPage);
      const items = res.data || [];
      if (!items.length && activityPage === 1) itemsContainer.innerHTML = '<div>No activity yet</div>';
      items.forEach(it=>{
        const div = el('div','vmp-activity-item', `<div class="meta">${escapeHtml(it.timestamp)} — ${escapeHtml(it.actor)}</div><div class="event">${escapeHtml(it.event)}</div>`);
        itemsContainer.appendChild(div);
      });
      // if returned less than perPage, hide load more
      if (items.length < activityPerPage) document.getElementById('vmp-activity-loadmore').style.display = 'none'; else document.getElementById('vmp-activity-loadmore').style.display = '';
      activityPage++;
    }catch(e){ console.error(e); itemsContainer.innerHTML = '<div class="vmp-error">Failed to load activity</div>'; }
  }

  document.addEventListener('click', function(e){
    if (e.target && e.target.id === 'vmp-activity-loadmore-btn'){
      if (activityRequestId) loadActivity(activityRequestId, false);
    }
  });

  // --- Detail modal + actions ---
  async function openDetail(id){
    const modal = document.getElementById('vmp-request-detail-modal');
    modal.setAttribute('aria-hidden','false');
    modal.innerHTML = '<div class="vmp-modal-inner">Loading...</div>';
    try{
      const res = await apiFetch('/admin/requests/' + id);
      renderDetail(res.data);
      // load health and activity for this request
      loadHealth(id);
      loadActivity(id, true);
    }catch(e){ modal.innerHTML = '<div class="vmp-modal-inner">Failed to load</div>'; }
  }

  function closeModal(){ const m = document.getElementById('vmp-request-detail-modal'); m.setAttribute('aria-hidden','true'); m.innerHTML = ''; }

  function renderDetail(detail){
    const modal = document.getElementById('vmp-request-detail-modal');
    const inner = document.createElement('div'); inner.className = 'vmp-modal-inner';

    const h = el('h2', 'vmp-title', `Request #${detail.id} — ${escapeHtml(detail.store_name)}`);
    inner.appendChild(h);

    const layout = el('div','vmp-detail-grid');
    const left = el('div','vmp-col-left'); left.innerHTML = `<div class="vmp-card"><h3>Vendor</h3><p>${escapeHtml(detail.vendor_name || '')}</p></div>`;
    const center = el('div','vmp-col-center'); center.innerHTML = `<div class="vmp-card"><h3>Store Preview</h3><p>Preview not available in demo.</p></div>`;
    const right = el('div','vmp-col-right'); right.innerHTML = `<div class="vmp-card"><h3>Timeline</h3><div id="vmp-timeline-${detail.id}">--</div></div>`;

    layout.appendChild(left); layout.appendChild(center); layout.appendChild(right);
    inner.appendChild(layout);

    const actions = el('div','vmp-actions');
    const activate = el('button','vmp-btn primary','Activate'); activate.addEventListener('click', ()=>confirmAction('activate', detail.id));
    const reqChanges = el('button','vmp-btn','Request Changes'); reqChanges.addEventListener('click', ()=>openRequestChanges(detail.id));
    const reject = el('button','vmp-btn danger','Reject'); reject.addEventListener('click', ()=>openReject(detail.id));
    const close = el('button','vmp-btn','Close'); close.addEventListener('click', closeModal);
    actions.appendChild(activate); actions.appendChild(reqChanges); actions.appendChild(reject); actions.appendChild(close);
    inner.appendChild(actions);

    modal.innerHTML = '';
    modal.appendChild(inner);
  }

  function confirmAction(type, id){
    if (!confirm('Are you sure?')) return;
    if (type === 'activate') doActivate(id);
  }

  async function doActivate(id){
    try{ const res = await apiFetch('/admin/request/' + id + '/activate', { method: 'POST' }); alert('Activated'); closeModal(); loadRequests(); }catch(e){ alert('Failed to activate'); console.error(e); }
  }

  function openRequestChanges(id){ const note = prompt('Enter change request message:'); if (!note) return; doRequestChanges(id, note); }
  async function doRequestChanges(id, message){ try{ const res = await apiFetch('/admin/request/' + id + '/request-changes', { method: 'POST', body: JSON.stringify({ message }) }); alert('Request changes sent'); closeModal(); loadRequests(); }catch(e){ alert('Failed'); console.error(e); } }

  function openReject(id){ const reason = prompt('Enter rejection reason:'); if (!reason) return; doReject(id, reason); }
  async function doReject(id, reason){ try{ const res = await apiFetch('/admin/request/' + id + '/reject', { method: 'POST', body: JSON.stringify({ reason }) }); alert('Rejected'); closeModal(); loadRequests(); }catch(e){ alert('Failed to reject'); console.error(e); } }

  // --- Bulk actions ---
  async function performBulk(action){
    const ids = Array.from(document.querySelectorAll('.vmp-select:checked')).map(cb=>cb.getAttribute('data-id'));
    if (!ids.length) return alert('No items selected');
    if (!confirm('Are you sure to perform bulk action?')) return;
    try{
      const res = await apiFetch('/admin/requests/bulk', { method: 'POST', body: JSON.stringify({ action: action, ids: ids }) });
      alert('Bulk operation finished'); loadRequests();
    }catch(e){ alert('Bulk operation failed'); console.error(e); }
  }

  document.addEventListener('DOMContentLoaded', function(){
    loadRequests();
    document.getElementById('vmp-search').addEventListener('input', function(e){ setTimeout(()=> loadRequests(e.target.value, document.getElementById('vmp-filter-status').value), 300); });
    document.getElementById('vmp-filter-status').addEventListener('change', function(e){ loadRequests(document.getElementById('vmp-search').value, e.target.value); });
    document.getElementById('vmp-bulk-activate').addEventListener('click', ()=>performBulk('activate'));
    document.getElementById('vmp-bulk-reject').addEventListener('click', ()=>performBulk('reject'));
  });
})();
