<?php
include "connections/connection.php";

$search = trim($_GET['search'] ?? '');
$from = $_GET['from_date'] ?? '';
$to = $_GET['to_date'] ?? '';

$conditions = [];

if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(document_number LIKE '%$safe%' OR requested_by_hris LIKE '%$safe%' OR printed_by LIKE '%$safe%')";
}
if ($from) {
    $conditions[] = "DATE(datetime_printed) >= '" . mysqli_real_escape_string($conn, $from) . "'";
}
if ($to) {
    $conditions[] = "DATE(datetime_printed) <= '" . mysqli_real_escape_string($conn, $to) . "'";
}

$where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=secure_print_logs_" . date('Ymd_His') . ".xls");

echo "<table border='1'>";
echo "<tr>
        <th>Document Number</th>
        <th>Requester's HRIS</th>
        <th>Printed By</th>
        <th>Date & Time Printed</th>
        <th>Copies Printed</th>
      </tr>";

$result = mysqli_query($conn, "SELECT * FROM tbl_admin_secure_print_logs $where ORDER BY datetime_printed DESC");
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>
            <td>" . htmlspecialchars($row['document_number']) . "</td>
            <td>" . htmlspecialchars($row['requested_by_hris']) . "</td>
            <td>" . htmlspecialchars($row['printed_by']) . "</td>
            <td>" . htmlspecialchars($row['datetime_printed']) . "</td>
            <td>" . htmlspecialchars($row['copies_printed']) . "</td>
          </tr>";
}
echo "</table>";
?>
