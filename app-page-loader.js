/* app-page-loader.js
   One-at-a-time page loader with abort, token-guard, and a single spinner overlay.
*/
(() => {
  if (window.AppPageLoader) return; // don't double-install

  let currentXHR = null;
  let token = 0;
  let spinnerTimer = null;

  // --- overlay (added once) ---
  function ensureOverlay() {
    let el = document.getElementById('globalPageSpinner');
    if (!el) {
      el = document.createElement('div');
      el.id = 'globalPageSpinner';
      el.className = 'd-none';
      el.innerHTML = `
        <div class="app-spinner-wrap">
          <div class="spinner-border" role="status"></div>
          <div class="mt-2 small text-muted">Loading…</div>
        </div>`;
      document.body.appendChild(el);
    }
    return el;
  }
  const overlay = ensureOverlay();

  function showOverlay() {
    // delay a bit so ultra-fast loads never show the spinner
    clearTimeout(spinnerTimer);
    spinnerTimer = setTimeout(() => overlay.classList.remove('d-none'), 120);
  }
  function hideOverlay() {
    clearTimeout(spinnerTimer);
    overlay.classList.add('d-none');
  }

  // Try to let the outgoing page clean itself up (optional)
  function tryDestroyActivePage() {
    try {
      // your modules expose these destroy() hooks; add more as needed
      window.__telephoneChartPage?.destroy?.();
      window.__electricityChartPage?.destroy?.();
      window.__loadGraphsPage?.destroy?.();
      // window.__activePage?.destroy?.(); // if you standardize on this
    } catch (e) {}
  }

  async function load(url, { into = '#contentArea', method = 'GET', data = null } = {}) {
    const my = ++token;

    // abort previous XHR
    if (currentXHR) { try { currentXHR.abort(); } catch (e) {} currentXHR = null; }

    // strong cache-buster
    const bust = url + (url.includes('?') ? '&' : '?') + 'ts=' + Date.now();

    // show overlay
    showOverlay();

    // disable re-entrant clicks on the active trigger (handled in wrapClicks)
    tryDestroyActivePage();

    return new Promise((resolve, reject) => {
      currentXHR = $.ajax({ url: bust, method, data, cache: false })
        .done((html) => {
          if (my !== token) return;         // ignore stale responses
          $(into).html(html);
          resolve(html);

          // optional page inits (if embedded page defines them)
          if (typeof window.runDashboardChart === 'function') {
            setTimeout(() => { try { window.runDashboardChart(); } catch (e) {} }, 50);
          }
          if (typeof window.initPage === 'function') {
            setTimeout(() => { try { window.initPage(); } catch (e) {} }, 50);
          }
        })
        .fail((xhr) => {
          if (my !== token) return;
          $(into).html('<div class="alert alert-danger mt-3">Error loading page.</div>');
          reject(xhr);
        })
        .always(() => {
          if (my !== token) return;
          hideOverlay();
          currentXHR = null;
        });
    });
  }

  // Enhance buttons/links so clicks call load() safely and don’t flicker
  function wrapClicks(selector = '.load-report', { into = '#contentArea' } = {}) {
    $(document)
      .off('click.singleflight', selector)
      .on('click.singleflight', selector, function (e) {
        e.preventDefault();
        const $btn = $(this);
        const page = $btn.data('page') || $btn.attr('href');
        if (!page) return;

        // lock the button visually (no width jump)
        const orig = $btn.html();
        $btn.prop('disabled', true).addClass('disabled')
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Opening…');

        load(page, { into })
          .finally(() => {
            $btn.prop('disabled', false).removeClass('disabled').html(orig);
          });
      });
  }

  window.AppPageLoader = { load, wrapClicks };
})();
