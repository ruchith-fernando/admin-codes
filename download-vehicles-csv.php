<?php
require_once 'connections/connection.php';

// Force browser to download as CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="approved_vehicles_' . date('Y-m-d_His') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, [
    'Vehicle Type',
    'Vehicle Number',
    'Chassis Number',
    'Make & Model',
    'Engine Capacity (cc)',
    'Year of Manufacture',
    'Fuel Type',
    'Purchase Date',
    'Purchase Value (LKR)',
    'Original Mileage',
    'Assigned User',
    'HRIS',
    'Vehicle Category',
    'Status'
]);

// Query approved vehicles
$query = "SELECT 
    vehicle_type, 
    vehicle_number, 
    chassis_number, 
    make_model, 
    engine_capacity, 
    year_of_manufacture, 
    fuel_type, 
    purchase_date, 
    purchase_value, 
    original_mileage, 
    assigned_user, 
    assigned_user_hris, 
    vehicle_category, 
    status 
FROM tbl_admin_vehicle 
WHERE status = 'Approved'
ORDER BY purchase_date DESC";

$result = $conn->query($query);

// Write rows to CSV
while ($row = $result->fetch_assoc()) {
    // Clean up commas/newlines to avoid CSV breakage
    $cleanRow = array_map(function ($value) {
        return preg_replace("/[\r\n]+/", " ", trim($value));
    }, $row);

    fputcsv($output, $cleanRow);
}

// Close output
fclose($output);
exit;
