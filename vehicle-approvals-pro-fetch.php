<?php
session_start();
$user = $_SESSION['hris'] ?? '';
// vehicle-approvals-pro-fetch.php
require_once 'connections/connection.php';
header('Content-Type: application/json; charset=utf-8');

function send_json($a){ while(ob_get_level()) ob_end_clean(); echo json_encode($a, JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function e($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function js($v){ return json_encode((string)($v ?? '')); }
function n2($v){
  $s = (string)($v ?? '');
  if ($s === '') return '';
  $n = floatval(str_replace(',','',$s));
  return is_finite($n) ? number_format($n, 2) : e($s);
}

$type = $_POST['type'] ?? '';
$map = [
  'maintenance' => 'tbl_admin_vehicle_maintenance',
  'service'     => 'tbl_admin_vehicle_service',
  'license'     => 'tbl_admin_vehicle_licensing_insurance',
];
$table = $map[$type] ?? '';
if(!$table){
  send_json(['pending'=>'<div class="alert alert-danger">Invalid type.</div>','rejected'=>'']);
}

$conn->set_charset('utf8mb4');
$userEsc = $conn->real_escape_string($user);

$p = '<div class="table-responsive"><table class="table table-bordered table-hover table-striped table-sm align-middle"><thead class="table-light">';
$r = '<div class="table-responsive"><table class="table table-bordered table-hover table-striped table-sm align-middle"><thead class="table-light">';

if($type === 'maintenance'){

  $p .= '<tr>
    <th>SR</th><th>Vehicle</th><th>Type</th><th>Date</th><th>Mileage</th><th>Shop</th><th>Total</th><th>Driver</th><th>Entered By</th>
  </tr></thead><tbody>';

  $rs = $conn->query("SELECT * FROM {$table} WHERE status='Pending' AND entered_by <> '{$userEsc}' ORDER BY id DESC");
  while($row = $rs->fetch_assoc()){
    $mt = (string)($row['maintenance_type'] ?? '');
    $date = in_array($mt, ['Battery','Tire'], true) ? ($row['purchase_date'] ?? '') : ($row['repair_date'] ?? '');

    $p .= '<tr class="pro-js-view" style="cursor:pointer"
              data-id="'.(int)$row['id'].'"
              data-type="maintenance"
              data-sr='.js($row['sr_number']).'>'.
            '<td>'.e($row['sr_number']).'</td>'.
            '<td>'.e($row['vehicle_number']).'</td>'.
            '<td>'.e($mt).'</td>'.
            '<td>'.e($date).'</td>'.
            '<td>'.e($row['mileage']).'</td>'.
            '<td>'.e($row['shop_name']).'</td>'.
            '<td>'.n2($row['price']).'</td>'.
            '<td>'.e($row['driver_name']).'</td>'.
            '<td>'.e($row['entered_by']).'</td>'.
          '</tr>';
  }
  $p .= '</tbody></table></div>';

  $r .= '<tr>
    <th>SR</th><th>Vehicle</th><th>Type</th><th>Date</th><th>Mileage</th><th>Shop</th><th>Total</th><th>Driver</th><th>Rejected By</th><th>Rejected At</th><th>Reason</th>
  </tr></thead><tbody>';

  $rs = $conn->query("SELECT * FROM {$table} WHERE status='Rejected' ORDER BY id DESC");
  while($row = $rs->fetch_assoc()){
    $mt = (string)($row['maintenance_type'] ?? '');
    $date = in_array($mt, ['Battery','Tire'], true) ? ($row['purchase_date'] ?? '') : ($row['repair_date'] ?? '');

    $r .= '<tr>'.
            '<td>'.e($row['sr_number']).'</td>'.
            '<td>'.e($row['vehicle_number']).'</td>'.
            '<td>'.e($mt).'</td>'.
            '<td>'.e($date).'</td>'.
            '<td>'.e($row['mileage']).'</td>'.
            '<td>'.e($row['shop_name']).'</td>'.
            '<td>'.n2($row['price']).'</td>'.
            '<td>'.e($row['driver_name']).'</td>'.
            '<td>'.e($row['rejected_by']).'</td>'.
            '<td>'.e($row['rejected_at']).'</td>'.
            '<td>'.e($row['rejection_reason']).'</td>'.
          '</tr>';
  }
  $r .= '</tbody></table></div>';

}
elseif($type === 'service'){

  $p .= '<tr>
    <th>SR</th><th>Vehicle</th><th>Date</th><th>Shop/Garage</th><th>Prev Meter</th><th>Next Meter</th><th>Amount</th><th>Driver</th><th>Entered By</th>
  </tr></thead><tbody>';

  $rs = $conn->query("SELECT * FROM {$table} WHERE status='Pending' AND entered_by <> '{$userEsc}' ORDER BY id DESC");
  while($row = $rs->fetch_assoc()){
    $p .= '<tr class="pro-js-view" style="cursor:pointer"
              data-id="'.(int)$row['id'].'"
              data-type="service"
              data-sr='.js($row['sr_number']).'>'.
            '<td>'.e($row['sr_number']).'</td>'.
            '<td>'.e($row['vehicle_number']).'</td>'.
            '<td>'.e($row['service_date']).'</td>'.
            '<td>'.e($row['shop_name']).'</td>'.
            '<td>'.e($row['meter_reading']).'</td>'.
            '<td>'.e($row['next_service_meter']).'</td>'.
            '<td>'.n2($row['amount']).'</td>'.
            '<td>'.e($row['driver_name']).'</td>'.
            '<td>'.e($row['entered_by']).'</td>'.
          '</tr>';
  }
  $p .= '</tbody></table></div>';

  $r .= '<tr>
    <th>SR</th><th>Vehicle</th><th>Date</th><th>Shop/Garage</th><th>Prev Meter</th><th>Next Meter</th><th>Amount</th><th>Driver</th><th>Rejected By</th><th>Rejected At</th><th>Reason</th>
  </tr></thead><tbody>';

  $rs = $conn->query("SELECT * FROM {$table} WHERE status='Rejected' ORDER BY id DESC");
  while($row = $rs->fetch_assoc()){
    $r .= '<tr>'.
            '<td>'.e($row['sr_number']).'</td>'.
            '<td>'.e($row['vehicle_number']).'</td>'.
            '<td>'.e($row['service_date']).'</td>'.
            '<td>'.e($row['shop_name']).'</td>'.
            '<td>'.e($row['meter_reading']).'</td>'.
            '<td>'.e($row['next_service_meter']).'</td>'.
            '<td>'.n2($row['amount']).'</td>'.
            '<td>'.e($row['driver_name']).'</td>'.
            '<td>'.e($row['rejected_by']).'</td>'.
            '<td>'.e($row['rejected_at']).'</td>'.
            '<td>'.e($row['rejection_reason']).'</td>'.
          '</tr>';
  }
  $r .= '</tbody></table></div>';

}
else { // license

  $p .= '<tr><th>SR</th><th>Vehicle</th><th>Emission Date</th><th>Emission Amt</th><th>License Date</th><th>License Amt</th><th>Insurance Amt</th><th>Handled By</th><th>Entered By</th></tr></thead><tbody>';
  $rs = $conn->query("SELECT * FROM {$table} WHERE status='Pending' AND entered_by <> '{$userEsc}' ORDER BY id DESC");
  while($row = $rs->fetch_assoc()){
    $p .= '<tr class="pro-js-view" style="cursor:pointer" data-id="'.(int)$row['id'].'" data-type="license" data-sr='.js($row['sr_number']).'>'.
      '<td>'.e($row['sr_number']).'</td>'.
      '<td>'.e($row['vehicle_number']).'</td>'.
      '<td>'.e($row['emission_test_date']).'</td>'.
      '<td>'.n2($row['emission_test_amount']).'</td>'.
      '<td>'.e($row['revenue_license_date']).'</td>'.
      '<td>'.n2($row['revenue_license_amount']).'</td>'.
      '<td>'.n2($row['insurance_amount']).'</td>'.
      '<td>'.e($row['person_handled']).'</td>'.
      '<td>'.e($row['entered_by']).'</td>'.
    '</tr>';
  }
  $p .= '</tbody></table></div>';

  $r .= '<tr><th>SR</th><th>Vehicle</th><th>Emission Date</th><th>Emission Amt</th><th>License Date</th><th>License Amt</th><th>Insurance Amt</th><th>Handled By</th><th>Rejected By</th><th>Rejected At</th><th>Reason</th></tr></thead><tbody>';
  $rs = $conn->query("SELECT * FROM {$table} WHERE status='Rejected' ORDER BY id DESC");
  while($row = $rs->fetch_assoc()){
    $r .= '<tr>'.
      '<td>'.e($row['sr_number']).'</td>'.
      '<td>'.e($row['vehicle_number']).'</td>'.
      '<td>'.e($row['emission_test_date']).'</td>'.
      '<td>'.n2($row['emission_test_amount']).'</td>'.
      '<td>'.e($row['revenue_license_date']).'</td>'.
      '<td>'.n2($row['revenue_license_amount']).'</td>'.
      '<td>'.n2($row['insurance_amount']).'</td>'.
      '<td>'.e($row['person_handled']).'</td>'.
      '<td>'.e($row['rejected_by']).'</td>'.
      '<td>'.e($row['rejected_at']).'</td>'.
      '<td>'.e($row['rejection_reason']).'</td>'.
    '</tr>';
  }
  $r .= '</tbody></table></div>';

}

send_json(['pending'=>$p,'rejected'=>$r]);
