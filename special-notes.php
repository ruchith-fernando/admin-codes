<?php
// special-notes.php
session_start();
if (!isset($_SESSION['hris']) || empty($_SESSION['hris'])) { echo '<div class="alert alert-warning m-3">Please log in.</div>'; exit; }
$hris = $_SESSION['hris'];

// pass through any search param if user arrived with ?q=
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
?>
<div class="content font-size bg-light">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="text-primary"><i class="fas fa-sticky-note me-2"></i> Special Notes</h5>
        <!-- <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button> -->
      </div>

      <div class="alert alert-success d-none" id="success-alert" role="alert">
        ✅ Action completed successfully!
      </div>

      <!-- Search -->
      <form class="d-flex mb-3" id="notes-search-form">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
               class="form-control me-2" placeholder="Search notes (category, record, SR, comment)…">
        <button class="btn btn-primary"><i class="fas fa-search me-1"></i> Search</button>
      </form>

      <!-- Table / results -->
      <div id="report-content">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
          <div>Loading notes…</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Note Details -->
<div class="modal fade" id="specialNoteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-sticky-note me-2"></i> Note Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="specialNoteModalBody">
          <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status"></div>
            <div>Loading…</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  /* make rows obviously clickable */
  .note-row.clickable { cursor: pointer; }
  .truncate{ display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
</style>

<script>
// Remove any legacy bindings for this page id
$(document)
  .off('click', '#update-selection')
  .off('click', '.open-remarks')
  .off('click', '#save-remark')
  .off('click', '#back-to-dashboard');

(function(){
  const NS = '.page.special-notes';

  function stripInlineScripts(html) {
    try { return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, ''); } catch(e){ return html; }
  }

  function loadNotes(urlParams) {
    $('#report-content').html(
      '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div>Loading notes…</div></div>'
    );
    $.get('special-notes-fetch.php' + (urlParams || ''), function(res){
      $('#report-content').html(stripInlineScripts(res));
      $('html, body').animate({ scrollTop: $('.card').offset().top }, 250);
    }).fail(function(){
      $('#report-content').html('<div class="alert alert-danger">Failed to load notes.</div>');
    });
  }

  // Initial load (preserve any ?q=)
  <?php $qs = $search !== '' ? ('?q='.urlencode($search)) : ''; ?>
  loadNotes('<?php echo $qs; ?>');

  // Back
  $('#contentArea').off('click' + NS, '#back-to-dashboard')
  .on('click' + NS, '#back-to-dashboard', function(){
    $('#contentArea').html('<div class="text-center p-4">Loading dashboard...</div>');
    $.get('dashboard.php', function(res){ $('#contentArea').html(res); });
  });

  // Search submit
  $('#contentArea').off('submit' + NS, '#notes-search-form')
  .on('submit' + NS, '#notes-search-form', function(e){
    e.preventDefault();
    const q = $.trim($(this).find('[name="q"]').val() || '');
    const u = q ? ('?q=' + encodeURIComponent(q)) : '';
    loadNotes(u);
  });

  // Pagination clicks
  $('#contentArea').off('click' + NS, '#report-content .pagination a')
  .on('click' + NS, '#report-content .pagination a', function(e){
    e.preventDefault();
    const href = $(this).attr('href') || '';
    const params = href.includes('?') ? href.substring(href.indexOf('?')) : '';
    loadNotes(params);
  });

  // "Read more" toggle
  $('#contentArea').off('click' + NS, '#report-content .js-readmore')
  .on('click' + NS, '#report-content .js-readmore', function(e){
    e.preventDefault();
    const link = this;
    const full = $(link).next('.full-text');
    if (!full.length) return;
    const hidden = full.hasClass('d-none');
    if (hidden) {
      full.removeClass('d-none');
      link.textContent = 'Show less';
    } else {
      full.addClass('d-none');
      link.textContent = 'Read more';
    }
  });

  $('#contentArea')
    .off('click' + NS, '#report-content .note-row.clickable')
    .on('click' + NS, '#report-content .note-row.clickable', function(e){
      e.preventDefault();
      const id    = $(this).data('id');
      const scope = $(this).data('scope') || 'inbox';
      if (!id) return;
      $('#specialNoteModal').modal('show');
      $('#specialNoteModalBody').html(
        '<div class="text-center p-4"><div class="spinner-border text-primary"></div><div>Loading…</div></div>'
      );
      $.get('special-notes-view.php', { id, scope }, function(res){
        $('#specialNoteModalBody').html(res);
      }).fail(function(){
        $('#specialNoteModalBody').html('<div class="alert alert-danger">Failed to load note.</div>');
      });
    });
  // Cleanup
  window.stopEverything = function(){ $('#contentArea').off(NS); };
})();
</script>
