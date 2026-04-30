/* ════════════════════════════════════════════════════════════════════
   Ekanet — Frontend público
   Vanilla JS, sin dependencias. GSAP se cargará a futuro para animaciones
   complejas (timelines, ScrollTrigger).
   ════════════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  // Quitar la clase pre-flag del modo claro
  document.documentElement.classList.remove('ek-light-pre');

  // ═══════════ Theme toggle ═══════════
  const themeToggle = document.getElementById('ek-theme-toggle');
  const setTheme = (theme) => {
    document.body.classList.toggle('ek-light', theme === 'light');
    if (themeToggle) {
      themeToggle.querySelectorAll('button').forEach((b) => {
        b.classList.toggle('is-active', b.dataset.theme === theme);
      });
    }
    try { localStorage.setItem('ek-theme', theme); } catch (e) {}
  };
  // Aplicar tema guardado o "light" (default según handoff)
  const savedTheme = (function () {
    try { return localStorage.getItem('ek-theme'); } catch (e) { return null; }
  })();
  setTheme(savedTheme || 'light');

  if (themeToggle) {
    themeToggle.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-theme]');
      if (!btn) return;
      setTheme(btn.dataset.theme);
    });
  }

  // ═══════════ Scroll reveal (Intersection Observer) ═══════════
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            obs.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -60px 0px' }
    );
    document.querySelectorAll('.reveal').forEach((el) => obs.observe(el));
  } else {
    // Fallback: mostrar todo
    document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
  }

  // ═══════════ Countdown de oferta flash ═══════════
  // Busca elementos [data-countdown="ISO_DATETIME"] y actualiza spans hijos
  // con clase .ek-cd-d, .ek-cd-h, .ek-cd-m, .ek-cd-s
  const countdowns = document.querySelectorAll('[data-countdown]');
  if (countdowns.length > 0) {
    const tick = () => {
      const now = Date.now();
      countdowns.forEach((root) => {
        const target = new Date(root.dataset.countdown).getTime();
        let diff = Math.max(0, Math.floor((target - now) / 1000));
        const days = Math.floor(diff / 86400); diff -= days * 86400;
        const hours = Math.floor(diff / 3600); diff -= hours * 3600;
        const mins = Math.floor(diff / 60); diff -= mins * 60;
        const secs = diff;
        const set = (sel, v) => {
          const el = root.querySelector(sel);
          if (el) el.textContent = String(v).padStart(2, '0');
        };
        set('.ek-cd-d', days);
        set('.ek-cd-h', hours);
        set('.ek-cd-m', mins);
        set('.ek-cd-s', secs);
      });
    };
    tick();
    setInterval(tick, 1000);
  }

  // ═══════════ Atajo de teclado ⌘K / Ctrl+K → focus búsqueda ═══════════
  document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      const search = document.querySelector('.ek-search');
      if (search) {
        search.scrollIntoView({ behavior: 'smooth', block: 'center' });
        search.style.outline = '2px solid var(--ek-gold)';
        setTimeout(() => { search.style.outline = ''; }, 600);
      }
    }
  });
})();
