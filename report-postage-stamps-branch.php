<?php
// report-postage-stamps-branch.php
include 'connections/connection.php';
$conn->set_charset('utf8mb4');

$title = "Postage & Stamps Branch Report (Actual vs Budget)";

// Months come ONLY from actuals table
$months = [];
$sqlMonths = "
  SELECT DISTINCT a.applicable_month AS m
  FROM tbl_admin_actual_branch_gl_postage a
  ORDER BY STR_TO_DATE(CONCAT('01 ', a.applicable_month), '%d %M %Y') DESC
";
$resMonths = mysqli_query($conn, $sqlMonths);
if ($resMonths) {
  while ($r = mysqli_fetch_assoc($resMonths)) {
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
            <option value="" selected disabled>-- Select Month --</option>
            <?php foreach ($months as $m): ?>
              <option value="<?= htmlspecialchars($m) ?>">
                <?= htmlspecialchars($m) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Branch Code (optional)</label>
          <input type="text" class="form-control" id="branchInput" placeholder="e.g. 001" disabled>
          <small class="text-muted">Select a month first to enable branch filter.</small>
        </div>
      </div>

      <!-- Start with an info message instead of table -->
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
    const m = $month.val();
    const b = $branch.val();

    if(!m){
      $wrap.html("<div class='alert alert-secondary mb-0'>Please select an <b>Applicable Month</b> to load the report.</div>");
      return;
    }

    $wrap.html("<div class='text-muted'>Loading...</div>");

    $.ajax({
      url: 'report-branch-expense-data.php',
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

    // When month selected: enable branch field + load report
    $month.on('change', function(){
      $branch.prop('disabled', false);
      fetchReport();
    });

    // Only fetch on branch typing if a month is selected
    $branch.on('keyup', function(){
      if(!$month.val()) return;
      clearTimeout(tmr);
      tmr = setTimeout(fetchReport, 250);
    });
  });

})(jQuery);
</script>
