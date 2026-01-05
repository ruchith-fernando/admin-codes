<?php
include 'connections/connection.php';

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$search = isset($_GET['query']) ? trim($_GET['query']) : '';

// Get tax rates
$taxQuery = $conn->query("SELECT * FROM tbl_admin_vat_sscl_rates ORDER BY effective_date DESC LIMIT 1");
$taxRow = $taxQuery->fetch_assoc();
$vatRate = $taxRow ? floatval($taxRow['vat_percentage']) / 100 : 0.18;
$ssclRate = $taxRow ? floatval($taxRow['sscl_percentage']) / 100 : 0.025;

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=stock-summary-$month.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Remaining Stock SQL
$stockSql = "
    SELECT 
        si.item_code,
        pm.item_description,
        SUM(si.remaining_quantity) AS remaining_qty,
        SUM(si.remaining_quantity * si.unit_price) AS subtotal
    FROM tbl_admin_stationary_stock_in si
    LEFT JOIN tbl_admin_print_stationary_master pm ON si.item_code = pm.item_code
    WHERE DATE_FORMAT(si.received_date, '%Y-%m') <= '$month'";
if ($search !== '') {
    $stockSql .= " AND (si.item_code LIKE '%$search%' OR pm.item_description LIKE '%$search%')";
}
$stockSql .= " GROUP BY si.item_code";
$stockValue = $conn->query($stockSql);

// Spent SQL
$spentSql = "
    SELECT 
        so.item_code,
        pm.item_description,
        SUM(so.quantity) AS total_issued,
        SUM(so.total_cost) AS subtotal
    FROM tbl_admin_stationary_stock_out so
    LEFT JOIN tbl_admin_print_stationary_master pm ON so.item_code = pm.item_code
    WHERE DATE_FORMAT(so.issued_date, '%Y-%m') = '$month'";
if ($search !== '') {
    $spentSql .= " AND (so.item_code LIKE '%$search%' OR pm.item_description LIKE '%$search%')";
}
$spentSql .= " GROUP BY so.item_code";
$spentItems = $conn->query($spentSql);

// Prepare spent data with branch breakdown
$spentData = [];
while ($row = $spentItems->fetch_assoc()) {
    $itemCode = $row['item_code'];
    $spentData[$itemCode] = [
        'item_description' => $row['item_description'],
        'total_issued' => $row['total_issued'],
        'subtotal' => $row['subtotal'],
        'branches' => []
    ];

    $branchQuery = "
        SELECT 
            branch_code,
            branch_name,
            SUM(quantity) AS branch_qty,
            SUM(total_cost) AS branch_cost
        FROM tbl_admin_stationary_stock_out
        WHERE DATE_FORMAT(issued_date, '%Y-%m') = '$month'
        AND item_code = '$itemCode'";
    if ($search !== '') {
        $branchQuery .= " AND (item_code LIKE '%$search%' OR branch_name LIKE '%$search%')";
    }
    $branchQuery .= " GROUP BY branch_code, branch_name";
    $branchRes = $conn->query($branchQuery);

    while ($b = $branchRes->fetch_assoc()) {
        $bSub = $b['branch_cost'];
        $bSSCL = $bSub * $ssclRate;
        $bVAT = ($bSub + $bSSCL) * $vatRate;
        $bTotal = $bSub + $bSSCL + $bVAT;

        $spentData[$itemCode]['branches'][] = [
            'branch_code' => $b['branch_code'],
            'branch_name' => $b['branch_name'],
            'branch_qty' => $b['branch_qty'],
            'branch_total' => $bTotal
        ];
    }
}
?>

<!-- Remaining Stock Table -->
<table border="1">
    <tr><th colspan="4">Remaining Stock Value as of <?= $month ?></th></tr>
    <tr>
        <th>Item Code</th>
        <th>Description</th>
        <th>Remaining Qty</th>
        <th>Value (Rs.)</th>
    </tr>
<?php
$totalRemainingQty = 0;
$totalStockValue = 0;
while ($row = $stockValue->fetch_assoc()):
    $subtotal = $row['subtotal'];
    $sscl = $subtotal * $ssclRate;
    $vat = ($subtotal + $sscl) * $vatRate;
    $total = $subtotal + $sscl + $vat;

    $totalRemainingQty += $row['remaining_qty'];
    $totalStockValue += $total;
?>
    <tr>
        <td><?= $row['item_code'] ?></td>
        <td><?= $row['item_description'] ?></td>
        <td><?= number_format($row['remaining_qty']) ?></td>
        <td><?= number_format($total, 2) ?></td>
    </tr>
<?php endwhile; ?>
    <tr>
        <td colspan="2"><strong>Total</strong></td>
        <td><strong><?= number_format($totalRemainingQty) ?></strong></td>
        <td><strong><?= number_format($totalStockValue, 2) ?></strong></td>
    </tr>
</table>

<br><br>

<!-- Spent Table -->
<table border="1">
    <tr><th colspan="4">Total Spent in <?= $month ?></th></tr>
    <tr>
        <th>Item Code</th>
        <th>Description / Branch</th>
        <th>Qty Issued</th>
        <th>Spent (Rs.)</th>
    </tr>
<?php
$totalIssuedQty = 0;
$totalSpent = 0;

foreach ($spentData as $itemCode => $data):
    $sscl = $data['subtotal'] * $ssclRate;
    $vat = ($data['subtotal'] + $sscl) * $vatRate;
    $totalWithTax = $data['subtotal'] + $sscl + $vat;

    $totalIssuedQty += $data['total_issued'];
    $totalSpent += $totalWithTax;
?>
    <tr style="font-weight:bold;">
        <td><?= $itemCode ?></td>
        <td><?= $data['item_description'] ?></td>
        <td><?= number_format($data['total_issued']) ?></td>
        <td><?= number_format($totalWithTax, 2) ?></td>
    </tr>
    <?php foreach ($data['branches'] as $branch): ?>
    <tr>
        <td></td>
        <td>↳ <?= $branch['branch_code'] ?> - <?= $branch['branch_name'] ?></td>
        <td><?= number_format($branch['branch_qty']) ?></td>
        <td><?= number_format($branch['branch_total'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
<?php endforeach; ?>
    <tr>
        <td colspan="2"><strong>Total</strong></td>
        <td><strong><?= number_format($totalIssuedQty) ?></strong></td>
        <td><strong><?= number_format($totalSpent, 2) ?></strong></td>
    </tr>
</table>
