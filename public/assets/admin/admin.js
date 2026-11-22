/* Ard Al-Hayat admin — progressive enhancement only (every form works without JS). */
(() => {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const ar = document.documentElement.lang === 'ar';
  /* client-side mirror of the server allowlist — used before any HTML is put into the editor */
  const OK = new Set(['P','BR','STRONG','B','EM','I','U','H2','H3','UL','OL','LI','BLOCKQUOTE','A']);
  const cleanFrag = htmlStr => {
    const doc = new DOMParser().parseFromString('<body>' + String(htmlStr || ''), 'text/html'); const out = document.createDocumentFragment();
    const walk = (src, dst) => src.childNodes.forEach(n => {
      if (n.nodeType === 3) { dst.append(document.createTextNode(n.textContent)); return; }
      if (n.nodeType !== 1) return;
      if (['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','SVG','MATH','TEMPLATE','NOSCRIPT','FORM','INPUT','BUTTON','TEXTAREA','SELECT'].includes(n.tagName)) return;
      if (!OK.has(n.tagName)) { walk(n, dst); return; }
      const el = document.createElement(n.tagName.toLowerCase());
      if (n.tagName === 'A') { const h = n.getAttribute('href') || ''; if (/^(https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i.test(h.trim())) el.setAttribute('href', h.trim()); }
      walk(n, el); dst.append(el);
    });
    walk(doc.body, out); return out;
  };
  const uid = document.body.dataset.u || 'anon';
  try { for (let i = localStorage.length - 1; i >= 0; i--) { const k = localStorage.key(i); if (k && k.startsWith('ah-draft:')) { const v = JSON.parse(localStorage.getItem(k) || 'null'); if (!v || Date.now() - v.at > 7 * 864e5) localStorage.removeItem(k); } } } catch {}
  /* side nav (mobile) */
  const tog = $('.menu-toggle'), side = $('#side'), bd = $('.side-backdrop');
  const setNav = o => { if (!side) return; side.classList.toggle('open', o); tog && tog.setAttribute('aria-expanded', o); if (bd) bd.hidden = !o; };
  tog && tog.addEventListener('click', () => setNav(!side.classList.contains('open')));
  bd && bd.addEventListener('click', () => setNav(false));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setNav(false); });
  /* confirm */
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
  /* auto-submit selects */
  $$('select[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form.requestSubmit ? s.form.requestSubmit() : s.form.submit()));
  /* tabs */
  $$('[data-tabs]').forEach(tl => {
    const tabs = $$('[role=tab]', tl);
    const sel = t => tabs.forEach(x => { const on = x === t; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1; const p = document.getElementById(x.getAttribute('aria-controls')); if (p) p.hidden = !on; });
    tabs.forEach((t, i) => {
      t.addEventListener('click', () => sel(t));
      t.addEventListener('keydown', e => { const k = e.key; if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(k)) return; e.preventDefault();
        let n = k === 'Home' ? 0 : k === 'End' ? tabs.length - 1 : i + ((k === 'ArrowRight') !== ar ? 1 : -1); n = (n + tabs.length) % tabs.length; tabs[n].focus(); sel(tabs[n]); });
    });
    // show the tab containing the first error
    const errP = $$('[role=tabpanel]').find(p => $('[aria-invalid=true]', p)); if (errP) { const t = tabs.find(x => x.getAttribute('aria-controls') === errP.id); t && sel(t); }
  });
  /* file inputs: show chosen names */
  // large photos are shrunk in the browser first (max 2400px, JPEG 0.9); the server still validates and re-encodes everything
  const shrink = f => new Promise(res => {
    if (!/^image\/(jpeg|png|webp)$/.test(f.type) || f.size < 1.2e6 || !window.createImageBitmap) return res(f);
    createImageBitmap(f, { imageOrientation: 'from-image' }).then(bm => {
      const k = Math.min(1, 2400 / Math.max(bm.width, bm.height)); const c = document.createElement('canvas');
      c.width = Math.round(bm.width * k); c.height = Math.round(bm.height * k); c.getContext('2d').drawImage(bm, 0, 0, c.width, c.height);
      const png = f.type === 'image/png' && f.size < 3e6; // keep transparency for logos
      c.toBlob(b => res(b && b.size < f.size ? new File([b], f.name.replace(/\.\w+$/, png ? '.png' : '.jpg'), { type: png ? 'image/png' : 'image/jpeg' }) : f), png ? 'image/png' : 'image/jpeg', .9);
    }).catch(() => res(f));
  });
  $$('.file-in').forEach(i => i.addEventListener('change', async () => {
    let o = i.nextElementSibling; if (!o || !o.classList.contains('file-name')) { o = document.createElement('p'); o.className = 'file-name'; o.setAttribute('aria-live', 'polite'); i.after(o); }
    o.textContent = [...i.files].map(f => f.name).join('، ');
    if (!window.DataTransfer || ![...i.files].some(f => f.type.startsWith('image/') && f.size >= 1.2e6)) return;
    i.dataset.busy = '1';
    try { const dt = new DataTransfer(); for (const f of await Promise.all([...i.files].map(shrink))) dt.items.add(f); i.files = dt.files; } catch (e) {}
    delete i.dataset.busy;
  }));
  // serverless hosting caps one request at ~4.5 MB: stop early with a clear message instead of a failed upload
  const maxReq = +(document.body.dataset.maxReq || 0);
  if (maxReq) $$('form[enctype="multipart/form-data"]').forEach(f => f.addEventListener('submit', e => {
    if ($$('.file-in', f).some(i => i.dataset.busy)) { e.preventDefault(); alert(document.documentElement.lang === 'en' ? 'Preparing the photos… try again in a second.' : 'جارٍ تجهيز الصور… أعد المحاولة بعد لحظة.'); return; }
    const total = $$('input[type=file]', f).reduce((n, i) => n + [...i.files].reduce((m, x) => m + x.size, 0), 0);
    if (total > maxReq) { e.preventDefault(); alert(document.documentElement.lang === 'en' ? 'The selected files are too large to send at once (max about 4 MB). Upload fewer files at a time.' : 'حجم الملفات المختارة أكبر من المسموح في المرة الواحدة (نحو 4 ميغابايت). ارفع عددًا أقل من الملفات في كل مرة.'); }
  }));
  /* copy link */
  $$('[data-copy]').forEach(b => b.addEventListener('click', async () => {
    const src = document.getElementById(b.dataset.copy); if (!src) return;
    try { await navigator.clipboard.writeText(src.value); } catch { src.select(); document.execCommand && document.execCommand('copy'); }
    const old = b.innerHTML; b.textContent = b.dataset.done || '✓'; setTimeout(() => (b.innerHTML = old), 1500);
  }));
  /* sortable gallery (drag + keyboard Alt+Arrow) */
  $$('[data-sortable]').forEach(list => {
    let drag = null;
    $$('li', list).forEach(li => { li.draggable = true; li.tabIndex = 0; });
    list.addEventListener('dragstart', e => { drag = e.target.closest('li'); drag && drag.classList.add('dragging'); });
    list.addEventListener('dragend', () => { drag && drag.classList.remove('dragging'); drag = null; });
    list.addEventListener('dragover', e => { e.preventDefault(); const over = e.target.closest('li'); if (!drag || !over || over === drag) return;
      const r = over.getBoundingClientRect(); const after = (e.clientY - r.top) > r.height / 2 || (e.clientX - r.left) > r.width / 2; over[after ? 'after' : 'before'](drag); });
    list.addEventListener('keydown', e => { const li = e.target.closest('li'); if (!li || !e.altKey) return;
      if (['ArrowUp', 'ArrowLeft'].includes(e.key) && li.previousElementSibling) { li.previousElementSibling.before(li); li.focus(); e.preventDefault(); }
      if (['ArrowDown', 'ArrowRight'].includes(e.key) && li.nextElementSibling) { li.nextElementSibling.after(li); li.focus(); e.preventDefault(); } });
  });
  /* minimal rich text editor — output is re-sanitised server-side by the allowlist cleaner */
  $$('[data-rte]').forEach(w => {
    const ta = $('textarea', w); if (!ta || ta.disabled) return;
    const bar = document.createElement('div'); bar.className = 'rte-bar'; bar.setAttribute('role', 'toolbar');
    const ed = document.createElement('div'); ed.className = 'rte-ed'; ed.contentEditable = 'true'; ed.setAttribute('role', 'textbox'); ed.setAttribute('aria-multiline', 'true');
    if (ta.getAttribute('aria-labelledby')) ed.setAttribute('aria-labelledby', ta.getAttribute('aria-labelledby'));
    if (w.dir) ed.dir = w.dir;
    ed.append(cleanFrag(ta.value));
    const L = ar ? { b: 'عريض', i: 'مائل', h2: 'عنوان', h3: 'عنوان فرعي', ul: 'قائمة', ol: 'قائمة مرقمة', q: 'اقتباس', a: 'رابط', p: 'فقرة', link: 'الرابط (https://…)' }
                 : { b: 'Bold', i: 'Italic', h2: 'Heading', h3: 'Subheading', ul: 'List', ol: 'Numbered list', q: 'Quote', a: 'Link', p: 'Paragraph', link: 'Link (https://…)' };
    const B = [['b', 'B', 'bold'], ['i', 'I', 'italic'], ['h2', 'H2', 'formatBlock', 'h2'], ['h3', 'H3', 'formatBlock', 'h3'], ['p', '¶', 'formatBlock', 'p'], ['ul', '•', 'insertUnorderedList'], ['ol', '1.', 'insertOrderedList'], ['q', '❝', 'formatBlock', 'blockquote'], ['a', '🔗', 'createLink']];
    B.forEach(([k, txt, cmd, val]) => { const b = document.createElement('button'); b.type = 'button'; b.textContent = txt; b.title = L[k]; b.setAttribute('aria-label', L[k]);
      b.addEventListener('click', () => { ed.focus(); if (cmd === 'createLink') { const u = prompt(L.link, 'https://'); if (!u || !/^(https?:\/\/|mailto:|\/)/i.test(u)) return; document.execCommand(cmd, false, u); } else document.execCommand(cmd, false, val); sync(); });
      bar.append(b); });
    const sync = () => { ta.value = ed.innerHTML; ta.dispatchEvent(new Event('input', { bubbles: true })); };
    ed.addEventListener('input', sync);
    ed.addEventListener('paste', e => { e.preventDefault(); const t = (e.clipboardData || window.clipboardData).getData('text/plain'); document.execCommand('insertText', false, t); });
    ta.hidden = true; w.prepend(bar); ta.before(ed);
    ta.form && ta.form.addEventListener('submit', sync);
  });
  /* autosave unsaved form drafts in this browser (restored on request) */
  $$('form[data-autosave]').forEach(f => {
    const key = 'ah-draft:' + uid + ':' + f.dataset.autosave; const skip = n => !n || n === '_csrf' || /pw|pass/i.test(n);
    const fields = () => $$('input,textarea,select', f).filter(x => !skip(x.name) && x.type !== 'file' && x.type !== 'hidden' && x.type !== 'password');
    let t; f.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { const d = {}; fields().forEach(x => d[x.name + '#' + x.id] = x.type === 'checkbox' ? x.checked : x.value); try { localStorage.setItem(key, JSON.stringify({ at: Date.now(), d })); } catch {} }, 600); });
    f.addEventListener('submit', () => { clearTimeout(t); t = null; try { localStorage.removeItem(key); } catch {} }, true);
    let saved; try { saved = JSON.parse(localStorage.getItem(key) || 'null'); } catch {}
    if (saved && Date.now() - saved.at < 7 * 864e5) {
      const n = document.createElement('div'); n.className = 'note note-warn'; n.setAttribute('role', 'status');
      const msg = document.createElement('span'); msg.textContent = ar ? 'توجد تعديلات غير محفوظة من جلسة سابقة.' : 'There are unsaved changes from an earlier session.';
      const r = document.createElement('button'); r.type = 'button'; r.className = 'btn btn-sm'; r.textContent = ar ? 'استعادتها' : 'Restore';
      const x = document.createElement('button'); x.type = 'button'; x.className = 'btn btn-sm btn-ghost'; x.textContent = ar ? 'تجاهل' : 'Discard';
      r.onclick = () => { fields().forEach(el => { const v = saved.d[el.name + '#' + el.id]; if (v === undefined) return; if (el.type === 'checkbox') el.checked = v; else el.value = v; const ed = el.closest('[data-rte]') && $('.rte-ed', el.closest('[data-rte]')); if (ed) { ed.replaceChildren(cleanFrag(el.value)); el.value = ed.innerHTML; } }); n.remove(); };
      x.onclick = () => { try { localStorage.removeItem(key); } catch {} n.remove(); };
      n.append(msg, r, x); f.prepend(n);
    }
  });
})();
