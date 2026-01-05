<?php
require_once 'connections/connection.php';

$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 10;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Base WHERE condition
$searchCondition = "WHERE status = 'Approved'";
$params = [];
$types  = "";

// Add search filters
if (!empty($search)) {
    $search = "%$search%";
    $searchCondition .= " AND (
        make_model LIKE ? OR 
        vehicle_number LIKE ? OR 
        assigned_user LIKE ? OR 
        assigned_user_hris LIKE ? OR 
        vehicle_type LIKE ? OR 
        chassis_number LIKE ?
    )";
    $params = array_fill(0, 6, $search);
    $types  = str_repeat("s", 6);
}

// Inline LIMIT/OFFSET (safe because casted as ints)
$query = "
    SELECT *
    FROM tbl_admin_vehicle
    $searchCondition
    ORDER BY purchase_date DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Count total rows
$totalQuery = "SELECT COUNT(*) AS total FROM tbl_admin_vehicle $searchCondition";
$totalStmt = $conn->prepare($totalQuery);
if (!empty($search)) {
    $totalStmt->bind_param($types, ...$params);
}
$totalStmt->execute();
$totalRows = (int)$totalStmt->get_result()->fetch_assoc()['total'];
$totalPages = (int)ceil($totalRows / $limit);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="text-primary mb-0">Approved Vehicle List</h5>
    <a href="download-vehicles-excel.php" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-excel"></i> Download Excel
    </a>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle table-sm">
        <thead class="table-light text-nowrap text-center">
            <tr>
                <th>Vehicle Type</th>
                <th>Vehicle Number</th>
                <th>Chassis Number</th>
                <th>Make & Model</th>
                <th>Engine Capacity (cc)</th>
                <th>Year of Manufacture</th>
                <th>Fuel Type</th>
                <th>Purchase Date</th>
                <th>Purchase Value (LKR)</th>
                <th>Original Mileage</th>
                <th>Assigned User (HRIS)</th>
                <th>Vehicle Category</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
                        <td><?= htmlspecialchars($row['vehicle_number']) ?></td>
                        <td><?= htmlspecialchars($row['chassis_number']) ?></td>
                        <td><?= htmlspecialchars($row['make_model']) ?></td>
                        <td class="text-end"><?= htmlspecialchars($row['engine_capacity']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['year_of_manufacture']) ?></td>
                        <td><?= htmlspecialchars($row['fuel_type']) ?></td>
                        <td><?= htmlspecialchars($row['purchase_date']) ?></td>
                        <td class="text-end"><?= number_format($row['purchase_value'], 2) ?></td>
                        <td class="text-end"><?= number_format($row['original_mileage']) ?></td>
                        <td>
                            <?= htmlspecialchars($row['assigned_user']) ?>
                            <?php if (!empty($row['assigned_user_hris'])): ?>
                                <br><small class="text-muted">(<?= htmlspecialchars($row['assigned_user_hris']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['vehicle_category']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="12" class="text-center text-muted">No records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<nav>
    <ul class="pagination justify-content-end flex-wrap mt-3">
        <!-- Previous -->
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="#" data-page="<?= max(1, $page - 1) ?>">Previous</a>
        </li>

        <?php
        $start = max(1, $page - 1);
        $end = min($totalPages, $page + 1);
        if ($page == 1) $end = min(3, $totalPages);
        if ($page == $totalPages) $start = max(1, $totalPages - 2);

        for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <!-- Next -->
        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
            <a class="page-link" href="#" data-page="<?= min($totalPages, $page + 1) ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
