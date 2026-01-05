<?php
include 'connections/connection.php';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$offset = ($page - 1) * $limit;

$where = $search ? "WHERE branch_name LIKE '%$search%' OR lease_agreement_number LIKE '%$search%'" : '';
$totalResult = $conn->query("SELECT COUNT(*) as total FROM tbl_admin_branch_contracts $where");
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$result = $conn->query("SELECT * FROM tbl_admin_branch_contracts $where ORDER BY CAST(branch_number AS UNSIGNED) ASC LIMIT $limit OFFSET $offset");
?>

<table class="table table-bordered table-hover">
    <thead class="table-light">
        <tr>
            <th>Branch ID</th>
            <th>Branch</th>
            <th>Lease No.</th>
            <th>Contract Period</th>
            <th>Start</th>
            <th>End</th>
            <th>View Contract Details</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['branch_number']) ?></td>
                <td><?= htmlspecialchars($row['branch_name']) ?></td>
                <td><?= htmlspecialchars($row['lease_agreement_number']) ?></td>
                <td><?= htmlspecialchars($row['contract_period']) ?></td>
                <td><?= htmlspecialchars($row['start_date']) ?></td>
                <td><?= htmlspecialchars($row['end_date']) ?></td>
                <td><button class="btn btn-sm btn-primary view-details" data-id="<?= $row['id'] ?>">View</button></td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="7" class="text-center text-danger">No records found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
<nav>
    <ul class="pagination justify-content-end">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item<?= $i == $page ? ' active' : '' ?>">
                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
