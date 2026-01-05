<?php
include 'connections/connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

echo "<!-- DEBUG: file loaded OK -->";

try {
    $limit  = 10;
    $page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $where = "1=1";
    $params = [];
    $types  = "";

    if ($search !== "") {
        $where = "(
            hris LIKE CONCAT('%', ?, '%') OR
            epf_no LIKE CONCAT('%', ?, '%') OR
            name_of_employee LIKE CONCAT('%', ?, '%') OR
            display_name LIKE CONCAT('%', ?, '%') OR
            designation LIKE CONCAT('%', ?, '%') OR
            location LIKE CONCAT('%', ?, '%') OR
            nic_no LIKE CONCAT('%', ?, '%') OR
            category LIKE CONCAT('%', ?, '%') OR
            employment_categories LIKE CONCAT('%', ?, '%') OR
            status LIKE CONCAT('%', ?, '%')
        )";
        for ($i = 0; $i < 10; $i++) {
            $params[] = $search;
            $types   .= "s";
        }
    }

    $count_sql = "SELECT COUNT(*) AS total FROM tbl_admin_employee_details WHERE $where";
    $count_stmt = $conn->prepare($count_sql);
    if ($types) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_rows = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = max(1, (int)ceil($total_rows / $limit));

    $sql = "
        SELECT id, hris, epf_no, company_hierarchy, title, name_of_employee,
               designation, display_name, location, nic_no, category,
               employment_categories, date_joined, date_resigned,
               category_ops_sales, status, upload_timestamp, sr_number
        FROM tbl_admin_employee_details
        WHERE $where
        ORDER BY name_of_employee ASC, hris ASC
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    if ($types) {
        $types2  = $types . "ii";
        $params2 = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($types2, ...$params2);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger mb-0">[ERR-HR-LIST-001] ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>
<div class="table-responsive font-size">
<?php if ($total_rows === 0): ?>
  <div class="alert alert-warning mb-0">No employee records found.</div>
<?php else: ?>
  <table class="table table-bordered table-striped align-middle text-start">
    <thead class="table-primary">
      <tr>
        <th>HRIS</th><th>EPF</th><th>Name</th><th>Display</th><th>Designation</th>
        <th>Location</th><th>NIC</th><th>Category</th><th>Emp. Cat</th>
        <th>Status</th><th>Joined</th><th>Resigned</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($r = $result->fetch_assoc()): ?>
      <tr class="table-row" style="cursor:pointer;"
          data-name="<?= htmlspecialchars($r['name_of_employee'] ?? '') ?>"
          data-display="<?= htmlspecialchars($r['display_name'] ?? '') ?>"
          data-hris="<?= htmlspecialchars($r['hris'] ?? '') ?>"
          data-epf="<?= htmlspecialchars($r['epf_no'] ?? '') ?>"
          data-designation="<?= htmlspecialchars($r['designation'] ?? '') ?>"
          data-location="<?= htmlspecialchars($r['location'] ?? '') ?>"
          data-nic="<?= htmlspecialchars($r['nic_no'] ?? '') ?>"
          data-status="<?= htmlspecialchars($r['status'] ?? '') ?>"
          data-category="<?= htmlspecialchars($r['category'] ?? '') ?>"
          data-empcat="<?= htmlspecialchars($r['employment_categories'] ?? '') ?>"
          data-joined="<?= htmlspecialchars($r['date_joined'] ?? '') ?>"
          data-resigned="<?= htmlspecialchars($r['date_resigned'] ?? '') ?>">
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
        <td><?= htmlspecialchars($r['date_resigned']) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  <nav><ul class="pagination justify-content-end">
  <?php
    $prev = max(1, $page - 1);
    $next = min($total_pages, $page + 1);
    if ($page > 1) {
      echo '<li class="page-item"><a href="#" class="page-link" data-pg="1">« First</a></li>';
      echo '<li class="page-item"><a href="#" class="page-link" data-pg="' . $prev . '">< Prev</a></li>';
    }
    for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
      echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
      echo '<a href="#" class="page-link" data-pg="' . $i . '">' . $i . '</a></li>';
    }
    if ($page < $total_pages) {
      echo '<li class="page-item"><a href="#" class="page-link" data-pg="' . $next . '">Next ></a></li>';
      echo '<li class="page-item"><a href="#" class="page-link" data-pg="' . $total_pages . '">Last »</a></li>';
    }
  ?>
  </ul></nav>
<?php endif; ?>
</div>
