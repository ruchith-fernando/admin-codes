<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$month_year = trim($_POST['month_year'] ?? '');
if($month_year === ''){
  exit("<div class='alert alert-warning'>No month selected.</div>");
}

$current_hris = trim((string)($_SESSION['hris'] ?? ''));
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$q = $conn->prepare("
  SELECT h.*, f.floor_name
  FROM tbl_admin_tea_service_hdr h
  INNER JOIN tbl_admin_floors f ON f.id = h.floor_id
  WHERE h.month_year=? AND h.approval_status='pending'
  ORDER BY h.entered_at DESC
");
$q->bind_param("s", $month_year);
$q->execute();
$res = $q->get_result();

if(!$res || $res->num_rows === 0){
  echo "<div class='alert alert-info'>No pending approvals for <b>".esc($month_year)."</b>.</div>";
  exit;
}

echo "<table class='table table-bordered table-hover align-middle'>
<thead class='table-light'>
<tr>
  <th>Month</th>
  <th>Floor</th>
  <th class='text-end'>Grand Total</th>
  <th>Entered By</th>
  <th>Entered HRIS</th>
  <th>Entered At</th>
  <th>Actions</th>
</tr>
</thead><tbody>";

while($r = $res->fetch_assoc()){
  $entered_hris = trim((string)($r['entered_hris'] ?? ''));
  $is_own = ($entered_hris !== '' && $entered_hris === $current_hris);

  echo "<tr>
    <td>".esc($r['month_year'])."</td>
    <td>".esc($r['floor_name'])."</td>
    <td class='text-end'>".number_format((float)$r['grand_total'],2)."</td>
    <td>".esc($r['entered_name'])."</td>
    <td>".esc($r['entered_hris'])."</td>
    <td>".esc($r['entered_at'])."</td>
    <td>";

  if($is_own){
    echo "<span class='text-muted small fst-italic'>Own entry</span>";
  } else {
    echo "
      <button class='btn btn-success btn-sm tea-approve-btn' data-id='".(int)$r['id']."'>Approve</button>
      <button class='btn btn-danger btn-sm tea-reject-btn ms-1' data-id='".(int)$r['id']."'>Reject</button>
    ";
  }

  echo "</td></tr>";
}
echo "</tbody></table>";
