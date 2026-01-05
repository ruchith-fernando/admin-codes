<?php
// export-disconnected-excel.php
include 'connections/connection.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=disconnected_report.xls");

$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$where = "WHERE t1.connection_status = 'disconnected'";

if ($from && $to) {
    $where .= " AND DATE(t1.disconnection_date) BETWEEN '$from' AND '$to'";
} elseif ($from) {
    $where .= " AND DATE(t1.disconnection_date) = '$from'";
}

$sql = "
    SELECT 
        t1.hris_no,
        t1.display_name,
        t1.company_hierarchy,
        t1.designation,
        t1.mobile_no,
        t1.voice_data,
        t1.date_joined,
        t1.connection_status,
        t1.disconnection_date,
        t2.Resignation_Effective_Date
    FROM tbl_admin_mobile_issues t1
    LEFT JOIN tbl_admin_employee_resignations t2 ON t2.HRIS = t1.hris_no
    $where
    ORDER BY t1.disconnection_date DESC
";

$result = $conn->query($sql);
?>

<table border="1">
    <thead>
        <tr>
            <th>HRIS</th>
            <th>Name</th>
            <th>Company Hierarchy</th>
            <th>Designation</th>
            <th>Mobile Number</th>
            <th>Voice / Data</th>
            <th>Date Joined</th>
            <th>Date Resigned</th>
            <th>Connection Status</th>
            <th>Disconnection Date</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['hris_no']) ?></td>
            <td><?= htmlspecialchars($row['display_name']) ?></td>
            <td><?= htmlspecialchars($row['company_hierarchy']) ?></td>
            <td><?= htmlspecialchars($row['designation']) ?></td>
            <td><?= htmlspecialchars($row['mobile_no']) ?></td>
            <td><?= htmlspecialchars($row['voice_data']) ?></td>
            <td><?= htmlspecialchars($row['date_joined']) ?></td>
            <td><?= htmlspecialchars($row['Resignation_Effective_Date']) ?></td>
            <td><?= htmlspecialchars($row['connection_status']) ?></td>
            <td><?= htmlspecialchars($row['disconnection_date']) ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
