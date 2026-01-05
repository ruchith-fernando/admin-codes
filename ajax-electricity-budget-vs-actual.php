<?php
require_once 'connections/connection.php';

$selectedMonth = $_GET['month'] ?? date('F Y');
$budget_data = [];
$report = [];

if ($selectedMonth) {
    $budget_sql = "SELECT branch_code, branch_name AS branch, budget_month AS month, amount AS budgeted_amount FROM tbl_admin_budget_electricity WHERE budget_month = ?";
    $stmt = $conn->prepare($budget_sql);
    $stmt->bind_param("s", $selectedMonth);
    $stmt->execute();
    $budget_result = $stmt->get_result();

    while ($row = $budget_result->fetch_assoc()) {
        $key = trim($row['branch_code']) . '_' . date('F Y', strtotime($row['month']));
        $budget_data[$key] = $row;
    }

    $actual_sql = "SELECT branch_code, branch, month_applicable AS month, actual_units, total_amount AS actual_amount FROM tbl_admin_actual_electricity WHERE month_applicable = ?";
    $stmt = $conn->prepare($actual_sql);
    $stmt->bind_param("s", $selectedMonth);
    $stmt->execute();
    $actual_result = $stmt->get_result();

    while ($row = $actual_result->fetch_assoc()) {
        $key = trim($row['branch_code']) . '_' . date('F Y', strtotime($row['month']));
        $report[$key] = array_merge($budget_data[$key] ?? [
            'branch_code' => $row['branch_code'],
            'branch' => $row['branch'],
            'month' => $row['month'],
            'budgeted_amount' => 0,
            'actual_units' => 0
        ], $row);
    }

    foreach ($budget_data as $key => $row) {
        if (!isset($report[$key])) {
            $report[$key] = array_merge($row, [
                'actual_units' => 0,
                'actual_amount' => 0
            ]);
        }
    }

    usort($report, function ($a, $b) {
        return [$a['branch_code'], $a['month']] <=> [$b['branch_code'], $b['month']];
    });

    $cumulative = [];
    foreach ($report as &$row) {
        $code = $row['branch_code'];
        if (!isset($cumulative[$code])) {
            $cumulative[$code] = ['budget' => 0, 'actual' => 0];
        }
        $budget_amt = floatval($row['budgeted_amount']);
        $actual_amt = floatval($row['actual_amount']);
        $cumulative[$code]['budget'] += $budget_amt;
        $cumulative[$code]['actual'] += $actual_amt;
        $row['difference'] = $actual_amt - $budget_amt;
        $row['cumulative_budget'] = $cumulative[$code]['budget'];
        $row['cumulative_actual'] = $cumulative[$code]['actual'];
        $row['cumulative_diff'] = $row['cumulative_actual'] - $row['cumulative_budget'];
    }
    unset($row);
}
?>

<?php if (!empty($report)): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle text-center wide-table">
            <thead class="table-light">
                <tr>
                    <th>Branch Code</th>
                    <th>Branch</th>
                    <th>Month</th>
                    <th>Budgeted Amount</th>
                    <th>Units Consumed</th>
                    <th>Actual Amount</th>
                    <th>Difference</th>
                    <th>Cumulative Budget</th>
                    <th>Cumulative Actual</th>
                    <th>Cumulative Diff</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['branch_code']) ?></td>
                    <td class="text-start"><?= htmlspecialchars($row['branch']) ?></td>
                    <td><?= htmlspecialchars($row['month']) ?></td>
                    <td><?= number_format(floatval($row['budgeted_amount']), 2) ?></td>
                    <td><?= htmlspecialchars($row['actual_units']) ?></td>
                    <td><?= number_format(floatval($row['actual_amount']), 2) ?></td>
                    <td><?= number_format(floatval($row['difference']), 2) ?></td>
                    <td><?= number_format(floatval($row['cumulative_budget']), 2) ?></td>
                    <td><?= number_format(floatval($row['cumulative_actual']), 2) ?></td>
                    <td><?= number_format(floatval($row['cumulative_diff']), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-warning">No data found for <?= htmlspecialchars($selectedMonth) ?>.</div>
<?php endif; ?>
