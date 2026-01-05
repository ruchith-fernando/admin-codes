<!-- postage-budget-vs-actual.php -->
<div class="content font-size bg-light">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="text-primary">Postage Budget vs Actual</h5>
        <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button>
      </div>

      <!-- ✅ Success alert -->
      <div class="alert alert-success d-none" id="success-alert" role="alert">
        ✅ Dashboard selection updated successfully!
      </div>
      <!-- ✅ Page-level alert (matching Security) -->
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

<script>
(function(){
  // Namespace so PageSandbox.reset() can unbind cleanly
  const NS = '.page.postage';

  // 🔔 Bootstrap alert helper (match security)
  function flash($el, type, msg, timeout=4000){
    $el.removeClass('d-none alert-success alert-danger alert-warning alert-info')
       .addClass('alert-' + type)
       .html(msg);
    if (timeout) setTimeout(()=> $el.addClass('d-none'), timeout);
  }

  function stripInlineScripts(html) {
    try { return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, ''); } catch(e){ return html; }
  }

  function loadPostageReport() {
    $('#report-content').html(
      '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div>Loading report...</div></div>'
    );
    $.get('ajax-postage-budget-vs-actual.php', function(res){
      $('#report-content').html(stripInlineScripts(res));
      $('html, body').animate({ scrollTop: $('.card').offset().top }, 300);
    }, 'html').fail(function(xhr){
      console.error('Postage AJAX failed:', xhr.status, xhr.responseText);
      $('#report-content').html('<div class="alert alert-danger">Failed to load report.</div>');
    });
  }

  // Clean previous handlers for this page
  $('#contentArea').off(NS);

  // Back to dashboard
  $('#contentArea').on('click' + NS, '#back-to-dashboard', function(){
    $('#contentArea').html('<div class="text-center p-4">Loading dashboard...</div>');
    $.get('dashboard.php', function(res){ $('#contentArea').html(res); });
  });

  // Update Dashboard Selection
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
        loadPostageReport();
      } else {
        flash($('#page-alert'), 'danger', '❌ Failed to update: ' + (res.message || 'Unknown error'));
      }
    }, 'json').fail(function(){
      flash($('#page-alert'), 'danger', '❌ Server error occurred while updating.');
    });
  });

  // Open remarks (target THIS page’s modal only)
  $('#contentArea').on('click' + NS, '.open-remarks', function(){
    const category = $(this).data('category');
    const record   = $(this).data('record');
    const modalSel = $(this).data('modal') || '#remarksModalPostage';
    const $modal   = $(modalSel);

    // reset modal alert + fields
    $modal.find('#remarks-alert').addClass('d-none')
          .removeClass('alert-success alert-danger alert-warning alert-info').empty();
    $modal.find('#remark-category').val(category);
    $modal.find('#remark-record').val(record);
    $modal.find('#modalRecordLabel').text(record);
    $modal.find('#new-remark').val('');
    $modal.find('#remark-history').html('Loading...');

    // Load existing remarks
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

      // recipients selector
      const $sel = $modal.find('#remark-recipients');
      if ($sel.data('select2')) { $sel.val(null).trigger('change'); $sel.select2('destroy'); }
      $sel.empty();

      $.getJSON('get-users.php', function(users){
        users.forEach(u => $sel.append(new Option(`${u.name} (${u.hris})`, u.hris)));
        if ($.fn.select2) $sel.select2({ dropdownParent: $modal, width:'100%', placeholder:'Select recipients' });
      });

      bootstrap.Modal.getOrCreateInstance($modal[0], { backdrop: 'static', keyboard: false }).show();
    }, 'json').fail(function(){
      flash($('#page-alert'), 'danger', '❌ Failed to load remarks.');
    });
  });

  // Save remarks (scoped to this page’s modal)
  $('#contentArea').on('click' + NS, '#save-remark', function(){
    const $modal     = $(this).closest('.modal');     // #remarksModalPostage
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
        // reload history in-place
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

  // Clean backdrops after THIS modal hides
  $('#contentArea').on('hidden.bs.modal' + NS, '#remarksModalPostage', function(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
  });

  // Let PageSandbox kill our bindings on navigation
  window.stopEverything = function(){
    $('#contentArea').off(NS);
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
  };

  // Initial load
  loadPostageReport();
})();
</script>

<!-- ✅ Remarks Modal (POSTAGE PAGE ONLY; unique ID) -->
<div class="modal fade"
     id="remarksModalPostage"
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
        <!-- modal-scoped alert -->
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
