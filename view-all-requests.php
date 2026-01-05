<?php

session_start();

include 'connections/connection.php';



// --- Access control ---

$allowed_levels = ['asm_rm', 'cluster_leader', 'division_head', 'super-admin', 'admin'];

if (!isset($_SESSION['username']) || !in_array($_SESSION['user_level'], $allowed_levels)) {

    echo "<div class='alert alert-danger'>Access denied. You do not have permission to access this page.</div>";

    exit;

}



$user_level = $_SESSION['user_level'];

$user_category = $_SESSION['category'];



// --- Form submission to approve/reject ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request_id'], $_POST['action'])) {

    $request_id = intval($_POST['action_request_id']);

    $action = $_POST['action'];



    if ($action === 'approve') {

        $approver = mysqli_real_escape_string($conn, $_SESSION['username']);

        $update_sql = "UPDATE tbl_admin_sim_request SET recommended_by = '$approver' WHERE id = $request_id";

        $action_msg = "approved";

    } elseif ($action === 'reject') {

        $update_sql = "UPDATE tbl_admin_sim_request SET recommended_by = 'REJECTED' WHERE id = $request_id";

        $action_msg = "rejected";

    }



    if (mysqli_query($conn, $update_sql)) {

        $message = "<div class='alert alert-success'>Request ID $request_id $action_msg successfully.</div>";

    } else {

        $message = "<div class='alert alert-danger'>Failed to $action request ID $request_id.</div>";

    }

}



// --- Fetch pending requests ---

$query = "SELECT id, hris, name, request_type, request_division, designation, branch_division,

data_package, other_amount, voice_data, voice_package, approved_by, accepted_by 

FROM tbl_admin_sim_request 

WHERE (

    (recommended_by IS NULL OR recommended_by = '') OR

    (approved_by IS NULL OR approved_by = '') OR

    (accepted_by IS NULL OR accepted_by = '') OR

    issue_status = 'Pending'

) 

AND 

    (recommended_by IS NULL OR recommended_by NOT LIKE '%REJECTED%') AND

    (approved_by IS NULL OR approved_by NOT LIKE '%REJECTED%') AND

    (accepted_by IS NULL OR accepted_by NOT LIKE '%REJECTED%')";





if (!in_array($user_level, ['super-admin', 'admin'])) {

    $query .= " AND request_division = '$user_category'";

}



$result = mysqli_query($conn, $query);

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>SIM Request Approval</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="styles.css">

</head>

<style>

    table th, table td {

        white-space: nowrap;

        vertical-align: middle;

        font-size: 0.85rem;

    }

    table td span.badge {

        white-space: normal;

        line-height: 1.2;

    }

</style>

<body class="bg-light">



<button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>

<div class="sidebar" id="sidebar">

    <?php include 'side-menu.php'; ?>

</div>



<div class="content font-size" id="contentArea">

    <div class="container-fluid">

        <div class="card shadow bg-white rounded p-4">

            <!-- <div class="card-header bg-primary text-white"> -->

                <h5 class="mb-4 text-primary">Pending SIM Requests</h5>

            <!-- </div> -->

            <div class="card-body">

                <?php if (isset($message)) echo $message; ?>

                <div class="alert alert-info">Found <?= mysqli_num_rows($result); ?> pending request(s).</div>



                <?php if (mysqli_num_rows($result) > 0) { ?>

                    <div class="table-responsive font-size">

                        <table class="table table-bordered table-striped align-middle text-start">

                            <thead class="table-light">

                                <tr>

                                    <th>ID</th>

                                    <th>HRIS</th>

                                    <th>Name</th>

                                    <th>Request Type</th>

                                    <th>Request Division</th>

                                    <th>Designation</th>

                                    <th>Branch/Division</th>

                                    <th>Voice / Data</th>

                                    <th>Voice Package</th>

                                    <th>Data Package</th>

                                    <th>Special Data Request</th>

                                    <th>Action</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                                <tr>

                                    <td><?= $row['id']; ?></td>

                                    <td><?= htmlspecialchars($row['hris']); ?></td>

                                    <td><?= htmlspecialchars($row['name']); ?></td>

                                    <td><?= htmlspecialchars($row['request_type']); ?></td>

                                    <td><?= htmlspecialchars($row['request_division']); ?></td>

                                    <td><?= htmlspecialchars($row['designation']); ?></td>

                                    <td><?= htmlspecialchars($row['branch_division']); ?></td>

                                    <td><?= htmlspecialchars($row['voice_data']); ?></td>

                                    <td><?= htmlspecialchars($row['voice_package']); ?></td>

                                    <td><?= htmlspecialchars($row['data_package']); ?></td>

                                    <td><?= htmlspecialchars($row['other_amount']); ?></td>

                                    <td>

                                        <?php

                                            if (empty($row['accepted_by'])) {

                                                if (empty($row['approved_by'])) {

                                                    if (empty($row['recommended_by'])) {

                                                        echo '<span class="badge bg-warning text-dark" title="No recommendation yet">To be recommended by Supervisor</span>';

                                                    } else {

                                                        echo '<span class="badge bg-info text-dark" title="Recommended by: ' . htmlspecialchars($row['recommended_by']) . '">To be approved by Head</span>';

                                                    }

                                                } else {

                                                    echo '<span class="badge bg-secondary" title="Approved by: ' . htmlspecialchars($row['approved_by']) . '">At Admin</span>';

                                                }

                                            } else {

                                                echo '<span class="badge bg-success" title="Accepted by: ' . htmlspecialchars($row['accepted_by']) . '">Completed</span>';

                                            }

                                        ?>

                                    </td>

                                </tr>

                                <?php } ?>

                            </tbody>

                        </table>

                    </div>

                <?php } else { ?>

                    <div class="alert alert-info">No requests pending recommendation.</div>

                <?php } ?>

            </div>

        </div>

    </div>

    <?php include 'footer.php'; ?>

</div>



<!-- Modal -->

<div class="modal fade" id="confirmActionModal" tabindex="-1">

  <div class="modal-dialog">

    <div class="modal-content">

      <form method="post" id="confirmActionForm">

        <div class="modal-header">

          <h5 class="modal-title">Please Confirm</h5>

          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

        </div>

        <div class="modal-body" id="confirmActionBody">Are you sure you want to proceed?</div>

        <div class="modal-footer">

          <input type="hidden" name="action_request_id" id="confirmRequestId">

          <input type="hidden" name="action" id="confirmAction">

          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

          <button type="submit" class="btn btn-primary">Yes</button>

        </div>

      </form>

    </div>

  </div>

</div>



<script>

function confirmAction(requestId, actionType) {

    $('#confirmRequestId').val(requestId);

    $('#confirmAction').val(actionType);

    $('#confirmActionBody').text(`Are you sure you want to ${actionType} Request ID ${requestId}?`);

    var confirmModal = new bootstrap.Modal(document.getElementById('confirmActionModal'));

    confirmModal.show();

}



function toggleMenu() {

    document.getElementById("sidebar").classList.toggle("active");

    document.getElementById("contentArea").classList.toggle("expanded");

}

</script>



</body>

</html>

