// vehicle-approvals-pro.js  (jQuery + Bootstrap 5)
(function (w, $) {
  if (!$) { console.error('jQuery not found'); return; }

  // ------- helpers -------

  // Ensure alert container exists at runtime
  function ensureAlertContainer() {
    if (!$('#proAlertContainer').length) {
      $('body').append(`
        <div id="proAlertContainer"
             class="position-fixed top-0 start-50 translate-middle-x mt-3"
             style="z-index: 2000; width: auto; pointer-events: none;"></div>
      `);
    }
  }

  // Generic alert function (auto-fading)
  function showAlert(message, type = 'success') {
    ensureAlertContainer();
    const alertId = 'alert-' + Date.now();
    const alertHtml = `
      <div id="${alertId}"
           class="alert alert-${type} alert-dismissible fade show shadow"
           role="alert"
           style="min-width: 300px; pointer-events: auto; margin-bottom: .5rem;">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
    $('#proAlertContainer').append(alertHtml);

    // Fade out smoothly after 5 seconds
    setTimeout(() => {
      const el = document.getElementById(alertId);
      if (el) {
        $(el).fadeOut(400, function () { $(this).remove(); });
      }
    }, 5000);
  }

  // Success message (replaces modal)
  function showOk(sr, verb) {
    const msg = `SR <b>${sr || ''}</b> was <span class="text-${verb === 'approved' ? 'success' : 'danger'}">${verb}</span> successfully.`;
    showAlert(msg, verb === 'approved' ? 'success' : 'danger');
  }

  // Error message (replaces modal)
  function showErr(html) {
    showAlert(html || 'Unexpected error.', 'danger');
  }

  // AJAX helper
  function j(url, data) {
    return $.ajax({ url, method: 'POST', data, dataType: 'json', timeout: 20000 });
  }

  // ------- module -------

  var curId = '', curType = '', curSr = '';

  function loadType(type, pSel, rSel) {
    $(pSel).html('<div class="small text-muted">Loading…</div>');
    $(rSel).empty();
    j('vehicle-approvals-pro-fetch.php', { type: type })
      .done(function (res) {
        $(pSel).html('<h6 class="mb-2">Pending</h6>' + (res.pending || '<div class="alert alert-secondary">No pending.</div>'));
        $(rSel).html('<h6 class="mb-2 mt-4">Rejected</h6>' + (res.rejected || '<div class="alert alert-secondary">No rejected.</div>'));
      })
      .fail(function (xhr) {
        showErr('Failed to load ' + type + '.<br><small>' + xhr.status + ' ' + xhr.statusText + '</small>');
      });
  }

  function loadAll() {
    loadType('maintenance', '#proMtPending', '#proMtRejected');
    loadType('service', '#proSvPending', '#proSvRejected');
    loadType('license', '#proLcPending', '#proLcRejected');
  }

  function openView(id, type, sr) {
    curId = id; curType = type; curSr = sr || '';
    $('#proApprSr').text(sr || '');
    $('#proApprActions').show();
    $('#proRejectWrap').hide();
    $('#proRejectReason').val('');
    $('#proRejectOther').val('').hide();

    j('vehicle-approvals-pro-view.php', { id: id, type: type })
      .done(function (res) {
        $('#proApprBody').html(res.html || '');
        new bootstrap.Modal(document.getElementById('proApprModal')).show();
      })
      .fail(function (xhr) {
        showErr('Failed to load details.<br><small>' + xhr.status + ' ' + xhr.statusText + '</small>');
      });
  }

  // ------- public API -------

  w.ApprovalsPro = {
    loadAll: loadAll,
    initFragment: function (root) {
      // delegate "View & Approve" (tables are dynamic)
      $(root).off('click', '.pro-js-view').on('click', '.pro-js-view', function () {
        openView($(this).data('id'), $(this).data('type'), $(this).data('sr'));
      });

      // reject flow
      $(root).off('click', '#proBtnReject').on('click', '#proBtnReject', function () {
        $('#proApprActions').hide();
        $('#proRejectWrap').show();
      });

      $(root).off('change', '#proRejectReason').on('change', '#proRejectReason', function () {
        $('#proRejectOther').toggle($(this).val() === 'Other');
      });

      $(root).off('click', '#proBtnRejectConfirm').on('click', '#proBtnRejectConfirm', function () {
        var reason = $('#proRejectReason').val();
        if (!reason) return showErr('Please select a rejection reason.');
        if (reason === 'Other') {
          reason = $.trim($('#proRejectOther').val());
          if (!reason) return showErr('Please enter the other reason.');
        }
        j('vehicle-approvals-pro-actions.php', { action: 'reject', id: curId, type: curType, reason: reason })
          .done(function (res) {
            if (res && res.status === 'success') {
              bootstrap.Modal.getInstance(document.getElementById('proApprModal')).hide();
              showOk(curSr, 'rejected');
              loadAll();
            } else {
              showErr((res && res.message) || 'Reject failed.');
            }
          })
          .fail(function (xhr) {
            showErr('Reject failed.<br><small>' + xhr.status + ' ' + xhr.statusText + '</small>');
          });
      });

      // approve
      $(root).off('click', '#proBtnApprove').on('click', '#proBtnApprove', function () {
        j('vehicle-approvals-pro-actions.php', { action: 'approve', id: curId, type: curType })
          .done(function (res) {
            if (res && res.status === 'success') {
              bootstrap.Modal.getInstance(document.getElementById('proApprModal')).hide();
              showOk(curSr, 'approved');
              loadAll();
            } else {
              showErr((res && res.message) || 'Approve failed.');
            }
          })
          .fail(function (xhr) {
            showErr('Approve failed.<br><small>' + xhr.status + ' ' + xhr.statusText + '</small>');
          });
      });
    }
  };
})(window, window.jQuery);
