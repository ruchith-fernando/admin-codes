<!-- ajax-vehicle-maintenance-report-body.php -->
<div class="content font-size bg-light">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Page header: title + back button -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="text-primary">Vehicle Maintenance Budget vs Actual Report</h5>
        <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button>
      </div>

      <!-- Shown only after a successful dashboard selection update -->
      <div class="alert alert-success d-none" id="success-alert" role="alert">
        ✅ Dashboard selection updated successfully!
      </div>

      <!-- Generic alert area for any page-level errors -->
      <div class="alert d-none" id="page-alert" role="alert"></div>

      <!-- Report fragment will be injected here -->
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

<!-- Remarks Modal -->
<div class="modal fade"
     id="remarksModal"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <!-- Modal header: shows which record we're commenting on -->
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Remarks for <span id="modalRecordLabel"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- Inline alert inside the modal (success/fail messages for remark actions) -->
        <div class="alert d-none" id="remarks-alert" role="alert"></div>

        <!-- Previous remarks list -->
        <div id="remark-history"
             class="mb-3 border p-3 bg-light"
             style="max-height: 200px; overflow-y: auto;"></div>

        <!-- Optional recipients list (Select2 if available) -->
        <label class="form-label">Send To (optional)</label>
        <select id="remark-recipients" class="form-select" multiple></select>
        <div class="form-text">Choose one or more people to notify.</div>

        <!-- New remark input -->
        <textarea id="new-remark"
                  class="form-control mb-2"
                  rows="3"
                  placeholder="Enter your remark..."></textarea>

        <!-- Hidden fields used when saving remarks -->
        <input type="hidden" id="remark-category" value="Vehicle Maintenance">
        <input type="hidden" id="remark-record">

        <button class="btn btn-success" id="save-remark">Save Remark</button>
      </div>

    </div>
  </div>
</div>

<script src="validation-dashboard-selection.js"></script>

<!-- Remove old non-namespaced handlers (prevents duplicate bindings from legacy scripts) -->
<script>
  $(document)
    .off('click', '#update-selection')
    .off('click', '.open-remarks')
    .off('click', '#save-remark')
    .off('click', '#back-to-dashboard');
</script>

<script>
(function(){
  const NS = '.page.vehicleMaintenance';

  // Make sure jQuery doesn't cache AJAX responses (helps when reports change often)
  $.ajaxSetup({ cache:false });

  // Small helper to show a bootstrap alert and hide it after a short delay
  function flash($el, type, msg, timeout = 4000){
    $el.removeClass('d-none alert-success alert-danger alert-warning alert-info')
       .addClass('alert-' + type)
       .html(msg);

    if (timeout) {
      setTimeout(() => $el.addClass('d-none'), timeout);
    }
  }

  // Report fragments can sometimes contain <script> tags.
  // We strip them to avoid accidentally executing inline scripts twice.
  function stripInlineScripts(html){
    try {
      return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
    } catch(e){
      return html;
    }
  }

  // If another page sets a focus target (category + record),
  // we highlight that row and optionally open the remarks modal.
  function applyFocusIfAny(){
    const t = window.__focusTarget;
    if (!t || !t.category || !t.record) return;

    const $row = $('#report-content .report-row[data-category="'+t.category+'"][data-record="'+t.record+'"]');

    if ($row.length){
      $row.addClass('row-focus');
      $row[0].scrollIntoView({ behavior:'smooth', block:'center' });

      const $btn = $row.find('.open-remarks[data-category="'+t.category+'"][data-record="'+t.record+'"]');
      if ($btn.length){
        setTimeout(() => $btn.trigger('click'), 200);
      }
    }

    window.__focusTarget = null;
  }

  // Recalculate totals/variance in the footer based on currently checked months
  function recalcFooter() {
    let totalBudget = 0,
        totalTire = 0,
        totalAlignment = 0,
        totalBattery = 0,
        totalAC = 0,
        totalOther = 0,
        totalService = 0,
        totalLic = 0,
        totalActual = 0;

    // Only count rows that the user has selected via checkbox
    $('#report-content .month-checkbox:checked').each(function(){
      const $r = $(this).closest('tr.report-row');

      totalBudget    += parseFloat($r.data('budget'))    || 0;
      totalTire      += parseFloat($r.data('tire'))      || 0;
      totalAlignment += parseFloat($r.data('alignment')) || 0;
      totalBattery   += parseFloat($r.data('battery'))   || 0;
      totalAC        += parseFloat($r.data('ac'))        || 0;
      totalOther     += parseFloat($r.data('other'))     || 0;
      totalService   += parseFloat($r.data('service'))   || 0;
      totalLic       += parseFloat($r.data('licensing')) || 0;
      totalActual    += parseFloat($r.data('actual'))    || 0;
    });

    const diff = totalBudget - totalActual;
    const variance = totalBudget > 0 ? Math.round((diff / totalBudget) * 100) : null;

    const fmt = n => (n || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    // Push totals into the footer cells
    $('#footer-total-budget').text(fmt(totalBudget));
    $('#footer-total-tire').text(fmt(totalTire));
    $('#footer-total-alignment').text(fmt(totalAlignment));
    $('#footer-total-battery').text(fmt(totalBattery));
    $('#footer-total-ac').text(fmt(totalAC));
    $('#footer-total-other').text(fmt(totalOther));
    $('#footer-total-service').text(fmt(totalService));
    $('#footer-total-licensing').text(fmt(totalLic));
    $('#footer-total-actual').text(fmt(totalActual));

    // Variance: red if negative (overspent)
    const $var = $('#footer-total-variance');
    $var.text(variance === null ? 'N/A' : variance + '%')
        .removeClass('text-danger')
        .toggleClass('text-danger', variance < 0);
  }

  // Reload the report fragment and re-attach any “after load” behavior
  function loadVehicleMaintenanceReport() {
    $('#report-content').html(
      '<div class="text-center py-5">' +
        '<div class="spinner-border text-primary"></div>' +
        '<div>Loading report...</div>' +
      '</div>'
    );

    // Cache-busting param (avoids stale fragments if browser/proxy is aggressive)
    $.get('ajax-vehicle-maintenance-report-body.php?_=' + Date.now(), function(res){
      $('#report-content').html(stripInlineScripts(res));

      // Bring the user back to the top of the report card
      $('html, body').animate({ scrollTop: $('.card').offset().top }, 300);

      applyFocusIfAny();

      // Make sure the footer totals match whatever is pre-selected
      recalcFooter();

    }).fail(function(){
      flash($('#page-alert'), 'danger', '❌ Failed to load report.');
      $('#report-content').html('<div class="alert alert-danger" role="alert">Failed to load report.</div>');
    });
  }

  // Remove any old handlers for this page namespace before binding new ones
  $('#contentArea').off(NS);

  // Back button: replace content area with dashboard
  $('#contentArea').on('click' + NS, '#back-to-dashboard', function(){
    $('#contentArea').html('<div class="text-center p-4">Loading dashboard...</div>');
    $.get('dashboard.php', function(res){
      $('#contentArea').html(res);
    });
  });

  // Update selection: validates and persists month selections for dashboard widgets
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

        // Reload report so the UI reflects the saved selection state
        loadVehicleMaintenanceReport();

      } else {
        flash($('#page-alert'), 'danger', '❌ Failed to update: ' + (res.message || 'Unknown error'));
      }
    }, 'json').fail(function(){
      flash($('#page-alert'), 'danger', '❌ Server error occurred while updating.');
    });
  });

  // Update footer totals immediately when the user checks/unchecks a month
  $('#contentArea').on('change' + NS, '.month-checkbox', recalcFooter);

  // Open remarks modal for a selected row
  $('#contentArea').on('click' + NS, '.open-remarks', function(){
    const category = $(this).data('category');
    const record   = $(this).data('record');
    const $modal   = $('#remarksModal');

    // Reset modal state
    $modal.find('#remarks-alert')
          .addClass('d-none')
          .removeClass('alert-success alert-danger alert-warning alert-info')
          .empty();

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

      // Load recipients list (Select2 if available)
      const $sel = $modal.find('#remark-recipients');

      if ($sel.data('select2')) {
        $sel.val(null).trigger('change');
        $sel.select2('destroy');
      }

      $sel.empty();

      $.getJSON('get-users.php', function(users){
        users.forEach(u => $sel.append(new Option(`${u.name} (${u.hris})`, u.hris)));

        if ($.fn.select2) {
          $sel.select2({
            dropdownParent: $modal,
            width: '100%',
            placeholder: 'Select recipients'
          });
        }
      }).always(function(){
        bootstrap.Modal.getOrCreateInstance($modal[0], { backdrop: 'static', keyboard: false }).show();
      });

    }, 'json').fail(function(){
      flash($('#page-alert'), 'danger', '❌ Failed to load remarks.');
    });
  });

  // Occasionally Bootstrap backdrops get stuck after AJAX navigation.
  // This keeps the UI from becoming unclickable.
  $('#contentArea').on('hidden.bs.modal' + NS, '#remarksModal', function(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
  });

  // Kick things off
  loadVehicleMaintenanceReport();

})();
</script>
