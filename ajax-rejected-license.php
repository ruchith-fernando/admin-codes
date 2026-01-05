<?php
require_once 'connections/connection.php';

$query = "SELECT t1.id, t1.vehicle_number, t2.vehicle_type, t1.revenue_license_date, 
t2.assigned_user, t1.person_handled, t1.rejected_by, t1.rejection_reason
FROM tbl_admin_vehicle_licensing_insurance t1 
LEFT JOIN tbl_admin_vehicle t2 ON t1.vehicle_number = t2.vehicle_number
WHERE t1.status = 'rejected' ORDER BY t1.rejected_at DESC";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    echo "<div class='alert alert-warning'>No rejected license/insurance records found.</div>";
} else {
    echo "<div class='table-responsive'>
            <table class='table table-bordered table-sm'>
                <thead class='table-light'>
                    <tr>
                        <th>Vehicle No</th>
                        <th>Type</th>
                        <th>Revenue License Date</th>
                        <th>Assigned User</th>
                        <th>Handled By</th>
                        <th>Rejected By</th>
                        <th>Rejection Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>
                <td>{$row['vehicle_number']}</td>
                <td>{$row['vehicle_type']}</td>
                <td>{$row['revenue_license_date']}</td>
                <td>{$row['assigned_user']}</td>
                <td>{$row['person_handled']}</td>
                <td>{$row['rejected_by']}</td>
                <td>{$row['rejection_reason']}</td>
                <td><button class='btn btn-danger btn-sm' onclick='deleteRejectedLicense({$row['id']})'>Delete</button></td>
              </tr>";
    }
    echo "</tbody></table></div>";
}
?>
<script>
function deleteRejectedLicense(id) {
  if (!confirm("Are you sure you want to remove this rejection and mark as Pending?")) return;

  $.post('delete-rejected-license.php', { id: id }, function(response) {
    if (response === 'success') {
      $('#licenseRejected').load('ajax-rejected-license.php');
    } else {
      alert('Error: ' + response);
    }
  }).fail(function(xhr) {
    alert('Server error: ' + xhr.statusText);
  });
}

</script>
