<?php
require_once '../connections/connection.php';
header('Content-Type: application/json');

$month = $_POST['month'] ?? '';
$html = '';
$downloadUrl = '';

if ($month) {
    $budget_data = [];      // Expected branches (from budget)
    $report = [];           // Actual report rows (submitted)
    $submitted = [];        // Branches that submitted
    $missing_branches = []; // Will hold branches that didn’t submit

    // Fetch budgeted branches (expected list)
    $stmt = $conn->prepare("SELECT branch_code, branch, month_applicable AS month, no_of_shifts, rate, (no_of_shifts * rate) AS budgeted_amount FROM tbl_admin_budget_security WHERE month_applicable = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $key = trim($r['branch_code']) . '_' . date('F Y', strtotime($r['month']));
        $budget_data[$key] = $r;
    }

    // Fetch actuals
    $stmt = $conn->prepare("SELECT branch_code, branch, month_applicable AS month, actual_shifts, total_amount AS actual_amount FROM tbl_admin_actual_security WHERE month_applicable = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $key = trim($r['branch_code']) . '_' . date('F Y', strtotime($r['month']));
        // ✅ FIXED: include records where either shifts > 0 or amount > 0
        if ((int)$r['actual_shifts'] > 0 || (float)$r['actual_amount'] > 0) {
            $submitted[] = trim($r['branch_code']);
            $report[$key] = array_merge($budget_data[$key] ?? [
                'branch_code' => $r['branch_code'],
                'branch' => $r['branch'],
                'month' => $r['month'],
                'no_of_shifts' => 0,
                'rate' => 0,
                'budgeted_amount' => 0
            ], $r);
        }
    }


    // Figure out missing branches
    foreach ($budget_data as $key => $bd) {
        $branchCode = trim($bd['branch_code']);
        if (!in_array($branchCode, $submitted)) {
            $missing_branches[] = $branchCode . ' - ' . $bd['branch'];
        }
    }

    // Sort report by branch code
    usort($report, fn($a, $b) => [$a['branch_code'], $a['month']] <=> [$b['branch_code'], $b['month']]);

    // Cumulative totals
    $cumulative = [];
    foreach ($report as &$row) {
        $code = $row['branch_code'];
        $cumulative[$code]['budget'] = ($cumulative[$code]['budget'] ?? 0) + $row['budgeted_amount'];
        $cumulative[$code]['actual'] = ($cumulative[$code]['actual'] ?? 0) + $row['actual_amount'];
        $row['difference'] = $row['actual_amount'] - $row['budgeted_amount'];
        $row['cumulative_budget'] = $cumulative[$code]['budget'];
        $row['cumulative_actual'] = $cumulative[$code]['actual'];
        $row['cumulative_diff'] = $row['cumulative_actual'] - $row['cumulative_budget'];
    }

    ob_start();
    ?>

    <?php if (!empty($missing_branches)): ?>
        <div class="alert alert-warning">
            <strong><?= count($missing_branches) ?> branches</strong> have not submitted their security charges for <strong><?= htmlspecialchars($month) ?></strong>.<br>
            <small>Missing:</small> <?= implode(', ', array_map('htmlspecialchars', $missing_branches)) ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle text-center wide-table">
            <thead class="table-light">
                <tr>
                    <th>Branch Code</th>
                    <th>Branch</th>
                    <th>Month</th>
                    <th>Budgeted Shifts</th>
                    <th>Actual Shifts</th>
                    <th>Rate</th>
                    <th>Budgeted Amount</th>
                    <th>Actual Amount</th>
                    <th>Difference</th>
                    <th>Cumulative Budget</th>
                    <th>Cumulative Actual</th>
                    <th>Cumulative Diff</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['branch_code']) ?></td>
                    <td class="text-start"><?= htmlspecialchars($r['branch']) ?></td>
                    <td><?= htmlspecialchars($r['month']) ?></td>
                    <td><?= $r['no_of_shifts'] ?></td>
                    <td><?= $r['actual_shifts'] ?></td>
                    <td><?= number_format($r['rate'], 0) ?></td>
                    <td><?= number_format($r['budgeted_amount'], 0) ?></td>
                    <td><?= number_format($r['actual_amount'], 0) ?></td>
                    <td><?= number_format($r['difference'], 0) ?></td>
                    <td><?= number_format($r['cumulative_budget'], 0) ?></td>
                    <td><?= number_format($r['cumulative_actual'], 0) ?></td>
                    <td><?= number_format($r['cumulative_diff'], 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $html = ob_get_clean();
    $downloadUrl = 'download-monthly-report.php?month=' . urlencode($month);
}

echo json_encode([
    'html' => $html,
    'downloadUrl' => $downloadUrl
]);
exit;
