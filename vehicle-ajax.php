<?php
include 'connections/connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

try {
    $limit  = 10;
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');

    $where  = "status = 'Approved'";
    $params = [];
    $types  = '';

    if ($search !== '') {
        $cols = [
            'make_model','vehicle_number','assigned_user','assigned_user_hris',
            'vehicle_type','chassis_number'
        ];
        $likes = [];
        foreach ($cols as $c) $likes[] = "$c LIKE CONCAT('%', ?, '%')";
        $where .= " AND (" . implode(' OR ', $likes) . ")";
        $types  = str_repeat('s', count($cols));
        $params = array_fill(0, count($cols), $search);
    }

    // Count total
    $countSql = "SELECT COUNT(*) FROM tbl_admin_vehicle WHERE $where";
    $countStmt = $conn->prepare($countSql);
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_row()[0];
    $pages = max(1, ceil($total / $limit));

    // Fetch rows
    $sql = "
      SELECT *
      FROM tbl_admin_vehicle
      WHERE $where
      ORDER BY purchase_date DESC
      LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types.'ii', ...array_merge($params, [$limit, $offset]));
    else $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $rs = $stmt->get_result();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">[ERR-VEHICLE] ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="table-responsive font-size">
<?php if ($total === 0): ?>
  <div class="alert alert-warning mb-0">No approved vehicle records found.</div>
<?php else: ?>
  <table class="table table-bordered table-striped align-middle text-start">
    <thead class="table-primary text-center text-nowrap">
      <tr>
        <th>Vehicle Type</th>
        <th>Vehicle Number</th>
        <th>Chassis Number</th>
        <th>Make & Model</th>
        <th>Engine Capacity (cc)</th>
        <th>Year</th>
        <th>Fuel Type</th>
        <th>Purchase Date</th>
        <th>Value (LKR)</th>
        <th>Original Mileage</th>
        <th>Assigned User</th>
        <th>Category</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($r = $rs->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($r['vehicle_type']) ?></td>
        <td><?= htmlspecialchars($r['vehicle_number']) ?></td>
        <td><?= htmlspecialchars($r['chassis_number']) ?></td>
        <td><?= htmlspecialchars($r['make_model']) ?></td>
        <td class="text-end"><?= htmlspecialchars($r['engine_capacity']) ?></td>
        <td class="text-center"><?= htmlspecialchars($r['year_of_manufacture']) ?></td>
        <td><?= htmlspecialchars($r['fuel_type']) ?></td>
        <td><?= htmlspecialchars($r['purchase_date']) ?></td>
        <td class="text-end"><?= number_format($r['purchase_value'], 2) ?></td>
        <td class="text-end"><?= number_format($r['original_mileage']) ?></td>
        <td>
          <?= htmlspecialchars($r['assigned_user']) ?>
          <?php if (!empty($r['assigned_user_hris'])): ?>
            <br><small class="text-muted">(<?= htmlspecialchars($r['assigned_user_hris']) ?>)</small>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($r['vehicle_category']) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <!-- ✅ Pagination -->
  <nav>
    <ul class="pagination justify-content-end flex-wrap mb-0">
      <?php
        $win   = 2;
        $start = max(1, $page - $win);
        $end   = min($pages, $page + $win);

        if ($page > 1) {
          echo '<li class="page-item"><span class="page-link page-btn" data-pg="1">« First</span></li>';
          echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.($page-1).'">‹ Prev</span></li>';
        }

        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        for ($i = $start; $i <= $end; $i++) {
          $active = $i == $page ? ' active' : '';
          echo '<li class="page-item'.$active.'"><span class="page-link page-btn" data-pg="'.$i.'">'.$i.'</span></li>';
        }
        if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';

        if ($page < $pages) {
          echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.($page+1).'">Next ›</span></li>';
          echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.$pages.'">Last »</span></li>';
        }
      ?>
    </ul>
  </nav>
<?php endif; ?>
</div>
