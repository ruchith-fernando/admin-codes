<?php
require_once 'connections/connection.php';

$assignedUsers = ['kasun.j', 'nimal.p', 'sunil.d', 'amaya.s', 'ravi.k', 'janith.r'];
$personsHandled = ['Samantha', 'Dilani', 'Ruwan', 'Chamari', 'Kavindu'];

for ($i = 1; $i <= 15; $i++) {
    // Generate unique vehicle number in format CXX-0000
    $prefix = 'C' . chr(rand(65, 90)) . chr(rand(65, 90));
    $number = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $vehicleNumber = $prefix . '-' . $number;

    // Random assigned user and handler
    $assignedUser = $assignedUsers[array_rand($assignedUsers)];
    $personHandled = $personsHandled[array_rand($personsHandled)];

    // Insert into tbl_admin_vehicle
    $vehicleType = 'Car';
    $makeModel = 'Toyota Axio';
    $purchaseDate = date('Y-m-d', strtotime('-' . rand(1, 10) . ' years'));
    $purchaseValue = rand(2000000, 6000000);
    $vehicleCategory = 'General';
    $fuelType = rand(0, 1) ? 'Petrol' : 'Hybrid';

    $conn->query("
        INSERT INTO tbl_admin_vehicle 
        (vehicle_type, vehicle_number, make_model, purchase_date, purchase_value, assigned_user, vehicle_category, fuel_type) 
        VALUES 
        ('$vehicleType', '$vehicleNumber', '$makeModel', '$purchaseDate', '$purchaseValue', '$assignedUser', '$vehicleCategory', '$fuelType')
    ");

    // Generate random revenue license date within 45 days from today (some overdue, some upcoming)
    $daysOffset = rand(-10, 45);
    $revenueDate = date('Y-m-d', strtotime("+$daysOffset days"));
    $emissionDate = date('Y-m-d', strtotime("-6 months"));
    $emissionAmount = rand(1000, 3000);
    $revenueAmount = rand(1500, 5000);
    $insuranceAmount = rand(7000, 15000);

    // Insert into tbl_admin_vehicle_licensing_insurance
    $conn->query("
        INSERT INTO tbl_admin_vehicle_licensing_insurance 
        (vehicle_number, emission_test_date, emission_test_amount, revenue_license_date, revenue_license_amount, insurance_amount, person_handled)
        VALUES
        ('$vehicleNumber', '$emissionDate', '$emissionAmount', '$revenueDate', '$revenueAmount', '$insuranceAmount', '$personHandled')
    ");
}

echo "15 test vehicle and license records inserted successfully.";
?>
