<?php
include "connections/connection.php";

$limit = 10;
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$offset = ($page - 1) * $limit;

$search = trim($_POST['search'] ?? '');
$from = $_POST['from_date'] ?? '';
$to = $_POST['to_date'] ?? '';

$conditions = [];

if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(document_number LIKE '%$safe%' OR requested_by_hris LIKE '%$safe%' OR printed_by LIKE '%$safe%')";
}
if ($from) {
    $conditions[] = "DATE(datetime_printed) >= '" . mysqli_real_escape_string($conn, $from) . "'";
}
if ($to) {
    $conditions[] = "DATE(datetime_printed) <= '" . mysqli_real_escape_string($conn, $to) . "'";
}

$where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tbl_admin_secure_print_logs $where"))['total'];
$totalPages = ceil($total / $limit);

$query = "SELECT * FROM tbl_admin_secure_print_logs $where ORDER BY datetime_printed DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);
?>

<table class="table table-bordered">
    <thead class="table-light">
        <tr>
            <th>Document Number</th>
            <th>Requester's HRIS</th>
            <th>Printed By</th>
            <th>Date & Time Printed</th>
            <th>Copies Printed</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (mysqli_num_rows($result)) {
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>
                    <td>" . htmlspecialchars($row['document_number']) . "</td>
                    <td>" . htmlspecialchars($row['requested_by_hris']) . "</td>
                    <td>" . htmlspecialchars($row['printed_by']) . "</td>
                    <td>" . htmlspecialchars($row['datetime_printed']) . "</td>
                    <td>" . htmlspecialchars($row['copies_printed']) . "</td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='5' class='text-center'>No logs found.</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
<nav><ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a href="#" class="page-link pagination-link" data-page="<?= $i ?>"><?= $i ?></a>
        </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>
