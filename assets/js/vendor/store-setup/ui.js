import { save } from './autosave.js';
import { applyServerErrors, clearFieldError } from './validation.js';

let refs = {};

function cacheRefs(){
  refs.root = document.getElementById('vmp-wizard-main');
  refs.progress = document.getElementById('vmp-wizard-progress');
  refs.statusIndicator = document.getElementById('vmp-autosave-indicator');
  refs.statusLabel = document.getElementById('vmp-autosave-label');
  refs.lastSaved = document.getElementById('vmp-last-saved');
}

function setAutosaveStatus(state, msg=''){
  if (!refs.statusIndicator) return;
  switch(state){
    case 'idle': refs.statusIndicator.textContent='○'; refs.statusLabel.textContent = window.wp && wp.i18n ? wp.i18n.__('Unsaved','vmp') : 'Unsaved'; refs.lastSaved.textContent=''; break;
    case 'saving': refs.statusIndicator.textContent='🟡'; refs.statusLabel.textContent = window.wp && wp.i18n ? wp.i18n.__('Saving...','vmp') : 'Saving...'; refs.lastSaved.textContent=msg; break;
    case 'saved': refs.statusIndicator.textContent='🟢'; refs.statusLabel.textContent = window.wp && wp.i18n ? wp.i18n.__('Saved','vmp') : 'Saved'; refs.lastSaved.textContent=msg; break;
    case 'error': refs.statusIndicator.textContent='🔴'; refs.statusLabel.textContent = window.wp && wp.i18n ? wp.i18n.__('Save error','vmp') : 'Save error'; refs.lastSaved.textContent=msg; break;
  }
}

function log(...args){ if(window.VMP_StoreSetup.debug) console.debug('[vmp.ui]', ...args); }

function showToast(msg){
  let t = document.getElementById('vmp-toast');
  if (!t) { t = document.createElement('div'); t.id='vmp-toast'; t.className='toast'; document.body.appendChild(t); }
  t.textContent = msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 2500);
}

async function doSaveStep(step, sessionUuid){
  // collect data for step
  const payload = collectStepData(step);
  // clear previous field errors
  Object.keys(payload).forEach(k=>{ const fld = document.querySelector(`[name="${k}"]`); if(fld) clearFieldError(fld); });
  const res = await save({ step, data: payload }, sessionUuid);
  if (res && res.json && res.json && res.json.errors) applyServerErrors(res.json.errors);
  return res;
}

function collectStepData(step){
  const data = {};
  if (step === 1){ data.store_name = document.getElementById('store_name')?.value || ''; data.description = document.getElementById('store_description')?.value || ''; }
  if (step === 2){ data.brand_color = document.getElementById('brand_color')?.value || ''; }
  if (step === 3){ data.phone = document.getElementById('contact_phone')?.value || ''; data.email = document.getElementById('contact_email')?.value || ''; data.address = document.getElementById('contact_address')?.value || ''; }
  if (step === 4){ data.shipping = document.getElementById('policy_shipping')?.value || ''; data.returns = document.getElementById('policy_returns')?.value || ''; data.privacy = document.getElementById('policy_privacy')?.value || ''; }
  if (step === 5){ data.facebook = document.getElementById('social_facebook')?.value || ''; data.instagram = document.getElementById('social_instagram')?.value || ''; data.x = document.getElementById('social_x')?.value || ''; data.website = document.getElementById('social_website')?.value || ''; }
  // wrap into expected structure
  const wrapper = {};
  if (step === 1) wrapper.store = { store_name: data.store_name, description: data.description };
  if (step === 2) wrapper.branding = { brand_color: data.brand_color };
  if (step === 3) wrapper.contact = { phone: data.phone, email: data.email, address: data.address };
  if (step === 4) wrapper.policies = { shipping: data.shipping, returns: data.returns, privacy: data.privacy };
  if (step === 5) wrapper.social = { facebook: data.facebook, instagram: data.instagram, x: data.x, website: data.website };
  return wrapper;
}

export { cacheRefs, setAutosaveStatus, doSaveStep, showToast, log };
