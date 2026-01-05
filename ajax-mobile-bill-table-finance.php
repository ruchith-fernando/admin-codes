<?php
include 'connections/connection.php';

$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$update_date = isset($_GET['update_date']) ? $conn->real_escape_string($_GET['update_date']) : '';
$offset = ($page - 1) * $limit;

$where = '';
if ($update_date !== '') {
    $where = "WHERE DATE_FORMAT(STR_TO_DATE(CONCAT('01-', Update_date), '%d-%M-%Y'), '%M-%Y') = '$update_date'";
}

// Get paginated records
$sql = "SELECT * FROM tbl_admin_mobile_bill_data $where 
        ORDER BY STR_TO_DATE(CONCAT('01-', Update_date), '%d-%M-%Y') DESC
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Get total rows for pagination
$count_sql = "SELECT COUNT(*) as total FROM tbl_admin_mobile_bill_data $where";
$count_result = $conn->query($count_sql);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
?>

<div class="table-responsive font-size">
    <table class="table table-bordered table-striped align-middle text-start">
        <thead class="table-primary text-start">
            <tr>
                <th>Billing Month</th>
                <th>HRIS</th>
                <th>Employee Name</th>
                <th>NIC</th>
                <th>Designation</th>
                <th>Hierarchy</th>
                <th>Mobile Number</th>
                <th>Voice Data</th>
                <th>Roaming</th>
                <th>VAS</th>
                <th>Add to Bill</th>
                <th>IDD</th>
                <th>Bill Charges</th>
                <th>Total Payable</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr class="table-row">
                    <td><?= htmlspecialchars($row['Update_date']) ?></td>
                    <td><?= htmlspecialchars($row['hris_no']) ?></td>
                    <td><?= htmlspecialchars($row['name_of_employee']) . ' - ' . htmlspecialchars($row['display_name']) ?></td>
                    <td><?= htmlspecialchars($row['nic_no']) ?></td>
                    <td><?= htmlspecialchars($row['designation']) ?></td>
                    <td><?= htmlspecialchars($row['company_hierarchy']) ?></td>
                    <td><?= htmlspecialchars($row['MOBILE_Number']) ?></td>
                    <td><?= htmlspecialchars($row['voice_data']) ?></td>
                    <td><?= number_format((float)$row['ROAMING'], 2) ?></td>
                    <td><?= number_format((float)$row['VALUE_ADDED_SERVICES'], 2) ?></td>
                    <td><?= number_format((float)$row['ADD_TO_BILL'], 2) ?></td>
                    <td><?= number_format((float)$row['IDD'], 2) ?></td>
                    <td><?= number_format((float)$row['CHARGES_FOR_BILL_PERIOD'], 2) ?></td>
                    <td><?= number_format((float)$row['TOTAL_AMOUNT_PAYABLE'], 2) ?></td>
                </tr>
                <?php } ?>
            <?php else: ?>
                <tr><td colspan="14" class="text-center">No records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination justify-content-end">
            <?php 
            if ($page > 1) {
                echo '<li class="page-item"><a href="#" class="page-link" data-page="1">« First</a></li>';
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . ($page - 1) . '">‹ Prev</a></li>';
            }
            for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                echo '<a href="#" class="page-link" data-page="' . $i . '">' . $i . '</a></li>';
            }
            if ($page < $total_pages) {
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . ($page + 1) . '">Next ›</a></li>';
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . $total_pages . '">Last »</a></li>';
            }
            ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
