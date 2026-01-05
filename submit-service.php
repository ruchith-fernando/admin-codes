<?php
// submit-service.php
session_start();
require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';
require_once 'includes/userlog.php';
header('Content-Type: application/json; charset=utf-8');

function respond($status, $title, $message, $extras = []) {
  echo json_encode(array_merge([
    'status'  => $status,
    'title'   => $title,
    'message' => $message,
  ], $extras));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond('error', 'Invalid Request', 'Only POST is allowed.');
}

if (!isset($_SESSION['hris'])) {
  respond('error', 'Unauthorized', 'You must be logged in.');
}

$entered_by         = $_SESSION['hris'] ?? 'unknown';
$vehicle_id         = intval($_POST['vehicle_id'] ?? 0);
$service_date       = trim($_POST['service_date'] ?? '');
$meter_reading      = intval($_POST['meter_reading'] ?? 0);
$next_service_meter = intval($_POST['next_service_meter'] ?? 0);
$shop_name          = trim($_POST['shop_name'] ?? '');
$amount             = (string)($_POST['amount'] ?? '0');
$amount             = str_replace(',', '', $amount); // keep as string (DB is char(50))
$driver_id          = intval($_POST['driver_id'] ?? 0);

if (!$vehicle_id) respond('error','Missing Data','Vehicle is required.');
if ($service_date === '') respond('error','Missing Data','Service date is required.');
if ($shop_name === '') respond('error','Missing Data','Shop/Garage is required.');
if (!$driver_id) respond('error','Missing Data','Driver is required.');

// 🚗 Vehicle validation
$stmt = $conn->prepare("SELECT vehicle_number FROM tbl_admin_vehicle WHERE id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$stmt->bind_result($vehicle_number);
$stmt->fetch();
$stmt->close();
if (!$vehicle_number) respond('error','Invalid Vehicle','Vehicle ID is not valid.');

// 👨‍✈️ Driver validation
$stmt = $conn->prepare("SELECT driver_name FROM tbl_admin_driver WHERE id = ?");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$stmt->bind_result($driver_name);
$stmt->fetch();
$stmt->close();
if (!$driver_name) respond('error','Invalid Driver','Driver ID is not valid.');

// ✅ Save shop if not existing
$shop_name = trim($shop_name);
$stmtShop = $conn->prepare("INSERT IGNORE INTO tbl_admin_shop_name (shop_name) VALUES (?)");
$stmtShop->bind_param("s", $shop_name);
$stmtShop->execute();
$stmtShop->close();

// 📎 File upload (optional)
$bill_upload = '';
if (!empty($_FILES['bill_file']['name']) && $_FILES['bill_file']['error'] === 0) {
  $dir = "uploads/service/";
  if (!is_dir($dir)) mkdir($dir, 0755, true);

  $ext = strtolower(pathinfo($_FILES['bill_file']['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'])) respond('error','Invalid File','Allowed: jpg, jpeg, png, pdf.');

  $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['bill_file']['name']);
  $filepath = $dir . $filename;

  if (!move_uploaded_file($_FILES['bill_file']['tmp_name'], $filepath)) {
    respond('error','Upload Failed','Could not move uploaded file.');
  }
  $bill_upload = $filepath;
}

// 🗓️ report_date based on service_date
$report_date = (!empty($service_date) && $service_date !== '0000-00-00') ? $service_date : date('Y-m-d');

// 🧾 Insert
$stmt = $conn->prepare("
  INSERT INTO tbl_admin_vehicle_service
    (vehicle_number, service_date, meter_reading, next_service_meter, shop_name, amount, driver_name, bill_upload, entered_by, report_date)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) respond('error','SQL Prepare Error',$conn->error);

$stmt->bind_param(
  "ssiissssss",
  $vehicle_number,
  $service_date,
  $meter_reading,
  $next_service_meter,
  $shop_name,
  $amount,
  $driver_name,
  $bill_upload,
  $entered_by,
  $report_date
);

if ($stmt->execute()) {
  $username = $_SESSION['name'] ?? 'SYSTEM';
  $hris     = $_SESSION['hris'] ?? 'UNKNOWN';

  $log_details = "✅ $username ($hris) submitted VEHICLE SERVICE | Vehicle: $vehicle_number | Date: $service_date | Meter: $meter_reading | Next: $next_service_meter | Shop: $shop_name | Amount: Rs.$amount | Driver: $driver_name";
  if (!empty($bill_upload)) $log_details .= " | Bill Uploaded";
  userlog($log_details);

  $record_id = $stmt->insert_id;
  $sr_number = generate_sr_number($conn, 'tbl_admin_vehicle_service', $record_id);

  respond('success','Service Saved','Saved successfully.', ['sr_number'=>$sr_number ?: null]);
} else {
  respond('error','Database Error',$stmt->error ?: 'Insert failed.');
}

$stmt->close();
