(function () {
  var root = document.getElementById('pulse'); if (!root) return;
  var btn = document.getElementById('pulse-btn'), panel = document.getElementById('pulse-panel'),
      log = document.getElementById('pulse-log'), form = document.getElementById('pulse-form'),
      input = document.getElementById('pulse-input'), chips = document.getElementById('pulse-chips');
  var csrf = root.dataset.csrf, name = root.dataset.name;
  var msgs = [], hist = [];
  try { msgs = JSON.parse(sessionStorage.getItem('pulseMsgs') || '[]'); hist = JSON.parse(sessionStorage.getItem('pulseHist') || '[]'); } catch (e) {}
  function save() { try { sessionStorage.setItem('pulseMsgs', JSON.stringify(msgs.slice(-40))); sessionStorage.setItem('pulseHist', JSON.stringify(hist.slice(-8))); } catch (e) {} }
  var out = document.querySelector('a[href="logout.php"]');
  if (out) out.addEventListener('click', function () { sessionStorage.removeItem('pulseMsgs'); sessionStorage.removeItem('pulseHist'); });

  function fmt(t) {
    t = t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    t = t.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
    t = t.replace(/\[([^\]]+)\]\(([a-z_]+\.php(?:\?[A-Za-z0-9_=%&.+-]*)?)\)/g, '<a href="$2">$1</a>');
    return t.replace(/\n/g, '<br>');
  }
  function add(role, text, skipSave) {
    var d = document.createElement('div'); d.className = 'pm ' + role; d.innerHTML = fmt(text);
    log.appendChild(d); log.scrollTop = log.scrollHeight;
    if (!skipSave) { msgs.push({ r: role, t: text }); save(); }
    return d;
  }
  function open(on) {
    panel.hidden = !on; btn.setAttribute('aria-expanded', on); btn.classList.toggle('open', on);
    if (on) { input.focus(); log.scrollTop = log.scrollHeight; }
    try { sessionStorage.setItem('pulseOpen', on ? '1' : '0'); } catch (e) {}
  }
  function send(text) {
    text = text.trim(); if (!text) return;
    add('user', text); input.value = ''; chips.hidden = true;
    var typing = add('bot typing', '<span class="dots"><i></i><i></i><i></i></span>', true); typing.innerHTML = '<span class="dots"><i></i><i></i><i></i></span>';
    fetch('chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ csrf: csrf, message: text, history: hist }) })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        typing.remove(); add('bot', j.reply);
        if (j.source === 'ai') { hist.push({ role: 'user', content: text }, { role: 'assistant', content: j.reply }); save(); }
      })
      .catch(function () { typing.remove(); add('bot', 'I could not reach the server. Check that XAMPP is running and try again.'); });
  }

  if (msgs.length) { msgs.forEach(function (m) { add(m.r, m.t, true); }); chips.hidden = true; }
  else { add('bot', 'Hi ' + name + '! I\'m Pulse. Ask me about today\'s visits, finding a patient, or how to use any page.', true); }

  btn.addEventListener('click', function () { open(panel.hidden); });
  document.getElementById('pulse-close').addEventListener('click', function () { open(false); btn.focus(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { open(false); btn.focus(); } });
  form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
  chips.addEventListener('click', function (e) {
    var c = e.target.closest('button'); if (!c) return;
    if (c.dataset.fill) { input.value = c.dataset.fill; input.focus(); } else send(c.dataset.q);
  });
  if (sessionStorage.getItem('pulseOpen') === '1') open(true);
})();
