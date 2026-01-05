(function() {
  // --- Clear AJAX / fetch cache ---
  // Disable caching globally for jQuery AJAX
  if (window.jQuery) {
    $.ajaxSetup({ cache: false });
  }

  // For browsers that cache aggressively, set cache control headers
  const noCacheHeaders = new Headers({
    'Cache-Control': 'no-store, no-cache, must-revalidate, max-age=0',
    'Pragma': 'no-cache',
    'Expires': '0'
  });

  // Optional: override fetch() to prevent cached responses
  const originalFetch = window.fetch;
  window.fetch = function(resource, config = {}) {
    config.cache = 'no-store';
    config.headers = new Headers(config.headers || {});
    noCacheHeaders.forEach((v, k) => config.headers.set(k, v));
    return originalFetch(resource, config);
  };

  // --- Clear all cookies except login/session ---
  function clearCookiesExcept(keepList = []) {
    document.cookie.split(';').forEach(cookie => {
      const [name] = cookie.split('=');
      const trimmedName = name.trim();
      if (!keepList.includes(trimmedName)) {
        document.cookie = `${trimmedName}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;`;
      }
    });
  }

  clearCookiesExcept(['PHPSESSID', 'login_token']);
  if (performance.navigation.type === performance.navigation.TYPE_RELOAD) {
    if ('caches' in window) {
      caches.keys().then(names => names.forEach(name => caches.delete(name)));
    }
  }

  console.log('✅ Cache and cookies cleared except login session.');
})();

