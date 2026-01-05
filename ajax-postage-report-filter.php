<?php
include 'connections/connection.php';

$from = $_POST['from'] ?? '';
$to = $_POST['to'] ?? '';
$search = trim($_POST['search'] ?? '');

$filter = "";
if ($from && $to) {
    $fromDate = date('Y-m-d', strtotime($from));
    $toDate = date('Y-m-d', strtotime($to));
    $filter = "WHERE entry_date BETWEEN '$fromDate' AND '$toDate'";
}

if ($search) {
    $search = $conn->real_escape_string($search);
    $filter .= $filter ? " AND" : "WHERE";
    $filter .= " (department LIKE '%$search%' OR serial_number LIKE '%$search%' OR postal_serial_number LIKE '%$search%')";
}

$query = "SELECT * FROM tbl_admin_actual_postage_stamps $filter ORDER BY department ASC, entry_date DESC";
$result = $conn->query($query);
?>

<table class="table table-bordered table-striped">
    <thead class="table-light">
        <tr>
            <th>Department</th>
            <th>Date</th>
            <th>Serial No</th>
            <th>Colombo</th>
            <th>Outstation</th>
            <th>Total</th>
            <th>Open Balance</th>
            <th>End Balance</th>
            <th>Total Spent (Rs.)</th>
            <th>Postal Serial No</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()):
                $spent = (float)$row['open_balance'] - (float)$row['end_balance'];
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td><?= htmlspecialchars($row['entry_date']) ?></td>
                    <td><?= htmlspecialchars($row['serial_number']) ?></td>
                    <td class="text-end"><?= number_format($row['where_to_colombo']) ?></td>
                    <td class="text-end"><?= number_format($row['where_to_outstation']) ?></td>
                    <td class="text-end"><?= number_format($row['total']) ?></td>
                    <td class="text-end"><?= number_format($row['open_balance'], 2) ?></td>
                    <td class="text-end"><?= number_format($row['end_balance'], 2) ?></td>
                    <td class="text-end text-success fw-bold"><?= number_format($spent, 2) ?></td>
                    <td><?= htmlspecialchars($row['postal_serial_number'] ?? '-') ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="10" class="text-center text-danger fw-bold">No records found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
