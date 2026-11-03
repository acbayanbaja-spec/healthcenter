</main></div>
<?php if (in_array(role(), ['admin', 'staff'], true)): ?>
<div id="pulse" data-csrf="<?= h($_SESSION['csrf'] ?? '') ?>" data-name="<?= h(explode(' ', $_SESSION['user'] ?? 'there')[0]) ?>">
  <button id="pulse-btn" type="button" aria-expanded="false" aria-controls="pulse-panel" title="Ask Pulse">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/><path d="M7 10.5h2l1.5-2.5 2 5 1.5-2.5h3"/></svg>
    <span class="sr">Ask Pulse</span>
  </button>
  <section id="pulse-panel" aria-label="Pulse assistant" hidden>
    <header><span class="av">P</span><div><b>Pulse</b><small>Health Center assistant</small></div>
      <button id="pulse-close" type="button" aria-label="Close assistant">✕</button></header>
    <div id="pulse-log" aria-live="polite"></div>
    <div id="pulse-chips">
      <button type="button" data-q="Today's visits">Today's visits</button>
      <button type="button" data-q="Book a visit">Book a visit</button>
      <button type="button" data-q="Reschedule a visit">Reschedule</button>
      <button type="button" data-q="Cancel a visit">Cancel a visit</button>
      <button type="button" data-fill="Find patient ">Find a patient</button>
      <button type="button" data-q="How do I export a report?">Export a report</button>
    </div>
    <form id="pulse-form" autocomplete="off">
      <input id="pulse-input" maxlength="500" placeholder="Ask Pulse…" aria-label="Message Pulse">
      <button>Send</button>
    </form>
    <p class="pnote">Pulse helps with the system. It can't give medical advice.</p>
  </section>
</div>
<script src="assistant.js"></script>
<?php endif; ?>
</body></html>
