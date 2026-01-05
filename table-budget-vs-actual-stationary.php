<?php
require_once 'connections/connection.php';
$category = 'Printing & Stationary';

// ✅ Fixed April–March financial year
$months = [];
$start = new DateTime('2025-04-01');
$end   = new DateTime('2026-03-01');
while ($start <= $end) {
    $months[] = $start->format('F Y');
    $start->modify('+1 month');
}

// Budget
$budgetData = [];
$res = $conn->query("SELECT month, budget_amount FROM tbl_admin_budget_stationary");
while ($row = $res->fetch_assoc()) {
    $budgetData[strtolower($row['month'])] = $row['budget_amount'];
}

// Actual
$actualData = [];
$res = $conn->query("
    SELECT DATE_FORMAT(issued_date, '%M %Y') AS month, SUM(total_cost) AS actual_amount
    FROM tbl_admin_stationary_stock_out
    WHERE dual_control_status = 'approved'
    GROUP BY DATE_FORMAT(issued_date, '%M %Y')
");
while ($row = $res->fetch_assoc()) {
    $actualData[strtolower($row['month'])] = $row['actual_amount'];
}

// Selected
$selectedStatus = [];
$res = $conn->query("SELECT month_name, is_selected FROM tbl_admin_dashboard_month_selection WHERE category='$category'");
while ($row = $res->fetch_assoc()) {
    $selectedStatus[strtolower($row['month_name'])] = $row['is_selected'];
}
?>
<style>
.table .form-switch { padding-left: 0; min-height: 0; }
.form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
.toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }
</style>

<div class="table-responsive">
  <table class="table table-bordered table-sm text-center wide-table">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Month</th>
        <th>Budgeted Amount (Rs)</th>
        <th>Actual Amount (Rs)</th>
        <th>Difference (Rs)</th>
        <th>Variance (%)</th>
        <th>Select / Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $i = 1; $total_budget = $total_actual = $total_difference = 0;
      foreach ($months as $month):
        $key = strtolower($month);
        if (!isset($actualData[$key]) || $actualData[$key] <= 0) continue;

        $budget     = $budgetData[$key] ?? 0;
        $actual     = $actualData[$key] ?? 0;
        $difference = $budget - $actual;
        $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;
        $checked    = ($selectedStatus[$key] ?? '') == 'yes' ? 'checked' : '';
      ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($month) ?></td>
        <td><?= number_format($budget, 2) ?></td>
        <td><?= number_format($actual, 2) ?></td>
        <td class="<?= $difference < 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format($difference, 2) ?></td>
        <td class="<?= ($variance !== null && $variance < 0) ? 'text-danger fw-bold' : '' ?>"><?= $variance !== null ? $variance.'%' : 'N/A' ?></td>
        <td class="toggle-cell">
          <div class="toggle-wrap">
            <div class="form-check form-switch m-0">
              <input type="checkbox" class="form-check-input month-checkbox"
                data-category="<?= htmlspecialchars($category) ?>"
                data-month="<?= htmlspecialchars($month) ?>"
                <?= $checked ?>>
            </div>
            <button class="btn btn-sm btn-outline-secondary open-remarks"
              data-category="<?= htmlspecialchars($category) ?>"
              data-record="<?= htmlspecialchars($month) ?>">💬</button>
          </div>
        </td>
      </tr>
      <?php 
        $total_budget     += $budget;
        $total_actual     += $actual;
        $total_difference += $difference;
      endforeach;

      $total_variance = $total_budget > 0 ? round(($total_difference / $total_budget) * 100) : null;
      ?>
      <tr class="fw-bold table-light">
        <td colspan="2" class="text-end">Total</td>
        <td><?= number_format($total_budget, 2) ?></td>
        <td><?= number_format($total_actual, 2) ?></td>
        <td class="<?= $total_difference < 0 ? 'text-danger' : '' ?>"><?= number_format($total_difference, 2) ?></td>
        <td></td><td></td>
      </tr>
      <tr class="fw-bold table-light">
        <td colspan="5" class="text-end">Total Variance (%)</td>
        <td class="<?= ($total_variance !== null && $total_variance < 0) ? 'text-danger' : '' ?>"><?= $total_variance !== null ? $total_variance.'%' : 'N/A' ?></td>
        <td></td>
      </tr>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>

<!-- Remarks Modal -->
<div class="modal fade" id="remarksModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Remarks for <span id="modalRecordLabel"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="remark-history" class="mb-3 border p-3 bg-light" style="max-height: 200px; overflow-y: auto;"></div>
        <textarea id="new-remark" class="form-control mb-2" rows="3" placeholder="Enter your remark..."></textarea>
        <input type="hidden" id="remark-category">
        <input type="hidden" id="remark-record">
        <button class="btn btn-success" id="save-remark">Save Remark</button>
      </div>
    </div>
  </div>
</div>
