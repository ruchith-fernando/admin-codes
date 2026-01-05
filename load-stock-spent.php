<?php
// load-stock-spent.php (updated to use approved_quantity and dual_control_status = 'approved')
include 'connections/connection.php';

$month = $_GET['month'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['query']) ? trim($_GET['query']) : '';
$limit = 10;
$offset = ($page - 1) * $limit;
$type = 'spent';

// Get latest tax rates
$taxQuery = $conn->query("SELECT * FROM tbl_admin_vat_sscl_rates ORDER BY effective_date DESC LIMIT 1");
$taxRow = $taxQuery->fetch_assoc();
$vatRate = $taxRow ? floatval($taxRow['vat_percentage']) / 100 : 0.18;
$ssclRate = $taxRow ? floatval($taxRow['sscl_percentage']) / 100 : 0.025;

// Main item totals
$sql = "SELECT 
        so.item_code,
        pm.item_description,
        SUM(so.approved_quantity) AS total_issued,
        SUM(so.total_cost) AS subtotal
    FROM tbl_admin_stationary_stock_out so
    LEFT JOIN tbl_admin_print_stationary_master pm ON so.item_code = pm.item_code
    WHERE DATE_FORMAT(so.issued_date, '%Y-%m') = '$month'
    AND so.dual_control_status = 'approved'";

if ($search !== '') {
    $sql .= " AND (so.item_code LIKE '%$search%' OR pm.item_description LIKE '%$search%')";
}

$sql .= " GROUP BY so.item_code";

$result = $conn->query($sql);
$allRows = [];
$totalQty = 0;
$totalWithTax = 0;

while ($row = $result->fetch_assoc()) {
    $itemCode = $row['item_code'];
    $subtotal = $row['subtotal'];
    $sscl = $subtotal * $ssclRate;
    $vat = ($subtotal + $sscl) * $vatRate;
    $total = $subtotal + $sscl + $vat;

    // Fetch branch breakdown for this item
    $branchSql = "SELECT 
                    so.branch_code,
                    so.branch_name,
                    SUM(so.approved_quantity) AS branch_qty,
                    SUM(so.total_cost) AS branch_cost
                  FROM tbl_admin_stationary_stock_out so
                  WHERE DATE_FORMAT(so.issued_date, '%Y-%m') = '$month'
                  AND so.item_code = '$itemCode'
                  AND so.dual_control_status = 'approved'
                  GROUP BY so.branch_code, so.branch_name";
    $branchRes = $conn->query($branchSql);
    $branches = [];
    while ($b = $branchRes->fetch_assoc()) {
        $bSub = $b['branch_cost'];
        $bSSCL = $bSub * $ssclRate;
        $bVAT = ($bSub + $bSSCL) * $vatRate;
        $bTotal = $bSub + $bSSCL + $bVAT;

        $branches[] = [
            'branch_code' => $b['branch_code'],
            'branch_name' => $b['branch_name'],
            'branch_qty' => $b['branch_qty'],
            'branch_total' => $bTotal
        ];
    }

    $row['total_with_tax'] = $total;
    $row['branches'] = $branches;
    $allRows[] = $row;

    $totalQty += $row['total_issued'];
    $totalWithTax += $total;
}

// Sort items: non-zero issued first
usort($allRows, function($a, $b) {
    if ($a['total_issued'] == 0 && $b['total_issued'] > 0) return 1;
    if ($a['total_issued'] > 0 && $b['total_issued'] == 0) return -1;
    return 0;
});

$pagedData = array_slice($allRows, $offset, $limit);
?>
<div class = "table-responsive">
     <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th style="width: 20%;">Item Code</th>
                <th style="width: 40%;">Description</th>
                <th style="width: 20%;" class="text-end">Qty Issued</th>
                <th style="width: 20%;" class="text-end">Spent (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pagedData as $row): ?>
                <tr class="fw-bold">
                    <td><?= $row['item_code'] ?></td>
                    <td><?= $row['item_description'] ?></td>
                    <td class="text-end"><?= number_format($row['total_issued']) ?></td>
                    <td class="text-end"><?= number_format($row['total_with_tax'], 2) ?></td>
                </tr>
                <?php foreach ($row['branches'] as $branch): ?>
                    <tr class="text-muted small">
                        <td></td>
                        <td>↳ <?= $branch['branch_code'] ?> - <?= $branch['branch_name'] ?></td>
                        <td class="text-end"><?= number_format($branch['branch_qty']) ?></td>
                        <td class="text-end"><?= number_format($branch['branch_total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="fw-bold table-light">
                <td colspan="2" class="text-end">Total:</td>
                <td class="text-end"><?= number_format($totalQty) ?></td>
                <td class="text-end"><?= number_format($totalWithTax, 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php
$totalPages = ceil(count($allRows) / $limit);
if ($totalPages > 1):
    $range = 3;
    $startPage = max(1, $page - 1);
    $endPage = min($totalPages, $startPage + $range - 1);
    if ($endPage - $startPage < $range - 1) {
        $startPage = max(1, $endPage - $range + 1);
    }
?>
<nav>
    <ul class="pagination pagination-sm justify-content-end">
        <?php if ($page > 1): ?>
            <li class="page-item"><a href="#" class="page-link paginate-<?= $type ?>" data-page="1">First</a></li>
            <li class="page-item"><a href="#" class="page-link paginate-<?= $type ?>" data-page="<?= $page - 1 ?>">Previous</a></li>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a href="#" class="page-link paginate-<?= $type ?>" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <li class="page-item"><a href="#" class="page-link paginate-<?= $type ?>" data-page="<?= $page + 1 ?>">Next</a></li>
            <li class="page-item"><a href="#" class="page-link paginate-<?= $type ?>" data-page="<?= $totalPages ?>">Last</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
