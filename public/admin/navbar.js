(() => {
  const NAV_HTML = `
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
      <a class="navbar-brand" href="/admin/agenda.html">Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="/admin/agenda.html">Agenda</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin/clients.html">Clientes</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="repairsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Reparaciones</a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="repairsDropdown">
              <li><a class="dropdown-item" href="/admin/repairs.html#order">Orden de reparación</a></li>
              <li><a class="dropdown-item" href="/admin/devices.html">Dispositivos</a></li>
              <li><a class="dropdown-item" href="/admin/technicians.html">Técnicos</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Administración</a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
              <li><a class="dropdown-item" href="/admin/compras.html">Compras</a></li>
              <li><a class="dropdown-item" href="/admin/ventas.html">Ventas</a></li>
              <li><a class="dropdown-item" href="/admin/presupuestos.html">Presupuestos</a></li>
              <li><a class="dropdown-item" href="/admin/remitos.html">Remitos</a></li>
              <li><a class="dropdown-item" href="/admin/facturas.html">Facturas</a></li>
              <li><a class="dropdown-item" href="/admin/cajas-diarias.html">Cajas diarias</a></li>
              <li><a class="dropdown-item" href="/admin/finanzas.html">Finanzas</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="stockDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Stock</a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="stockDropdown">
              <li><a class="dropdown-item" href="/admin/brands.html">Marcas</a></li>
              <li><a class="dropdown-item" href="/admin/models.html">Modelos</a></li>
              <li><a class="dropdown-item" href="/admin/categories.html">Rubros</a></li>
              <li><a class="dropdown-item" href="/admin/items.html">Artículos</a></li>
              <li><a class="dropdown-item" href="/admin/warehouses.html">Depósitos</a></li>
              <li><a class="dropdown-item" href="/admin/stocks.html">Stock</a></li>
              <li><a class="dropdown-item" href="/admin/suppliers.html">Proveedores</a></li>
            </ul>
          </li>
          <li class="nav-item"><button id="logout" class="btn btn-outline-light btn-sm ms-3">Cerrar sesión</button></li>
        </ul>
      </div>
    </div>
  </nav>`;

  function inject() {
    const container = document.getElementById('adminNavbar');
    if (!container) return;

    // If this script already injected the navbar, don't inject again
    if (document.querySelector('nav.navbar[data-injected-by="navbar.js"]')) return;

    // Inject navbar HTML
    container.innerHTML = NAV_HTML;
    const nav = container.querySelector('nav.navbar');
    if (nav) nav.setAttribute('data-injected-by', 'navbar.js');

    setupLogout();
    setActive();
  }

  

  function setupLogout(){
    const btn = document.getElementById('logout');
    if(!btn) return;
    btn.addEventListener('click', async ()=>{
      try{ await fetch('/auth.php/logout',{method:'POST'}); }catch(e){}
      window.location = '/admin/login.html';
    });
  }

  function setActive(){
    const path = location.pathname;
    const links = document.querySelectorAll('#adminNavbar a.nav-link, #adminNavbar .dropdown-item');
    links.forEach(a=>{
      try{
        const href = new URL(a.href, location.origin).pathname;
        if(href === path) a.classList.add('active'); else a.classList.remove('active');
        const parent = a.closest('.dropdown-menu');
        if(parent && parent.querySelector('.dropdown-item.active')){
          const toggle = parent.closest('.nav-item').querySelector('.nav-link'); if(toggle) toggle.classList.add('active');
        }
      }catch(e){ }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', inject); else inject();

})();
