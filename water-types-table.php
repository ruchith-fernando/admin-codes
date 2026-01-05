<?php
// water-types-table.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
$user = $_SESSION['hris'] ?? 'Unknown';

if ($search !== '') {
    userlog("🔍 Water Types Search | User: $user | Term: '$search' | Page: $page | IP: $ip");
} else {
    userlog("📄 Water Types Table Load | User: $user | Page: $page | IP: $ip");
}

try {
    $limit  = 15;
    $offset = ($page - 1) * $limit;

    $where  = '1';
    $params = [];
    $types  = '';

    if ($search !== '') {
        $cols  = ['water_type_code','water_type_name'];
        $likes = [];
        foreach ($cols as $c) $likes[] = "$c LIKE CONCAT('%', ?, '%')";
        $where = '(' . implode(' OR ', $likes) . ')';
        $types = str_repeat('s', count($cols));
        $params = array_fill(0, count($cols), $search);
    }

    // count
    $countSql = "SELECT COUNT(*) FROM tbl_admin_water_types WHERE $where";
    $countStmt = $conn->prepare($countSql);
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_row()[0];
    $pages = max(1, (int)ceil($total / $limit));

    // data
    $sql = "
        SELECT water_type_id, water_type_code, water_type_name, is_active
        FROM tbl_admin_water_types
        WHERE $where
        ORDER BY water_type_code ASC
        LIMIT ? OFFSET ?
    ";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $paramsFull = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($types . 'ii', ...$paramsFull);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $limit, $offset);
    }
    $stmt->execute();
    $rs = $stmt->get_result();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">[ERR-WATER-TYPES-TABLE] ' .
         htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="table-responsive font-size">
<?php if ($total === 0): ?>
  <div class="alert alert-warning mb-0">No water types found.</div>
<?php else: ?>
  <table class="table table-bordered table-striped table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th style="width: 20%;">Type Code</th>
        <th>Type Name</th>
        <th style="width: 10%;">Active</th>
        <th style="width: 10%;">Action</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($r = $rs->fetch_assoc()): ?>
      <tr
        data-id="<?= (int)($r['water_type_id'] ?? 0) ?>"
        data-code="<?= htmlspecialchars($r['water_type_code'] ?? '') ?>"
        data-name="<?= htmlspecialchars($r['water_type_name'] ?? '') ?>"
        data-active="<?= (int)($r['is_active'] ?? 0) ?>"
      >
        <td><?= htmlspecialchars($r['water_type_code'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['water_type_name'] ?? '') ?></td>
        <td class="text-center">
          <?php if (!empty($r['is_active'])): ?>
            <span class="badge bg-success">Yes</span>
          <?php else: ?>
            <span class="badge bg-secondary">No</span>
          <?php endif; ?>
        </td>
        <td class="text-center">
          <button class="btn btn-sm btn-outline-primary btn-edit-water-type">Edit</button>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <nav>
    <ul class="pagination pagination-sm justify-content-end flex-wrap mb-0">
      <?php
        $win   = 2;
        $start = max(1, $page - $win);
        $end   = min($pages, $page + $win);

        if ($page > 1) {
          echo '<li class="page-item"><span class="page-link water-type-page-btn" data-pg="1">« First</span></li>';
          echo '<li class="page-item"><span class="page-link water-type-page-btn" data-pg="'.($page-1).'">‹ Prev</span></li>';
        }

        if ($start > 1) {
          echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
          $active = $i == $page ? ' active' : '';
          echo '<li class="page-item'.$active.'"><span class="page-link water-type-page-btn" data-pg="'.$i.'">'.$i.'</span></li>';
        }

        if ($end < $pages) {
          echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }

        if ($page < $pages) {
          echo '<li class="page-item"><span class="page-link water-type-page-btn" data-pg="'.($page+1).'">Next ›</span></li>';
          echo '<li class="page-item"><span class="page-link water-type-page-btn" data-pg="'.$pages.'">Last »</span></li>';
        }
      ?>
    </ul>
  </nav>
<?php endif; ?>
</div>
