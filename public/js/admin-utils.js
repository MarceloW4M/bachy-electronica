// Utilidades compartidas para admin: toasts, auth headers, validaciones
(function(){
  function getAuthHeaders(){
    const token = localStorage.getItem('token');
    return token ? { Authorization: 'Bearer ' + token } : {};
  }

  function ensureToastContainer(){
    let c = document.getElementById('toastContainer');
    if (!c) {
      const wrapper = document.createElement('div');
      wrapper.className = 'position-fixed bottom-0 end-0 p-3';
      wrapper.style.zIndex = 1100;
      wrapper.id = 'globalToastWrapper';
      c = document.createElement('div');
      c.id = 'toastContainer';
      wrapper.appendChild(c);
      document.body.appendChild(wrapper);
    }
    return c;
  }

  function showToast(message, kind='info', timeout=5000){
    const container = ensureToastContainer();
    const id = 't' + Date.now() + Math.floor(Math.random()*1000);
    const div = document.createElement('div');
    div.innerHTML = `<div id="${id}" class="toast align-items-center text-bg-${kind} border-0" role="alert" aria-live="assertive" aria-atomic="true"><div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>`;
    container.appendChild(div);
    const tEl = document.getElementById(id);
    const t = new bootstrap.Toast(tEl);
    t.show();
    if (timeout>0) setTimeout(()=>{ t.hide(); }, timeout);
  }

  function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function validateRequired(fieldId, message){
    const el = document.getElementById(fieldId);
    if(!el) return true;
    if(String(el.value||'').trim()===''){
      showToast(message || 'Campo requerido', 'warning');
      el.focus();
      return false;
    }
    return true;
  }

  function formatError(err){
    try{
      if(!err) return 'Error desconocido';
      if(err.response && err.response.data){
        console.error('API error response:', err.response.data);
        if(typeof err.response.data === 'string') return err.response.data;
        if(err.response.data.message) return err.response.data.message;
        return JSON.stringify(err.response.data);
      }
      if(err.message) return err.message;
      return String(err);
    }catch(e){ return 'Error desconocido'; }
  }

  window.AdminUtils = { getAuthHeaders, getAuth: getAuthHeaders, showToast, escapeHtml, validateRequired, formatError };
  // Backwards compatibility: expose top-level functions if not already defined
  if (!window.getAuthHeaders) window.getAuthHeaders = getAuthHeaders;
  if (!window.showToast) window.showToast = showToast;
  if (!window.escapeHtml) window.escapeHtml = escapeHtml;
  if (!window.validateRequired) window.validateRequired = validateRequired;
  if (!window.getAuth) window.getAuth = getAuthHeaders;
  if (!window.formatError) window.formatError = formatError;
})();

// Global logout hookup: if a `#logout` button exists, attach default behavior
document.addEventListener('DOMContentLoaded', function(){
  try{
    const lo = document.getElementById('logout');
    if(!lo) return;
    lo.addEventListener('click', async function(){
      localStorage.removeItem('token');
      try{ await fetch('/auth.php/logout', { method: 'POST' }); }catch(e){}
      window.location.href = '/admin/login.html';
    });
  }catch(e){ /* ignore */ }
});
