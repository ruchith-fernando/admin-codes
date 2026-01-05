<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$res = mysqli_query($conn, "
  SELECT id, month_applicable, branch_code, branch, model, serial, copy_count, total_amount, entered_name, entered_at
  FROM tbl_admin_actual_photocopy
  WHERE approval_status='pending'
  ORDER BY entered_at ASC
");

$table = "<table class='table table-bordered'>
  <thead class='table-light'>
    <tr>
      <th>Month</th><th>Branch</th><th>Machine</th><th class='text-end'>Copies</th>
      <th class='text-end'>Line Total</th><th>Entered By</th><th>Entered At</th><th>Action</th>
    </tr>
  </thead><tbody>";

if ($res && mysqli_num_rows($res)>0) {
  while ($r=mysqli_fetch_assoc($res)) {
    $id=(int)$r['id'];
    $table .= "<tr>
      <td>".htmlspecialchars($r['month_applicable'])."</td>
      <td>".htmlspecialchars(($r['branch']??'')." (".$r['branch_code'].")")."</td>
      <td>".htmlspecialchars(($r['model']??'')." | ".$r['serial'])."</td>
      <td class='text-end'>".number_format((float)$r['copy_count'])."</td>
      <td class='text-end'>".number_format((float)$r['total_amount'],2)."</td>
      <td>".htmlspecialchars($r['entered_name']??'')."</td>
      <td>".htmlspecialchars($r['entered_at']??'')."</td>
      <td>
        <button class='btn btn-sm btn-success pc-approve-btn' data-id='{$id}'>Approve</button>
        <button class='btn btn-sm btn-danger pc-reject-btn' data-id='{$id}'>Reject</button>
      </td>
    </tr>";
  }
} else {
  $table .= "<tr><td colspan='8' class='text-muted'>No pending items.</td></tr>";
}
$table .= "</tbody></table>";

echo json_encode(['table'=>$table]);
exit;
