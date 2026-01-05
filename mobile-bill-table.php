<?php
// mobile-bill-table.php
include 'connections/connection.php';

$limit  = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page   = $page < 1 ? 1 : 1 * $page;
$offset = ($page - 1) * $limit;

$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchEsc = $conn->real_escape_string($search);

$where = '';
if ($search !== '') {
    $like  = "%{$searchEsc}%";
    $where = "
        WHERE t1.MOBILE_Number    LIKE '$like'
           OR t1.name_of_employee LIKE '$like'
           OR t1.Update_date      LIKE '$like'
           OR t1.nic_no           LIKE '$like'
           OR t1.hris_no          LIKE '$like'
    ";
}

$sql = "
    SELECT
        t1.*,
        CONCAT(t1.name_of_employee, ' - ', t1.display_name) AS full_display_name,

        -- Contribution trail (linked by mobile, not fixed HRIS)
        (
            SELECT c.contribution_amount
            FROM tbl_admin_hris_contributions c
            WHERE c.mobile_no = t1.MOBILE_Number
              AND c.effective_from <= STR_TO_DATE(CONCAT('01-', t1.Update_date), '%d-%M-%Y')
            ORDER BY c.effective_from DESC
            LIMIT 1
        ) AS company_contribution,

        -- Current HRIS for this mobile (at billing month)
        (
            SELECT h.hris_no
            FROM tbl_admin_hris_contributions h
            WHERE h.mobile_no = t1.MOBILE_Number
              AND h.effective_from <= STR_TO_DATE(CONCAT('01-', t1.Update_date), '%d-%M-%Y')
            ORDER BY h.effective_from DESC
            LIMIT 1
        ) AS current_hris

    FROM tbl_admin_mobile_bill_data t1
    $where
    ORDER BY
        STR_TO_DATE(CONCAT('01-', t1.Update_date), '%d-%M-%Y') ASC,
        t1.hris_no ASC
    LIMIT $limit OFFSET $offset
";

$result = $conn->query($sql);
if (!$result) {
    http_response_code(500);
    echo '<div class="alert alert-danger mb-0">Database error while loading table: '
        . htmlspecialchars($conn->error) . '</div>';
    exit;
}

$count_sql = "SELECT COUNT(*) AS total FROM tbl_admin_mobile_bill_data t1 $where";
$count_res = $conn->query($count_sql);
if (!$count_res) {
    http_response_code(500);
    echo '<div class="alert alert-danger mb-0">Database error while counting rows: '
        . htmlspecialchars($conn->error) . '</div>';
    exit;
}
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
                <th>HRIS (at time)</th>
                <th>Current HRIS</th>
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
                <th>Company Contribution</th>
                <th>Salary Deduction</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()):
            $company     = isset($row['company_contribution']) ? (float)$row['company_contribution'] : 0.0;
            $total       = (float)$row['TOTAL_AMOUNT_PAYABLE'];
            $roam        = (float)$row['ROAMING'];
            $vas         = (float)$row['VALUE_ADDED_SERVICES'];
            $addbill     = (float)$row['ADD_TO_BILL'];
            $idd         = (float)$row['IDD'];
            $billCharges = (float)$row['CHARGES_FOR_BILL_PERIOD'];
            $voiceData   = isset($row['VOICE_DATA']) ? (float)$row['VOICE_DATA'] : (isset($row['voice_data']) ? (float)$row['voice_data'] : 0.0);

            $X = $total - $company;
            $Y = $roam + $vas + $addbill;
            $salary_deduction = max($X, $Y);

            $name = trim($row['name_of_employee'] ?? '');
            $disp = trim($row['display_name'] ?? '');
            $employee_name = $disp !== '' ? ($name !== '' ? ($name . ' - ' . $disp) : $disp) : $name;
        ?>
            <tr class="table-row"
                style="cursor:pointer;"
                data-mobile="<?php echo htmlspecialchars($row['MOBILE_Number']); ?>"
                data-employee="<?php echo htmlspecialchars($employee_name); ?>"
                data-nic="<?php echo htmlspecialchars($row['nic_no']); ?>"
                data-designation="<?php echo htmlspecialchars($row['designation']); ?>"
                data-hierarchy="<?php echo htmlspecialchars($row['company_hierarchy']); ?>"
                data-hris="<?php echo htmlspecialchars($row['hris_no']); ?>"
                data-currenthris="<?php echo htmlspecialchars($row['current_hris']); ?>"
                data-total="<?php echo number_format($total, 2); ?>"
                data-contribution="<?php echo number_format($company, 2); ?>"
                data-deduction="<?php echo number_format($salary_deduction, 2); ?>"
                data-roaming="<?php echo number_format($roam, 2); ?>"
                data-vas="<?php echo number_format($vas, 2); ?>"
                data-addtobill="<?php echo number_format($addbill, 2); ?>"
                data-date="<?php echo htmlspecialchars($row['Update_date']); ?>">
                <td><?php echo htmlspecialchars($row['Update_date']); ?></td>
                <td><?php echo htmlspecialchars($row['hris_no']); ?></td>
                <td><?php echo htmlspecialchars($row['current_hris']); ?></td>
                <td><?php echo htmlspecialchars($employee_name); ?></td>
                <td><?php echo htmlspecialchars($row['nic_no']); ?></td>
                <td><?php echo htmlspecialchars($row['designation']); ?></td>
                <td><?php echo htmlspecialchars($row['company_hierarchy']); ?></td>
                <td><?php echo htmlspecialchars($row['MOBILE_Number']); ?></td>
                <td><?php echo number_format($voiceData, 2); ?></td>
                <td><?php echo number_format($roam, 2); ?></td>
                <td><?php echo number_format($vas, 2); ?></td>
                <td><?php echo number_format($addbill, 2); ?></td>
                <td><?php echo number_format($idd, 2); ?></td>
                <td><?php echo number_format($billCharges, 2); ?></td>
                <td><?php echo number_format($total, 2); ?></td>
                <td><?php echo number_format($company, 2); ?></td>
                <td><?php echo number_format($salary_deduction, 2); ?></td>
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
