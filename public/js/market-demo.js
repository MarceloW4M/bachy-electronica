function initMobileMenu() {
  const toggle = document.getElementById('menuToggle');
  const mobileMenu = document.getElementById('mobileMenu');
  if (!toggle || !mobileMenu) {
    return;
  }

  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    mobileMenu.hidden = !open;
    mobileMenu.dataset.open = String(open);
    document.body.classList.toggle('menu-open', open);
  };

  setOpen(false);

  toggle.addEventListener('click', () => {
    const next = toggle.getAttribute('aria-expanded') !== 'true';
    setOpen(next);
  });

  mobileMenu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setOpen(false));
  });
}

function initProductTabs() {
  const tabsRoot = document.getElementById('productTabs');
  if (!tabsRoot) {
    return;
  }

  const tabs = Array.from(tabsRoot.querySelectorAll('[data-target]'));
  const panels = Array.from(document.querySelectorAll('[data-panel]'));

  const activate = (target) => {
    tabs.forEach((tab) => {
      tab.classList.toggle('tab-button-active', tab.dataset.target === target);
    });
    panels.forEach((panel) => {
      panel.classList.toggle('product-panel-active', panel.dataset.panel === target);
    });
  };

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => activate(tab.dataset.target));
  });
}

function initAnnouncementRotation() {
  const track = document.getElementById('announcementTrack');
  if (!track || window.matchMedia('(max-width: 900px)').matches) {
    return;
  }

  window.setInterval(() => {
    const first = track.firstElementChild;
    if (!first) {
      return;
    }
    track.appendChild(first);
  }, 3200);
}

function preventPlaceholderForms() {
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initMobileMenu();
  initProductTabs();
  initAnnouncementRotation();
  preventPlaceholderForms();
});
