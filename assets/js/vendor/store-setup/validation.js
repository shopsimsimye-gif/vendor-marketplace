function setFieldError(fieldEl, message){
  if (!fieldEl) return;
  fieldEl.setAttribute('aria-invalid', 'true');
  let desc = fieldEl.nextElementSibling;
  if (!desc || !desc.classList.contains('field-error')){
    desc = document.createElement('div'); desc.className='field-error'; desc.setAttribute('role','alert'); desc.style.color = '#dc3545'; desc.style.fontSize = '12px';
    fieldEl.parentNode.insertBefore(desc, fieldEl.nextSibling);
  }
  desc.textContent = message;
}

function clearFieldError(fieldEl){
  if (!fieldEl) return;
  fieldEl.removeAttribute('aria-invalid');
  const desc = fieldEl.nextElementSibling;
  if (desc && desc.classList.contains('field-error')) desc.remove();
}

function applyServerErrors(errors){
  // errors: { field: message }
  Object.keys(errors).forEach(k=>{
    const el = document.querySelector(`[name="${k}"]`) || document.getElementById(k);
    if (el) setFieldError(el, errors[k]);
  });
}

export { setFieldError, clearFieldError, applyServerErrors };
