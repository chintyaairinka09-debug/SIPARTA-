document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');
  const closeSidebar = () => document.body.classList.remove('sidebar-open');
  toggle?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
  backdrop?.addEventListener('click', closeSidebar);

  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (event) => {
      const message = el.getAttribute('data-confirm') || 'Lanjutkan tindakan ini?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.getAttribute('data-toggle-password'));
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      const icon = button.querySelector('i');
      if (icon) icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  });

  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.getAttribute('data-copy') || '';
      try {
        await navigator.clipboard.writeText(value);
        const original = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check2 me-1"></i>Tersalin';
        setTimeout(() => { button.innerHTML = original; }, 1600);
      } catch (_) { window.prompt('Salin kode ini:', value); }
    });
  });

  document.querySelectorAll('[data-table-search]').forEach((input) => {
    input.addEventListener('input', () => {
      const target = document.querySelector(input.getAttribute('data-table-search'));
      if (!target) return;
      const query = input.value.toLowerCase();
      target.querySelectorAll('tbody tr').forEach((row) => {
        row.hidden = !row.textContent.toLowerCase().includes(query);
      });
    });
  });

  document.querySelectorAll('form[data-disable-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]');
      if (button) { button.disabled = true; button.dataset.originalText = button.innerHTML; button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...'; }
    });
  });
});
