<?php
include 'connections/connection.php';

$limit  = 10;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT 
        i.period AS billing_month,
        i.invoice_no,
        d.mobile_number,
        d.total_amount_payable
    FROM tbl_admin_dialog_invoices i
    INNER JOIN tbl_admin_dialog_invoice_details d ON i.id = d.invoice_id
    WHERE (? = '' OR 
           d.mobile_number LIKE CONCAT('%', ?, '%') OR
           i.period LIKE CONCAT('%', ?, '%') OR
           i.invoice_no LIKE CONCAT('%', ?, '%'))
    ORDER BY 
        CASE 
            WHEN i.period REGEXP '^[A-Za-z]+-[0-9]{4}$' 
            THEN STR_TO_DATE(CONCAT('01-', i.period), '%d-%M-%Y')
            ELSE NULL
        END DESC,
        i.period DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$count_sql = "
    SELECT COUNT(*) AS total
    FROM tbl_admin_dialog_invoices i
    INNER JOIN tbl_admin_dialog_invoice_details d ON i.id = d.invoice_id
    WHERE (? = '' OR 
           d.mobile_number LIKE CONCAT('%', ?, '%') OR
           i.period LIKE CONCAT('%', ?, '%') OR
           i.invoice_no LIKE CONCAT('%', ?, '%'))
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("ssss", $search, $search, $search, $search);
$count_stmt->execute();
$count_res = $count_stmt->get_result();
$total_rows = (int)$count_res->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_rows / $limit));
?>

<div class="table-responsive font-size">
<?php if ($total_rows === 0): ?>
  <div class="alert alert-warning mb-0">No records found.</div>
<?php else: ?>
  <table class="table table-bordered table-striped align-middle text-start">
    <thead class="table-primary">
      <tr>
        <th>Billing Month</th>
        <th>Invoice No</th>
        <th>Mobile Number</th>
        <th>Total Payable</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr class="table-row" style="cursor:pointer;"
          data-period="<?= htmlspecialchars($row['billing_month']); ?>"
          data-invoice="<?= htmlspecialchars($row['invoice_no']); ?>"
          data-mobile="<?= htmlspecialchars($row['mobile_number']); ?>"
          data-total="<?= number_format($row['total_amount_payable'], 2); ?>">
        <td><?= htmlspecialchars($row['billing_month']); ?></td>
        <td><?= htmlspecialchars($row['invoice_no']); ?></td>
        <td><?= htmlspecialchars($row['mobile_number']); ?></td>
        <td><?= number_format($row['total_amount_payable'], 2); ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <nav>
    <ul class="pagination justify-content-end">
      <?php
      $prev = max(1, $page - 1);
      $next = min($total_pages, $page + 1);
      if ($page > 1) {
        echo '<li class="page-item"><a href="#" class="page-link" data-pg="1">« First</a></li>';
        echo '<li class="page-item"><a href="#" class="page-link" data-pg="' . $prev . '">< Prev</a></li>';
      }
      $start = max(1, $page - 2);
      $end   = min($total_pages, $page + 2);
      for ($i = $start; $i <= $end; $i++) {
        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
        echo '<a href="#" class="page-link" data-pg="' . $i . '">' . $i . '</a></li>';
      }
      if ($page < $total_pages) {
        echo '<li class="page-item"><a href="#" class="page-link" data-pg="' . $next . '">Next ></a></li>';
        echo '<li class="page-item"><a href="#" class="page-link" data-pg="' . $total_pages . '">Last »</a></li>';
      }
      ?>
    </ul>
  </nav>
<?php endif; ?>
</div>

<?php
// ✅ Log table load success
try {
    require_once 'includes/userlog.php';
    $hris = $_SESSION['hris'] ?? 'UNKNOWN';
    $username = $_SESSION['name'] ?? getUserInfo();
    $searchText = $search !== '' ? $search : 'All Records';
    $actionMessage = sprintf(
        '✅ Viewed Finance Dialog Report table | Search: %s | Page: %d | Rows: %d',
        $searchText,
        $page,
        $total_rows
    );
    userlog($actionMessage);
} catch (Throwable $e) {}
?>
