<?php
// ajax-mobile-allocation-preview.php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function bad($msg){ echo json_encode(["ok"=>false, "error"=>$msg]); exit; }
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$raw = file_get_contents("php://input");
$in = json_decode($raw, true);
if (!is_array($in)) bad("Invalid JSON");

$type = trim($in['request_type'] ?? '');
$mobile = trim($in['mobile_number'] ?? '');
$eff_from = trim($in['effective_from'] ?? '');
$to_hris = trim($in['to_hris_no'] ?? '');
$owner = trim($in['owner_name'] ?? '');
$eff_to = trim($in['effective_to'] ?? '');

if ($type === '' || !in_array($type, ['NEW','TRANSFER','CLOSE'], true)) bad("Select a request type");
if ($mobile === '') bad("Mobile number is required");
if ($eff_from === '') bad("Effective from is required");

if (($type === 'NEW' || $type === 'TRANSFER') && $to_hris === '') bad("To HRIS is required");
if ($type === 'CLOSE' && $eff_to === '') bad("Effective to is required");

if ($type === 'CLOSE' && $eff_to < $eff_from) bad("effective_to cannot be earlier than effective_from");

/* Pull current allocations for that mobile */
$stmt = $conn->prepare("
  SELECT id, mobile_number, hris_no, owner_name, effective_from, effective_to, status
  FROM tbl_admin_mobile_allocations
  WHERE mobile_number = ?
  ORDER BY effective_from DESC, id DESC
  LIMIT 10
");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

$existing_html = "";
if (count($rows) > 0) {
  $existing_html .= "<div class='alert alert-warning py-2'><b>Existing allocations for {$mobile}</b></div>";
  $existing_html .= "<div class='table-responsive'><table class='table table-sm table-bordered'>";
  $existing_html .= "<thead><tr><th>ID</th><th>HRIS</th><th>Owner</th><th>From</th><th>To</th><th>Status</th></tr></thead><tbody>";
  foreach($rows as $r){
    $existing_html .= "<tr>";
    $existing_html .= "<td>".(int)$r['id']."</td>";
    $existing_html .= "<td>".esc($r['hris_no'])."</td>";
    $existing_html .= "<td>".esc($r['owner_name'])."</td>";
    $existing_html .= "<td>".esc($r['effective_from'])."</td>";
    $existing_html .= "<td>".esc($r['effective_to'])."</td>";
    $existing_html .= "<td>".esc($r['status'])."</td>";
    $existing_html .= "</tr>";
  }
  $existing_html .= "</tbody></table></div>";
}

/* Overlap check (same logic as trigger, but preview-friendly) */
$check = $conn->prepare("
  SELECT 1
  FROM tbl_admin_mobile_allocations x
  WHERE x.mobile_number = ?
    AND COALESCE(x.effective_to, '9999-12-31') >= ?
    AND COALESCE(?, '9999-12-31') >= x.effective_from
  LIMIT 1
");
$check_to = ($type === 'CLOSE') ? $eff_to : null; // NEW/TRANSFER is open-ended
$check->bind_param("sss", $mobile, $eff_from, $check_to);
$check->execute();
$has_overlap = $check->get_result()->num_rows > 0;
$check->close();

if ($type !== 'CLOSE' && $has_overlap) {
  bad("This will overlap an existing allocation. Transfer/close the current one first.");
}

$preview_html = "<div class='card p-3 border'>
  <h6 class='text-primary mb-2'>Preview</h6>
  <ul class='mb-0'>
    <li><b>Type:</b> ".esc($type)."</li>
    <li><b>Mobile:</b> ".esc($mobile)."</li>
    <li><b>Effective From:</b> ".esc($eff_from)."</li>";

if ($type === 'CLOSE') {
  $preview_html .= "<li><b>Effective To:</b> ".esc($eff_to)."</li>";
} else {
  $preview_html .= "<li><b>To HRIS:</b> ".esc($to_hris)."</li>";
  $preview_html .= "<li><b>Owner Name:</b> ".esc($owner)."</li>";
  $preview_html .= "<li><b>Result:</b> A pending request will be created. (Approval step can be added next.)</li>";
}
$preview_html .= "</ul></div>";

echo json_encode([
  "ok" => true,
  "existing_html" => $existing_html,
  "preview_html" => $preview_html
]);
