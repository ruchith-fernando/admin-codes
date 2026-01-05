<?php
include 'connections/connection.php';
$conn->set_charset('utf8mb4');

$title = "Newspaper Branch Report (Actual vs Budget)";

/**
 * Clean months (NBSP + CR/LF + trim)
 */
$monthExpr = "TRIM(REPLACE(REPLACE(REPLACE(applicable_month, CHAR(194,160), ' '), CHAR(13), ''), CHAR(10), ''))";

$months = [];
$sqlMonths = "
  SELECT DISTINCT $monthExpr AS m
  FROM tbl_admin_actual_branch_gl_newspaper
  WHERE $monthExpr <> ''
  ORDER BY STR_TO_DATE(CONCAT('01 ', $monthExpr), '%d %M %Y') DESC, m DESC
";

$resMonths = $conn->query($sqlMonths);
if ($resMonths) {
  while ($r = $resMonths->fetch_assoc()) {
    $months[] = $r['m'];
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
                <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
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

      <!-- Start with message instead of loading the table -->
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

  let tmr = null;

  function fetchReport(){
    const m = ($month.val() || '').trim();
    const b = ($branch.val() || '').trim();

    if(!m){
      $wrap.html("<div class='alert alert-secondary mb-0'>Please select an <b>Applicable Month</b> to load the report.</div>");
      return;
    }

    $wrap.html("<div class='text-muted'>Loading...</div>");

    $.ajax({
      url: 'report-branch-expense-data-newspaper.php',
      method: 'GET',
      data: { month: m, branch: b },
      dataType: 'html'
    })
    .done(html => $wrap.html(html))
    .fail(() => $wrap.html("<div class='alert alert-danger mb-0'>❌ Failed to load report.</div>"));
  }

  $(document).ready(function(){

    // DO NOT auto-load on page load
    // fetchReport();

    $month.on('change', function(){
      if(!$month.val()) return;
      $branch.prop('disabled', false);
      fetchReport();
    });

    $branch.on('keyup', function(){
      if(!$month.val()) return;
      clearTimeout(tmr);
      tmr = setTimeout(fetchReport, 250);
    });

  });

})(jQuery);
</script>
