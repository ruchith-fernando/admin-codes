<?php
session_start();
include 'connections/connection.php';

header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => 'Something went wrong.',
];

// Helper function to log errors (optional)
function log_error($message) {
    file_put_contents('tea-service-error.log', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_tea_service'])) {
    $month_year_input = $_POST['month_year'] ?? '';
    if (empty($month_year_input)) {
        $response['message'] = "Month is missing.";
        echo json_encode($response);
        exit;
    }

    $month_year = date('F Y', strtotime($month_year_input));

    $items = [
        'Milk Tea' => 50,
        'Plain Tea' => 23,
        'Plain Coffee' => 23,
        'Milk Coffee' => 50,
        'Green Tea' => 25,
        'Tea Pot' => 85
    ];

    // 1. Check if at least one item has quantity > 0
    $has_data = false;
    foreach ($items as $item_name => $unit_price) {
        $field = str_replace(' ', '_', strtolower($item_name));
        $units = intval($_POST[$field] ?? 0);
        if ($units > 0) {
            $has_data = true;
            break;
        }
    }

    if (!$has_data) {
        $response = [
            'status' => 'warning',
            'message' => 'Please enter at least one item with quantity greater than 0.',
        ];
        echo json_encode($response);
        exit;
    }

    // 2. Check for duplicates
    $check_query = "SELECT 1 FROM tbl_admin_tea_service WHERE month_year = '" . mysqli_real_escape_string($conn, $month_year) . "' LIMIT 1";
    $check_result = mysqli_query($conn, $check_query);
    if (!$check_result) {
        log_error("Check query failed: " . mysqli_error($conn));
        $response['message'] = "Database error during duplicate check.";
        echo json_encode($response);
        exit;
    }

    if (mysqli_num_rows($check_result) > 0) {
        $response = [
            'status' => 'duplicate',
            'message' => "Tea Service for <strong>$month_year</strong> already exists.",
        ];
        echo json_encode($response);
        exit;
    }

    // 3. Get VAT and SSCL
    $rate_query = "SELECT vat_percentage, sscl_percentage FROM tbl_admin_vat_sscl_rates ORDER BY id DESC LIMIT 1";
    $rate_result = mysqli_query($conn, $rate_query);
    if (!$rate_result) {
        log_error("Rate query failed: " . mysqli_error($conn));
        $response['message'] = "Unable to retrieve VAT/SSCL rates.";
        echo json_encode($response);
        exit;
    }

    $rates = mysqli_fetch_assoc($rate_result);
    $vat = $rates['vat_percentage'] ?? 0;
    $sscl = $rates['sscl_percentage'] ?? 0;

    // 4. Insert data
    $inserted = false;
    foreach ($items as $item_name => $unit_price) {
        $field = str_replace(' ', '_', strtolower($item_name));
        $units = intval($_POST[$field] ?? 0);
        if ($units > 0) {
            $total_before_tax = $units * $unit_price;
            $sscl_amount = $total_before_tax * ($sscl / 100);
            $vat_amount = ($total_before_tax + $sscl_amount) * ($vat / 100);
            $total_price = $total_before_tax + $sscl_amount + $vat_amount;

            $insert_query = "INSERT INTO tbl_admin_tea_service 
                (month_year, item_name, units, unit_price, total_price) 
                VALUES (
                    '" . mysqli_real_escape_string($conn, $month_year) . "', 
                    '" . mysqli_real_escape_string($conn, $item_name) . "', 
                    $units, 
                    $unit_price, 
                    $total_price
                )";
            if (!mysqli_query($conn, $insert_query)) {
                log_error("Insert failed for $item_name: " . mysqli_error($conn));
            } else {
                $inserted = true;
            }
        }
    }

    if ($inserted) {
        $response = [
            'status' => 'success',
            'message' => "Tea Service data for <strong>$month_year</strong> saved successfully.",
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => "No valid data saved. Please try again.",
        ];
    }
}

echo json_encode($response);
