<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$res = mysqli_query($conn, "
  SELECT mv.move_date, pm.serial, pm.model,
         mv.from_branch_code, mv.to_branch_code, mv.reason, mv.moved_by, mv.moved_at
  FROM tbl_admin_photocopy_machine_moves mv
  INNER JOIN tbl_admin_photocopy_machines pm ON pm.machine_id = mv.machine_id
  ORDER BY mv.moved_at DESC
  LIMIT 100
");

$table = "<table class='table table-bordered'>
<thead class='table-light'>
<tr>
  <th>Date</th><th>Serial</th><th>Model</th><th>From</th><th>To</th><th>Reason</th><th>By</th><th>At</th>
</tr>
</thead><tbody>";

if ($res && mysqli_num_rows($res)>0) {
  while ($r=mysqli_fetch_assoc($res)) {
    $table .= "<tr>
      <td>".htmlspecialchars($r['move_date'])."</td>
      <td>".htmlspecialchars($r['serial'])."</td>
      <td>".htmlspecialchars($r['model'])."</td>
      <td>".htmlspecialchars($r['from_branch_code'])."</td>
      <td>".htmlspecialchars($r['to_branch_code'])."</td>
      <td>".htmlspecialchars($r['reason'])."</td>
      <td>".htmlspecialchars($r['moved_by'])."</td>
      <td>".htmlspecialchars($r['moved_at'])."</td>
    </tr>";
  }
} else {
  $table .= "<tr><td colspan='8' class='text-muted'>No moves found.</td></tr>";
}
$table .= "</tbody></table>";

echo json_encode(['table'=>$table]);
exit;
