(function(){
  // If already initialized, bail out (idempotent load)
  if (window.Fresh && window.Fresh.__version === '1.2.0') return;

  // ---- shared global state (ONE pool for the whole app) ----
  const GLOBAL = window.__FRESH_GLOBAL__ = window.__FRESH_GLOBAL__ || {
    xhrPool: [],
    ajaxTrackingEnabled: false
  };

  // Default no-cache headers
  let defaultHeaders = {
    'Cache-Control': 'no-store, no-cache, must-revalidate, max-age=0',
    'Pragma': 'no-cache',
    'Expires': '0'
  };

  // ---- helpers ----
  function bust(url){
    try {
      const u = new URL(url, location.origin);
      u.searchParams.set('_r', Date.now().toString(36) + Math.random().toString(36).slice(2));
      return u.toString();
    } catch(e){
      const sep = url.includes('?') ? '&' : '?';
      return url + sep + '_r=' + Date.now().toString(36) + Math.random().toString(36).slice(2);
    }
  }

  async function clearWebCaches(){
    try {
      if ('caches' in window && caches.keys) {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => caches.delete(k)));
      }
    } catch(e){}
    try { performance.clearResourceTimings(); } catch(e){}
  }

  async function nukeServiceWorkers(){
    try {
      if (!('serviceWorker' in navigator)) return;
      const regs = await navigator.serviceWorker.getRegistrations();
      await Promise.all(regs.map(r => r.unregister()));
    } catch(e){}
  }

  function abortAll(){
    try {
      GLOBAL.xhrPool.forEach(x=>{ try{x.abort()}catch(e){} });
      GLOBAL.xhrPool.length = 0;
    } catch(e){}
  }

  function setNoCacheHeaders(hdrs){
    defaultHeaders = { ...defaultHeaders, ...(hdrs || {}) };
  }

  // Enable ONE global jQuery XHR tracker (idempotent)
  function enableGlobalXHRTracking(){
    if (GLOBAL.ajaxTrackingEnabled) return;
    if (typeof $ === 'undefined' || !$.ajaxSetup) return;
    $.ajaxSetup({
      cache: false,
      beforeSend: function (jqXHR) { GLOBAL.xhrPool.push(jqXHR); },
      complete:  function (jqXHR) {
        const i = GLOBAL.xhrPool.indexOf(jqXHR);
        if (i > -1) GLOBAL.xhrPool.splice(i, 1);
      }
    });
    GLOBAL.ajaxTrackingEnabled = true;
  }

  // ---- HTML (jQuery) ----
  function getHTMLFresh(url){
    if (typeof $ === 'undefined') throw new Error('fresh-loader: jQuery is required for getHTMLFresh()');
    url = bust(url);
    return $.ajax({
      url,
      method: 'GET',
      dataType: 'html',
      cache: false,
      headers: defaultHeaders
    });
  }

  // ---- JSON (fetch) ----
  async function getJSONFresh(url, signal){
    url = bust(url);
    const res = await fetch(url, {
      cache: 'reload',
      headers: defaultHeaders,
      credentials: 'same-origin',
      signal
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
  }

  async function postJSONFresh(url, body, signal){
    url = bust(url);
    const res = await fetch(url, {
      method: 'POST',
      cache: 'reload',
      headers: { 'Content-Type': 'application/json', ...defaultHeaders },
      credentials: 'same-origin',
      body: JSON.stringify(body || {}),
      signal
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
  }

  // ---- one-liners used everywhere ----
  async function loadFragmentFresh($target, url, spinnerHTML){
    if (!$target || !$target.length) throw new Error('fresh-loader: target missing');
    $target.html(spinnerHTML || '<div class="text-center p-4">Loading...</div>');
    abortAll();                 // kill any in-flight XHRs (shared pool)
    await clearWebCaches();     // wipe caches (best effort)
    const html = await getHTMLFresh(url);
    $target.html(html);
    return html;
  }

  async function hardReload(buildFn){
    abortAll();
    await clearWebCaches();
    return buildFn && buildFn();
  }

  // ---- expose API (singleton) ----
  window.Fresh = {
    __version: '1.2.0',
    // cache control
    clearWebCaches,
    nukeServiceWorkers,
    abortAll,
    setNoCacheHeaders,
    enableGlobalXHRTracking,
    // loaders
    getHTMLFresh,
    getJSONFresh,
    postJSONFresh,
    loadFragmentFresh,
    hardReload,
    // utils
    bust
  };

  // Turn on global XHR tracking once
  enableGlobalXHRTracking();
})();
