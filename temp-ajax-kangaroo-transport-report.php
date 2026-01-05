<?php
session_start();
include 'connections/connection.php';

if (!isset($_SESSION['name']) || !in_array($_SESSION['user_level'], ['authorizer', 'super-admin'])) {
    echo '<div class="alert alert-danger">Access Denied</div>';
    exit;
}

$search = trim($_GET['search'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where = " WHERE 1=1";

if ($from !== '') {
    $where .= " AND date >= '" . $conn->real_escape_string($from) . "'";
}
if ($to !== '') {
    $where .= " AND date <= '" . $conn->real_escape_string($to) . "'";
}
if ($search !== '') {
    $safe = '%' . $conn->real_escape_string($search) . '%';
    $where .= " AND (voucher_no LIKE '$safe' OR vehicle_no LIKE '$safe')";
}

// Get total records for pagination
$countQuery = "SELECT COUNT(*) AS total FROM tbl_admin_kangaroo_transport $where";
$countResult = $conn->query($countQuery);
$totalRows = ($countResult && $countResult->num_rows > 0) ? (int)$countResult->fetch_assoc()['total'] : 0;
$totalPages = ceil($totalRows / $limit);

// Main query with pagination
$sql = "SELECT * FROM tbl_admin_kangaroo_transport $where ORDER BY date DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

if (!$result) {
    echo '<div class="alert alert-danger">Query Error: ' . $conn->error . '</div>';
    exit;
}
if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning">No transport records found.</div>';
    exit;
}
?>

<table class="table table-bordered table-hover table-sm align-middle">
  <thead class="table-light">
    <tr>
      <th>Date</th>
      <th>Voucher No</th>
      <th>Vehicle No</th>
      <th>From</th>
      <th>To</th>
      <th>Total KM</th>
      <th>Additional Charges</th>
      <th>Total</th>
      <th>Uploaded File</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['date']) ?></td>
        <td><?= htmlspecialchars($row['voucher_no']) ?></td>
        <td><?= htmlspecialchars($row['vehicle_no']) ?></td>
        <td><?= htmlspecialchars($row['start_location']) ?></td>
        <td><?= htmlspecialchars($row['end_location']) ?></td>
        <td><?= number_format((float)$row['total_km'], 2) ?></td>
        <td><?= number_format((float)$row['additional_charges'], 2) ?></td>
        <td><strong><?= number_format((float)$row['total'], 2) ?></strong></td>
        <td>
          <?php if (!empty($row['chit_file'])): ?>
            <a href="uploads/kangaroo/<?= htmlspecialchars($row['chit_file']) ?>" target="_blank" class="btn btn-sm btn-primary">View</a>
          <?php else: ?>
            <span class="text-muted">No file</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav>
  <ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
        <a href="#" class="page-link pagination-link" data-page="<?= $i ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
