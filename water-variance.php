<?php
// water-variance.php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Load distinct approved months
$months = [];
$res = mysqli_query($conn, "
    SELECT DISTINCT month_applicable
    FROM tbl_admin_actual_water
    WHERE approval_status = 'approved'
    ORDER BY STR_TO_DATE(month_applicable, '%M %Y') ASC
");
while ($r = mysqli_fetch_assoc($res)) {
    $months[] = $r['month_applicable'];
}
?>

<div class="content font-size">
  <div class="container-fluid">

    <div class="card shadow bg-white rounded p-4">

      <h5 class="text-primary mb-4">
        Water — Budget vs Actual (Variance Report)
      </h5>

      <!-- Month Selector -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month</label>
        <select id="variance_month" class="form-select">
            <option value="">-- Choose a month --</option>
            <?php foreach ($months as $m): ?>
              <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
            <?php endforeach; ?>
        </select>
      </div>

      <div id="variance_loading" class="alert alert-info d-none">Loading...</div>

      <div id="variance_report_section" class="table-responsive d-none"></div>

    </div>

  </div>
</div>

<script src="water-variance.js"></script>
