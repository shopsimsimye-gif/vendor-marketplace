// session helpers
function getSessionUuid(){ return localStorage.getItem('vmp_store_setup_uuid'); }
function setSessionUuid(uuid){ localStorage.setItem('vmp_store_setup_uuid', uuid); }
function clearSessionUuid(){ localStorage.removeItem('vmp_store_setup_uuid'); }

export { getSessionUuid, setSessionUuid, clearSessionUuid };
