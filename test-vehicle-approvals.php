<?php
session_start();
require_once 'connections/connection.php';

// Debug: show session
echo "<h3>Session</h3><pre>";
print_r($_SESSION);
echo "</pre>";

// Debug: show if logged in
if (!isset($_SESSION['hris'])) {
    echo "<div style='color: red;'>❌ No active session (HRIS missing)</div>";
    exit;
} else {
    echo "<div style='color: green;'>✅ Logged in as: {$_SESSION['hris']}</div>";
}

// Function to print table
function printTable($results, $type) {
    if (!$results || $results->num_rows === 0) {
        echo "<p>No pending $type records found.</p>";
        return;
    }

    echo "<h4>$type Records</h4>";
    echo "<table border='1' cellpadding='5' cellspacing='0'><tr><th>ID</th><th>Vehicle</th><th>Entered By</th><th>Date</th></tr>";
    while ($row = $results->fetch_assoc()) {
        echo "<tr>
            <td>{$row['id']}</td>
            <td>{$row['vehicle_number']}</td>
            <td>{$row['entered_by']}</td>
            <td>{$row['created_at']}</td>
        </tr>";
    }
    echo "</table><br>";
}

// Maintenance
$m = $conn->query("SELECT id, vehicle_number, entered_by, created_at FROM tbl_admin_vehicle_maintenance WHERE status = 'Pending'");
printTable($m, 'Maintenance');

// Service
$s = $conn->query("SELECT id, vehicle_number, entered_by, created_at FROM tbl_admin_vehicle_service WHERE status = 'Pending'");
printTable($s, 'Service');

// License
$l = $conn->query("SELECT id, vehicle_number, person_handled AS entered_by, created_at FROM tbl_admin_vehicle_licensing_insurance WHERE status IS NULL OR status = 'Pending'");
printTable($l, 'License');
