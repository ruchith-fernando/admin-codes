<?php
// submit-license.php
session_start();
require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';
require_once 'includes/userlog.php';
header('Content-Type: application/json');

function respond($status,$title,$message,$extras=[]){
  echo json_encode(array_merge(['status'=>$status,'title'=>$title,'message'=>$message],$extras)); 
  exit;
}

if (!isset($_SESSION['hris'])) respond('error','Unauthorized','You must be logged in.');
$entered_by = $_SESSION['hris'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond('error','Invalid Request','Only POST is allowed.');

$vehicle_id       = intval($_POST['vehicle_id'] ?? 0);
$emission_date    = $_POST['emission_date'] ?? null;
$emission_amount  = str_replace(',','',$_POST['emission_amount'] ?? '0');
$revenue_date     = $_POST['revenue_date'] ?? null;
$revenue_amount   = str_replace(',','',$_POST['revenue_amount'] ?? '0');
$insurance_amount = str_replace(',','',$_POST['insurance_amount'] ?? '0');
$driver_id        = intval($_POST['driver_id'] ?? 0);

// 🚗 Vehicle
$stmt = $conn->prepare("SELECT vehicle_number, fuel_type FROM tbl_admin_vehicle WHERE id = ?");
$stmt->bind_param("i",$vehicle_id);
$stmt->execute();
$stmt->bind_result($vehicle_number,$fuel_type);
$stmt->fetch();
$stmt->close();
if (!$vehicle_number) respond('error','Vehicle Not Found','Invalid vehicle.');

// 👨‍✈️ Driver
$stmt = $conn->prepare("SELECT driver_name FROM tbl_admin_driver WHERE id = ?");
$stmt->bind_param("i",$driver_id);
$stmt->execute();
$stmt->bind_result($driver_name);
$stmt->fetch();
$stmt->close();
if (!$driver_name) respond('error','Driver Not Found','Invalid driver.');

$fuel_type = strtolower(trim($fuel_type));

// 🗓️ Determine report_date (ONLY from revenue_date)
if (!empty($revenue_date) && $revenue_date !== '0000-00-00') {
    $report_date = $revenue_date;
} else {
    $report_date = date('Y-m-d'); // fallback to today
}

// ---- INSERT RECORD ----
if (in_array($fuel_type, ['hybrid','electric'])) {
  // Hybrid/Electric: no emission fields
  $stmt = $conn->prepare("
    INSERT INTO tbl_admin_vehicle_licensing_insurance
      (vehicle_number, revenue_license_date, revenue_license_amount, insurance_amount, person_handled, entered_by, report_date)
    VALUES (?,?,?,?,?,?,?)
  ");
  if (!$stmt) respond('error','SQL Prepare Error',$conn->error);
  $stmt->bind_param("sssssss",
    $vehicle_number,
    $revenue_date,
    $revenue_amount,
    $insurance_amount,
    $driver_name,
    $entered_by,
    $report_date
  );

  $record_type = "Hybrid/Electric License+Insurance";

} else {
  // Petrol/Diesel etc. include emission fields
  $stmt = $conn->prepare("
    INSERT INTO tbl_admin_vehicle_licensing_insurance
      (vehicle_number, emission_test_date, emission_test_amount, revenue_license_date, revenue_license_amount, insurance_amount, person_handled, entered_by, report_date)
    VALUES (?,?,?,?,?,?,?,?,?)
  ");
  if (!$stmt) respond('error','SQL Prepare Error',$conn->error);
  $stmt->bind_param("sssssssss",
    $vehicle_number,
    $emission_date,
    $emission_amount,
    $revenue_date,
    $revenue_amount,
    $insurance_amount,
    $driver_name,
    $entered_by,
    $report_date
  );

  $record_type = "License+Insurance+Emission";
}

if ($stmt->execute()) {
  // 🧠 Logging
  $username = $_SESSION['name'] ?? 'SYSTEM';
  $hris = $_SESSION['hris'] ?? 'UNKNOWN';
  $log_details = "✅ $username ($hris) submitted VEHICLE LICENSE & INSURANCE | Vehicle: $vehicle_number | Fuel: $fuel_type | Type: $record_type | Revenue: Rs.$revenue_amount | Insurance: Rs.$insurance_amount | Driver: $driver_name";
  if (!in_array($fuel_type,['hybrid','electric'])) {
      $log_details .= " | Emission Date: $emission_date | Emission Amt: Rs.$emission_amount";
  }
  userlog($log_details);

  $sr = generate_sr_number($conn,'tbl_admin_vehicle_licensing_insurance',$stmt->insert_id);
  respond('success','License/Insurance Saved','Saved successfully.', ['sr_number'=>$sr ?: null]);

} else {
  respond('error','Database Error',$stmt->error ?: 'Insert failed.');
}

$stmt->close();
?>
<script>
window.alert = function(){};
</script>
