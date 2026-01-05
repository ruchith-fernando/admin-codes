<?php
// table-hr-report-dialog.php
include 'connections/connection.php';

$limit  = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page   = $page < 1 ? 1 : $page;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$limit  = (int)$limit;
$offset = (int)$offset;

$sql = "
    SELECT 
        billing_month,
        hris_no,
        employee_name,
        mobile_number,
        nic_no,
        total_amount_payable,
        contribution_amount,
        salary_deduction,
        voice_data,
        designation
    FROM tbl_admin_hr_report_dialog_summary
    WHERE (
        ? = '' OR 
        mobile_number   LIKE CONCAT('%', ?, '%') OR
        billing_month   LIKE CONCAT('%', ?, '%') OR
        hris_no         LIKE CONCAT('%', ?, '%') OR
        employee_name   LIKE CONCAT('%', ?, '%') OR
        nic_no          LIKE CONCAT('%', ?, '%') OR
        voice_data      LIKE CONCAT('%', ?, '%')
    )
    ORDER BY 
        CASE 
            WHEN billing_month REGEXP '^[A-Za-z]+-[0-9]{4}$' 
            THEN STR_TO_DATE(CONCAT('01-', billing_month), '%d-%M-%Y')
            ELSE NULL
        END DESC,
        billing_month DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo '<div class="alert alert-danger mb-0">Prepare failed: ' . htmlspecialchars($conn->error) . '</div>';
    exit;
}

$stmt->bind_param("sssssss", $search, $search, $search, $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$count_sql = "
    SELECT COUNT(*) AS total
    FROM tbl_admin_hr_report_dialog_summary
    WHERE (
        ? = '' OR 
        mobile_number   LIKE CONCAT('%', ?, '%') OR
        billing_month   LIKE CONCAT('%', ?, '%') OR
        hris_no         LIKE CONCAT('%', ?, '%') OR
        employee_name   LIKE CONCAT('%', ?, '%') OR
        nic_no          LIKE CONCAT('%', ?, '%') OR
        voice_data      LIKE CONCAT('%', ?, '%')
    )
";

$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) {
    http_response_code(500);
    echo '<div class="alert alert-danger mb-0">Prepare failed: ' . htmlspecialchars($conn->error) . '</div>';
    exit;
}
$count_stmt->bind_param("sssssss", $search, $search, $search, $search, $search, $search, $search);
$count_stmt->execute();
$count_res = $count_stmt->get_result();
$total_rows  = (int)$count_res->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_rows / $limit));
?>

<div class="table-responsive font-size">
<?php if ($total_rows === 0): ?>
    <div class="alert alert-warning mb-0">No records found.</div>
<?php else: ?>
    <table class="table table-bordered table-striped align-middle text-start">
        <thead class="table-primary text-start">
            <tr>
                <th>Billing Month</th>
                <th>HRIS</th>
                <th>Employee</th>
                <th>Designation</th>
                <th>NIC</th>
                <th>Mobile Number</th>
                <th>Total Payable</th>
                <th>Contribution</th>
                <th>Salary Deduction</th>
                <th>Voice Data</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()):
            $billingMonth = !empty($row['billing_month']) ? $row['billing_month'] : 'From Data Bucket';
        ?>
            <tr class="table-row"
                style="cursor:pointer;"
                data-mobile="<?php echo htmlspecialchars($row['mobile_number'] ?? ''); ?>"
                data-employee="<?php echo htmlspecialchars($row['employee_name'] ?? ''); ?>"
                data-designation="<?php echo htmlspecialchars($row['designation'] ?? ''); ?>" 
                data-hris="<?php echo htmlspecialchars($row['hris_no'] ?? ''); ?>"
                data-nic="<?php echo htmlspecialchars($row['nic_no'] ?? ''); ?>"
                data-period="<?php echo htmlspecialchars($billingMonth ?? ''); ?>"
                data-total="<?php echo number_format((float)$row['total_amount_payable'], 2); ?>"
                data-contribution="<?php echo number_format((float)$row['contribution_amount'], 2); ?>"
                data-deduction="<?php echo number_format((float)$row['salary_deduction'], 2); ?>"
                data-voice="<?php echo htmlspecialchars($row['voice_data'] ?? ''); ?>">
                <td><?php echo htmlspecialchars($billingMonth ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['hris_no'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['employee_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['designation'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['nic_no'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['mobile_number'] ?? ''); ?></td>
                <td><?php echo number_format((float)$row['total_amount_payable'], 2); ?></td>
                <td><?php echo number_format((float)$row['contribution_amount'], 2); ?></td>
                <td><?php echo number_format((float)$row['salary_deduction'], 2); ?></td>
                <td><?php echo htmlspecialchars($row['voice_data'] ?? ''); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <nav>
      <ul class="pagination justify-content-end">
        <?php
        $prev = ($page > 1) ? $page - 1 : 1;
        $next = ($page < $total_pages) ? $page + 1 : $total_pages;

        if ($page > 1) {
          echo '<li class="page-item"><a href="#" class="page-link" data-pg="1">« First</a></li>';
          echo '<li class="page-item"><a href="#" class="page-link" data-pg="' . $prev . '">< Prev</a></li>';
        }

        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
          echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
          echo '<a href="#" class="page-link" data-pg="' . $i . '">' . $i . '</a>';
          echo '</li>';
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
// ✅ Success log
try {
    require_once 'includes/userlog.php';
    $hris = $_SESSION['hris'] ?? 'UNKNOWN';
    $username = $_SESSION['name'] ?? getUserInfo();
    $searchText = $search !== '' ? $search : 'All Records';
    $actionMessage = sprintf(
        '✅ Viewed HR Dialog Report table | Search: %s | Page: %d | Rows: %d',
        $searchText,
        $page,
        $total_rows
    );
    userlog($actionMessage);
} catch (Throwable $e) {}
?>
