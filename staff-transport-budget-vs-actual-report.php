<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!-- staff-transport-budget-vs-actual-report.php -->
<div class="content font-size bg-light">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="text-primary">Staff Transport Budget vs Actual</h5>
        <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button>
      </div>

      <!-- ✅ Success alert -->
      <div class="alert alert-success d-none" id="success-alert" role="alert">
        ✅ Dashboard selection updated successfully!
      </div>
      <!-- ✅ Page-level alert -->
      <div class="alert d-none" id="page-alert" role="alert"></div>

      <!-- ✅ Report section -->
      <div id="report-content">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <div>Loading report...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="validation-dashboard-selection.js"></script>

<script>
  // Kill legacy non-namespaced bindings (from older code)
  $(document)
    .off('click', '#update-selection')
    .off('click', '.open-remarks')
    .off('click', '#save-remark')
    .off('click', '#back-to-dashboard');
</script>
<!-- Put this after jQuery, near the end of main.php -->
<script>
(function ($) {
  let currentXHR = null;

  // Best-effort cache clear (safe if unsupported)
  async function clearAjaxCaches(){
    try { if ('caches' in window) {
      const keys = await caches.keys();
      await Promise.all(keys.map(k => caches.delete(k)));
    }} catch(e){}
    try { performance.clearResourceTimings(); } catch(e){}
  }

  // Call this to load any PHP fragment fresh into #contentArea
  window.loadNoCache = async function(url, target = '#contentArea'){
    // abort previous
    if (currentXHR && currentXHR.readyState !== 4) { try { currentXHR.abort(); } catch(e){} }

    await clearAjaxCaches();

    const $t = $(target).html(
      '<div class="text-center p-4"><div class="spinner-border text-primary"></div> Loading…</div>'
    );

    const busted = url + (url.indexOf('?') > -1 ? '&' : '?') + '_ts=' + Date.now();

    currentXHR = $.ajax({
      url: busted,
      method: 'GET',
      cache: false,
      headers: {
        'Cache-Control': 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma': 'no-cache',
        'Expires': '0'
      }
    })
    .done(function (html) {
      $t.html(html);
    })
    .fail(function () {
      $t.html('<div class="alert alert-danger mt-3">Failed to load.</div>');
    });
  };

  // Make all jQuery GETs non-cached by default (extra safety)
  $.ajaxSetup({ cache: false });
})(jQuery);
</script>

<script>
(function(){
  const NS = '.page.staffTransport';

  // Disable AJAX cache globally + track requests
  window.__xhrPool = window.__xhrPool || [];
  $.ajaxSetup({
    cache: false,
    beforeSend: function (jqXHR) { window.__xhrPool.push(jqXHR); },
    complete:  function (jqXHR) { window.__xhrPool = window.__xhrPool.filter(x => x !== jqXHR); }
  });

  // 🔔 Bootstrap alert helper
  function flash($el, type, msg, timeout=4000){
    $el.removeClass('d-none alert-success alert-danger alert-warning alert-info')
       .addClass('alert-' + type)
       .html(msg);
    if (timeout) setTimeout(()=> $el.addClass('d-none'), timeout);
  }

  function stripInlineScripts(html) {
    try { return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, ''); } catch(e){ return html; }
  }

  // Live footer recalculation from checked rows
  function parseNum(txt){ const n = parseFloat((txt||'').toString().replace(/,/g,'').trim()); return isNaN(n)?0:n; }
  function fmt(n){ return (n||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

  function recalcSelectionTotals(){
    const $table = $('#report-content table');
    if (!$table.length) return;

    let sumBudget = 0, sumPickme = 0, sumKangaroo = 0, sumActual = 0;

    // Sum only checked rows
    $('#report-content .month-checkbox:checked').each(function(){
      const $tds = $(this).closest('tr').find('td'); 
      // Columns: [#=0, Month=1, Budget=2, PickMe=3, Kangaroo=4, TotalActual=5, Diff=6, Var=7, Toggle=8]
      sumBudget  += parseNum($tds.eq(2).text());
      sumPickme  += parseNum($tds.eq(3).text());
      sumKangaroo+= parseNum($tds.eq(4).text());
      sumActual  += parseNum($tds.eq(5).text());
    });

    const diff = sumBudget - sumActual;
    const variance = sumBudget > 0 ? Math.round((diff / sumBudget) * 100) : null;

    // Totals rows are the last two <tr> in <tbody>
    const $tbody = $table.find('tbody');
    const $rows  = $tbody.find('tr');
    if ($rows.length < 2) return;

    const $totalRow    = $rows.eq($rows.length - 2); // "Total"
    const $varianceRow = $rows.eq($rows.length - 1); // "Total Variance (%)"

    // "Total" row cells: [colspan2, Budget, PickMe, Kangaroo, TotalActual, Difference, '', '']
    const $tBudget   = $totalRow.find('td').eq(1);
    const $tPickme   = $totalRow.find('td').eq(2);
    const $tKangaroo = $totalRow.find('td').eq(3);
    const $tActual   = $totalRow.find('td').eq(4);
    const $tDiff     = $totalRow.find('td').eq(5);

    $tBudget.text(fmt(sumBudget));
    $tPickme.text(fmt(sumPickme));
    $tKangaroo.text(fmt(sumKangaroo));
    $tActual.text(fmt(sumActual));
    $tDiff.text(fmt(diff)).removeClass('text-danger');
    if (diff < 0) $tDiff.addClass('text-danger');

    // "Total Variance (%)" row: [colspan5, value, colspan2]
    const $tVar = $varianceRow.find('td').eq(1);
    $tVar.text(variance === null ? 'N/A' : (variance + '%')).removeClass('text-danger');
    if (variance !== null && variance < 0) $tVar.addClass('text-danger');
  }

  // Optional deep-link focus
  function applyFocusIfAny(){
    const t = window.__focusTarget;
    if (!t || !t.category || !t.record) return;

    const $row = $('#report-content .report-row[data-category="'+t.category+'"][data-record="'+t.record+'"]');
    if ($row.length){
      $row.addClass('row-focus');
      $row[0].scrollIntoView({ behavior:'smooth', block:'center' });

      const $btn = $row.find('.open-remarks[data-category="'+t.category+'"][data-record="'+t.record+'"]');
      if ($btn.length){
        setTimeout(()=> $btn.trigger('click'), 200);
      }
    }
    window.__focusTarget = null;
  }

  function loadStaffTransportReport() {
    $('#report-content').html(
      '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div>Loading report...</div></div>'
    );

    $.ajax({
      url: 'ajax-staff-transport-budget-vs-actual-data.php',
      method: 'GET',
      data: { _: Date.now() } // cache buster
    }).done(function(res){
      $('#report-content').html(stripInlineScripts(res));
      $('html, body').animate({ scrollTop: $('.card').offset().top }, 300);
      applyFocusIfAny();
      recalcSelectionTotals(); // reflect current selection immediately
    }).fail(function(xhr){
      console.error('Staff Transport AJAX failed:', xhr.status, xhr.responseText);
      $('#report-content').html('<div class="alert alert-danger" role="alert">Failed to load report.</div>');
      flash($('#page-alert'), 'danger', '❌ Failed to load report.');
    });
  }

  // CLEAN rebinds
  $('#contentArea').off(NS);

  // Back to dashboard (namespaced + delegated)
  $('#contentArea').on('click' + NS, '#back-to-dashboard', function(){
    try { window.__xhrPool.forEach(x=>{try{x.abort()}catch(e){}}); window.__xhrPool.length=0; } catch(e){}
    $('#contentArea').html('<div class="text-center p-4">Loading dashboard...</div>');
    $.get('dashboard.php', function(res){ $('#contentArea').html(res); });
  });

  // Update Dashboard Selection (namespaced)
  $('#contentArea').on('click' + NS, '#update-selection', function(){
    if (typeof validateMonthSelection === 'function' && !validateMonthSelection()) return;

    const selected = [];
    $('#report-content .month-checkbox:checked').each(function(){
      selected.push({
        month: $(this).data('month'),
        category: $(this).data('category')
      });
    });

    $.post('update-dashboard-selection.php', { data: selected }, function(res){
      if (res.status === 'success') {
        $('#success-alert').removeClass('d-none');
        setTimeout(() => $('#success-alert').addClass('d-none'), 5000);
        loadStaffTransportReport(); // refresh after update
      } else {
        flash($('#page-alert'), 'danger', '❌ Failed to update: ' + (res.message || 'Unknown error'));
      }
    }, 'json').fail(function(){
      flash($('#page-alert'), 'danger', '❌ Server error occurred while updating.');
    });
  });

  // Remarks: open (with recipients)
  $('#contentArea').on('click' + NS, '.open-remarks', function(){
    const category = $(this).data('category');
    const record   = $(this).data('record');
    const $modal   = $('#remarksModal');

    // reset modal alert + fields
    $modal.find('#remarks-alert').addClass('d-none').removeClass('alert-success alert-danger alert-warning alert-info').empty();

    $modal.find('#remark-category').val(category);
    $modal.find('#remark-record').val(record);
    $modal.find('#modalRecordLabel').text(record);
    $modal.find('#new-remark').val('');
    $modal.find('#remark-history').html('Loading...');

    $.post('get-remarks.php', { category, record }, function(data){
      let html = '';
      if (Array.isArray(data) && data.length) {
        data.forEach(item => {
          html += `<div>
                    <strong>${item.hris_id}</strong>
                    <small class="text-muted">${item.commented_at}</small>
                    <span class="badge bg-secondary ms-2">SR: ${item.sr_number}</span><br>
                    ${item.comment}
                  </div><hr>`;
        });
      } else {
        html = '<div class="text-muted">No remarks yet.</div>';
      }
      $modal.find('#remark-history').html(html);

      const $sel = $modal.find('#remark-recipients');
      if ($sel.data('select2')) { $sel.val(null).trigger('change'); $sel.select2('destroy'); }
      $sel.empty();

      $.getJSON('get-users.php', function(users){
        users.forEach(u => $sel.append(new Option(`${u.name} (${u.hris})`, u.hris)));
        if ($.fn.select2) $sel.select2({ dropdownParent: $modal, width:'100%', placeholder:'Select recipients' });
      });

      bootstrap.Modal.getOrCreateInstance($modal[0], {
        backdrop: 'static', keyboard: false
      }).show();
    }, 'json').fail(function(){
      flash($('#page-alert'), 'danger', '❌ Failed to load remarks.');
    });
  });

  // Remarks: save (with recipients)
  $('#contentArea').on('click' + NS, '#save-remark', function(){
    const $modal     = $(this).closest('.modal');
    const category   = $modal.find('#remark-category').val();
    const record     = $modal.find('#remark-record').val();
    const comment    = $modal.find('#new-remark').val();
    const $sel       = $modal.find('#remark-recipients');
    const recipients = $sel.val() || [];
    const $alertBox  = $modal.find('#remarks-alert');

    if (!comment || !comment.trim()) {
      flash($alertBox, 'warning', 'Please enter a comment.');
      return;
    }

    $.post('save-remark.php', { category, record, comment, recipients }, function(response){
      if (response.status === 'success') {
        $('.open-remarks[data-category="'+category+'"][data-record="'+record+'"]').trigger('click');
        flash($alertBox, 'success', 'Saved successfully.', 2500);

        if ($sel.data('select2')) { $sel.val(null).trigger('change'); } else { $sel.val([]); }
        $modal.find('#new-remark').val('');

        if (typeof refreshSpecialNotesBadge === 'function') refreshSpecialNotesBadge();
      } else {
        flash($alertBox, 'danger', response.message || 'Failed to save.');
      }
    }, 'json').fail(function(){
      flash($alertBox, 'danger', '❌ Server error occurred while saving.');
    });
  });

  // 🔧 Safety: remove stray backdrops after modal is hidden
  $('#contentArea').on('hidden.bs.modal' + NS, '#remarksModal', function(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
  });

  // Recalc totals whenever a checkbox is toggled
  $('#contentArea').on('change' + NS, '.month-checkbox', recalcSelectionTotals);

  // Let PageSandbox kill our bindings on navigation
  window.stopEverything = function(){
    $('#contentArea').off(NS);
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
  };

  // Initial report load
  loadStaffTransportReport();
})();
</script>

<!-- ✅ Remarks Modal (matched with Tea) -->
<div class="modal fade"
     id="remarksModal"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Remarks for <span id="modalRecordLabel"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert d-none" id="remarks-alert" role="alert"></div>
        <div id="remark-history" class="mb-3 border p-3 bg-light" style="max-height: 200px; overflow-y: auto;"></div>
        <label class="form-label">Send To (optional)</label>
        <select id="remark-recipients" class="form-select" multiple></select>
        <div class="form-text">Choose one or more people to notify.</div>
        <textarea id="new-remark" class="form-control mb-2" rows="3" placeholder="Enter your remark..."></textarea>
        <input type="hidden" id="remark-category">
        <input type="hidden" id="remark-record">
        <button class="btn btn-success" id="save-remark">Save Remark</button>
      </div>
    </div>
  </div>
</div>
