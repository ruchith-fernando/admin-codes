<?php
require_once 'connections/connection.php';

$selectedMonth = $_GET['month'] ?? date('F Y');

// Code sets
$BUNGALOW_CODES = ['2022','2017','2018','2019'];
$YARD_CODES     = ['2023','2023-1','2003','2004','2024','2007','2025','2001','2008','2012'];
$CHARGING_POINT = '2009'; // Charging Point - Ananda Coomaraswamy Mw

// Helper to coerce numbers that may contain commas/spaces
function to_num($v): float {
    return (float) str_replace([',',' '], '', (string)$v);
}

$stmt = $conn->prepare("
    SELECT 
        a.branch_code,
        b.branch_name AS branch,
        b.account_no,
        a.bill_from_date,
        a.bill_to_date,
        a.total_amount,
        a.number_of_days,
        a.actual_units,
        a.paid_amount
    FROM tbl_admin_actual_electricity a
    LEFT JOIN tbl_admin_branch_electricity b ON a.branch_code = b.branch_code
    WHERE a.month_applicable = ?
    ORDER BY a.branch_code
");
$stmt->bind_param("s", $selectedMonth);
$stmt->execute();
$result = $stmt->get_result();

$records_branches = [];
$records_CP       = []; // Charging Point
$records_B        = []; // Bungalows
$records_Y        = []; // Yards

$totals = [
    'branches' => ['total_amount' => 0.0, 'units' => 0.0, 'paid_amount' => 0.0],
    'CP'       => ['total_amount' => 0.0, 'units' => 0.0, 'paid_amount' => 0.0],
    'B'        => ['total_amount' => 0.0, 'units' => 0.0, 'paid_amount' => 0.0],
    'Y'        => ['total_amount' => 0.0, 'units' => 0.0, 'paid_amount' => 0.0],
];

while ($row = $result->fetch_assoc()) {
    $code  = trim((string)$row['branch_code']);
    $amt   = to_num($row['total_amount']);
    $units = to_num($row['actual_units']);
    $paid  = to_num($row['paid_amount']);

    if ($code === $CHARGING_POINT) {
        $records_CP[] = $row;
        $totals['CP']['total_amount'] += $amt;
        $totals['CP']['units']        += $units;
        $totals['CP']['paid_amount']  += $paid;
    } elseif (in_array($code, $BUNGALOW_CODES, true)) {
        $records_B[] = $row;
        $totals['B']['total_amount'] += $amt;
        $totals['B']['units']        += $units;
        $totals['B']['paid_amount']  += $paid;
    } elseif (in_array($code, $YARD_CODES, true)) {
        $records_Y[] = $row;
        $totals['Y']['total_amount'] += $amt;
        $totals['Y']['units']        += $units;
        $totals['Y']['paid_amount']  += $paid;
    } else {
        $records_branches[] = $row;
        $totals['branches']['total_amount'] += $amt;
        $totals['branches']['units']        += $units;
        $totals['branches']['paid_amount']  += $paid;
    }
}

// Natural sort by branch_code for all groups
$natcmp = fn($a,$b) => strnatcasecmp((string)$a['branch_code'], (string)$b['branch_code']);
usort($records_branches, $natcmp);
usort($records_CP,       $natcmp);
usort($records_B,        $natcmp);
usort($records_Y,        $natcmp);

function renderElectricityTable($title, $records, $totals) {
    ?>
    <div class="mb-5">
        <?php if ($title !== ''): ?>
            <h5 class="mt-4 mb-2"><?= htmlspecialchars($title) ?></h5>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm wide-table text-center align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Branch Code</th>
                        <th>Branch</th>
                        <th>Account No</th>
                        <th>Bill From Date</th>
                        <th>To Bill</th>
                        <th>Total Amount</th>
                        <th>No. of Days</th>
                        <th>Units</th>
                        <th>Paid Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No records found in this category.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['branch_code']) ?></td>
                                <td class="text-start"><?= htmlspecialchars($row['branch']) ?></td>
                                <td><?= htmlspecialchars($row['account_no']) ?></td>
                                <td><?= htmlspecialchars($row['bill_from_date']) ?></td>
                                <td><?= htmlspecialchars($row['bill_to_date']) ?></td>
                                <td><?= number_format(to_num($row['total_amount']), 2) ?></td>
                                <td><?= htmlspecialchars($row['number_of_days']) ?></td>
                                <td><?= number_format(to_num($row['actual_units'])) ?></td>
                                <td><?= number_format(to_num($row['paid_amount']), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="text-end fw-bold">
            Total Amount: <?= number_format($totals['total_amount'], 2) ?> |
            Total Units: <?= number_format($totals['units']) ?> |
            <!-- Total Cheque Amount: <?= number_format($totals['paid_amount'], 2) ?> -->
        </div>
    </div>
    <?php
}
?>

<div class="col-md-12">
    <h5 class="mb-2 text-primary">Branches</h5>
    <?php renderElectricityTable('', $records_branches, $totals['branches']); ?>
</div>

<div class="col-md-12">
    <h5 class="mb-2 text-primary">Charging Point - Ananda Coomaraswamy Mw</h5>
    <?php renderElectricityTable('', $records_CP, $totals['CP']); ?>
</div>

<div class="col-md-12">
    <h5 class="mb-2 text-primary">Bungalow</h5>
    <?php renderElectricityTable('', $records_B, $totals['B']); ?>
</div>

<div class="col-md-12">
    <h5 class="mb-2 text-primary">Yards</h5>
    <?php renderElectricityTable('', $records_Y, $totals['Y']); ?>
</div>
