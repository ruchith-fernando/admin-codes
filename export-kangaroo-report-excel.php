<?php
include 'connections/connection.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = "1=1";
$params = [];
$types = "";

if ($from && $to) {
    $where .= " AND date BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
    $types .= "ss";
}

if ($search !== "") {
    $where .= " AND (voucher_no LIKE ? OR vehicle_no LIKE ? OR passengers LIKE ? OR department LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, array_fill(0, 4, $like));
    $types .= "ssss";
}

$sql = "SELECT * FROM tbl_admin_kangaroo_transport WHERE $where ORDER BY date DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Output headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"kangaroo-transport-report.xls\"");

echo "<table border='1'>";
echo "<tr>
<th>Date</th>
<th>Cab No</th>
<th>Voucher No</th>
<th>Vehicle No</th>
<th>Start → End</th>
<th>KM</th>
<th>Additional</th>
<th>Total</th>
<th>Passengers</th>
<th>Department</th>
</tr>";

$total_additional = 0;
$total_main = 0;

while ($row = $result->fetch_assoc()) {
    $additional = (float)$row['additional_charges'];
    $main_total = (float)$row['total'];

    $total_additional += $additional;
    $total_main += $main_total;

    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cab_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['voucher_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['vehicle_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['start_location']) . " → " . htmlspecialchars($row['end_location']) . "</td>";
    echo "<td>" . number_format($row['total_km'], 1) . "</td>";
    echo "<td>" . number_format($additional, 2) . "</td>";
    echo "<td>" . number_format($main_total, 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['passengers']) . "</td>";
    echo "<td>" . htmlspecialchars($row['department']) . "</td>";
    echo "</tr>";
}

// Totals
echo "<tr style='font-weight:bold; background-color:#f2f2f2'>";
echo "<td colspan='6' align='right'>Total</td>";
echo "<td>" . number_format($total_additional, 2) . "</td>";
echo "<td>" . number_format($total_main, 2) . "</td>";
echo "<td colspan='2'></td>";
echo "</tr>";

echo "<tr style='font-weight:bold; background-color:#dff0d8'>";
echo "<td colspan='6' align='right'>Grand Total (Additional + Total)</td>";
echo "<td colspan='2'>" . number_format($total_additional + $total_main, 2) . "</td>";
echo "<td colspan='2'></td>";
echo "</tr>";

echo "</table>";

$stmt->close();
$conn->close();
exit;
?>
