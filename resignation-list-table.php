<?php
// resignation-list-table.php
include 'connections/connection.php';

$limit = 25;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$offset = ($page - 1) * $limit;

$where = "(
    t1.HRIS LIKE '%$search%' 
    OR t1.Name LIKE '%$search%' 
    OR t1.NIC LIKE '%$search%' 
    OR t1.Branch LIKE '%$search%' 
    OR t2.mobile_no LIKE '%$search%'
)";

// Get individual rows per connected mobile number
$sql = "
    SELECT 
        t1.HRIS, t1.Name, t1.NIC, t1.Designation, t1.Department, t1.Branch,
        t1.DOJ, t1.Category, t1.Employment_Type, t1.Resignation_Effective_Date,
        t1.Resignation_Type, t1.Reason,
        t2.mobile_no,t2.voice_data
    FROM tbl_admin_employee_resignations t1
    JOIN tbl_admin_mobile_issues t2 ON t1.HRIS = t2.hris_no
    WHERE t2.connection_status = 'connected'
    AND $where
    ORDER BY t1.Resignation_Effective_Date ASC
    LIMIT $limit OFFSET $offset
";

$count_sql = "
    SELECT COUNT(*) as total
    FROM tbl_admin_employee_resignations t1
    JOIN tbl_admin_mobile_issues t2 ON t1.HRIS = t2.hris_no
    WHERE t2.connection_status = 'connected'
    AND $where
";

$result = $conn->query($sql);
$total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
?>
<div class="table-responsive font-size">
    <table class="table table-bordered table-striped align-middle text-start">
        <thead class="table-primary">
            <tr>
                <th style="min-width: 100px;">HRIS</th>
                <th style="min-width: 300px;">Name</th>
                <th style="min-width: 80px;">NIC</th>
                <th style="min-width: 300px;">Designation</th>
                <th style="min-width: 200px;">Department</th>
                <th style="min-width: 150px;">Branch</th>
                <th style="min-width: 150px;">Mobile Number</th>
                <th style="min-width: 150px;">Voice / Data</th>
                <th style="min-width: 200px;">Resignation Date</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr class="table-row"
                data-hris="<?= htmlspecialchars($row['HRIS']) ?>"
                data-name="<?= htmlspecialchars($row['Name']) ?>"
                data-nic="<?= htmlspecialchars($row['NIC']) ?>"
                data-designation="<?= htmlspecialchars($row['Designation']) ?>"
                data-department="<?= htmlspecialchars($row['Department']) ?>"
                data-branch="<?= htmlspecialchars($row['Branch']) ?>"
                data-doj="<?= htmlspecialchars($row['DOJ']) ?>"
                data-category="<?= htmlspecialchars($row['Category']) ?>"
                data-type="<?= htmlspecialchars($row['Employment_Type']) ?>"
                data-effective="<?= htmlspecialchars($row['Resignation_Effective_Date']) ?>"
                data-resigtype="<?= htmlspecialchars($row['Resignation_Type']) ?>"
                data-reason="<?= htmlspecialchars($row['Reason']) ?>"
                data-mobile="<?= htmlspecialchars(trim($row['mobile_no'])) ?>"
                data-voice_data="<?= htmlspecialchars(trim($row['voice_data'])) ?>"
                style="cursor:pointer;">
                <td><?= $row['HRIS'] ?></td>
                <td><?= $row['Name'] ?></td>
                <td><?= $row['NIC'] ?></td>
                <td><?= $row['Designation'] ?></td>
                <td><?= $row['Department'] ?></td>
                <td><?= $row['Branch'] ?></td>
                <td><?= $row['mobile_no'] ?></td>
                <td><?= $row['voice_data'] ?></td>
                <td><?= $row['Resignation_Effective_Date'] ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <nav>
        <ul class="pagination">
            <?php 
            $prev = max(1, $page - 1);
            $next = min($total_pages, $page + 1);

            if ($page > 1) {
                echo '<li class="page-item"><a href="#" class="page-link" data-page="1">« First</a></li>';
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . $prev . '">‹ Prev</a></li>';
            }

            for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a href="#" class="page-link" data-page="' . $i . '">' . $i . '</a></li>';
            }

            if ($page < $total_pages) {
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . $next . '">Next ›</a></li>';
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . $total_pages . '">Last »</a></li>';
            }
            ?>
        </ul>
    </nav>
</div>
