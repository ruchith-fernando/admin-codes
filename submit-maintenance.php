<?php
// submit-maintenance.php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function respond($status, $title, $message, $extras = []) {
  echo json_encode(array_merge([
    'status' => $status,
    'title'  => $title,
    'message'=> $message
  ], $extras));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond('error', 'Invalid Request', 'Only POST is allowed.');

$entered_by = $_SESSION['hris'] ?? 'unknown';

$vehicle_id       = intval($_POST['vehicle_id'] ?? 0);
$maintenance_type = trim($_POST['maintenance_type'] ?? '');

if (!$vehicle_id || !$maintenance_type) respond('error', 'Missing Data', 'Vehicle and Maintenance Type are required.');

$typeMap = [
  'battery'          => 'Battery',
  'tire'             => 'Tire',
  'ac'               => 'AC',
  'running_repairs'  => 'Running Repairs',
];

$db_maintenance_type = $typeMap[$maintenance_type] ?? '';
if ($db_maintenance_type === '') respond('error','Invalid Type','Invalid maintenance type.');

// vehicle lookup
$stmt = $conn->prepare("SELECT vehicle_number FROM tbl_admin_vehicle WHERE id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$stmt->bind_result($vehicle_number);
$stmt->fetch();
$stmt->close();
if (!$vehicle_number) respond('error', 'Invalid Vehicle', 'Vehicle not found.');

// upload helper (STRICT)
function uploadFileStrict($inputName, $folder = 'uploads/maintenance/') {
  if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) return '';
  if ($_FILES[$inputName]['error'] !== 0) respond('error','Upload Failed',"Upload error for $inputName.");

  if (!is_dir($folder)) mkdir($folder, 0755, true);

  $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['pdf','jpg','jpeg','png'])) respond('error','Invalid File','Allowed: pdf, jpg, jpeg, png.');

  $safe = preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES[$inputName]['name']);
  $unique = uniqid($inputName . '_', true) . '_' . $safe;
  $target = $folder . $unique;

  if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $target)) {
    respond('error','Upload Failed','Could not move uploaded file.');
  }
  return $target;
}

// defaults
$shop = $price = $make = $purchase_date = $warranty_period = "";
$tire_size = $tire_quantity = $warranty_mileage = "";
$wheel_alignment_amount = "";
$repair_date = $problem_description = $driver_name = "";
$bill_upload = $warranty_card_upload = "";
$mileage = "";

$tire_items = [];

// gather data per type
if ($maintenance_type === 'battery') {

  $shop            = trim($_POST['shop_name'] ?? '');
  $price           = str_replace(',', '', $_POST['battery_price'] ?? '0');
  $make            = trim($_POST['battery_make'] ?? '');
  $purchase_date   = trim($_POST['battery_purchase_date'] ?? '');
  $warranty_period = trim($_POST['battery_warranty'] ?? '');
  $driver_name     = trim($_POST['battery_driver'] ?? '');
  $bill_upload     = uploadFileStrict('battery_bill');
  $warranty_card_upload = uploadFileStrict('battery_warranty_card');
  $mileage         = trim($_POST['battery_mileage'] ?? '');

  if ($shop === '' || $make === '' || $purchase_date === '' || $warranty_period === '' || $driver_name === '' || $mileage === '') {
    respond('error','Missing Data','Please fill all required battery fields.');
  }

} elseif ($maintenance_type === 'tire') {

  $purchase_date   = trim($_POST['tire_purchase_date'] ?? '');
  $tire_size       = trim($_POST['tire_size'] ?? '');
  $tire_quantity   = intval($_POST['tire_quantity'] ?? 0);
  $shop            = trim($_POST['shop_name'] ?? '');
  $warranty_mileage= trim($_POST['tire_warranty_km'] ?? '');
  $wheel_alignment_amount = str_replace(',', '', $_POST['wheel_alignment_amount'] ?? '');
  $driver_name     = trim($_POST['tire_driver'] ?? '');
  $bill_upload     = uploadFileStrict('tire_bill');
  $mileage         = trim($_POST['tire_mileage'] ?? '');

  $tire_items = $_POST['tire_items'] ?? [];

  if ($purchase_date === '' || $tire_size === '' || $shop === '' || $warranty_mileage === '' || $driver_name === '' || $mileage === '') {
    respond('error','Missing Data','Please fill all required tire fields.');
  }
  if ($tire_quantity < 1 || $tire_quantity > 4) respond('error','Invalid Tire Count','Tire count must be 1 to 4.');

  // Validate each tire item + compute total
  $total = 0.0;
  $brands = [];

  for ($i=1; $i <= $tire_quantity; $i++) {
    $b = trim($tire_items[$i]['brand'] ?? '');
    $p = str_replace(',', '', $tire_items[$i]['price'] ?? '');
    if ($b === '' || $p === '') respond('error','Missing Tire Data',"Brand and price required for tire #$i.");
    $brands[] = $b;
    $total += floatval($p);

    // normalize stored values
    $tire_items[$i]['brand'] = $b;
    $tire_items[$i]['price'] = $p;
  }

  // store total in header "price"; keep brands summary in "make"
  $price = (string)$total;
  $make  = "Tires: " . implode(', ', array_unique($brands));

} elseif ($maintenance_type === 'ac') {

  $repair_date         = trim($_POST['ac_repair_date'] ?? '');
  $shop                = trim($_POST['shop_name'] ?? '');
  $problem_description = trim($_POST['ac_problem'] ?? '');
  $price               = str_replace(',', '', $_POST['ac_amount'] ?? '0');
  $driver_name         = trim($_POST['ac_driver'] ?? '');
  $bill_upload         = uploadFileStrict('ac_bill');
  $mileage             = trim($_POST['ac_mileage'] ?? '');

  if ($repair_date === '' || $shop === '' || $problem_description === '' || $driver_name === '' || $mileage === '') {
    respond('error','Missing Data','Please fill all required AC fields.');
  }

} else { // running_repairs

  $repair_date         = trim($_POST['running_repairs_repair_date'] ?? '');
  $shop                = trim($_POST['shop_name'] ?? '');
  $problem_description = trim($_POST['running_repairs_problem'] ?? '');
  $price               = str_replace(',', '', $_POST['running_repairs_amount'] ?? '0');
  $driver_name         = trim($_POST['running_repairs_driver'] ?? '');
  $bill_upload         = uploadFileStrict('running_repairs_bill');
  $mileage             = trim($_POST['running_repairs_mileage'] ?? '');

  if ($repair_date === '' || $shop === '' || $problem_description === '' || $driver_name === '' || $mileage === '') {
    respond('error','Missing Data','Please fill all required Running Repairs fields.');
  }
}

// image_path json (existing behavior)
$imagePathJson = json_encode(array_values(array_filter([$bill_upload, $warranty_card_upload])));

// ✅ Determine report date
if (!empty($repair_date) && $repair_date !== '0000-00-00') {
  $report_date = $repair_date;
} elseif (!empty($purchase_date) && $purchase_date !== '0000-00-00') {
  $report_date = $purchase_date;
} else {
  $report_date = date('Y-m-d');
}

// ✅ Save shop name (common shop table)
if ($shop !== '') {
  $stmtShop = $conn->prepare("INSERT IGNORE INTO tbl_admin_shop_name (shop_name) VALUES (?)");
  $stmtShop->bind_param("s", $shop);
  $stmtShop->execute();
  $stmtShop->close();
}

// ✅ Insert maintenance header
$q = "INSERT INTO tbl_admin_vehicle_maintenance
(vehicle_number, maintenance_type, shop_name, price, make, purchase_date, warranty_period,
 tire_size, tire_quantity, warranty_mileage, wheel_alignment_amount,
 repair_date, problem_description, driver_name,
 bill_upload, warranty_card_upload, image_path, mileage, entered_by, report_date)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($q);
if (!$stmt) respond('error', 'SQL Prepare Error', $conn->error);

// bind_param() needs VARIABLES only (no casts/functions inside)
$tire_quantity_db = (string)$tire_quantity;

$stmt->bind_param(
  "ssssssssssssssssssss",
  $vehicle_number,
  $db_maintenance_type,
  $shop,
  $price,
  $make,
  $purchase_date,
  $warranty_period,
  $tire_size,
  $tire_quantity_db,
  $warranty_mileage,
  $wheel_alignment_amount,
  $repair_date,
  $problem_description,
  $driver_name,
  $bill_upload,
  $warranty_card_upload,
  $imagePathJson,
  $mileage,
  $entered_by,
  $report_date
);


if ($stmt->execute()) {
  $maintenance_id = $stmt->insert_id;

  // ✅ If Tire: insert per-tire items
  if ($maintenance_type === 'tire') {
    $ins = $conn->prepare("
      INSERT INTO tbl_admin_vehicle_maintenance_tire_items
        (maintenance_id, tire_no, tire_brand, tire_price)
      VALUES (?, ?, ?, ?)
    ");
    if (!$ins) respond('error','SQL Prepare Error', $conn->error);

    for ($i=1; $i <= $tire_quantity; $i++) {
      $b = $tire_items[$i]['brand'];
      $p = $tire_items[$i]['price'];
      $ins->bind_param("iiss", $maintenance_id, $i, $b, $p);
      $ins->execute();
    }
    $ins->close();
  }

  // ✅ Logging + SR
  require_once 'includes/userlog.php';
  require_once 'includes/sr-generator.php';

  $username = $_SESSION['name'] ?? 'SYSTEM';
  $hris     = $_SESSION['hris'] ?? 'UNKNOWN';

  $log_details = "✅ $username ($hris) submitted VEHICLE MAINTENANCE | Vehicle: $vehicle_number | Type: $db_maintenance_type | Shop: $shop | Amount: Rs.$price | Mileage: $mileage";

  if ($maintenance_type === 'battery') {
    $log_details .= " | Make: $make | Warranty: $warranty_period | Driver: $driver_name";
  }
  if ($maintenance_type === 'tire') {
    $log_details .= " | Size: $tire_size | Qty: $tire_quantity | Warranty KM: $warranty_mileage | Wheel Align: Rs.$wheel_alignment_amount | Driver: $driver_name";
  }
  if ($maintenance_type === 'ac' || $maintenance_type === 'running_repairs') {
    $log_details .= " | Date: $repair_date | Problem: $problem_description | Driver: $driver_name";
  }

  if (!empty($bill_upload)) $log_details .= " | Bill Uploaded";
  if (!empty($warranty_card_upload)) $log_details .= " | Warranty Card Uploaded";

  userlog($log_details);

  $sr = generate_sr_number($conn, 'tbl_admin_vehicle_maintenance', $maintenance_id);
  respond('success', 'Maintenance Saved', 'Saved successfully.', ['sr_number' => $sr ?: null]);

} else {
  respond('error', 'Execution Failed', $stmt->error ?: 'Insert failed.');
}

$stmt->close();
