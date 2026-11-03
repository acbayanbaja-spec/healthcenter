(function () {
  var k = 'hcTheme', d = document.documentElement, saved = null;
  try { saved = localStorage.getItem(k); } catch (e) {}
  d.setAttribute('data-theme', saved || (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
  var MOON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8 8 0 019.5 4 8 8 0 1020 14.5z"/></svg>';
  var SUN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
  document.addEventListener('DOMContentLoaded', function () {
    var b = document.getElementById('themeBtn'); if (!b) return;
    function paint() { var dk = d.getAttribute('data-theme') === 'dark'; b.innerHTML = dk ? SUN : MOON; b.setAttribute('aria-label', dk ? 'Switch to light mode' : 'Switch to dark mode'); b.title = b.getAttribute('aria-label'); }
    b.onclick = function () { var n = d.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'; d.setAttribute('data-theme', n); try { localStorage.setItem(k, n); } catch (e) {} paint(); };
    paint();
  });
})();
