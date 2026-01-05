// page-loader.js — FIXED
(function ($) {
  $(document)
    .off('click.pageLoader', '[data-page]')
    .on('click.pageLoader', '[data-page]', function (e) {
      e.preventDefault();
      const page = $(this).data('page');
      const $area = $('#contentArea');

      $area.html(
        '<div class="text-center p-4"><div class="spinner-border text-primary"></div><div>Loading...</div></div>'
      );

      // Allow current page to clean up
      if (typeof window.destroyPage === 'function') {
        try { window.destroyPage(); } catch (err) { console.error('destroyPage threw:', err); }
      }

      $.get(page).done(function (res) {
        // Parse HTML and keep <script> tags
        const nodes = $.parseHTML(res, document, true); // keepScripts = true
        $area.empty().append(nodes);

        // Execute scripts in-order (inline + external)
        const $scripts = $area.find('script');
        $scripts.each(function () {
          const srcEl = this;
          const s = document.createElement('script');

          // Preserve attributes
          if (srcEl.type) s.type = srcEl.type;
          if (srcEl.src) {
            s.src = srcEl.src;
            s.async = false; // preserve order
          } else {
            s.text = srcEl.text || srcEl.textContent || srcEl.innerHTML;
          }
          if (srcEl.nonce) s.nonce = srcEl.nonce;
          if (srcEl.crossOrigin) s.crossOrigin = srcEl.crossOrigin;
          if (srcEl.referrerPolicy) s.referrerPolicy = srcEl.referrerPolicy;

          // Replace to trigger execution
          srcEl.parentNode.insertBefore(s, srcEl);
          srcEl.parentNode.removeChild(srcEl);
        });

        // Call page initializer if present
        setTimeout(function () {
          if (typeof window.initPage === 'function') {
            try { window.initPage(); } catch (e) { console.error('initPage threw:', e); }
          } else {
            if (console && console.debug) console.debug('initPage not defined in loaded page:', page);
          }
        }, 0);
      }).fail(function () {
        $area.html('<div class="alert alert-danger mt-4 text-center">❌ Failed to load page.</div>');
      });
    });
})(jQuery);
