<?php
// ajax-photocopy-budget-report-table.php
require_once 'connections/connection.php';

$category = 'Photocopy';

// ✅ Financial Year
$months = [];
$start = new DateTime('2025-04-01');
$end   = new DateTime('2026-03-01');
while ($start <= $end) {
    $months[] = $start->format('F Y');
    $start->modify('+1 month');
}

$report = [];

foreach ($months as $month) {
    $month_esc    = mysqli_real_escape_string($conn, $month);
    $category_esc = mysqli_real_escape_string($conn, $category);

    // ✅ Actuals (show month only if there is actual > 0, to match Security behavior)
    $actual_row = $conn->query("
        SELECT SUM(total) AS actual_amount
        FROM tbl_admin_actual_photocopy
        WHERE record_date = '$month_esc'
    ")->fetch_assoc();
    $actual = $actual_row['actual_amount'] ?? 0;
    if ($actual <= 0) continue;

    // ✅ Budget (do NOT skip if 0 — match Security)
    $budget_row = $conn->query("
        SELECT SUM(budget_amount) AS budget_amount
        FROM tbl_admin_budget_photocopies
        WHERE month_year = '$month_esc'
    ")->fetch_assoc();
    $budget = $budget_row['budget_amount'] ?? 0;

    // ✅ Checkbox selection
    $selected_row = $conn->query("
        SELECT is_selected
        FROM tbl_admin_dashboard_month_selection
        WHERE category='$category_esc' AND month_name='$month_esc'
    ")->fetch_assoc();
    $selected = ($selected_row['is_selected'] ?? '') === 'yes' ? 'checked' : '';

    // ✅ Variance
    $difference = $budget - $actual;
    $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;

    // (Photocopy has no branch completion concept; keep the column for a 100% UI match)
    $report[] = [
        'month'      => $month,
        'budget'     => $budget,
        'actual'     => $actual,
        'difference' => $difference,
        'variance'   => $variance,
        'checked'    => $selected,
    ];
}
?>

<style>
/* Match Security: style the Bootstrap switch inside table cells */
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
        <th>Completion Status</th>
        <th>Select / Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $i = 1; $total_budget = $total_actual = $total_difference = 0;
      foreach ($report as $row):
        $total_budget     += $row['budget'];
        $total_actual     += $row['actual'];
        $total_difference += $row['difference'];
      ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['month']) ?></td>
        <td><?= number_format($row['budget'], 2) ?></td>
        <td><?= number_format($row['actual'], 2) ?></td>
        <td class="<?= $row['difference'] < 0 ? 'text-danger fw-bold' : '' ?>">
          <?= number_format($row['difference'], 2) ?>
        </td>
        <td class="<?= ($row['variance'] !== null && $row['variance'] < 0) ? 'text-danger fw-bold' : '' ?>">
          <?= $row['variance'] !== null ? $row['variance'].'%' : 'N/A' ?>
        </td>
        <!-- Photocopy: no branch completion metric; show en dash to match column structure -->
        <td class="text-muted">—</td>
        <td class="toggle-cell">
          <div class="toggle-wrap">
            <div class="form-check form-switch m-0">
              <input
                type="checkbox"
                class="form-check-input month-checkbox"
                role="switch"
                id="month_switch_<?= htmlspecialchars(str_replace(' ', '_', $row['month'])) ?>"
                data-category="<?= htmlspecialchars($category) ?>"
                data-month="<?= htmlspecialchars($row['month']) ?>"
                <?= $row['checked'] ?>>
            </div>

            <button class="btn btn-sm btn-outline-secondary open-remarks"
                    data-category="<?= htmlspecialchars($category) ?>"
                    data-record="<?= htmlspecialchars($row['month']) ?>">💬</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>

      <?php
      $total_variance = $total_budget > 0 ? round(($total_difference / $total_budget) * 100) : null;
      ?>
      <tr class="fw-bold table-light">
        <td colspan="2" class="text-end">Total</td>
        <td><?= number_format($total_budget, 2) ?></td>
        <td><?= number_format($total_actual, 2) ?></td>
        <td class="<?= $total_difference < 0 ? 'text-danger' : '' ?>">
          <?= number_format($total_difference, 2) ?>
        </td>
        <td></td>
        <td colspan="2"></td>
      </tr>
      <tr class="fw-bold table-light">
        <td colspan="5" class="text-end">Total Variance (%)</td>
        <td class="<?= ($total_variance !== null && $total_variance < 0) ? 'text-danger' : '' ?>">
          <?= $total_variance !== null ? $total_variance.'%' : 'N/A' ?>
        </td>
        <td colspan="2"></td>
      </tr>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
