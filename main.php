<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Portal</title>

  <!-- Favicons -->
  <link rel="icon" type="image/png" sizes="32x32" href="https://www.cdb.lk/assets/images/fav/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="https://www.cdb.lk/assets/images/fav/favicon-16x16.png">

  <!-- External CSS -->
<!-- 
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/css/bootstrap-datepicker.min.css" rel="stylesheet">  
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="/assets/vendor/select2/select2.min.css" rel="stylesheet"> -->
<link href="assets/css/fontawesome.min.css" rel="stylesheet">
<link href="assets/css/all.min.css" rel="stylesheet">
<link href="assets/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/bootstrap-datepicker.min.css" rel="stylesheet">
<link href="assets/css/select2.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/litepicker/litepicker.css">
<link rel="stylesheet" href="styles.css?v=6">

<!-- <link href="styles.css" rel="stylesheet?v=2"> -->


  <style>
    :root { --sidebar-w: 300px; }
    body { background-color: #f8f9fa; }
    .sidebar {
      width: var(--sidebar-w);
      min-width: var(--sidebar-w);
      background-color: #343a40;
      color: white;
      padding: 10px;
      min-height: 100vh;
      overflow-y: auto;
      overscroll-behavior: contain;
    }
    #contentArea { flex: 1 1 auto; min-width: 0; }
    .sidebar a { color: #ccc; text-decoration: none; display: block; padding: 8px; }
    .sidebar a:hover { color: white; background-color: #d2d8dfff; }
    .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); border: none; }
    .btn-primary:hover { background: linear-gradient(135deg, #5a67d8, #6b46c1); }
    @media (max-width: 991.98px) {
      .sidebar { position: absolute; top: 56px; left: 0; width: var(--sidebar-w); z-index: 1050; background-color: #343a40; }
    }
    .table { font-size: 0.9rem; }
    th, td { white-space: nowrap; padding: 0.5rem 1rem; }

    /* === Full-view overrides that work with your existing pages === */
    /* Many of your inner pages use a .content wrapper with margin-left: var(--sidebar-w); */
    body.sidebar-off .sidebar { display: none !important; }
    body.sidebar-off .content { margin-left: 0 !important; }     /* override inner pages */
    body.sidebar-off #contentArea { margin-left: 0 !important; }  /* safety for any local margin */
  </style>
</head>

<body class="bg-light">
  <!-- Floating Full-View toggle -->
  <button id="fullViewToggle"
          class="btn btn-sm btn-light border shadow position-fixed"
          style="top:12px; right:12px; z-index:1101"
          type="button" title="Full view" aria-label="Toggle full view">
    <i class="fa-solid fa-expand"></i>
  </button>

  <div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar d-none d-lg-block" id="sidebarMenu">
      <?php include 'side-menu.php'; ?>
    </div>

    <!-- Main Content Area -->
    <div id="contentArea" class="container-fluid">
      <div class="text-center text-muted mt-5">
        <h4>Welcome</h4>
        <p>Select a menu item to load content here.</p>
      </div>
    </div>
  </div>

  <!-- External JS -->
  <!-- <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/js/bootstrap-datepicker.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> -->
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->


  <script src="assets/js/jquery-3.7.1.min.js"></script>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/chart.min.js"></script>
  <script src="assets/js/chartjs-plugin-datalabels.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.min.js"></script>
  <script src="assets/litepicker/litepicker.js"></script>
  <script src="assets/js/select2.min.js"></script>
  <script src="assets/js/JsBarcode.all.min.js"></script>

  <!-- 0) Silence ONLY the legacy "Blocked nested #contentArea.load(...)" spam -->
  <script>
    (function () {
      var ow = console.warn;
      console.warn = function () {
        try {
          if (arguments[0] && typeof arguments[0] === 'string' &&
              arguments[0].indexOf('Blocked nested #contentArea.load(') !== -1) {
            return; // swallow that specific noisy warning
          }
        } catch(e){}
        return ow.apply(this, arguments);
      };
    })();
  </script>

  <!-- 1) Global shim to fix legacy invalid selector everywhere -->
  <script>
  (function () {
    var BAD  = '[data-bs-toggle="tooltip" data-bs-placement="bottom"]';
    var GOOD = '[data-bs-toggle="tooltip"][data-bs-placement="bottom"]';

    var origDocQSA = Document.prototype.querySelectorAll;
    Document.prototype.querySelectorAll = function (sel) {
      if (typeof sel === 'string' && sel.indexOf(BAD) !== -1) sel = sel.split(BAD).join(GOOD);
      try { return origDocQSA.call(this, sel); } catch (e) {
        return document.createDocumentFragment().querySelectorAll('x');
      }
    };

    var origElemQSA = Element.prototype.querySelectorAll;
    Element.prototype.querySelectorAll = function (sel) {
      if (typeof sel === 'string' && sel.indexOf(BAD) !== -1) sel = sel.split(BAD).join(GOOD);
      try { return origElemQSA.call(this, sel); } catch (e) {
        return document.createDocumentFragment().querySelectorAll('x');
      }
    };
  })();
  </script>

  <!-- 2) Install and lock a queued $.fn.load so no script can hard-block nested loads -->
  <script>
  (function ($) {
    if (!$.fn || !$.ajax) return;

    function createQueuedLoad(origLoad) {
      var FLAG  = '__jqLoadLoading';
      var QUEUE = '__jqLoadQueue';
      function isFn(x){ return typeof x === 'function'; }
      function isPlainObject(x){ return x && typeof x === 'object' && !Array.isArray(x); }

      function queued(url, params, callback) {
        if (typeof url !== 'string') {
          return origLoad ? origLoad.apply(this, arguments) : this;
        }
        return this.each(function () {
          var $el = $(this);
          var data, complete;
          if (isFn(params)) { complete = params; data = undefined; }
          else { data = isPlainObject(params) ? params : undefined; complete = isFn(callback) ? callback : undefined; }

          function run() {
            $el.data(FLAG, true);
            $.ajax({ url: url, method: data ? 'POST' : 'GET', data: data, cache: false })
              .done(function (resp, status, xhr) {
                $el.html(resp);
                if (complete) complete.call($el[0], resp, 'success', xhr);
              })
              .fail(function (xhr) {
                $el.html('<div class="alert alert-danger mt-3">Error loading page.</div>');
                if (complete) complete.call($el[0], xhr.responseText || '', 'error', xhr);
              })
              .always(function () {
                $el.data(FLAG, false);
                var q = $el.data(QUEUE) || [];
                if (q.length) { var next = q.shift(); $el.data(QUEUE, q); next(); }
                if (window._initTooltips) window._initTooltips(document);
                if (typeof window.initPage === 'function') { try { window.initPage(); } catch(e){} }
              });
          }

          if ($el.data(FLAG)) {
            var q = $el.data(QUEUE) || [];
            q.push(run);
            $el.data(QUEUE, q);
          } else {
            run();
          }
        });
      }
      return queued;
    }

    var _orig = $.fn.load;
    var _current = createQueuedLoad(_orig);

    try {
      Object.defineProperty($.fn, 'load', {
        configurable: false,
        enumerable: true,
        get: function () { return _current; },
        set: function (val) {
          if (typeof val === 'function') {
            // Even if someone tries to override later, we wrap THEIR impl with queueing
            _current = createQueuedLoad(val);
          }
        }
      });
    } catch (e) {
      $.fn.load = _current; // fallback
    }
  })(jQuery);
  </script>

  <!-- 3) PageSandbox: reset + plugin cleanup between AJAX pages -->
  <script>
  const PageSandbox = (() => {
    const state = { jqXHRs:new Set(), controllers:new Set(), timeouts:new Set(), intervals:new Set() };

    $.ajaxSetup({
      cache: false,
      beforeSend: function (jqXHR) { state.jqXHRs.add(jqXHR); jqXHR.always(() => state.jqXHRs.delete(jqXHR)); }
    });

    function addTimeout(fn, ms=0) {
      const id = window.setTimeout(() => { state.timeouts.delete(id); try { fn(); } catch(e){} }, ms);
      state.timeouts.add(id); return id;
    }
    function addInterval(fn, ms) { const id = window.setInterval(fn, ms); state.intervals.add(id); return id; }
    function clearAllTimers() { for (const id of state.timeouts) clearTimeout(id); for (const id of state.intervals) clearInterval(id); state.timeouts.clear(); state.intervals.clear(); }
    function newAbortController() { const c=new AbortController(); state.controllers.add(c); const cleanup=()=>state.controllers.delete(c); c.signal.addEventListener('abort', cleanup, { once:true }); return c; }

    function destroyPluginsIn($root) {
      try {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => { const inst=bootstrap.Tooltip.getInstance(el); if (inst) inst.dispose(); });
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => { const inst=bootstrap.Popover.getInstance(el); if (inst) inst.dispose(); });
      } catch(e){}

      $root.find('.modal.show').each(function(){ try { bootstrap.Modal.getInstance(this)?.hide(); } catch(e){} });
      $('.modal-backdrop').remove();

      if ($.fn.select2) { $root.find('select.select2-hidden-accessible').each(function(){ try { $(this).select2('destroy'); } catch(e){} }); }
      if ($.fn.datepicker) {
        $root.find('.datepicker, [data-provide="datepicker"], input').each(function(){
          try { const $el=$(this); if ($el.data('datepicker')) $el.datepicker('destroy'); } catch(e){}
        });
      }

      try {
        const canvases = $root.find('canvas').toArray();
        canvases.forEach(cv => { const inst = Chart.getChart ? Chart.getChart(cv) : (cv._chart || null); if (inst && typeof inst.destroy === 'function') inst.destroy(); });
      } catch(e){}
    }

    async function clearHttpCaches() {
      try {
        if ('caches' in window) { const keys = await caches.keys(); await Promise.all(keys.map(k => caches.delete(k))); }
      } catch (e) { /* ok */ }
    }

    function reset() {
      for (const jq of state.jqXHRs) { try { jq.abort(); } catch(e){} } state.jqXHRs.clear();
      for (const c of state.controllers) { try { c.abort(); } catch(e){} } state.controllers.clear();
      clearAllTimers();
      $(document).off('.page'); $(window).off('.page');
      destroyPluginsIn($('#contentArea'));
      window.initPage = undefined;
      if (typeof window.stopEverything === 'function') { try { window.stopEverything(); } catch(e){} }
      window.stopEverything = undefined;
    }

    return { reset, addTimeout, addInterval, newAbortController, clearHttpCaches };
  })();
  </script>

  <!-- 4) One safe tooltip initializer -->
  <script>
  (function () {
    function initTooltips(ctx) {
      if (!window.bootstrap || !bootstrap.Tooltip) return;
      var root = ctx || document;
      var els = root.querySelectorAll('[data-bs-toggle="tooltip"]');
      els.forEach(function (el) {
        try {
          var inst = bootstrap.Tooltip.getInstance(el);
          if (inst) inst.dispose();
          new bootstrap.Tooltip(el, {
            placement: el.getAttribute('data-bs-placement') || 'bottom'
          });
        } catch (e) {}
      });
    }
    window._initTooltips = initTooltips;
    document.addEventListener('DOMContentLoaded', function(){ initTooltips(document); });
  })();
  </script>

  <!-- 5) Main AJAX loader (uses $.ajax, not .load) + Full-view wiring -->
  <script>
  $(document).ready(function () {

    // === Full-view helpers ===
    function setFull(on){
      $('body').toggleClass('sidebar-off', !!on);
      $('#fullViewToggle i')
        .toggleClass('fa-expand', !on)
        .toggleClass('fa-compress', on);
      $('#fullViewToggle').attr('title', on ? 'Exit full view' : 'Full view');
    }
    $('#fullViewToggle').on('click', function () {
      setFull(!$('body').hasClass('sidebar-off'));
    });
    // Optional: ESC exits full view
    $(document).on('keyup', function(e){ if (e.key === 'Escape') setFull(false); });

    $('#burgerToggle').on('click', function () {
      $('#sidebarMenu').toggleClass('d-none d-block');
    });

    async function loadPage(href, push = true) {
    PageSandbox.reset();
    await PageSandbox.clearHttpCaches();

    $('#contentArea').html(`
      <div class="text-center mt-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-3">Loading...</p>
      </div>
    `);

    const urlNoCache = href + (href.includes('?') ? '&' : '?') + 'ts=' + Date.now();

    $.ajax({
      url: urlNoCache,
      method: 'GET',
      cache: false
    })
    .done(async function (data, status, xhr) {
      // ✅ --- SESSION CHECK ---
      if (typeof data === 'string' && (
            data.trim() === 'SESSION_EXPIRED' ||
            data.includes('Admin Portal Login') || // Fallback: detect login HTML
            data.includes('<title>Login') ||
            data.includes('Admin Portal Login</title>')
          )) {
        window.location.href = 'index.php';
        return;
      }

      // --- Inject HTML and execute any <script> tags in order ---
      async function injectAndRun(html) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        // Collect scripts in document order
        const scripts = Array.from(wrapper.querySelectorAll('script'));
        scripts.forEach(s => s.parentNode.removeChild(s));

        // Insert HTML first
        $('#contentArea').empty().append(wrapper.childNodes);

        // Helper to load scripts sequentially
        function loadScriptTag(tag) {
          return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            for (const { name, value } of Array.from(tag.attributes)) {
              s.setAttribute(name, value);
            }

            if (tag.src) {
              s.onload = () => resolve();
              s.onerror = () => reject(new Error('Failed to load ' + tag.src));
              s.src = tag.src;
              document.body.appendChild(s);
            } else {
              s.text = tag.text || tag.textContent || '';
              document.body.appendChild(s);
              resolve();
            }
          });
        }

        for (const tag of scripts) {
          try { await loadScriptTag(tag); } catch (e) { console.error(e); }
        }
      }

      try {
        await injectAndRun(data);
      } catch (e) {
        console.error('Script injection error:', e);
        $('#contentArea').html('<div class="alert alert-danger mt-3">Error rendering page.</div>');
        return;
      }

      // Re-init tooltips and other page hooks
      if (window._initTooltips) window._initTooltips(document);

      if (typeof window.runDashboardChart === 'function')
        PageSandbox.addTimeout(() => window.runDashboardChart(), 100);

      if (typeof window.initPage === 'function')
        try { window.initPage(); } catch (e) { console.error('initPage error:', e); }

      if (push)
        history.pushState({ page: href, full: $('body').hasClass('sidebar-off') }, '', 'main.php');
    })
    .fail(function (xhr) {
      // ✅ --- SESSION CHECK (for 401 or expired session) ---
      if (xhr.status === 401 ||
          (xhr.responseText && xhr.responseText.trim() === 'SESSION_EXPIRED')) {
        window.location.href = 'index.php';
        return;
      }

      // --- Normal error fallback ---
      alert('Failed to load the page. Please try again.');
      console.error('Load error:', xhr.status, xhr.statusText);
      $('#contentArea').html('<div class="alert alert-danger mt-3">Error loading page.</div>');
    });
  }

    // Sidebar link click handling (normal open)
    $('.sidebar').on('click', 'a[href]', function (e) {
      const href = $(this).attr('href');
      if (href && href !== '#' && !href.includes('logout')) {
        e.preventDefault();
        loadPage(href);
      }
    });

    // QUICK ACCESS buttons (normal open)
    $(document).on('click.page', '.quick-access[data-page]', function () {
      const page = $(this).data('page');
      if (page) loadPage(page);
    });

    // Open certain links/buttons directly in FULL view
    // - <a class="open-full" href="...">
    // - <anything data-open-full="1" data-href="...">
    // - .quick-access[data-page="..."][data-full="1"]
    $(document).on('click.page', 'a.open-full, [data-open-full="1"], .quick-access[data-full="1"]', function (e) {
      e.preventDefault();
      const href = $(this).attr('href') || $(this).data('page') || $(this).data('href');
      if (href) {
        setFull(true);
        loadPage(href);
      }
    });

    // Browser back/forward (restore full-view state too)
    window.onpopstate = function (event) {
      if (event.state && event.state.page) {
        setFull(!!event.state.full);
        loadPage(event.state.page, false);
      }
    };

    // Initial content
    loadPage('home-content.php', false);

    window.PageSandbox = PageSandbox;
  });
  </script>

  <script src="page-loader.js"></script>

  <script>
  (function ($) {
    if (!$.fn || !$.ajax) return;
    var cur = $.fn.load;
    try {
      Object.defineProperty($.fn, 'load', {
        configurable: false,
        enumerable: true,
        get: function () { return cur; },
        set: function (val) {
          if (typeof val === 'function') {
            // Wrap any late override with a pass-through that still points to our queued impl
            var late = val;
            cur = function () { return late.apply(this, arguments); };
          }
        }
      });
    } catch(e) {
      $.fn.load = cur;
    }
  })(jQuery);
  </script>
  <script>
    // Link the Special Notes button to special-notes.php
    (function(){
      const NS = '.specialNotesLink';
      // remove any earlier bindings to avoid duplicates
      $(document).off('click' + NS, '#btnSpecialNotes, .load-special-notes');

      $(document).on('click' + NS, '#btnSpecialNotes, .load-special-notes', function (e) {
        e.preventDefault();

        // show a tiny loader
        $('#contentArea').html(`
          <div class="text-center mt-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3">Loading Special Notes…</p>
          </div>
        `);

        // prefer your global loadPage if present, else fallback
        if (typeof window.loadPage === 'function') {
          window.loadPage('special-notes.php', { force: true });
        } else {
          $.get('special-notes.php')
            .done(function (html) { $('#contentArea').html(html); })
            .fail(function () {
              $('#contentArea').html('<div class="alert alert-danger mt-3">Failed to load Special Notes.</div>');
            });
        }
      });
    })();
  </script>

  <!-- somewhere in main.php -->
  <script src="vehicle-approvals-pro.js?v=1"></script>

</body>
</html>
