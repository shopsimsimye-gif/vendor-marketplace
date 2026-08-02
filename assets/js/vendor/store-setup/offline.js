// offline utilities
function queueItem(item){
  const key = 'vmp_offline_queue';
  const arr = JSON.parse(localStorage.getItem(key) || '[]');
  arr.push(item);
  localStorage.setItem(key, JSON.stringify(arr));
}

function getQueue(){
  return JSON.parse(localStorage.getItem('vmp_offline_queue') || '[]');
}

function clearQueue(){ localStorage.removeItem('vmp_offline_queue'); }

export { queueItem, getQueue, clearQueue };
