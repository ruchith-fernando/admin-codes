<?php
// submit-tea-service.php
include 'connections/connection.php';
header('Content-Type: application/json');

// Validate POST
if (!isset($_POST['month_year'])) {
    echo json_encode(['status' => 'error', 'message' => 'Month is required.']);
    exit;
}

$month_input = $_POST['month_year'];
$month_year = date('F Y', strtotime($month_input));

$items = [
    'Milk Tea' => 50,
    'Plain Tea' => 23,
    'Plain Coffee' => 23,
    'Milk Coffee' => 50,
    'Green Tea' => 25,
    'Tea Pot' => 85
];

// Get latest tax rates
$rate_query = mysqli_query($conn, "SELECT * FROM tbl_admin_vat_sscl_rates ORDER BY effective_date DESC LIMIT 1");
if (!$rate_query || mysqli_num_rows($rate_query) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Tax rates not found.']);
    exit;
}
$rate = mysqli_fetch_assoc($rate_query);
if (!$rate || !isset($rate['sscl_percentage'], $rate['vat_percentage'])) {
    echo json_encode(['status' => 'error', 'message' => 'Tax percentages not found.']);
    exit;
}
$sscl_rate = floatval($rate['sscl_percentage']) / 100;
$vat_rate = floatval($rate['vat_percentage']) / 100;



$debug_data = [];

try {
    foreach ($items as $item_name => $unit_price) {
        $field = strtolower(str_replace(' ', '_', $item_name));
        $units = isset($_POST[$field]) ? intval($_POST[$field]) : 0;
        if ($units <= 0) continue;

        $total_price = round($units * $unit_price, 2);
        $sscl_amount = round($total_price * $sscl_rate, 2);
        $vat_amount = round(($total_price + $sscl_amount) * $vat_rate, 2);
        $grand_total = round($total_price + $sscl_amount + $vat_amount, 2);

        $debug_data[] = [
            'item' => $item_name,
            'units' => $units,
            'unit_price' => $unit_price,
            'total_price' => $total_price,
            'sscl_amount' => $sscl_amount,
            'vat_amount' => $vat_amount,
            'grand_total' => $grand_total,
            'sscl_rate' => $sscl_rate,
            'vat_rate' => $vat_rate
        ];

        $exists = mysqli_query($conn, "SELECT id FROM tbl_admin_tea_service WHERE month_year='$month_year' AND item_name='$item_name'");
        if (mysqli_num_rows($exists) > 0) {
            $update = mysqli_query($conn, "UPDATE tbl_admin_tea_service SET 
                units='$units',
                unit_price='$unit_price',
                total_price='$total_price',
                sscl_amount='$sscl_amount',
                vat_amount='$vat_amount',
                grand_total='$grand_total',
                ot_amount=NULL
                WHERE month_year='$month_year' AND item_name='$item_name'");
            if (!$update) {
                $debug_data[] = ['db_error' => mysqli_error($conn), 'query' => 'UPDATE FAILED'];
            }
        } else {
            $insert = mysqli_query($conn, "INSERT INTO tbl_admin_tea_service 
                (month_year, item_name, units, unit_price, total_price, sscl_amount, vat_amount, grand_total) 
                VALUES 
                ('$month_year', '$item_name', '$units', '$unit_price', '$total_price', '$sscl_amount', '$vat_amount', '$grand_total')");
            if (!$insert) {
                $debug_data[] = ['db_error' => mysqli_error($conn), 'query' => 'INSERT FAILED'];
            }
        }
    }

    // OT HANDLING
    $ot_amount = isset($_POST['ot_amount']) ? floatval($_POST['ot_amount']) : 0;
    if ($ot_amount > 0) {
        $exists_ot = mysqli_query($conn, "SELECT id FROM tbl_admin_tea_service WHERE month_year='$month_year' AND item_name='OT'");
        if (mysqli_num_rows($exists_ot) > 0) {
            $ot_update = mysqli_query($conn, "UPDATE tbl_admin_tea_service SET 
                units=0, unit_price=0, total_price=0, sscl_amount=0, vat_amount=0, grand_total='$ot_amount', ot_amount='$ot_amount' 
                WHERE month_year='$month_year' AND item_name='OT'");
            if (!$ot_update) {
                $debug_data[] = ['db_error' => mysqli_error($conn), 'query' => 'OT UPDATE FAILED'];
            }
        } else {
            $ot_insert = mysqli_query($conn, "INSERT INTO tbl_admin_tea_service 
                (month_year, item_name, units, unit_price, total_price, sscl_amount, vat_amount, grand_total, ot_amount) 
                VALUES 
                ('$month_year', 'OT', 0, 0, 0, 0, 0, '$ot_amount', '$ot_amount')");
            if (!$ot_insert) {
                $debug_data[] = ['db_error' => mysqli_error($conn), 'query' => 'OT INSERT FAILED'];
            }
        }
    }
    // ✅ Add User Logging
    try {
        require_once 'includes/userlog.php';
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $username = $_SESSION['name'] ?? 'SYSTEM';
        $hris = $_SESSION['hris'] ?? 'UNKNOWN';

        $logMessage = "✅ $username ($hris) saved Tea Service data for $month_year";
        userlog($logMessage);

        // Local file fallback logging
        if (!is_dir('logs')) {
            @mkdir('logs', 0777, true);
        }
        @file_put_contents('logs/tea_service.log', "[" . date('Y-m-d H:i:s') . "] $logMessage\n", FILE_APPEND);

    } catch (Throwable $e) {
        // Silent catch, no interruption for the user
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Data saved successfully!',
        'debug_data' => $debug_data
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Exception: '.$e->getMessage()]);
    exit;
}
