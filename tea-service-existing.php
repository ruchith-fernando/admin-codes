<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$month_year = trim($_POST['month_year'] ?? '');
$floor_id   = (int)($_POST['floor_id'] ?? 0);

if($month_year === '' || $floor_id <= 0){
  echo json_encode(['success'=>true,'exists'=>false]);
  exit;
}

$stmt = $conn->prepare("
  SELECT h.*, f.floor_name, f.floor_no
  FROM tbl_admin_tea_service_hdr h
  INNER JOIN tbl_admin_floors f ON f.id = h.floor_id
  WHERE h.month_year=? AND h.floor_id=?
  LIMIT 1
");
$stmt->bind_param("si", $month_year, $floor_id);
$stmt->execute();
$hdr = $stmt->get_result()->fetch_assoc();

if(!$hdr){
  echo json_encode(['success'=>true,'exists'=>false]);
  exit;
}

$hdr_id  = (int)$hdr['id'];
$floorNo = (int)$hdr['floor_no'];

$status = strtolower(trim($hdr['approval_status'] ?? 'pending'));
$badge  = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');

$html = "<div class='alert alert-secondary'>
  <div class='d-flex justify-content-between align-items-center'>
    <div>
      <b>Existing Record:</b> ".esc($hdr['month_year'])." | ".esc($hdr['floor_name'])."
      <span class='badge bg-{$badge} ms-2'>".strtoupper($status)."</span>
    </div>
  </div>
  <div class='mt-2 small'>
    <b>Entered By:</b> ".esc($hdr['entered_name'])." (".esc($hdr['entered_hris']).") | ".esc($hdr['entered_at'])."
  </div>";

if($status === 'approved'){
  $html .= "<div class='small'><b>Approved By:</b> ".esc($hdr['approved_name'])." (".esc($hdr['approved_hris']).") | ".esc($hdr['approved_at'])."</div>";
}
if($status === 'rejected'){
  $html .= "<div class='small'><b>Rejected By:</b> ".esc($hdr['rejected_name'])." (".esc($hdr['rejected_hris']).") | ".esc($hdr['rejected_at'])."</div>";
  if(trim($hdr['rejection_reason'] ?? '') !== ''){
    $html .= "<div class='small text-danger'><b>Reason:</b> ".esc($hdr['rejection_reason'])."</div>";
  }
}

$html .= "</div>";

/* ✅ OT floor: show only OT */
if($floorNo === 8){
  $html .= "<div class='mt-2'><b>OT:</b> ".number_format((float)$hdr['ot_amount'],2)." |
  <b>Grand Total:</b> ".number_format((float)$hdr['grand_total'],2)."</div>";

  echo json_encode([
    'success'=>true,
    'exists'=>true,
    'status'=>$status,
    'html'=>$html
  ]);
  exit;
}

/* Normal floors: show item lines */
$dt = $conn->prepare("
  SELECT d.*, i.item_name
  FROM tbl_admin_tea_service_dtl d
  INNER JOIN tbl_admin_tea_items i ON i.id = d.item_id
  WHERE d.hdr_id=?
  ORDER BY i.sort_order, i.item_name
");
$dt->bind_param("i", $hdr_id);
$dt->execute();
$res = $dt->get_result();

$html .= "<div class='table-responsive'><table class='table table-bordered align-middle'>
<thead class='table-light'>
<tr><th>Item</th><th>Units</th><th>Rate</th><th>Total</th><th>SSCL</th><th>VAT</th><th>Grand</th></tr>
</thead><tbody>";

$has = false;
while($r = $res->fetch_assoc()){
  $has = true;
  $html .= "<tr>
    <td>".esc($r['item_name'])."</td>
    <td>".esc($r['units'])."</td>
    <td>".number_format((float)$r['unit_price'],2)."</td>
    <td>".number_format((float)$r['total_price'],2)."</td>
    <td>".number_format((float)$r['sscl_amount'],2)."</td>
    <td>".number_format((float)$r['vat_amount'],2)."</td>
    <td>".number_format((float)$r['line_grand_total'],2)."</td>
  </tr>";
}

if(!$has){
  $html .= "<tr><td colspan='7' class='text-center'>No line data.</td></tr>";
}

$html .= "</tbody></table></div>";

$html .= "<div class='mt-2'><b>OT:</b> ".number_format((float)$hdr['ot_amount'],2)." |
<b>Grand Total:</b> ".number_format((float)$hdr['grand_total'],2)."</div>";

echo json_encode([
  'success'=>true,
  'exists'=>true,
  'status'=>$status,
  'html'=>$html
]);
