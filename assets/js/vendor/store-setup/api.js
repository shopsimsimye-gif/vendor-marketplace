// API helpers for store setup
const REST_BASE = window.VMP_StoreSetup.restBase;
const NONCE = window.VMP_StoreSetup.nonce;

async function request(path, method='GET', body=null, headers={}){
  const url = REST_BASE + path;
  const opts = { method, credentials: 'same-origin', headers: Object.assign({'X-WP-Nonce': NONCE}, headers) };
  if (body) { opts.body = typeof body === 'string' ? body : JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
  const res = await fetch(url, opts);
  const contentType = res.headers.get('content-type') || '';
  let json = null;
  if (contentType.includes('application/json')) json = await res.json().catch(()=>null);
  return { status: res.status, json };
}

export { request };
