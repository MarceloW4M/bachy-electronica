/* Inserta un sidebar compartido en elementos con atributo data-sidebar */
(function(){
  const markup = `
    <div class="list-group">
      <a href="/admin/dashboard.html" class="list-group-item list-group-item-action">Dashboard</a>
      <a href="/admin/clients.html" class="list-group-item list-group-item-action">Clientes</a>
      <a href="/admin/devices.html" class="list-group-item list-group-item-action">Dispositivos</a>
      <a href="/admin/repairs.html" class="list-group-item list-group-item-action">Reparaciones</a>
    </div>
  `;

  function setActive(container){
    const path = window.location.pathname;
    const links = container.querySelectorAll('a');
    links.forEach(a => {
      try{
        const hrefPath = new URL(a.href, window.location.origin).pathname;
        if (hrefPath === path) a.classList.add('active'); else a.classList.remove('active');
      }catch(e){ }
    });
  }

  document.querySelectorAll('[data-sidebar]').forEach(el => {
    el.innerHTML = markup;
    setActive(el);
  });

  // observar cambios de ruta (si aplican navegaciones SPA futuras)
  window.addEventListener('popstate', ()=>{ document.querySelectorAll('[data-sidebar]').forEach(setActive); });
})();
