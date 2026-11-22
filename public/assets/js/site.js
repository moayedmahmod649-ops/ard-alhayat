/* Ard Al-Hayat — site behaviour. Vanilla, no dependencies; every block fails soft. */
(() => {
  const d = document, h = d.documentElement;
  if (window.__moSafe) clearTimeout(window.__moSafe);
  const $ = (s, r = d) => r.querySelector(s), $$ = (s, r = d) => [...r.querySelectorAll(s)];
  const rmq = matchMedia('(prefers-reduced-motion: reduce)');
  const fine = matchMedia('(hover: hover) and (pointer: fine)');
  const rtl = h.dir === 'rtl';
  rmq.addEventListener && rmq.addEventListener('change', e => { if (e.matches) h.classList.remove('mo'); });

  // header: solid after scrolling past the hero top
  const head = $('.site-head');
  const onScroll = () => head && head.classList.toggle('scrolled', scrollY > 40);
  addEventListener('scroll', onScroll, { passive: true }); onScroll();

  // mobile nav
  const tog = $('.nav-toggle'), nav = $('#main-nav');
  if (tog && nav) {
    $$('.nav-list li', nav).forEach((li, i) => li.style.setProperty('--i', i));
    const set = open => { tog.setAttribute('aria-expanded', open); nav.classList.toggle('open', open); head.classList.toggle('menu-open', open); d.body.style.overflow = open ? 'hidden' : ''; };
    tog.addEventListener('click', () => set(tog.getAttribute('aria-expanded') !== 'true'));
    d.addEventListener('keydown', e => { if (e.key === 'Escape' && nav.classList.contains('open')) { set(false); tog.focus(); } });
    matchMedia('(min-width: 1201px)').addEventListener('change', e => e.matches && set(false));
  }

  // reveal once in view
  const rev = $$('.reveal');
  if (h.classList.contains('mo') && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }), { rootMargin: '0px 0px -8% 0px', threshold: .08 });
    rev.forEach((el, i) => { el.style.transitionDelay = (i % 3) * 90 + 'ms'; io.observe(el); });
  } else rev.forEach(el => el.classList.add('in'));

  // hero slider: autoplay 7s with progress; pauses on hover/focus; arrow keys + swipe; no autoplay with reduced motion
  const hero = $('[data-hero]');
  if (hero) {
    const slides = $$('.hero-slide', hero), dots = $$('.dot', hero), cur = $('.hero-count .cur', hero), tg = $('[data-toggle]', hero);
    const MS = 7000; let i = 0, timer = 0, paused = rmq.matches, hold = false;
    hero.style.setProperty('--hero-ms', MS + 'ms');
    const go = n => {
      if (!slides.length) return;
      i = (n + slides.length) % slides.length;
      slides.forEach((s, k) => { const on = k === i; s.classList.toggle('is-active', on); s.setAttribute('aria-hidden', !on); $$('a', s).forEach(a => on ? a.removeAttribute('tabindex') : a.setAttribute('tabindex', '-1')); });
      dots.forEach((dt, k) => { dt.classList.toggle('is-active', k === i); k === i ? dt.setAttribute('aria-current', 'true') : dt.removeAttribute('aria-current'); const b = $('.bar i', dt); if (b) { b.style.animation = 'none'; void b.offsetWidth; b.style.animation = ''; } });
      if (cur) cur.textContent = String(i + 1).padStart(2, '0');
      schedule();
    };
    const schedule = () => { clearTimeout(timer); if (!paused && !hold && slides.length > 1) timer = setTimeout(() => go(i + 1), MS); };
    const state = () => { hero.classList.toggle('paused', paused || hold); hero.classList.toggle('playing', !paused && !hold); if (tg) tg.setAttribute('aria-label', paused ? tg.dataset.lPlay : tg.dataset.lPause); };
    dots.forEach(dt => dt.addEventListener('click', () => go(+dt.dataset.go)));
    $('[data-next]', hero)?.addEventListener('click', () => go(i + 1));
    $('[data-prev]', hero)?.addEventListener('click', () => go(i - 1));
    rmq.addEventListener?.('change', () => { paused = rmq.matches; state(); schedule(); });
    hero.addEventListener('keydown', e => { if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' || e.target.closest('input,textarea')) return; e.preventDefault(); go(i + ((e.key === 'ArrowLeft') === rtl ? 1 : -1)); });
    if (fine.matches) { hero.addEventListener('pointerenter', () => { hold = true; state(); clearTimeout(timer); }); hero.addEventListener('pointerleave', () => { hold = false; state(); schedule(); }); }
    hero.addEventListener('focusin', () => { hold = true; state(); clearTimeout(timer); });
    hero.addEventListener('focusout', e => { if (!hero.contains(e.relatedTarget)) { hold = false; state(); schedule(); } });
    let sx = 0; hero.addEventListener('touchstart', e => sx = e.touches[0].clientX, { passive: true });
    hero.addEventListener('touchend', e => { const dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 50) go(i + ((dx < 0) !== rtl ? 1 : -1)); }, { passive: true });
    d.addEventListener('visibilitychange', () => d.hidden ? clearTimeout(timer) : schedule());
    state(); schedule();
  }

  // stats count-up (once); final value is in the DOM from the start for assistive tech
  const counts = $$('.count[data-count]');
  if (counts.length && h.classList.contains('mo') && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(es => es.forEach(en => {
      if (!en.isIntersecting) return; io.unobserve(en.target);
      const el = en.target, final = el.textContent, target = parseInt(el.dataset.count, 10);
      if (!(target > 0)) return;
      el.setAttribute('aria-label', final); const t0 = performance.now(), dur = 1600;
      const step = t => { const p = Math.min(1, (t - t0) / dur), v = Math.round(target * (1 - Math.pow(1 - p, 3))); el.textContent = p < 1 ? v.toLocaleString('en') : final; if (p < 1) requestAnimationFrame(step); };
      requestAnimationFrame(step);
    }), { threshold: .4 });
    counts.forEach(c => io.observe(c));
  }

  // edge light follows the pointer (desktop only)
  if (fine.matches) $$('.edge').forEach(c => { let raf = 0; c.addEventListener('pointermove', e => { if (raf) return; raf = requestAnimationFrame(() => { raf = 0; const r = c.getBoundingClientRect(); c.style.setProperty('--mx', (e.clientX - r.left) + 'px'); c.style.setProperty('--my', (e.clientY - r.top) + 'px'); }); }); });

  // partners: marquee only when logos overflow the row; clones are inert; explicit pause button
  $$('[data-marquee]').forEach(row => {
    const strip = $('.logo-strip', row); if (!strip || !h.classList.contains('mo')) return;
    const items = [...strip.children]; if (items.length < 4 || items.length * 216 <= row.clientWidth + 40) return;
    items.forEach(li => { const c = li.cloneNode(true); c.setAttribute('aria-hidden', 'true'); c.inert = true; $$('a', c).forEach(a => a.tabIndex = -1); strip.appendChild(c); });
    row.classList.add('mq'); row.style.setProperty('--mq-sign', rtl ? 1 : -1); row.style.setProperty('--mq-dur', Math.max(30, items.length * 7) + 's');
    const en = h.lang === 'en', b = d.createElement('button'); b.type = 'button'; b.className = 'btn btn-outline mq-btn';
    const lab = p => { b.textContent = p ? (en ? 'Play logos' : 'تشغيل الشريط') : (en ? 'Pause logos' : 'إيقاف الشريط'); b.setAttribute('aria-pressed', p); };
    lab(false); b.addEventListener('click', () => { const p = !row.classList.contains('paused'); row.classList.toggle('paused', p); lab(p); });
    row.after(b);
  });

  // lightbox for images and videos (Escape closes, focus returns to the opener)
  const lb = $('#lightbox');
  if (lb) {
    const media = $('.lb-media', lb), cap = $('.lb-cap', lb); let group = [], idx = 0, opener = null;
    const show = k => {
      idx = (k + group.length) % group.length; const a = group[idx]; media.textContent = '';
      if (a.dataset.lb === 'video') { const f = d.createElement('iframe'); f.src = a.dataset.embed; f.title = a.dataset.cap || 'video'; f.allow = 'autoplay; encrypted-media; picture-in-picture'; f.allowFullscreen = true; media.appendChild(f); }
      else { const im = new Image(); im.src = a.getAttribute('href'); im.alt = a.dataset.cap || ''; media.appendChild(im); }
      cap.textContent = a.dataset.cap || ''; $('.lb-prev', lb).hidden = $('.lb-next', lb).hidden = group.length < 2;
    };
    const close = () => { lb.hidden = true; lb.classList.remove('show'); media.textContent = ''; d.body.style.overflow = ''; opener && opener.focus(); };
    d.addEventListener('click', e => {
      const a = e.target.closest('a[data-lb]'); if (!a) return; e.preventDefault();
      const g = a.closest('[data-lightbox-group]'); group = g ? $$('a[data-lb]', g) : [a]; opener = a;
      lb.hidden = false; requestAnimationFrame(() => lb.classList.add('show')); d.body.style.overflow = 'hidden'; show(group.indexOf(a)); $('.lb-close', lb).focus();
    });
    $('.lb-close', lb).addEventListener('click', close);
    $('.lb-prev', lb).addEventListener('click', () => show(idx - 1));
    $('.lb-next', lb).addEventListener('click', () => show(idx + 1));
    lb.addEventListener('click', e => { if (e.target === lb) close(); });
    d.addEventListener('keydown', e => {
      if (lb.hidden) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowRight') show(idx + (rtl ? -1 : 1));
      if (e.key === 'ArrowLeft') show(idx + (rtl ? 1 : -1));
      if (e.key === 'Tab') { const f = $$('button:not([hidden])', lb); const first = f[0], last = f[f.length - 1]; if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); } }
    });
  }

  // copy link
  $$('.copy-link').forEach(b => b.addEventListener('click', async () => {
    const st = b.closest('.share')?.querySelector('.copy-status');
    try { await navigator.clipboard.writeText(b.dataset.url); if (st) st.textContent = b.dataset.done; } catch (e) { if (st) st.textContent = b.dataset.url; }
  }));

  // click-to-load map (no third-party request until asked)
  $$('.map-load').forEach(b => b.addEventListener('click', () => { const f = d.createElement('iframe'); f.src = b.dataset.map; f.title = b.dataset.title; f.loading = 'lazy'; f.referrerPolicy = 'no-referrer-when-downgrade'; b.replaceWith(f); }));

  // move focus to form feedback
  const fb = $('[data-focus]'); if (fb) { fb.scrollIntoView({ block: 'center' }); fb.focus({ preventScroll: true }); }
})();
