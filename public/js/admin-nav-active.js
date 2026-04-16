// Activar item de navbar según la ruta actual
(function(){
  try{
    const path = location.pathname.replace(/\/+/g,'/');
    document.querySelectorAll('.navbar-nav a, .navbar-nav .dropdown-item').forEach(el=>{
      const href = el.getAttribute('href');
      if(!href) return;
      if(href === path){ el.classList.add('active'); const parent = el.closest('.dropdown'); if(parent) parent.querySelector('.nav-link.dropdown-toggle')?.classList.add('active'); }
    });

    // Make dropdown toggles with an actual href navigate when clicked (useful to open list directly)
    document.querySelectorAll('.nav-link.dropdown-toggle[href]').forEach(toggle => {
      const href = toggle.getAttribute('href');
      if(!href || href === '#' || href === 'javascript:void(0)') return;
      toggle.addEventListener('click', function(e){
        // allow ctrl/meta/shift clicks to open in new tab/window
        if(e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
        // navigate to href (allow default dropdown show on hover via CSS)
        window.location.href = href;
      });
    });

  }catch(e){ console && console.warn && console.warn('admin-nav-active error', e); }
})();
