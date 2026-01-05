<?php
// photocopy-monthly-report.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Auto-detect FY (April → March)
$current_year  = (int)date('Y');
$current_month = (int)date('n');
$fy_start_year = ($current_month < 4) ? ($current_year - 1) : $current_year;
$fy_end_year   = $fy_start_year + 1;

// Build FY fixed month list (value = YYYY-MM-01)
$start = strtotime("{$fy_start_year}-04-01");
$end   = strtotime("{$fy_end_year}-03-01");
$fixed_months = [];
while ($start <= $end) {
    $fixed_months[] = [
        'val'   => date("Y-m-01", $start),
        'label' => date("F Y", $start),
    ];
    $start = strtotime("+1 month", $start);
}

// Load months that have actual data already (within FY)
$data_months = [];
$from = "{$fy_start_year}-04-01";
$to   = "{$fy_end_year}-03-31";

$sql = "
    SELECT DISTINCT month_applicable
    FROM tbl_admin_actual_photocopy
    WHERE month_applicable BETWEEN '{$from}' AND '{$to}'
      AND total_amount IS NOT NULL
      AND total_amount <> 0
    ORDER BY month_applicable DESC
";
$q = mysqli_query($conn, $sql);
if ($q) {
    while ($r = mysqli_fetch_assoc($q)) {
        $data_months[] = date("Y-m-01", strtotime($r['month_applicable']));
    }
}
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-4 text-primary">
        Photocopy — Monthly Budget vs Actual (<?= $fy_start_year ?>–<?= $fy_end_year ?>)
      </h5>

      <div class="mb-3 d-flex gap-2 align-items-end">
        <div style="min-width:280px; flex: 1;">
          <label class="form-label fw-bold">Select Month to View Report</label>
          <select id="pc_month_view" class="form-select">
            <option value="">-- Choose a Month --</option>
            <?php foreach ($fixed_months as $m): ?>
              <?php if (in_array($m['val'], $data_months, true)): ?>
                <option value="<?= htmlspecialchars($m['val']) ?>">
                  <?= htmlspecialchars($m['label']) ?>
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <button class="btn btn-primary" id="pc_download_csv_btn" type="button" disabled>
            Download CSV
          </button>
        </div>
      </div>


      <div id="pc_report_section" class="table-responsive d-none"></div>

    </div>
  </div>
</div>

<script src="photocopy-monthly-report.js?v=2"></script>
