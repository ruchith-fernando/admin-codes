<?php 
// <!-- security-cost-report.php -->
  include 'nocache.php'; 
  include 'connections/connection.php'; 
?>
<head>
  <script src="nocache.js"></script>
  <script src="update-dashboard-selection.js"></script>
</head>
<div class="content font-size bg-light">
<div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="text-primary">Security Budget vs Actual</h5>
            <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button>
        </div>

        <!-- ✅ Success alert -->
        <div class="alert alert-success d-none" id="success-alert" role="alert">
            ✅ Dashboard selection updated successfully!
        </div>
        <!-- ✅ Page-level alert (NEW) -->
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
<script>
(function(){
  const NS = '.page.security';

  // ---- XHR pool + no-cache (skip cache-buster for .php to avoid rewrite 404s) ----
  window.__xhrPool = window.__xhrPool || [];
  $.ajaxSetup({
    cache: false,
    beforeSend: function (jqXHR, settings) {
      const method = (settings.type || 'GET').toUpperCase();
      const isPHP  = /\.php(\?|$)/i.test(settings.url);
      if ((method === 'GET' || method === 'HEAD') && !isPHP) {
        const sep = settings.url.includes('?') ? '&' : '?';
        settings.url += sep + '_=' + Date.now();
      }
      window.__xhrPool.push(jqXHR);
    },
    complete: function (jqXHR) {
      window.__xhrPool = window.__xhrPool.filter(x => x !== jqXHR);
    }
  });

  // Abort any carry-over requests on init
  try {
    window.__xhrPool.forEach(x => { try { x.abort(); } catch(e){} });
    window.__xhrPool.length = 0;
  } catch(e){}

  // ---- helpers ----
  function stripInlineScripts(html){
    try { return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, ''); } catch(e){ return html; }
  }
  function applyFocusIfAny(){
    const t = window.__focusTarget;
    if (!t || !t.category || !t.record) return;
    const $row = $('#report-content .report-row[data-category="'+t.category+'"][data-record="'+t.record+'"]');
    if ($row.length){
      $row.addClass('row-focus');
      $row[0].scrollIntoView({ behavior:'smooth', block:'center' });
      const $btn = $row.find('.open-remarks[data-category="'+t.category+'"][data-record="'+t.record+'"]');
      if ($btn.length){ setTimeout(()=> $btn.trigger('click'), 200); }
    }
    window.__focusTarget = null;
  }

  // Strong guard: only render when the HTML contains the expected table & rows
  function isValidReportHTML(raw){
    if (!raw || typeof raw !== 'string') return false;
    if (/page\s*not\s*found/i.test(raw)) return false; // themed 404s
    const html = stripInlineScripts(raw);
    if (!/<table[^>]*class=["'][^"']*wide-table/i.test(html)) return false;
    if (!/class=["']report-row["']/.test(html)) return false;
    return true;
  }

  // ---- totals helpers ----
  function parseNum(txt){
    const s = (txt || '').toString().replace(/,/g, '').trim();
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }
  function fmt(n){ return (n || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
  function recalcSelectionTotals(){
    const $table = $('#report-content table');
    if (!$table.length) return;

    let sumBudget = 0, sumActual = 0;
    $('#report-content .report-row').each(function(){
      if (!$(this).find('.month-checkbox').prop('checked')) return;
      const $tds = $(this).find('td'); // #, Month, Budget, Actual, Difference, Variance, Completion, Select/Remark
      sumBudget += parseNum($tds.eq(2).text());
      sumActual += parseNum($tds.eq(3).text());
    });

    const diff = sumBudget - sumActual;
    const variance = sumBudget > 0 ? Math.round((diff / sumBudget) * 100) : null;

    const $rows = $table.find('tbody > tr');
    if ($rows.length < 2) return;

    const $totalRow    = $rows.eq($rows.length - 2); // "Total"
    const $varianceRow = $rows.eq($rows.length - 1); // "Total Variance (%)"

    $totalRow.find('td').eq(1).text(fmt(sumBudget)); // Budget
    $totalRow.find('td').eq(2).text(fmt(sumActual)); // Actual
    const $tDiff = $totalRow.find('td').eq(3).text(fmt(diff)).removeClass('text-danger');
    if (diff < 0) $tDiff.addClass('text-danger');

    const $tVar = $varianceRow.find('td').eq(1).text(variance === null ? 'N/A' : (variance + '%')).removeClass('text-danger');
    if (variance !== null && variance < 0) $tVar.addClass('text-danger');
  }

  // ---- single, guarded loader (NO alerts on bad payload) ----
  let reportLoadXHR = null;
  let loadSeq = 0;

  function loadSecurityReport(){
    const mySeq = ++loadSeq;

    if (reportLoadXHR && reportLoadXHR.readyState !== 4) {
      try { reportLoadXHR.abort(); } catch(e){}
    }

    $('#report-content').html(
      '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div>Loading report...</div></div>'
    );

    reportLoadXHR = $.ajax({
      url: 'security-budget-fetch.php',
      method: 'GET',
      dataType: 'html',
      cache: false
    }).done(function(res){
      if (mySeq !== loadSeq) return;

      // If not valid, do nothing (keep spinner). No Bootstrap alert.
      if (!isValidReportHTML(res)) {
        console.debug('Report payload blocked (keeping spinner).');
        return;
      }

      $('#report-content').html(stripInlineScripts(res));
      $('html, body').animate({ scrollTop: $('.card').offset().top }, 300);
      applyFocusIfAny();
      recalcSelectionTotals();
    }).fail(function(jqXHR){
      if (mySeq !== loadSeq) return;
      // keep a minimal message on hard failure (network/server). If you want *no* text at all, comment next line.
      $('#report-content').html('<div class="alert alert-danger" role="alert">Failed to load report (' + jqXHR.status + ').</div>');
    });
  }

  // ---- event bindings (scoped) ----
  $('#contentArea').off(NS);

  $('#contentArea').on('click' + NS, '#back-to-dashboard', function(){
    try {
      window.__xhrPool.forEach(x => { try { x.abort(); } catch(e){} });
      window.__xhrPool.length = 0;
    } catch(e){}
    $('#contentArea').html('<div class="text-center p-4">Loading dashboard...</div>');
    $.get('dashboard.php', function(res){ $('#contentArea').html(res); });
  });

  $('#contentArea').on('click' + NS, '#update-selection', function(){
    if (typeof validateMonthSelection === 'function' && !validateMonthSelection()) return;

    const selected = [];
    $('#report-content .month-checkbox:checked').each(function(){
      selected.push({ month: $(this).data('month'), category: $(this).data('category') });
    });

    $.post('update-dashboard-selection.php', { data: selected }, function(res){
      if (res.status === 'success') {
        $('#success-alert').removeClass('d-none');
        setTimeout(() => $('#success-alert').addClass('d-none'), 5000);
        loadSecurityReport();
      } else {
        // keep other alerts (this is not the 404 one)
        const $p = $('#page-alert');
        $p.removeClass('d-none alert-success alert-warning alert-info').addClass('alert-danger')
          .html('❌ Failed to update: ' + (res.message || 'Unknown error'));
        setTimeout(()=> $p.addClass('d-none'), 4000);
      }
    }, 'json').fail(function(){
      const $p = $('#page-alert');
      $p.removeClass('d-none alert-success alert-warning alert-info').addClass('alert-danger')
        .html('❌ Server error occurred while updating.');
      setTimeout(()=> $p.addClass('d-none'), 4000);
    });
  });

  $('#contentArea').on('click' + NS, '.open-remarks', function(){
    const category = $(this).data('category');
    const record   = $(this).data('record');
    const $modal   = $('#remarksModal');

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
      const $p = $('#page-alert');
      $p.removeClass('d-none alert-success alert-warning alert-info').addClass('alert-danger')
        .html('❌ Failed to load remarks.');
      setTimeout(()=> $p.addClass('d-none'), 4000);
    });
  });

  $('#contentArea').on('click' + NS, '#save-remark', function(){
    const $modal     = $(this).closest('.modal');
    const category   = $modal.find('#remark-category').val();
    const record     = $modal.find('#remark-record').val();
    const comment    = $modal.find('#new-remark').val();
    const $sel       = $modal.find('#remark-recipients');
    const recipients = $sel.val() || [];
    const $alertBox  = $modal.find('#remarks-alert');

    if (!comment || !comment.trim()) {
      $alertBox.removeClass('d-none alert-success alert-danger alert-info').addClass('alert-warning').html('Please enter a comment.');
      setTimeout(()=> $alertBox.addClass('d-none'), 2500);
      return;
    }

    $.post('save-remark.php', { category, record, comment, recipients }, function(response){
      if (response.status === 'success') {
        $('.open-remarks[data-category="'+category+'"][data-record="'+record+'"]').trigger('click');
        $alertBox.removeClass('d-none alert-danger alert-warning alert-info').addClass('alert-success').html('Saved successfully.');
        setTimeout(()=> $alertBox.addClass('d-none'), 2500);
        if ($sel.data('select2')) { $sel.val(null).trigger('change'); } else { $sel.val([]); }
        $modal.find('#new-remark').val('');
        if (typeof refreshSpecialNotesBadge === 'function') refreshSpecialNotesBadge();
      } else {
        $alertBox.removeClass('d-none alert-success alert-warning alert-info').addClass('alert-danger').html(response.message || 'Failed to save.');
      }
    }, 'json').fail(function(){
      $alertBox.removeClass('d-none alert-success alert-warning alert-info').addClass('alert-danger').html('❌ Server error occurred while saving.');
    });
  });

  $('#contentArea').on('change' + NS, '.month-checkbox', recalcSelectionTotals);

  $('#contentArea').on('hidden.bs.modal' + NS, '#remarksModal', function(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
  });

  // ---- initial load ----
  loadSecurityReport();
})();
</script>

