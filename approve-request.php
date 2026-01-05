<?php

session_start();

include 'connections/connection.php';



// --- Access control ---

$allowed_levels = ['agm_sales', 'dgm_sales', 'head_branch_ops', 'agm_operations', 'dgm_operations', 'super-admin'];

// if (!isset($_SESSION['username']) || !in_array($_SESSION['user_level'], $allowed_levels)) {

//     echo "<div class='alert alert-danger'>Access denied.</div>";

//     exit;

// }



if (!isset($_SESSION['username']) || !in_array($_SESSION['user_level'], $allowed_levels)) {

    header("Location: access-denied.php");

    exit;

}



$user_category = $_SESSION['category'];



// --- Form submission to approve/reject ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request_id'], $_POST['action'])) {

    $request_id = intval($_POST['action_request_id']);

    $action = $_POST['action'];



    if ($action === 'approve') {

        $approver = mysqli_real_escape_string($conn, $_SESSION['username']);

        $update_sql = "UPDATE tbl_admin_sim_request SET approved_by = '$approver' WHERE id = $request_id";

        $action_msg = "approved";

    } elseif ($action === 'reject') {

        $approver = mysqli_real_escape_string($conn, $_SESSION['username']);

        $update_sql = "UPDATE tbl_admin_sim_request SET approved_by = 'REJECTED by $approver' WHERE id = $request_id";

        $action_msg = "rejected";

    }



    if (mysqli_query($conn, $update_sql)) {

        $message = "<div class='alert alert-success'>Request ID $request_id $action_msg successfully.</div>";

    } else {

        $message = "<div class='alert alert-danger'>Failed to $action request ID $request_id.</div>";

    }

}



// --- Fetch pending approval requests ---

$query = "SELECT id, hris, name, request_type, request_division, designation, branch_division, 

data_package, other_amount, voice_data, voice_package, recommended_by 

FROM tbl_admin_sim_request 

WHERE 

(recommended_by IS NOT NULL 

AND recommended_by <> '' 

AND LOWER(recommended_by) NOT LIKE '%rejected%') 

AND (approved_by IS NULL OR approved_by = '') 

AND request_division = '$user_category'";

$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Approve SIM/Mobile/Transfer Requests</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="styles.css">

    <style>

        tr.clickable-row { cursor: pointer; }

    </style>

</head>

<body class="bg-light">



<button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>

<div class="sidebar" id="sidebar">

    <?php include 'side-menu.php'; ?>

</div>



<div class="content font-size" id="contentArea">

    <div class="container-fluid">

        <div class="card shadow bg-white rounded p-4">

            <h5 class="mb-4 text-primary">Approve SIM/Mobile/Transfer Requests</h5>



            <?php if (isset($message)) echo $message; ?>



            <?php if (mysqli_num_rows($result) > 0) { ?>

                <div style="overflow-x:auto;">

                    <table class="table table-bordered table-striped table-hover w-100 mt-3">

                        <thead class="table-light">

                            <tr>

                                <th style="width: 50px;">ID</th>

                                <th style="width: 80px;">HRIS</th>

                                <th style="min-width: 350px;">Name</th>

                                <th style="min-width: 140px;">Request Type</th>

                                <th style="min-width: 140px;">Request Division</th>

                                <th style="min-width: 250px;">Designation</th>

                                <th style="min-width: 140px;">Branch/Division</th>

                                <th style="min-width: 100px;">Voice/Data</th>

                                <th style="min-width: 250px;">Voice Package</th>

                                <th style="min-width: 130px;">Data Package</th>

                                <th style="min-width: 150px;">Special Data Request</th>

                                <th style="min-width: 150px;">Recommended By</th>

                                <th style="min-width: 200px;">Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                            <tr class="clickable-row" 

                                data-hris="<?php echo htmlspecialchars($row['hris']); ?>" 

                                data-requesttype="<?php echo htmlspecialchars($row['request_type']); ?>">

                                <td><?php echo $row['id']; ?></td>

                                <td><?php echo htmlspecialchars($row['hris']); ?></td>

                                <td><?php echo htmlspecialchars($row['name']); ?></td>

                                <td><?php echo htmlspecialchars($row['request_type']); ?></td>

                                <td><?php echo htmlspecialchars($row['request_division']); ?></td>

                                <td><?php echo htmlspecialchars($row['designation']); ?></td>

                                <td><?php echo htmlspecialchars($row['branch_division']); ?></td>

                                <td><?php echo htmlspecialchars($row['voice_data']); ?></td>

                                <td><?php echo htmlspecialchars($row['voice_package']); ?></td>

                                <td><?php echo htmlspecialchars($row['data_package']); ?></td>

                                <td><?php echo htmlspecialchars($row['other_amount']); ?></td>

                                <td><?php echo htmlspecialchars($row['recommended_by']); ?></td>

                                <td>

                                    <form method="post" class="d-flex gap-1" onclick="event.stopPropagation();">

                                        <input type="hidden" name="action_request_id" value="<?php echo $row['id']; ?>">

                                        <button type="button" class="btn btn-success btn-sm" 

                                                onclick="confirmAction(<?php echo $row['id']; ?>, 'approve')">Approve</button>

                                        <button type="button" class="btn btn-danger btn-sm" 

                                                onclick="confirmAction(<?php echo $row['id']; ?>, 'reject')">Reject</button>

                                    </form>

                                </td>

                            </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                </div>

            <?php } else { ?>

                <div class="alert alert-info">No requests pending approval for your division (<?php echo htmlspecialchars($user_category); ?>).</div>

            <?php } ?>

        </div>

    </div>

</div>



<!-- Modals -->

<div class="modal fade" id="mobileDetailsModal" tabindex="-1">

  <div class="modal-dialog">

    <div class="modal-content">

      <div class="modal-header"><h5 class="modal-title">Mobile Numbers Issued</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>

      <div class="modal-body" id="mobileDetailsBody">Loading...</div>

      <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button></div>

    </div>

  </div>

</div>



<div class="modal fade" id="phoneDetailsModal" tabindex="-1">

  <div class="modal-dialog">

    <div class="modal-content">

      <div class="modal-header"><h5 class="modal-title">Issued Phone Details</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>

      <div class="modal-body" id="phoneDetailsBody">Loading...</div>

      <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button></div>

    </div>

  </div>

</div>



<div class="modal fade" id="noRecordsModal" tabindex="-1">

  <div class="modal-dialog">

    <div class="modal-content">

      <div class="modal-header"><h5 class="modal-title">No Existing Connections</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>

      <div class="modal-body"><p>No existing mobile numbers or phones were found for this employee.</p></div>

      <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button></div>

    </div>

  </div>

</div>



<!-- Confirm Action Modal -->

<div class="modal fade" id="confirmActionModal" tabindex="-1">

  <div class="modal-dialog">

    <div class="modal-content">

      <div class="modal-header">

        <h5 class="modal-title">Please Confirm</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

      </div>

      <div class="modal-body" id="confirmActionBody">Are you sure you want to proceed?</div>

      <div class="modal-footer">

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

        <button type="button" class="btn btn-primary" id="confirmActionYes">Yes</button>

      </div>

    </div>

  </div>

</div>

<!-- Unified Employee & Connection Details Modal -->

<div class="modal fade" id="employeeDetailsModal" tabindex="-1">

   <div class="modal-dialog" style="max-width: 500px;">

    <div class="modal-content">

      <div class="modal-header">

        <h5 class="modal-title">Employee & Connection Details</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

      </div>

      <div class="modal-body" id="employeeDetailsBody">Loading...</div>

      <div class="modal-footer">

        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>

      </div>

    </div>

  </div>

</div>



<!-- Hidden form -->

<form id="confirmActionForm" method="post">

    <input type="hidden" name="action_request_id" id="confirmRequestId">

    <input type="hidden" name="action" id="confirmAction">

</form>



<script>

$(document).ready(function(){

    $(".clickable-row").click(function(){

        let hris = $(this).data("hris");

        let requestType = $(this).data("requesttype").toLowerCase();



        fetch('get-employee-details.php', {

            method: 'POST',

            headers: { 'Content-Type': 'application/json' },

            body: JSON.stringify({ hris: hris })

        })

        .then(response => response.json())

        .then(data => {

            let html = '';

            if (data.status === 'success') {

                // ✅ Basic info

                html += `

                    <div class="mb-3">

                        <strong>Name:</strong> ${data.full_name || 'N/A'}<br>

                        <strong>HRIS:</strong> ${hris}<br>

                        <strong>Division:</strong> ${data.location || 'N/A'}<br>

                        <strong>Branch:</strong> ${data.company_hierarchy || 'N/A'}

                    </div><hr>

                `;



                // ✅ Connection info

                if (requestType === 'mobile' && data.phones?.length > 0) {

                    html += '<strong>Issued Phones:</strong><ul class="list-group">';

                    data.phones.forEach(phone => {

                        html += `<li class="list-group-item">Issue Date: ${phone.issue_date} | IMEI: ${phone.imei_number}</li>`;

                    });

                    html += '</ul>';

                } else if (requestType === 'sim' && data.mobiles?.length > 0) {

                    html += '<strong>Issued SIMs:</strong><ul class="list-group">';

                    data.mobiles.forEach(mobile => {

                        html += `<li class="list-group-item">Mobile: ${mobile.mobile_no} | Voice/Data: ${mobile.voice_data}</li>`;

                    });

                    html += '</ul>';

                } else {

                    html += '<div class="alert alert-warning">No existing mobile numbers or phones were found for this employee.</div>';

                }



            } else {

                html += '<div class="alert alert-danger">Employee not found or an error occurred.</div>';

            }



            $('#employeeDetailsBody').html(html);

            new bootstrap.Modal(document.getElementById('employeeDetailsModal')).show();

        })

        .catch(error => {

            console.error('Error:', error);

            $('#employeeDetailsBody').html('<div class="alert alert-danger">Failed to fetch details.</div>');

            new bootstrap.Modal(document.getElementById('employeeDetailsModal')).show();

        });

    });

});





function toggleMenu() {

    document.getElementById("sidebar").classList.toggle("active");

}



function confirmAction(requestId, actionType) {

    $('#confirmRequestId').val(requestId);

    $('#confirmAction').val(actionType);

    $('#confirmActionBody').text(`Are you sure you want to ${actionType} Request ID ${requestId}?`);

    const confirmModal = new bootstrap.Modal(document.getElementById('confirmActionModal'));

    confirmModal.show();

    $('#confirmActionYes').off('click').on('click', function() {

        document.getElementById('confirmActionForm').submit();

    });

}

</script>

</body>

</html>

