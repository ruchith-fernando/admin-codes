<div class="content font-size bg-light">
<div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="text-primary">Printing & Stationary Budget vs Actual</h5>
            <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button>
        </div>

        <!-- ✅ Success alert -->
        <div class="alert alert-success d-none" id="success-alert" role="alert">
            ✅ Dashboard selection updated successfully!
        </div>

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
  $(document)
    .off('click', '#update-selection')
    .off('click', '.open-remarks')
    .off('click', '#save-remark')
    .off('click', '#back-to-dashboard');
</script>

<script>
(function(){
  const NS = '.page.stationary';

  function stripInlineScripts(html) {
    try { return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, ''); } catch(e){ return html; }
  }

  function loadStationaryReport() {
    $('#report-content').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><div>Loading report...</div></div>');
    $.get('table-budget-vs-actual-stationary.php', function(res){
      $('#report-content').html(stripInlineScripts(res));
      $('html, body').animate({ scrollTop: $('.card').offset().top }, 300);
    }).fail(function(){
      $('#report-content').html('<div class="alert alert-danger">Failed to load report.</div>');
    });
  }

  $('#contentArea').off(NS);

  $('#contentArea').on('click' + NS, '#back-to-dashboard', function(){
    $('#contentArea').html('<div class="text-center p-4">Loading dashboard...</div>');
    $.get('dashboard.php', function(res){ $('#contentArea').html(res); });
  });

  $('#contentArea').on('click' + NS, '#update-selection', function(){
    if (!validateMonthSelection()) return;

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
        loadStationaryReport();
      } else {
        showBootstrapAlert('❌ Failed to update: ' + (res.message || 'Unknown error'));
      }
    }, 'json').fail(function(){
      showBootstrapAlert('❌ Server error occurred while updating.');
    });
  });

  $('#contentArea').on('click' + NS, '.open-remarks', function(){
    const category = $(this).data('category');
    const record   = $(this).data('record');

    $('#remark-category').val(category);
    $('#remark-record').val(record);
    $('#modalRecordLabel').text(record);
    $('#new-remark').val('');
    $('#remark-history').html('Loading...');

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
      $('#remark-history').html(html);
      const modal = new bootstrap.Modal(document.getElementById('remarksModal'));
      modal.show();
    }, 'json');
  });

  $('#contentArea').on('click' + NS, '#save-remark', function(){
    const category = $('#remark-category').val();
    const record   = $('#remark-record').val();
    const comment  = $('#new-remark').val();

    if (comment.trim() === '') return alert('Please enter a comment.');

    $.post('save-remark.php', { category, record, comment }, function(response){
      if (response.status === 'success') {
        $('.open-remarks[data-record="'+record+'"]').trigger('click');
      }
    }, 'json');
  });

  window.stopEverything = function(){ $('#contentArea').off(NS); };

  loadStationaryReport();
})();
</script>
