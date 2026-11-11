(() => {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  window.CMS = {
    csrfToken: csrfMeta ? csrfMeta.getAttribute('content') : '',

    async postJson(url, data) {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: JSON.stringify(data)
      });
      return res.json();
    },

    toggleTheme() {
      const html = document.documentElement;
      const current = html.getAttribute('data-theme') || 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      html.setAttribute('data-bs-theme', next);
      this.postJson('/settings/personalization', { theme: next }).catch(() => {});
    },

    toggleSidebar() {
      const shell = document.getElementById('cmsShell');
      if (!shell) return;
      if (window.innerWidth < 992) {
        shell.classList.toggle('mobile-open');
      } else {
        shell.classList.toggle('sidebar-collapsed');
        const collapsed = shell.classList.contains('sidebar-collapsed') ? 1 : 0;
        this.postJson('/settings/personalization', {
          sidebar_collapsed_submitted: 1,
          sidebar_collapsed: collapsed
        }).catch(() => {});
      }
    }
  };

  // Global delete/action confirmation handler
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm) {
      if (!window.confirm(form.dataset.confirm)) {
        ev.preventDefault();
      }
    }
  });
})();
