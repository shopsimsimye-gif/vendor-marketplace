// Simple wizard autosave (vanilla JS)
(function() {
  const AUTO_SAVE_INTERVAL = 30000;
  let timer;
  function autoSave() {
    const forms = document.querySelectorAll('#vmp-wizard-step-1, #vmp-wizard-step-2, #vmp-wizard-step-3');
    const data = {};
    forms.forEach(f => {
      new FormData(f).forEach((v,k) => data[k] = v);
    });
    fetch('/wp-json/vmp/v1/vendor/draft', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(data)
    }).catch(()=>{});
  }
  window.addEventListener('load', function(){
    timer = setInterval(autoSave, AUTO_SAVE_INTERVAL);
  });
})();
