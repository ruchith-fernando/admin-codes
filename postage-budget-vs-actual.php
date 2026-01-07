<?php /* postage-budget-vs-actual.php */ ?>
<?php
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
        <h5 class="text-primary">Postage Budget vs Actual</h5>
        <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button>
      </div>

      <div class="alert alert-success d-none" id="success-alert" role="alert">
        ✅ Dashboard selection updated successfully!
      </div>
      <div class="alert d-none" id="page-alert" role="alert"></div>

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

<!-- kill legacy bindings -->
<script>
$(document)
  .off('click', '#update-selection')
  .off('click', '.open-remarks')
  .off('click', '#save-remark')
  .off('click', '#back-to-dashboard');
</script>

<script>
(function(){
  const NS = '.page.postage';

  // Base dir of this PHP (e.g. "/pages/")
  const BASE = '<?= htmlspecialchars(rtrim(dirname($_SERVER["PHP_SELF"]), "/")."/", ENT_QUOTES) ?>';
  const ROOT = BASE.replace(/[^/]+\/$/, ''); // one level up

  // Path candidates helper
  function C(file){ return [ BASE + file, ROOT + file ]; }

  // Cache "working url" per endpoint once found
  const OK = {}; // e.g. OK.fetch = '...'

  const DASHBOARD_URLS = C('dashboard.php');
  const FETCH_URLS     = C('ajax-postage-budget-vs-actual.php');
  const UPDATE_URLS    = C('update-dashboard-selection.php');
  const GET_REMARKS    = C('get-remarks.php');
  const SAVE_REMARKS   = C('save-remark.php');
  const GET_USERS      = C('get-users.php');

  /* ---------------- AJAX hardening ---------------- */
  window.__xhrPool = window.__xhrPool || [];
  $.ajaxSetup({
    cache: false,
    beforeSend: function (jqXHR) { window.__xhrPool.push(jqXHR); },
    complete: function (jqXHR)   { window.__xhrPool = window.__xhrPool.filter(x => x !== jqXHR); }
  });

  /* ---------------- helpers ---------------- */
  function flash($el, type, msg, timeout=4000){
    $el.removeClass('d-none alert-success alert-danger alert-warning alert-info')
       .addClass('alert-' + type)
       .html(msg);
    if (timeout) setTimeout(()=> $el.addClass('d-none'), timeout);
  }

  function stripInlineScripts(html){
    try { return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, ''); } catch(e){ return html; }
  }

  function parseNum(txt){
    const n = parseFloat((txt||'').toString().replace(/,/g,'').trim());
    return isNaN(n) ? 0 : n;
  }

  function fmt(n){
    return (n||0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fetchWithFallback(urls, onSuccess, onFail, idx=0){
    if (idx >= urls.length) { onFail && onFail(); return; }
    $.ajax({ url: urls[idx], method: 'GET', cache: false })
      .done(res => onSuccess(res, urls[idx]))
      .fail(() => fetchWithFallback(urls, onSuccess, onFail, idx+1));
  }

  function postWithFallback(urls, data, dataType, onSuccess, onFail, idx=0){
    if (idx >= urls.length) { onFail && onFail(); return; }
    $.ajax({ url: urls[idx], method: 'POST', data, dataType })
      .done(res => onSuccess(res, urls[idx]))
      .fail(() => postWithFallback(urls, data, dataType, onSuccess, onFail, idx+1));
  }

  /* ---------------- totals (like Photocopy) ---------------- */
  function recalcSelectionTotals(){
    const $table = $('#report-content table');
    if (!$table.length) return;

    let sumBudget = 0, sumActual = 0;

    $('#report-content .month-checkbox:checked').each(function(){
      const $tds = $(this).closest('tr').find('td');
      // Table cols: 0 #, 1 Month, 2 Budget, 3 Actual, 4 Diff, 5 Var, 6 Completion, 7 Select
      sumBudget += parseNum($tds.eq(2).text());
      sumActual += parseNum($tds.eq(3).text());
    });

    const diff = sumBudget - sumActual;
    const variance = sumBudget > 0 ? Math.round((diff / sumBudget) * 100) : null;

    // Update spans if present (this table uses spans)
    $('#total_budget').text(fmt(sumBudget));
    $('#total_actual').text(fmt(sumActual));
    $('#total_difference').text(fmt(diff));
    $('#total_variance').text(variance === null ? 'N/A' : (variance + '%'));

    const $diffTd = $('#total_difference_td');
    $diffTd.removeClass('text-danger fw-bold');
    if (diff < 0) $diffTd.addClass('text-danger fw-bold');

    const $varTd = $('#total_variance_td');
    $varTd.removeClass('text-danger fw-bold');
    if (variance !== null && variance < 0) $varTd.addClass('text-danger fw-bold');
  }

  /* ---------------- loader ---------------- */
  let currentFetchUrl = null;

  function loadPostageReport(){
    $('#report-content').html(
      '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div>Loading report...</div></div>'
    );

    const urls = currentFetchUrl ? [currentFetchUrl] : FETCH_URLS;

    fetchWithFallback(
      urls,
      (res, okUrl) => {
        currentFetchUrl = okUrl;
        $('#report-content').html(stripInlineScripts(res));
        $('html, body').animate({ scrollTop: $('.card').offset().top }, 300);
        recalcSelectionTotals(); // initialize selection totals
      },
      () => {
        $('#report-content').html('<div class="alert alert-danger" role="alert">Failed to load report (404).</div>');
        console.error('Postage fetch failed on all paths:', urls);
      }
    );
  }

  /* ---------------- clean rebinds ---------------- */
  $('#contentArea').off(NS);

  // Back to dashboard (abort in-flight xhr like Photocopy)
  $('#contentArea').on('click' + NS, '#back-to-dashboard', function(){
    try { window.__xhrPool.forEach(x => { try { x.abort(); } catch(e){} }); window.__xhrPool.length = 0; } catch(e){}
    $('#contentArea').html('<div class="text-center p-4">Loading dashboard...</div>');

    const urls = OK.dashboard ? [OK.dashboard] : DASHBOARD_URLS;

    fetchWithFallback(urls, (res, okUrl)=>{
      OK.dashboard = okUrl;
      $('#contentArea').html(res);
    }, ()=>{
      $('#contentArea').html('<div class="alert alert-danger">Failed to load dashboard.</div>');
    });
  });

  // Update selection (fallback POST)
  $('#contentArea').on('click' + NS, '#update-selection', function(){
    if (typeof validateMonthSelection === 'function' && !validateMonthSelection()) return;

    const selected = [];
    $('#report-content .month-checkbox:checked').each(function(){
      selected.push({ month: $(this).data('month'), category: $(this).data('category') });
    });

    const urls = OK.update ? [OK.update] : UPDATE_URLS;

    postWithFallback(
      urls,
      { data: selected },
      'json',
      (res, okUrl) => {
        OK.update = okUrl;
        if (res && res.status === 'success') {
          $('#success-alert').removeClass('d-none');
          setTimeout(() => $('#success-alert').addClass('d-none'), 5000);
          loadPostageReport();
        } else {
          flash($('#page-alert'), 'danger', '❌ Failed to update: ' + ((res && res.message) ? res.message : 'Unknown error'));
        }
      },
      () => {
        flash($('#page-alert'), 'danger', '❌ Server error occurred while updating.');
      }
    );
  });

  // Open remarks (scoped to postage modal)
  $('#contentArea').on('click' + NS, '.open-remarks', function(){
    const category = $(this).data('category');
    const record   = $(this).data('record');
    const $modal   = $('#remarksModalPostage');

    $modal.find('#remarks-alert').addClass('d-none')
          .removeClass('alert-success alert-danger alert-warning alert-info').empty();

    $modal.find('#remark-category').val(category);
    $modal.find('#remark-record').val(record);
    $modal.find('#modalRecordLabel').text(record);
    $modal.find('#new-remark').val('');
    $modal.find('#remark-history').html('Loading...');

    const urls = OK.getRemarks ? [OK.getRemarks] : GET_REMARKS;

    postWithFallback(
      urls,
      { category, record },
      'json',
      (data, okUrl) => {
        OK.getRemarks = okUrl;

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

        // recipients
        const $sel = $modal.find('#remark-recipients');
        if ($sel.data('select2')) { $sel.val(null).trigger('change'); $sel.select2('destroy'); }
        $sel.empty();

        const userUrls = OK.getUsers ? [OK.getUsers] : GET_USERS;
        fetchWithFallback(userUrls, (users, okUsersUrl)=>{
          OK.getUsers = okUsersUrl;
          try {
            if (typeof users === 'string') users = JSON.parse(users);
          } catch(e){ users = []; }

          (users || []).forEach(u => $sel.append(new Option(`${u.name} (${u.hris})`, u.hris)));
          if ($.fn.select2) $sel.select2({ dropdownParent: $modal, width:'100%', placeholder:'Select recipients' });

          bootstrap.Modal.getOrCreateInstance($modal[0], { backdrop: 'static', keyboard: false }).show();
        }, ()=>{
          bootstrap.Modal.getOrCreateInstance($modal[0], { backdrop: 'static', keyboard: false }).show();
        });
      },
      () => {
        flash($('#page-alert'), 'danger', '❌ Failed to load remarks.');
      }
    );
  });

  // Save remark (scoped)
  $('#contentArea').on('click' + NS, '#save-remark', function(){
    const $modal     = $(this).closest('.modal');
    const category   = $modal.find('#remark-category').val();
    const record     = $modal.find('#remark-record').val();
    const comment    = $modal.find('#new-remark').val();
    const $sel       = $modal.find('#remark-recipients');
    const recipients = $sel.val() || [];
    const $alertBox  = $modal.find('#remarks-alert');

    if (!comment || !comment.trim()) { flash($alertBox, 'warning', 'Please enter a comment.'); return; }

    const urls = OK.saveRemarks ? [OK.saveRemarks] : SAVE_REMARKS;

    postWithFallback(
      urls,
      { category, record, comment, recipients, origin_page: 'postage-budget-vs-actual.php' },
      'json',
      (response, okUrl) => {
        OK.saveRemarks = okUrl;

        if (response && response.status === 'success') {
          $('.open-remarks[data-category="'+category+'"][data-record="'+record+'"]').trigger('click');
          flash($alertBox, 'success', 'Saved successfully.', 2500);

          if ($sel.data('select2')) { $sel.val(null).trigger('change'); } else { $sel.val([]); }
          $modal.find('#new-remark').val('');
          if (typeof refreshSpecialNotesBadge === 'function') refreshSpecialNotesBadge();
        } else {
          flash($alertBox, 'danger', (response && response.message) ? response.message : 'Failed to save.');
        }
      },
      () => {
        flash($alertBox, 'danger', '❌ Server error occurred while saving.');
      }
    );
  });

  // Recalc totals when checkbox changes (delegated + namespaced)
  $('#contentArea').on('change' + NS, '.month-checkbox', function(){
    if ($('#report-content').has(this).length) recalcSelectionTotals();
  });

  // Clean backdrops for THIS modal
  $('#contentArea').on('hidden.bs.modal' + NS, '#remarksModalPostage', function(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
  });

  // Initial load
  loadPostageReport();
})();
</script>

<!-- ✅ Remarks Modal (POSTAGE only; unique) -->
<div class="modal fade" id="remarksModalPostage" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
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
