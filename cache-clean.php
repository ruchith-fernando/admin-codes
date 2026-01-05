<?php
// Dynamically detect the PHP session cookie name
if (session_status() === PHP_SESSION_NONE) session_start();
$session_cookie = session_name();
?>
<script>
(function() {
  // --- Disable AJAX caching globally ---
  if (window.jQuery) {
    $.ajaxSetup({ cache: false });
  }

  // --- Prevent fetch() from using cache ---
  const originalFetch = window.fetch;
  window.fetch = function(resource, config = {}) {
    config.cache = 'no-store';
    config.headers = new Headers(config.headers || {});
    config.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    config.headers.set('Pragma', 'no-cache');
    config.headers.set('Expires', '0');
    return originalFetch(resource, config);
  };

  // --- Clear cookies except for session/login ones ---
  function clearCookiesExcept(keepList = []) {
    const cookies = document.cookie.split(';');
    for (const cookie of cookies) {
      const [name] = cookie.split('=');
      const cname = name.trim();
      if (!keepList.includes(cname)) {
        document.cookie = cname + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
      }
    }
  }

  // Automatically keep PHP session cookie and optional login token
  clearCookiesExcept(['<?= $session_cookie ?>', 'login_token']);

  // --- Clear cache storage on page reload ---
  if (performance.navigation.type === performance.navigation.TYPE_RELOAD) {
    if ('caches' in window) {
      caches.keys().then(names => names.forEach(name => caches.delete(name)));
    }
  }

  console.log('✅ AJAX cache and cookies cleared except session (<?= $session_cookie ?>).');
})();
</script>
