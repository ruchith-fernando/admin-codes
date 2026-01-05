<?php
// vehicle-ajax.php
include 'connections/connection.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset('utf8mb4');

function formatMileage($v): string {
    if ($v === null) return 'Not Available';

    $raw = trim((string)$v);
    if ($raw === '') return 'Not Available';

    $u = strtoupper($raw);
    if ($u === 'NA' || $u === 'N/A') return 'Not Available';

    // If numeric and equals 1 => Not Available
    if (is_numeric($raw)) {
        $num = (float)$raw;

        if (abs($num - 1.0) < 0.0000001) return 'Not Available';

        // ✅ return as plain number (NO thousand separators)
        // If it's an integer, remove decimals
        if (floor($num) == $num) return (string)(int)$num;

        // Otherwise return as-is (keeps decimals if any)
        // You can also control decimals here if needed
        return rtrim(rtrim((string)$num, '0'), '.');
    }

    return 'Not Available';
}


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
            'make_model',
            'vehicle_number',
            'assigned_user',
            'assigned_user_hris',
            'vehicle_type',
            'chassis_number'
        ];

        $likes = [];
        foreach ($cols as $c) {
            $likes[] = "$c LIKE CONCAT('%', ?, '%')";
        }

        $where .= " AND (" . implode(' OR ', $likes) . ")";
        $types  = str_repeat('s', count($cols));
        $params = array_fill(0, count($cols), $search);
    }

    // ✅ Count total
    $countSql  = "SELECT COUNT(*) FROM tbl_admin_vehicle WHERE $where";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $pages = max(1, (int)ceil($total / $limit));

    // ✅ clamp page if user requested too high
    if ($page > $pages) {
        $page = $pages;
        $offset = ($page - 1) * $limit;
    }

    // ✅ Fetch rows
    $sql = "
      SELECT
        vehicle_type,
        vehicle_number,
        chassis_number,
        make_model,
        engine_capacity,
        year_of_manufacture,
        fuel_type,
        purchase_date,
        purchase_value,
        original_mileage,
        assigned_user,
        assigned_user_hris,
        vehicle_category
      FROM tbl_admin_vehicle
      WHERE $where
      ORDER BY purchase_date DESC
      LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $bindTypes = $types . 'ii';
        $bindVals  = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($bindTypes, ...$bindVals);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }

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
        <td><?= htmlspecialchars($r['vehicle_type'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['vehicle_number'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['chassis_number'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['make_model'] ?? '') ?></td>
        <td class="text-end"><?= htmlspecialchars($r['engine_capacity'] ?? '') ?></td>
        <td class="text-center"><?= htmlspecialchars($r['year_of_manufacture'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['fuel_type'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['purchase_date'] ?? '') ?></td>
        <td class="text-end"><?= number_format((float)($r['purchase_value'] ?? 0), 2) ?></td>
        <td class="text-end"><?= htmlspecialchars(formatMileage($r['original_mileage'] ?? null)) ?></td>
        <td>
          <?= htmlspecialchars($r['assigned_user'] ?? '') ?>
          <?php if (!empty($r['assigned_user_hris'])): ?>
            <br><small class="text-muted">(<?= htmlspecialchars($r['assigned_user_hris']) ?>)</small>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($r['vehicle_category'] ?? '') ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <!-- ✅ Pagination (buttons, not spans) -->
  <nav>
    <ul class="pagination justify-content-end flex-wrap mb-0">
      <?php
        $win   = 2;
        $start = max(1, $page - $win);
        $end   = min($pages, $page + $win);

        if ($page > 1) {
          echo '<li class="page-item">
                  <button type="button" class="page-link page-btn" data-pg="1">« First</button>
                </li>';
          echo '<li class="page-item">
                  <button type="button" class="page-link page-btn" data-pg="'.($page-1).'">‹ Prev</button>
                </li>';
        }

        if ($start > 1) {
          echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
          $active = ($i == $page) ? ' active' : '';
          echo '<li class="page-item'.$active.'">
                  <button type="button" class="page-link page-btn" data-pg="'.$i.'">'.$i.'</button>
                </li>';
        }

        if ($end < $pages) {
          echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }

        if ($page < $pages) {
          echo '<li class="page-item">
                  <button type="button" class="page-link page-btn" data-pg="'.($page+1).'">Next ›</button>
                </li>';
          echo '<li class="page-item">
                  <button type="button" class="page-link page-btn" data-pg="'.$pages.'">Last »</button>
                </li>';
        }
      ?>
    </ul>
  </nav>
<?php endif; ?>
</div>

<?php
// cleanup
if (isset($stmt)) $stmt->close();
?>
