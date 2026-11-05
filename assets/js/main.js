(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = ($('meta[name=csrf]') || {}).content || '';

  // Sidebar toggle: overlay on small screens, collapsible on desktop.
  var sb = $('#sidebar'), scrim = $('#scrim');
  var mb = $('#menuBtn');
  function isMobileSidebar() { return window.matchMedia('(max-width: 860px)').matches; }
  function toggleSb(open) {
    if (!sb) return;
    if (isMobileSidebar()) {
      sb.classList.toggle('open', open);
      if (scrim) scrim.classList.toggle('show', open);
    } else {
      sb.classList.remove('open');
      if (scrim) scrim.classList.remove('show');
      document.body.classList.toggle('sidebar-collapsed', !open);
      try { localStorage.setItem('tms-sidebar-collapsed', open ? '0' : '1'); } catch (e) {}
    }
    if (mb) mb.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  function syncSidebar() {
    if (!sb) return;
    if (isMobileSidebar()) {
      sb.classList.remove('open');
      if (scrim) scrim.classList.remove('show');
      if (mb) mb.setAttribute('aria-expanded', 'false');
    } else {
      sb.classList.remove('open');
      if (scrim) scrim.classList.remove('show');
      var collapsed = false;
      try { collapsed = localStorage.getItem('tms-sidebar-collapsed') === '1'; } catch (e) {}
      document.body.classList.toggle('sidebar-collapsed', collapsed);
      if (mb) mb.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
  }
  if (mb) mb.addEventListener('click', function () {
    toggleSb(isMobileSidebar() ? !sb.classList.contains('open') : document.body.classList.contains('sidebar-collapsed'));
  });
  if (scrim) scrim.addEventListener('click', function () { toggleSb(false); });
  syncSidebar();
  window.addEventListener('resize', syncSidebar);

  // dark mode
  var tb = $('#themeBtn');
  if (tb) tb.addEventListener('click', function () {
    var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', cur);
    try { localStorage.setItem('tms-theme', cur); } catch (e) {}
  });

  // live date & time, full screen, user menu, Ctrl+K search
  var clk = $('#clockTxt'), dt = $('#dateTxt');
  function tick() {
    var d = new Date();
    if (clk) clk.textContent = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    if (dt) dt.textContent = d.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }).replace(',', '');
  }
  if (clk) { tick(); setInterval(tick, 20000); }
  var fs = $('#fsBtn');
  if (fs) fs.addEventListener('click', function () { if (!document.fullscreenElement) document.documentElement.requestFullscreen && document.documentElement.requestFullscreen(); else document.exitFullscreen(); });
  var um = $('#userMenu');
  if (um) { $('.um-btn', um).addEventListener('click', function (e) { e.stopPropagation(); um.classList.toggle('open'); }); document.addEventListener('click', function () { um.classList.remove('open'); }); }
  document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); var g = $('#gsearch'); if (g) g.focus(); } });

  // confirm dialogs, print, auto-submit controls
  $$('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  $$('[data-autosubmit]').forEach(function (c) { c.addEventListener('change', function () { c.form.submit(); }); });

  // flash auto-dismiss
  var fl = $('.alert'); if (fl) setTimeout(function () { fl.classList.add('hide'); }, 5000);

  // password show/hide
  $$('[data-toggle-pass]').forEach(function (b) { b.addEventListener('click', function () { var i = $(b.dataset.togglePass); i.type = i.type === 'password' ? 'text' : 'password'; }); });

  // Kanban drag & drop
  var board = $('#board');
  if (board) {
    var dragging = null, base = board.dataset.move;
    $$('.kcard', board).forEach(function (c) {
      c.addEventListener('dragstart', function (e) {
        dragging = c; c.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', c.dataset.id);
        var ok = (c.dataset.allowed || '').split('|');
        $$('.kcol', board).forEach(function (col) { col.classList.toggle('ok', ok.indexOf(col.dataset.status) > -1); });
      });
      c.addEventListener('dragend', function () { c.classList.remove('drag'); $$('.kcol', board).forEach(function (col) { col.classList.remove('ok', 'over'); }); });
    });
    $$('.kcol', board).forEach(function (col) {
      col.addEventListener('dragover', function (e) { if (dragging && col.classList.contains('ok')) { e.preventDefault(); col.classList.add('over'); } });
      col.addEventListener('dragleave', function () { col.classList.remove('over'); });
      col.addEventListener('drop', function (e) {
        e.preventDefault(); col.classList.remove('over');
        if (!dragging || !col.classList.contains('ok')) return;
        var card = dragging, fd = new FormData(); fd.append('csrf', csrf); fd.append('id', card.dataset.id); fd.append('status', col.dataset.status);
        fetch(base, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) { alert(j.error || 'Could not move the task.'); return; }
          $('.kbody', col).prepend(card); card.dataset.allowed = j.allowed.join('|'); card.dataset.status = col.dataset.status;
          var b = $('.badge', card); if (b) { b.textContent = j.label; b.className = 'badge b-' + j.label.toLowerCase().replace(' ', '-'); }
          $$('.kcol', board).forEach(function (cc) { $('.kcount', cc).textContent = $$('.kcard', cc).length; });
          card.draggable = j.allowed.length > 0;
        }).catch(function () { alert('Network error. Please try again.'); });
      });
    });
  }
})();
