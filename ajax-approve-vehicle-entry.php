<?php
require_once 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('Invalid Request');

$entry_id = intval($_POST['entry_id']);
$entry_type = $_POST['entry_type'];

$validTypes = ['maintenance', 'service', 'license'];
if (!in_array($entry_type, $validTypes)) exit('Invalid Type');

$tableMap = [
    'maintenance' => 'tbl_admin_vehicle_maintenance',
    'service' => 'tbl_admin_vehicle_service',
    'license' => 'tbl_admin_vehicle_licensing_insurance',
];

$table = $tableMap[$entry_type];

$stmt = $conn->prepare("UPDATE $table SET status = 'approved' WHERE id = ?");
$stmt->bind_param("i", $entry_id);

if ($stmt->execute()) {
    // Simulate payment advice generation
    $adviceId = "PA-" . strtoupper($entry_type[0]) . "-" . str_pad($entry_id, 6, "0", STR_PAD_LEFT);
    echo "<div class='alert alert-success'>
        <strong>Success:</strong> Entry approved.<br>
        <strong>Payment Advice No:</strong> <code>{$adviceId}</code>
    </div>";
} else {
    echo "<div class='alert alert-danger'>Failed to approve entry.</div>";
}

$stmt->close();
