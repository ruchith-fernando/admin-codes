<?php
// mobile-allocation-check-hris.php
require_once 'connections/connection.php';
require_once 'includes/helpers.php';

$hris = normalize_hris($_POST['hris'] ?? '');
if ($hris === '') json_response(['ok' => false, 'error' => 'HRIS is required.']);

$isNumeric6 = preg_match('/^\d{6}$/', $hris) === 1;

$emp = null;
if ($isNumeric6) {
  $st = $conn->prepare("
    SELECT name_of_employee, nic_no, company_hierarchy, status
    FROM tbl_admin_employee_details
    WHERE TRIM(hris)=?
    LIMIT 1
  ");
  $st->bind_param("s", $hris);
  $st->execute();
  $emp = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$emp) json_response(['ok' => false, 'error' => 'HRIS not found in employee master.']);
  if (strcasecmp((string)$emp['status'], 'Active') !== 0) {
    json_response(['ok' => false, 'locked' => true, 'error' => 'HRIS is not Active. Only Active employees allowed.']);
  }
}

$q = $conn->prepare("
  SELECT mobile_number, owner_name, effective_from
  FROM tbl_admin_mobile_allocations
  WHERE TRIM(hris_no)=?
    AND status='Active'
    AND effective_to IS NULL
  ORDER BY effective_from DESC, id DESC
");
$q->bind_param("s", $hris);
$q->execute();
$rs = $q->get_result();

$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$q->close();

$html = "<div class='alert alert-success mb-2'>✅ HRIS <b>".esc($hris)."</b> is OK.</div>";
$html .= "<div class='card p-3 border'><b>Active connections on this HRIS:</b> ".count($rows);

if (!count($rows)) {
  $html .= "<div class='text-muted small mt-2'>No active connections found.</div>";
} else {
  $html .= "<div class='table-responsive mt-2'>
    <table class='table table-sm table-bordered align-middle mb-0'>
      <thead class='table-light'><tr><th>Mobile</th><th>Owner</th><th>Effective From</th></tr></thead><tbody>";
  foreach ($rows as $r) {
    $html .= "<tr>
      <td><b>".esc($r['mobile_number'])."</b></td>
      <td>".esc($r['owner_name'])."</td>
      <td>".esc($r['effective_from'])."</td>
    </tr>";
  }
  $html .= "</tbody></table></div>";
}
$html .= "</div>";

json_response([
  'ok' => true,
  'locked' => false,
  'html' => $html,
  'emp' => $emp ? [
    'name' => $emp['name_of_employee'] ?? '',
    'nic' => $emp['nic_no'] ?? '',
    'hierarchy' => $emp['company_hierarchy'] ?? ''
  ] : null
]);
