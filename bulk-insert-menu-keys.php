<?php
include 'connections/connection.php';

$menuItems = [
    // 'menu_key' => ['label', 'group', 'file']
    'stock-in' => ['Stocks In', 'Printing & Stationary', 'stock-in.php'],
    'stock-out' => ['Stocks Out', 'Printing & Stationary', 'stock-out.php'],
    'stock-out-approval' => ['Approve Stocks Out', 'Printing & Stationary', 'stock-out-approval.php'],
    'stock-ledger-report' => ['Stock Ledger Report', 'Printing & Stationary', 'stock-ledger-report.php'],
    'monthly-stock-report' => ['Monthly Stock Report', 'Printing & Stationary', 'monthly-stock-report.php'],
    'budget-vs-actual-stationary' => ['Monthly Budget Vs Actual', 'Printing & Stationary', 'budget-vs-actual-stationary.php'],
    'stationary-stock-in' => ['Test Stock', 'Printing & Stationary', 'stationary-stock-in.php'],
    'stationary-request' => ['Test Request', 'Printing & Stationary', 'stationary-request.php'],
    'boic-requests' => ['BOIC Approval', 'Printing & Stationary', 'boic-requests.php'],
    'approval-orders' => ['Store Keeper', 'Printing & Stationary', 'approval-orders.php'],

    'electricity-initial-entry' => ['Initial Electricity Bill Entry', 'Electricity', 'electricity-initial-entry.php'],
    'electricity-cheque-entry' => ['Cheque Details', 'Electricity', 'electricity-cheque-entry.php'],
    'electricity-full-report' => ['Full Report - Monthly', 'Electricity', 'electricity-full-report.php'],
    'electricity-budget-vs-actual' => ['Monthly Budget Vs Actual', 'Electricity', 'electricity-budget-vs-actual.php'],
    'electricity-overview' => ['Overview', 'Electricity', 'electricity-overview.php'],
    'electricity-graph-report' => ['Graph', 'Electricity', 'electricity-graph-report.php'],

    // Add all remaining menu keys similarly...

    'full-backup' => ['System Full Backup', 'Admin', 'full-backup.php'],
    'user-access-management' => ['User Access', 'Admin', 'user-access-management.php'],
    'register' => ['Register User', 'Admin', 'register.php'],
    'edit-user' => ['Edit Registered User', 'Admin', 'edit-user.php']
];

$count = 0;
foreach ($menuItems as $key => $data) {
    $label = $data[0];
    $group = $data[1];
    $file = $data[2];

    // Check if exists
    $check = mysqli_query($conn, "SELECT id FROM tbl_admin_menu_keys WHERE menu_key='$key'");
    if (mysqli_num_rows($check) == 0) {
        $insert = mysqli_query($conn, "INSERT INTO tbl_admin_menu_keys (menu_key, menu_label, menu_group, menu_file) 
            VALUES ('$key', '$label', '$group', '$file')");
        if ($insert) {
            echo "Inserted: $key<br>";
            $count++;
        } else {
            echo "Error inserting $key<br>";
        }
    } else {
        echo "Skipped (exists): $key<br>";
    }
}
echo "<br>Total Inserted: $count";
?>
