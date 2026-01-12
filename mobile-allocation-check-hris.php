<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

function out($ok, $arr=[]){ echo json_encode(array_merge(['ok'=>$ok], $arr)); exit; }
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$hris = trim($_POST['hris'] ?? '');
if ($hris === '') out(false, ['error' => 'HRIS is required']);

// numeric but not 6 digits => hard error
if (ctype_digit($hris) && !preg_match('/^\d{6}$/', $hris)) {
  out(false, ['error' => 'Numeric HRIS must be exactly 6 digits (example: 006428). Text HRIS is allowed.']);
}

// alphanumeric => allowed, no lookup
if (!preg_match('/^\d{6}$/', $hris)) {
  out(true, [
    'locked' => false,
    'html' => "<div class='alert alert-info'>ℹ️ HRIS is alphanumeric. Employee lookup skipped. You can save.</div>"
  ]);
}

/**
 * ✅ ONLY ACTIVE employees (your required query)
 */
$stmt = $conn->prepare("
  SELECT name_of_employee, status
  FROM tbl_admin_employee_details
  WHERE TRIM(hris)=? AND status='Active'
  LIMIT 1
");
$stmt->bind_param("s", $hris);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
  out(true, [
    'locked' => true,
    'html' => "<div class='alert alert-danger'>
      ⛔ HRIS <b>".esc($hris)."</b> is not an <b>Active</b> employee (or not found). Allocation blocked.
    </div>"
  ]);
}

$ownerName = $emp['name_of_employee'] ?? '';

/**
 * Active open mobiles for this HRIS (from allocations)
 */
$q = $conn->prepare("
  SELECT a.mobile_number, a.effective_from
  FROM tbl_admin_mobile_allocations a
  WHERE TRIM(a.hris_no)=?
    AND a.status='Active'
    AND a.effective_to IS NULL
  ORDER BY a.effective_from DESC, a.id DESC
");
$q->bind_param("s", $hris);
$q->execute();
$rs = $q->get_result();

$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$q->close();

$empHtml = "<div class='alert alert-success mb-2'>
  ✅ Active employee found: <b>".esc($ownerName)."</b>
</div>";

$connectionsHtml = "";

if (count($rows) > 0) {

  $connectionsHtml .= "<div class='alert alert-info mb-2'>
    ℹ️ This HRIS has <b>".count($rows)."</b> active connection(s).
  </div>";

  $connectionsHtml .= "<div class='table-responsive'>
    <table class='table table-sm table-bordered align-middle mb-0'>
      <thead class='table-light'>
        <tr>
          <th>Mobile</th>
          <th>Effective From</th>
          <th>Voice/Data</th>
          <th>Connection Status</th>
          <th>Disconnection Date</th>
        </tr>
      </thead>
      <tbody>";

  foreach ($rows as $r) {
    $mobile = $r['mobile_number'];

    // latest issue row for this mobile (NO created_at column in your table)
    $s2 = $conn->prepare("
      SELECT voice_data, connection_status, disconnection_date
      FROM tbl_admin_mobile_issues
      WHERE mobile_no = ?
      ORDER BY id DESC
      LIMIT 1
    ");
    $s2->bind_param("s", $mobile);
    $s2->execute();
    $issue = $s2->get_result()->fetch_assoc();
    $s2->close();

    $voiceData = $issue['voice_data'] ?? '-';
    $connStatus = $issue['connection_status'] ?? '-';
    $disDate = $issue['disconnection_date'] ?? '-';

    // small badges
    $badge = '';
    if (strcasecmp((string)$connStatus, 'Connected') === 0) $badge = "<span class='badge bg-success'>Connected</span>";
    else if (strcasecmp((string)$connStatus, 'Disconnected') === 0) $badge = "<span class='badge bg-danger'>Disconnected</span>";
    else $badge = "<span class='badge bg-secondary'>".esc($connStatus)."</span>";

    $connectionsHtml .= "<tr>
      <td><b>".esc($mobile)."</b></td>
      <td>".esc($r['effective_from'])."</td>
      <td>".esc($voiceData)."</td>
      <td>{$badge}</td>
      <td>".esc($disDate)."</td>
    </tr>";
  }

  $connectionsHtml .= "</tbody></table></div>";

} else {
  $connectionsHtml .= "<div class='alert alert-secondary'>No active connections found for this HRIS.</div>";
}

out(true, [
  'locked' => false,
  'owner_name' => $ownerName,
  'html' => $empHtml . $connectionsHtml
]);
