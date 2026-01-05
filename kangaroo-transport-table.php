<?php
include 'connections/connection.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = (int) ($_GET['page'] ?? 1);
$limit = 15;
$offset = ($page - 1) * $limit;

$where = "1=1";
$params = [];
$types = "";

if ($from && $to) {
    $where .= " AND date BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
    $types .= "ss";
}

if ($search !== "") {
    $where .= " AND (voucher_no LIKE ? OR vehicle_no LIKE ? OR passengers LIKE ? OR department LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, array_fill(0, 4, $like));
    $types .= "ssss";
}

// Count total records
$count_sql = "SELECT COUNT(*) FROM tbl_admin_kangaroo_transport WHERE $where";
$count_stmt = $conn->prepare($count_sql);
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_records);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Fetch paginated records
$sql = "SELECT * FROM tbl_admin_kangaroo_transport WHERE $where ORDER BY date DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$total_additional = 0;
$total_grand = 0;
?>

<!-- <div class="d-flex justify-content-between align-items-center mb-3">
  <div></div>
  <a id="exportBtn" class="btn btn-success" href="#" target="_blank">
    <i class="bi bi-file-earmark-excel"></i> Export XLS
  </a>
</div> -->

<table class="table table-bordered table-sm align-middle table-hover">
  <thead class="table-light">
    <tr>
      <th>Date</th>
      <th>Cab No</th>
      <th>Voucher No</th>
      <th>Vehicle No</th>
      <th>Start → End</th>
      <th>KM</th>
      <th>Additional (LKR)</th>
      <th>Total (LKR)</th>
      <th>Passengers</th>
      <th>Department</th>
      <th>Slip</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()):
          $total_additional += $row['additional_charges'];
          $total_grand += $row['total'];
      ?>
        <tr>
          <td><?= htmlspecialchars($row['date']) ?></td>
          <td><?= htmlspecialchars($row['cab_number']) ?></td>
          <td><?= htmlspecialchars($row['voucher_no']) ?></td>
          <td><?= htmlspecialchars($row['vehicle_no']) ?></td>
          <td><?= htmlspecialchars($row['start_location']) ?> → <?= htmlspecialchars($row['end_location']) ?></td>
          <td><?= number_format($row['total_km'], 1) ?></td>
          <td class="text-end"><?= number_format($row['additional_charges'], 2) ?></td>
          <td class="fw-bold text-end"><?= number_format($row['total'], 2) ?></td>
          <td><?= nl2br(htmlspecialchars($row['passengers'])) ?></td>
          <td><?= htmlspecialchars($row['department']) ?></td>
          <td>
            <?php if (!empty($row['chit_file'])): ?>
              <a href="uploads/kangaroo/<?= urlencode($row['chit_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="11" class="text-center text-muted">No records found.</td></tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
  <tr class="table-warning fw-bold">
    <td colspan="6" class="text-end">Total:</td>
    <td class="text-end"><?= number_format($total_additional, 2) ?></td>
    <td class="text-end"><?= number_format($total_grand, 2) ?></td>
    <td colspan="3"></td>
  </tr>
  <tr class="table-success fw-bold">
    <td colspan="6" class="text-end">Grand Total (Add. + Total):</td>
    <td colspan="2" class="text-end"><?= number_format($total_additional + $total_grand, 2) ?></td>
    <td colspan="3"></td>
  </tr>
</tfoot>

</table>

<?php if ($total_pages > 1): ?>
<nav>
  <ul class="pagination justify-content-end">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <li class="page-item <?= $i == $page ? 'active' : '' ?>">
        <a class="page-link" href="#" onclick="changePage(<?= $i ?>); return false;"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php
$stmt->close();
$conn->close();
?>
