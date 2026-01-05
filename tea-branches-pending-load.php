<?php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$month = trim($_POST['month'] ?? '');
if(!$month){
  exit("<div class='alert alert-warning'>No month selected.</div>");
}

$current_hris = trim($_SESSION['hris'] ?? '');
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$q = mysqli_query($conn, "
  SELECT *
  FROM tbl_admin_actual_tea_branches
  WHERE month_applicable = '" . mysqli_real_escape_string($conn,$month) . "'
    AND (approval_status='pending' OR approval_status IS NULL)
  ORDER BY entered_at DESC
");

if(!$q || mysqli_num_rows($q)==0){
  echo "<div class='alert alert-info'>No pending approvals for <b>".esc($month)."</b>.</div>";
  exit;
}

echo "<table class='table table-bordered table-hover align-middle'>
<thead class='table-light'>
<tr>
  <th>Branch Code</th>
  <th>Branch</th>
  <th class='text-end'>Amount</th>
  <th>Provision</th>
  <th>Provision Reason</th>
  <th>Entered By</th>
  <th>Entered HRIS</th>
  <th>Entered At</th>
  <th>Actions</th>
</tr>
</thead><tbody>";

while($r=mysqli_fetch_assoc($q)){
  $entered_hris = trim((string)($r['entered_hris'] ?? ''));
  $is_own = ($entered_hris !== '' && $current_hris !== '' && $entered_hris === $current_hris);

  echo "<tr>
    <td>".esc($r['branch_code'])."</td>
    <td>".esc($r['branch'])."</td>
    <td class='text-end'>".number_format((float)str_replace(',', '', (string)$r['total_amount']),2)."</td>
    <td>".(($r['is_provision']==='yes')?'Yes':'No')."</td>
    <td>".esc($r['provision_reason'])."</td>
    <td>".esc($r['entered_name'])."</td>
    <td>".esc($r['entered_hris'])."</td>
    <td>".esc($r['entered_at'])."</td>
    <td>";

  if($is_own){
    echo "<span class='text-muted small fst-italic'>Own entry</span>";
  }else{
    echo "
      <button class='btn btn-success btn-sm tea-approve-btn'
        data-id='".esc($r['id'])."'>Approve</button>
      <button class='btn btn-danger btn-sm tea-reject-btn ms-1'
        data-id='".esc($r['id'])."'
        data-branch='".esc($r['branch'])."'
        data-month='".esc($r['month_applicable'])."'>Reject</button>
    ";
  }

  echo "</td></tr>";
}

echo "</tbody></table>";
