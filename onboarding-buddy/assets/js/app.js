/* ============================================================
   Onboarding Buddy System — App JavaScript
   ============================================================ */

(function() {
  'use strict';

  // ── Active nav link ──────────────────────────────────────────
  document.querySelectorAll('.navbar .nav-link').forEach(link => {
    if (link.href === window.location.href ||
        window.location.pathname.startsWith(new URL(link.href, location).pathname)) {
      link.classList.add('active');
    }
  });

  // ── Progress circle CSS custom property ─────────────────────
  document.querySelectorAll('.obs-progress-circle').forEach(el => {
    const pct = el.dataset.pct || 0;
    el.style.setProperty('--pct', pct);
    el.style.background =
      `conic-gradient(#16a34a ${pct}%, #e2e8f0 0)`;
  });

  // ── Auto-dismiss flash alerts after 5s ──────────────────────
  document.querySelectorAll('.alert.alert-success').forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      if (bsAlert) bsAlert.close();
    }, 5000);
  });

  // ── Confirm before critical form submit ─────────────────────
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', e => {
      if (!confirm(form.dataset.confirm)) e.preventDefault();
    });
  });

  // ── Client-side form validation visual ─────────────────────
  document.querySelectorAll('form[novalidate]').forEach(form => {
    form.addEventListener('submit', e => {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

})();
