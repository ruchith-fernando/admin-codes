<?php
include 'connections/connection.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$search = trim($_GET['search'] ?? '');

$filter = '';
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

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Postage_Report_{$from}_to_{$to}.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr>
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
      </tr>";

$query = "SELECT * FROM tbl_admin_actual_postage_stamps $filter ORDER BY department ASC, entry_date DESC";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $spent = (float)$row['open_balance'] - (float)$row['end_balance'];
        echo "<tr>
                <td>{$row['department']}</td>
                <td>{$row['entry_date']}</td>
                <td>{$row['serial_number']}</td>
                <td align='right'>" . number_format($row['where_to_colombo']) . "</td>
                <td align='right'>" . number_format($row['where_to_outstation']) . "</td>
                <td align='right'>" . number_format($row['total']) . "</td>
                <td align='right'>" . number_format($row['open_balance'], 2) . "</td>
                <td align='right'>" . number_format($row['end_balance'], 2) . "</td>
                <td align='right'>" . number_format($spent, 2) . "</td>
                <td>{$row['postal_serial_number']}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='10' align='center'>No records found.</td></tr>";
}

echo "</table>";
?>
