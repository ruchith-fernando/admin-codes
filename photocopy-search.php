<?php
session_start();
require_once "connections/connection.php"; // $conn must be mysqli

$serial = trim($_POST['serial_number'] ?? '');

if ($serial === '') {
    echo "<div class='alert-danger'>⚠ Please enter a serial number.</div>";
    exit;
}

$sql = "SELECT * FROM tbl_admin_actual_photocopy WHERE serial_number = ? ORDER BY record_date DESC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo "<div class='alert-danger'>❌ SQL Error: " . $conn->error . "</div>";
    exit;
}

$stmt->bind_param("s", $serial);
if (!$stmt->execute()) {
    echo "<div class='alert-danger'>❌ Query failed: " . $stmt->error . "</div>";
    exit;
}

$res = $stmt->get_result();

if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "<div class='result-block'>
                <p><b>Date:</b> {$row['record_date']}</p>
                <p><b>Serial:</b> {$row['serial_number']}</p>
                <p><b>Branch:</b> {$row['branch_name']} ({$row['branch_code']})</p>
                <p><b>Copies:</b> {$row['number_of_copy']}</p>
                <p><b>Rate:</b> {$row['rate']}</p>
                <p><b>Amount:</b> {$row['amount']}</p>
                <p><b>SSCL:</b> {$row['sscl']}</p>
                <p><b>VAT:</b> {$row['vat']}</p>
                <p><b>Total:</b> {$row['total']}</p>
                <p><b>Type:</b> {$row['replacement_type']}</p>
                <p><b>Note:</b> {$row['replacement_note']}</p>
              </div>";
    }
} else {
    echo "<div class='alert-danger'>❌ No records found for serial <b>$serial</b>.</div>";
}

$stmt->close();
$conn->close();
