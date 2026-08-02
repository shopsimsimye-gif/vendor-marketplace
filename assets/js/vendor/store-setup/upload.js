// Client-side upload helper with simple hybrid crop (center-crop to target dims)
// Usage: uploadFile(file, 'logo'|'banner') -> returns Promise resolving to server response

function loadImage(file){
  return new Promise((resolve, reject)=>{
    const img = new Image();
    img.onload = ()=>resolve(img);
    img.onerror = reject;
    img.src = URL.createObjectURL(file);
  });
}

function canvasCenterCrop(img, targetW, targetH){
  const canvas = document.createElement('canvas');
  canvas.width = targetW; canvas.height = targetH;
  const ctx = canvas.getContext('2d');
  // cover strategy
  const scale = Math.max(targetW / img.width, targetH / img.height);
  const sw = Math.round(targetW / scale);
  const sh = Math.round(targetH / scale);
  const sx = Math.round((img.width - sw) / 2);
  const sy = Math.round((img.height - sh) / 2);
  ctx.drawImage(img, sx, sy, sw, sh, 0, 0, targetW, targetH);
  return canvas;
}

function blobFromCanvas(canvas, mime='image/jpeg', quality=0.9){
  return new Promise(resolve => canvas.toBlob(b => resolve(b), mime, quality));
}

function uploadBlob(blob, type, sessionUuid, onProgress){
  return new Promise((resolve, reject)=>{
    const form = new FormData();
    form.append('file', blob, (type==='logo'?'logo':'banner') + '.' + (blob.type.split('/')[1]||'jpg'));
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.VMP_StoreSetup.restBase + (type==='logo'?'/store/upload/logo':'/store/upload/banner'));
    xhr.setRequestHeader('X-WP-Nonce', window.VMP_StoreSetup.nonce);
    xhr.setRequestHeader('X-Session-UUID', sessionUuid);
    xhr.upload.onprogress = function(e){ if (e.lengthComputable && onProgress) onProgress(Math.round(e.loaded / e.total * 100)); };
    xhr.onload = function(){
      try{ const json = JSON.parse(xhr.responseText); resolve({ status: xhr.status, json }); } catch(e){ reject(e); }
    };
    xhr.onerror = ()=> reject(new Error('Network error'));
    xhr.send(form);
  });
}

async function uploadFile(file, type, sessionUuid, opts={}){
  // type: 'logo' or 'banner'
  // opts: for logo target 512x512 min 256, banner 1500x500 min 1200x400
  const cfg = {
    logo: { w: 512, h: 512, mime: 'image/jpeg' },
    banner: { w: 1500, h: 500, mime: 'image/jpeg' }
  };
  const img = await loadImage(file);
  // ensure min dimensions
  const min = (type==='logo') ? {w:256,h:256} : {w:1200,h:400};
  if (img.width < min.w || img.height < min.h) throw new Error('dimensions_too_small');

  // center crop to target
  const canvas = canvasCenterCrop(img, cfg[type].w, cfg[type].h);
  const blob = await blobFromCanvas(canvas, cfg[type].mime, 0.92);
  // upload with progress
  return await uploadBlob(blob, type, sessionUuid, opts.onProgress);
}

export { uploadFile };
