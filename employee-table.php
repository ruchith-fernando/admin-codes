<?php
// employee-table.php
include 'connections/connection.php';
require_once 'includes/userlog.php';   
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Log user actions
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
$user = $_SESSION['hris'] ?? 'Unknown';

// Read parameters
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

// Log search or pagination action
if ($search !== '') {
    userlog("🔍 Employee Search | User HRIS: $user | Search Term: '$search' | Page: $page | IP: $ip");
} else {
    userlog("📄 Employee Table Loaded | User HRIS: $user | Page: $page | IP: $ip");
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

try {
    $limit  = 10;
    $offset = ($page - 1) * $limit;

    $where  = '1';
    $params = [];
    $types  = '';

    if ($search !== '') {
        $cols = [
            'hris','epf_no','name_of_employee','display_name','designation',
            'location','nic_no','category','employment_categories','status'
        ];
        $likes = [];
        foreach ($cols as $c) $likes[] = "$c LIKE CONCAT('%', ?, '%')";
        $where  = '(' . implode(' OR ', $likes) . ')';
        $types  = str_repeat('s', count($cols));
        $params = array_fill(0, count($cols), $search);
    }

    // Count rows
    $countSql = "SELECT COUNT(*) FROM tbl_admin_employee_details WHERE $where";
    $countStmt = $conn->prepare($countSql);
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_row()[0];
    $pages = max(1, ceil($total / $limit));

    // Get table rows
    $sql = "
      SELECT id, hris, epf_no, name_of_employee, display_name, designation, location,
             nic_no, category, employment_categories, status, date_joined, date_resigned
      FROM tbl_admin_employee_details
      WHERE $where
      ORDER BY CAST(hris AS UNSIGNED) ASC
      LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types.'ii', ...array_merge($params, [$limit, $offset]));
    else $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $rs = $stmt->get_result();

} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">[ERR-TABLE] ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="table-responsive font-size">
<?php if ($total === 0): ?>
  <div class="alert alert-warning mb-0">No employee records found.</div>
<?php else: ?>
  <table class="table table-bordered table-striped align-middle text-start">
    <thead class="table-primary">
      <tr>
        <th>HRIS</th><th>EPF</th><th>Name</th><th>Display</th>
        <th>Designation</th><th>Location</th><th>NIC</th>
        <th>Category</th><th>Emp. Cat</th><th>Status</th>
        <th>Joined</th><th>Resigned</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($r = $rs->fetch_assoc()): ?>
      <?php
        // Fix invalid resigned dates
        $resigned = $r['date_resigned'];
        if ($resigned === '0000-00-00' || $resigned === null || trim($resigned) === '') {
            $resigned = '';
        }
      ?>
      <tr class="emp-row" style="cursor:pointer" data-hris="<?= htmlspecialchars($r['hris']) ?>">
        <td><?= htmlspecialchars($r['hris']) ?></td>
        <td><?= htmlspecialchars($r['epf_no']) ?></td>
        <td><?= htmlspecialchars($r['name_of_employee']) ?></td>
        <td><?= htmlspecialchars($r['display_name']) ?></td>
        <td><?= htmlspecialchars($r['designation']) ?></td>
        <td><?= htmlspecialchars($r['location']) ?></td>
        <td><?= htmlspecialchars($r['nic_no']) ?></td>
        <td><?= htmlspecialchars($r['category']) ?></td>
        <td><?= htmlspecialchars($r['employment_categories']) ?></td>
        <td><?= htmlspecialchars($r['status']) ?></td>
        <td><?= htmlspecialchars($r['date_joined']) ?></td>
        <td><?= htmlspecialchars($resigned) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

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
