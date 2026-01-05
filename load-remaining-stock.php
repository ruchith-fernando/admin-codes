<?php
// load-remaining-stock.php
include 'connections/connection.php';

$month = $_GET['month'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['query']) ? trim($_GET['query']) : '';
$limit = 10;
$offset = ($page - 1) * $limit;
$type = 'stock';

// Get latest tax rates
$taxQuery = $conn->query("SELECT * FROM tbl_admin_vat_sscl_rates ORDER BY effective_date DESC LIMIT 1");
$taxRow = $taxQuery->fetch_assoc();
$vatRate = $taxRow ? floatval($taxRow['vat_percentage']) / 100 : 0.18;
$ssclRate = $taxRow ? floatval($taxRow['sscl_percentage']) / 100 : 0.025;

$sql = "SELECT 
        si.item_code,
        pm.item_description,
        SUM(si.remaining_quantity) AS remaining_qty,
        SUM(si.remaining_quantity * si.unit_price) AS subtotal,
        SUM(si.unit_price) AS unit_price
    FROM tbl_admin_stationary_stock_in si
    LEFT JOIN tbl_admin_print_stationary_master pm ON si.item_code = pm.item_code
    WHERE DATE_FORMAT(si.received_date, '%Y-%m') <= '$month'";

if ($search !== '') {
    $sql .= " AND (si.item_code LIKE '%$search%' OR pm.item_description LIKE '%$search%')";
}

$sql .= " GROUP BY si.item_code";

$result = $conn->query($sql);
$allRows = [];
$totalQty = 0;
$totalWithTax = 0;

while ($row = $result->fetch_assoc()) {
    $qty = $row['remaining_qty'];
    $subtotal = $row['subtotal'];
    $sscl = $subtotal * $ssclRate;
    $vat = ($subtotal + $sscl) * $vatRate;
    $total = $subtotal + $sscl + $vat;

    $row['total_with_tax'] = $total;
    $allRows[] = $row;

    $totalQty += $qty;
    $totalWithTax += $total;
}

// Sort: non-zero first, then zero
usort($allRows, function($a, $b) {
    if ($a['remaining_qty'] == 0 && $b['remaining_qty'] > 0) return 1;
    if ($a['remaining_qty'] > 0 && $b['remaining_qty'] == 0) return -1;
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
                <th style="width: 20%;" class="text-end">Remaining Qty</th>
                <th style="width: 20%;" class="text-end">Value (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pagedData as $row): ?>
                <tr>
                    <td><?= $row['item_code'] ?></td>
                    <td><?= $row['item_description'] ?></td>
                    <td class="text-end"><?= number_format($row['remaining_qty']) ?></td>
                    <td class="text-end"><?= number_format($row['total_with_tax'], 2) ?></td>
                </tr>
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
