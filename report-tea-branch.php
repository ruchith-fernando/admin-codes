<?php
// report-tea-branch.php
include 'connections/connection.php';

$conn->set_charset('utf8mb4');

$title = $title ?? 'Tea Expense - Branch Report';

/**
 * Pull months ONLY from the ACTUAL table (distinct applicable_month).
 * Clean up NBSP + CR/LF and trim.
 */
$monthExpr = "TRIM(REPLACE(REPLACE(REPLACE(applicable_month, CHAR(194,160), ' '), CHAR(13), ''), CHAR(10), ''))";

$months = [];
$sqlMonths = "
  SELECT DISTINCT $monthExpr AS m
  FROM tbl_admin_actual_branch_gl_tea
  WHERE $monthExpr <> ''
  ORDER BY
    STR_TO_DATE(CONCAT('01 ', $monthExpr), '%d %M %Y') DESC,
    m DESC
";

$resMonths = $conn->query($sqlMonths);
if ($resMonths) {
  while ($row = $resMonths->fetch_assoc()) {
    $months[] = $row['m'];
  }
}

// IMPORTANT: do NOT preselect a month
$defaultMonth = '';
?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="text-primary mb-3"><?= htmlspecialchars($title) ?></h5>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label">Applicable Month</label>
          <select class="form-select" id="monthSelect">
            <?php if (!$months): ?>
              <option value="" selected>No months found</option>
            <?php else: ?>
              <option value="" selected disabled>-- Select Month --</option>
              <?php foreach ($months as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>">
                  <?= htmlspecialchars($m) ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Branch Code (optional)</label>
          <input type="text" class="form-control" id="branchInput" placeholder="e.g. 001" disabled>
          <small class="text-muted">Select a month first to enable branch filter.</small>
        </div>
      </div>

      <div class="d-flex gap-2 mb-3">
        <a class="btn btn-sm btn-success disabled" id="csvBtn" href="#" target="_self" rel="noopener" aria-disabled="true">
          ⬇️ Download CSV
        </a>
      </div>

      <!-- Start with message, not report -->
      <div id="reportWrapper">
        <div class="alert alert-secondary mb-0">
          Please select an <b>Applicable Month</b> to load the report.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function($){
  'use strict';

  const $wrap   = $('#reportWrapper');
  const $month  = $('#monthSelect');
  const $branch = $('#branchInput');
  const $csvBtn = $('#csvBtn');

  let t = null;

  function setCsvDisabled(disabled){
    if(disabled){
      $csvBtn.attr('href', '#')
             .addClass('disabled')
             .attr('aria-disabled', 'true');
    }else{
      $csvBtn.removeClass('disabled')
             .attr('aria-disabled', 'false');
    }
  }

  function updateCsvLink(){
    const m = ($month.val() || '').trim();
    const b = ($branch.val() || '').trim();

    if(!m){
      setCsvDisabled(true);
      return;
    }

    const qs = $.param({ month: m, branch: b });
    $csvBtn.attr('href', 'report-branch-expense-download-tea.php?' + qs);
    setCsvDisabled(false);
  }

  function fetchReport(){
    const m = ($month.val() || '').trim();
    const b = ($branch.val() || '').trim();

    updateCsvLink();

    if(!m){
      $wrap.html("<div class='alert alert-secondary mb-0'>Please select an <b>Applicable Month</b> to load the report.</div>");
      return;
    }

    $wrap.html("<div class='text-muted'>Loading...</div>");

    $.ajax({
      url: 'report-branch-expense-data-tea.php',
      method: 'GET',
      data: { month: m, branch: b },
      dataType: 'html'
    })
    .done(function(html){ $wrap.html(html); })
    .fail(function(){
      $wrap.html("<div class='alert alert-danger mb-0'>❌ Failed to load report.</div>");
    });
  }

  $(document).ready(function(){

    // DO NOT auto-load report on page load
    // fetchReport();

    // initial CSV disabled
    setCsvDisabled(true);

    // When month selected: enable branch + load report + enable CSV
    $month.on('change', function(){
      if(!$month.val()) return;
      $branch.prop('disabled', false);
      fetchReport();
    });

    // Only fetch on branch typing if month selected
    $branch.on('keyup', function(){
      if(!$month.val()) return;
      clearTimeout(t);
      t = setTimeout(fetchReport, 250);
    });

  });

})(jQuery);
</script>
