/* Runs in <head>: enable motion only when allowed; fail-safe reveals content if site.js never runs. */
(function(d){var h=d.documentElement;h.classList.remove('no-js');h.classList.add('js');
if(!(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)){h.classList.add('mo');window.__moSafe=setTimeout(function(){h.classList.remove('mo')},4000);}})(document);
