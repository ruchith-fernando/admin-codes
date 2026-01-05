<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
include('connections/connection.php');
date_default_timezone_set('Asia/Colombo');
header('Content-Type: application/json');

// Session
$hris_id     = $_SESSION['hris'] ?? 'unknown';
$branch_code = $_SESSION['branch_code'] ?? '';
$branch_name = $_SESSION['branch_name'] ?? '';

// POST values
$item_code     = $_POST['item_code'] ?? '';
$quantity      = (int)($_POST['quantity'] ?? 0);
$unit_price_in = floatval($_POST['unit_price'] ?? 0); // user-entered
$received_date = $_POST['received_date'] ?? '';
$tax_included  = $_POST['tax_included'] ?? 'yes';

// Validate
if ($item_code === '' || $quantity <= 0 || $unit_price_in <= 0 || $received_date === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input values.']);
    exit;
}

// Fetch tax rates
$rate_sql = "SELECT vat_percentage, sscl_percentage FROM tbl_admin_vat_sscl_rates ORDER BY effective_date DESC LIMIT 1";
$rate_result = mysqli_query($conn, $rate_sql);

if ($rate_result && mysqli_num_rows($rate_result) > 0) {
    $rate_row = mysqli_fetch_assoc($rate_result);
    $vat_rate = floatval($rate_row['vat_percentage']);
    $sscl_rate = floatval($rate_row['sscl_percentage']);
} else {
    $vat_rate = 0;
    $sscl_rate = 0;
    error_log("[WARNING] No VAT/SSCL rates found.");
}

$sscl_amount = 0;
$vat_amount = 0;
$unit_price = $unit_price_in;

if ($tax_included === 'yes') {
    // Proper reverse calculation: VAT reversed first, then SSCL
    $vat_rate_decimal = $vat_rate / 100;
    $sscl_rate_decimal = $sscl_rate / 100;

    $price_ex_vat = $unit_price_in / (1 + $vat_rate_decimal);
    $base_price = $price_ex_vat / (1 + $sscl_rate_decimal);

    $sscl_amount = round($base_price * $sscl_rate_decimal, 2);
    $vat_amount = round(($base_price + $sscl_amount) * $vat_rate_decimal, 2);

    $unit_price = round($base_price, 2);
    $stock_value = round($unit_price_in * $quantity, 2); // paid amount
} else {
    // Tax excluded: add tax on top of base
    $unit_price = $unit_price_in;
    $sscl_amount = round($unit_price * ($sscl_rate / 100), 2);
    $vat_amount = round(($unit_price + $sscl_amount) * ($vat_rate / 100), 2);
    $stock_value = round(($unit_price + $sscl_amount + $vat_amount) * $quantity, 2);
}


// Calculate stock value correctly
if ($tax_included === 'yes') {
    $stock_value = round($unit_price_in * $quantity, 2);  // use original price
} else {
    $stock_value = round(($unit_price + $sscl_amount + $vat_amount) * $quantity, 2);
}

// Debug log
error_log("[DEBUG] unit_price_in=$unit_price_in | final_unit_price=$unit_price | sscl=$sscl_amount | vat=$vat_amount | stock_value=$stock_value");

// Insert
$sql = "INSERT INTO tbl_admin_stationary_stock_in (
    item_code, quantity, unit_price, received_date,
    remaining_quantity, branch_code, branch_name, created_by,
    sscl_rate, vat_rate, tax_included, sscl_amount, vat_amount,
    created_at, status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $err = $conn->error;
    error_log("[ERROR] SQL Prepare Failed: $err");
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $err]);
    exit;
}

$stmt->bind_param(
    "sidsssssddssd",
    $item_code, $quantity, $unit_price, $received_date,
    $quantity, $branch_code, $branch_name, $hris_id,
    $sscl_rate, $vat_rate, $tax_included, $sscl_amount, $vat_amount
);

if ($stmt->execute()) {
    error_log("[SUCCESS] Inserted $item_code by $hris_id | Stock Value: $stock_value");
    echo json_encode(['status' => 'success', 'message' => 'Stock In saved successfully.']);
} else {
    $err = $stmt->error;
    error_log("[ERROR] Execution failed: $err");
    echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $err]);
}

$stmt->close();
$conn->close();
?>
