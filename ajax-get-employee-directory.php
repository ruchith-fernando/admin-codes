<?php
include 'connections/connection.php';

$search = $_GET['search'] ?? '';
$search = "%$search%";

$stmt = $conn->prepare("SELECT * FROM tbl_admin_employee_details 
    WHERE hris LIKE ? OR full_name LIKE ? OR nic_no LIKE ? OR mobile_no LIKE ? 
    LIMIT 100");
$stmt->bind_param('ssss', $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();
?>

<table class="table table-hover table-bordered">
    <thead>
        <tr>
            <th>HRIS</th>
            <th>Full Name</th>
            <th>NIC</th>
            <th>Mobile</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="view-employee" data-employee='<?php echo json_encode($row); ?>'>
            <td><?= htmlspecialchars($row['hris']) ?></td>
            <td><?= htmlspecialchars($row['full_name']) ?></td>
            <td><?= htmlspecialchars($row['nic_no']) ?></td>
            <td><?= htmlspecialchars($row['mobile_no']) ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
