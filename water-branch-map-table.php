<?php
// water-branch-map-table.php
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

/* show 5 rows per page */
$limit  = 5;
$offset = ($page - 1) * $limit;

$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
$user = $_SESSION['hris'] ?? 'Unknown';

if ($search !== '') {
    userlog("🔍 Branch water map search | User: $user | Term: '$search' | Page: $page | IP: $ip");
} else {
    userlog("📄 Branch water map table load | User: $user | Page: $page | IP: $ip");
}

// Build WHERE + params
$where  = '1';
$params = [];
$types  = '';

if ($search !== '') {
    $cols  = [
        'bw.branch_code',
        'b.branch_name',
        'wt.water_type_name',
        'wt.water_type_code',
        'wv.vendor_name',
        'bw.account_number',
        'bw.connection_no'
    ];
    $likes = [];
    foreach ($cols as $c) {
        $likes[] = "$c LIKE CONCAT('%', ?, '%')";
    }
    $where = '(' . implode(' OR ', $likes) . ')';
    $types = str_repeat('s', count($cols));
    $params = array_fill(0, count($cols), $search);
}

try {
    // Count rows
    $countSql = "
        SELECT COUNT(*)
        FROM tbl_admin_branch_water bw
        LEFT JOIN tbl_admin_branches b
               ON b.branch_code = bw.branch_code
        LEFT JOIN tbl_admin_water_types wt
               ON wt.water_type_id = bw.water_type_id
        LEFT JOIN tbl_admin_water_vendors wv
               ON wv.vendor_id = bw.vendor_id
        WHERE $where
    ";
    $countStmt = $conn->prepare($countSql);
    if ($params) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_row()[0];
    $pages = max(1, (int)ceil($total / $limit));

    // Data query
    $sql = "
        SELECT
            bw.branch_code,
            IFNULL(b.branch_name, '') AS branch_name,
            wt.water_type_code,
            wt.water_type_name,
            IFNULL(wv.vendor_name, '') AS vendor_name,
            bw.account_number,
            bw.no_of_machines,
            bw.connection_no,
            bw.updated_at
        FROM tbl_admin_branch_water bw
        LEFT JOIN tbl_admin_branches b
               ON b.branch_code = bw.branch_code
        LEFT JOIN tbl_admin_water_types wt
               ON wt.water_type_id = bw.water_type_id
        LEFT JOIN tbl_admin_water_vendors wv
               ON wv.vendor_id = bw.vendor_id
        WHERE $where
        ORDER BY CAST(bw.branch_code AS UNSIGNED), bw.branch_code, wt.water_type_name, bw.connection_no
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
    echo '<div class="alert alert-danger">[ERR-BRANCH-MAP-TABLE] '
        . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="table-responsive font-size">
<?php if ($total === 0): ?>
  <div class="alert alert-warning mb-0">No branch water mappings found.</div>
<?php else: ?>
  <table class="table table-bordered table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th style="width:10%;">Branch Code</th>
        <th style="width:18%;">Branch Name</th>
        <th style="width:15%;">Water Type</th>
        <th style="width:6%;">Conn #</th>
        <th style="width:20%;">Vendor</th>
        <th style="width:17%;">Account No. (Tap Line)</th>
        <th style="width:8%;">Machines</th>
        <th style="width:10%;">Updated</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($r = $rs->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($r['branch_code'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['branch_name'] ?? '') ?></td>
        <td>
          <?= htmlspecialchars($r['water_type_name'] ?? '') ?>
          <?php if (!empty($r['water_type_code'])): ?>
            <br><span class="text-muted small">
              (<?= htmlspecialchars($r['water_type_code']) ?>)
            </span>
          <?php endif; ?>
        </td>
        <td class="text-center"><?= (int)($r['connection_no'] ?? 1) ?></td>
        <td><?= htmlspecialchars($r['vendor_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['account_number'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['no_of_machines'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['updated_at'] ?? '') ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <nav>
    <ul class="pagination branch-map-pagination justify-content-end flex-wrap mb-0">
      <?php
        $win   = 2;
        $start = max(1, $page - $win);
        $end   = min($pages, $page + $win);

        if ($page > 1) {
          echo '<li class="page-item"><span class="page-link branch-map-page-btn" data-pg="1">« First</span></li>';
          echo '<li class="page-item"><span class="page-link branch-map-page-btn" data-pg="'.($page-1).'">‹ Prev</span></li>';
        }

        if ($start > 1) {
          echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
          $active = $i == $page ? ' active' : '';
          echo '<li class="page-item'.$active.'"><span class="page-link branch-map-page-btn" data-pg="'.$i.'">'.$i.'</span></li>';
        }

        if ($end < $pages) {
          echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }

        if ($page < $pages) {
          echo '<li class="page-item"><span class="page-link branch-map-page-btn" data-pg="'.($page+1).'">Next ›</span></li>';
          echo '<li class="page-item"><span class="page-link branch-map-page-btn" data-pg="'.$pages.'">Last »</span></li>';
        }
      ?>
    </ul>
  </nav>
<?php endif; ?>
</div>
