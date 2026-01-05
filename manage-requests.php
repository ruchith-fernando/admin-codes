<?php

session_start();

include 'connections/connection.php';



// ✅ Capture POST values

$new_mobile_no = mysqli_real_escape_string($conn, $_POST['new_mobile_no'] ?? '');

$new_company_contribution = mysqli_real_escape_string($conn, $_POST['new_company_contribution'] ?? '');

$request_id = intval($_POST['accept_request_id'] ?? 0);

$reject_request_id = intval($_POST['reject_request_id'] ?? 0);



$allowed_levels = ['super-admin','admin'];

// --- Access control ---

if (!isset($_SESSION['username']) || !in_array($_SESSION['user_level'], $allowed_levels)) {

  header("Location: access-denied.php");

    exit;

}



// --- Handle Reject ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reject_request_id > 0) {



    $rejected_by = mysqli_real_escape_string($conn, $_SESSION['username']);



    mysqli_query($conn, "UPDATE tbl_admin_sim_request 

                     SET issue_status = 'Rejected by $rejected_by', 

                         close_status = 'Closed',

                         accepted_by = 'REJECTED by $rejected_by'

                     WHERE id = $reject_request_id");





    $log_entry = "==== Request Rejected ====\n";

    $log_entry .= "Request ID: $reject_request_id\n";

    $log_entry .= "Rejected by: $rejected_by\n";

    $log_entry .= "===========================\n\n";

    file_put_contents('mobile_issue_log.txt', $log_entry, FILE_APPEND);

}



// --- Handle Accept & Issue ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request_id > 0) {



    $accepted_by = mysqli_real_escape_string($conn, $_SESSION['username']);

    mysqli_query($conn, "UPDATE tbl_admin_sim_request SET accepted_by = '$accepted_by' WHERE id = $request_id");



    if (!empty($new_mobile_no)) {



        // 🔎 Get SIM request info

        $hris_result = mysqli_query($conn, "SELECT hris, voice_data FROM tbl_admin_sim_request WHERE id = $request_id");

        $hris_row = mysqli_fetch_assoc($hris_result);



        $hris = $hris_row['hris'];

        $voice_data = $hris_row['voice_data'];



        // 🔎 Get employee info

        $emp_result = mysqli_query($conn, "SELECT * FROM tbl_admin_employee_details WHERE hris = '$hris'");

        if ($emp_row = mysqli_fetch_assoc($emp_result)) {



            $name_of_employee = $emp_row['name_of_employee'];

            $epf_no = $emp_row['epf_no'];

            $company_hierarchy = $emp_row['company_hierarchy'];

            $title = $emp_row['title'];

            $designation = $emp_row['designation'];

            $display_name = $emp_row['display_name'];

            $location = $emp_row['location'];

            $nic_no = $emp_row['nic_no'];

            $category = $emp_row['category'];

            $employment_categories = $emp_row['employment_categories'];

            $date_joined = $emp_row['date_joined'];

            $date_resigned = $emp_row['date_resigned'];

            $category_ops_sales = $emp_row['category_ops_sales'];

            $status = $emp_row['status'];



            $insert_sql = "INSERT INTO tbl_admin_mobile_issues 

            (mobile_no, remarks, voice_data, branch_operational_remarks, name_of_employee, hris_no, company_contribution, epf_no, company_hierarchy, title, designation, display_name, location, nic_no, category, employment_categories, date_joined, date_resigned, category_ops_sales, status, connection_status) 

            VALUES 

            ('$new_mobile_no', 

            'Issued via Manage Requests page', 

            '$voice_data', 

            'From SIM request', 

            '$name_of_employee', 

            '$hris', 

            '$new_company_contribution', 

            '$epf_no', 

            '$company_hierarchy', 

            '$title', 

            '$designation', 

            '$display_name', 

            '$location', 

            '$nic_no', 

            '$category', 

            '$employment_categories', 

            '$date_joined', 

            '$date_resigned', 

            '$category_ops_sales', 

            '$status', 

            'Active')";



            if (mysqli_query($conn, $insert_sql)) {



                // ✅ Update SIM Request as Issued & Closed

                mysqli_query($conn, "UPDATE tbl_admin_sim_request 

                                    SET issue_status = 'Issued', close_status = 'Closed', company_contribution = '$new_company_contribution' 

                                    WHERE id = $request_id");



                $log_entry = "==== New Mobile Issue Entry ====\n";

                $log_entry .= "Request ID: $request_id\n";

                $log_entry .= "mobile_no: $new_mobile_no\n";

                $log_entry .= "company_contribution: $new_company_contribution\n";

                $log_entry .= "Issued by: $accepted_by\n";

                $log_entry .= "=====================================\n\n";



                file_put_contents('mobile_issue_log.txt', $log_entry, FILE_APPEND);



            } else {

                echo "❌ Error inserting into tbl_admin_mobile_issues: " . mysqli_error($conn);

            }



        } else {

            echo "❌ No employee record found for HRIS $hris.";

        }

    }

}



// --- Fetch All Requests ---

$query = "SELECT id, hris, name, request_type, request_division, designation, branch_division, 

recommended_by, approved_by, accepted_by, issue_status, close_status 

FROM tbl_admin_sim_request 

WHERE 

    recommended_by IS NOT NULL 

    AND recommended_by <> '' 

    AND approved_by IS NOT NULL 

    AND approved_by <> '' 

    AND recommended_by NOT LIKE 'REJECTED%' 

    AND approved_by NOT LIKE 'REJECTED%'

    AND close_status = 'Open'";



$result = mysqli_query($conn, $query);

?>





<!DOCTYPE html>

<html lang="en">

<head>

    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"> -->

    <meta charset="UTF-8">

    <title>Manage SIM/Mobile/Transfer Requests</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="styles.css">

    <style>

        table {

            table-layout: fixed;

            width: 100%;

        }



        th, td {

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

        }

    </style>



</head>

<body class="bg-light">

<div class="sidebar" id="sidebar">

    <?php include 'side-menu.php'; ?>

</div>



<div class="content font-size" id="contentArea">

    <div class="container-fluid">

    <div class="card shadow bg-white rounded p-4">

        <h5 class="mb-4 text-primary">Manage SIM/Mobile/Transfer Requests</h5>



            <?php if (isset($message)) echo $message; ?>



                <?php if (mysqli_num_rows($result) > 0) { ?>

                    <div style="overflow-x:auto;">

                    <table class="table table-bordered table-striped table-hover w-100 mt-3">

                        <thead class="table-light">

                            <tr>

                                <th style="width: 50px;">ID</th>

                                <th style="width: 80px;">HRIS</th>

                                <th style="width: 350px;">Name</th>

                                <th style="width: 140px;">Type</th>

                                <th style="width: 150px;">Division</th>

                                <th style="width: 250px;">Designation</th>

                                <th style="width: 250px;">Branch</th>

                                <th style="width: 150px;">Recommended</th>

                                <th style="width: 150px;">Approved</th>

                                <th style="width: 150px;">Accepted</th>

                                <th style="width: 150px;">Issued</th>

                                <th style="width: 150px;">Closed</th>

                                <th style="width: 250px;">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                            <tr class="clickable-row" data-hris="<?php echo htmlspecialchars($row['hris']); ?>" data-requesttype="<?php echo htmlspecialchars($row['request_type']); ?>">

                                <td><?php echo $row['id']; ?></td>

                                <td><?php echo htmlspecialchars($row['hris']); ?></td>

                                <td><?php echo htmlspecialchars($row['name']); ?></td>

                                <td><?php echo htmlspecialchars($row['request_type']); ?></td>

                                <td><?php echo htmlspecialchars($row['request_division']); ?></td>

                                <td><?php echo htmlspecialchars($row['designation']); ?></td>

                                <td><?php echo htmlspecialchars($row['branch_division']); ?></td>

                                <td><?php echo htmlspecialchars($row['recommended_by']); ?></td>

                                <td><?php echo htmlspecialchars($row['approved_by']); ?></td>

                                <td><?php echo htmlspecialchars($row['accepted_by']); ?></td>

                                <td><?php echo htmlspecialchars($row['issue_status']); ?></td>

                                <td><?php echo htmlspecialchars($row['close_status']); ?></td>

                                <td>

                                    <?php if (!$row['accepted_by']) { ?>

                                        <button type="button" class="btn btn-warning btn-sm"

                                                onclick="event.stopPropagation(); openAcceptModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['request_type']); ?>')">

                                            Accept & Issue

                                        </button>

                                        <button type="button" class="btn btn-danger btn-sm"

                                                onclick="event.stopPropagation(); confirmReject(<?php echo $row['id']; ?>)">

                                            Reject

                                        </button>

                                    <?php } elseif ($row['accepted_by'] && $row['issue_status'] != 'Issued') { ?>

                                        <span class="text-primary">Awaiting Issue</span>

                                    <?php } elseif ($row['issue_status'] == 'Issued' && $row['close_status'] != 'Closed') { ?>

                                        <span class="text-warning">Issued but not closed</span>

                                    <?php } else { ?>

                                        <span class="text-muted">Completed</span>

                                    <?php } ?>

                                    </td>





                            </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                    </div>

                

                </div>

            <?php } else { ?>

                <div class="alert alert-info">No requests found.</div>

            <?php } ?>

        </div>

    </div>

</div>



<!-- Combined Details + Accept Modal -->

<div class="modal fade" id="detailsModal" tabindex="-1">

  <div class="modal-dialog" style="max-width: 500px;">

    <div class="modal-content">

      <form method="post" id="combinedAcceptIssueForm">

        <div class="modal-header">

          <h5 class="modal-title">Connection Info & Accept/Issue</h5>

          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

        </div>

        <div class="modal-body">

          <!-- Existing Connections & Request Info -->

          <div id="detailsBody">Loading...</div>

          <hr>

          <!-- Accept & Issue Inputs -->

          <div id="acceptFieldsContainer"></div>

          <input type="hidden" name="accept_request_id" id="modalAcceptRequestId">

        </div>

        <div class="modal-footer">

          <button type="submit" class="btn btn-primary">Accept & Issue</button>

        </div>

      </form>

    </div>

  </div>

</div>





<!-- Confirm Action Modal -->

<div class="modal fade" id="confirmActionModal" tabindex="-1">

  <div class="modal-dialog" style="max-width: 500px;">

    <div class="modal-content">

      <div class="modal-header">

        <h5 class="modal-title">Please Confirm</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

      </div>

      <div class="modal-body" id="confirmActionBody">Are you sure you want to proceed?</div>

      <div class="modal-footer">

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

        <button type="submit" class="btn btn-primary" id="confirmActionYes">Yes</button>

      </div>

    </div>

  </div>

</div>



<!-- Accept & Issue Modal -->

<div class="modal fade" id="acceptIssueModal" tabindex="-1">

    <div class="modal-dialog" style="max-width: 500px;">

        <div class="modal-content">

            <form method="post" id="acceptIssueForm">

                <div class="modal-header">

                    <h5 class="modal-title">Accept & Issue</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <input type="hidden" name="accept_request_id" id="modalAcceptRequestId">

                    <div id="modalInputContainer"></div>

                </div>

                <div class="modal-footer">

                    <button type="submit" class="btn btn-primary">Accept & Issue</button>

                </div>

            </form>

        </div>

    </div>

</div>

<div class="modal fade" id="rejectConfirmModal" tabindex="-1">

    <div class="modal-dialog" style="max-width: 500px;">

        <div class="modal-content">

            <form method="post">

                <div class="modal-header">

                    <h5 class="modal-title">Confirm Rejection</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    Are you sure you want to reject this request?

                    <input type="hidden" name="reject_request_id" id="rejectRequestId">

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

                    <button type="submit" class="btn btn-danger">Yes, Reject</button>

                </div>

            </form>

        </div>

    </div>

</div>







<script>

function openAcceptModal(requestId, requestType) {

    $('#modalAcceptRequestId').val(requestId);



    if (requestType.toLowerCase() === 'sim') {

        $('#modalInputContainer').html(`

            <label>Mobile Number:</label>

            <input type="text" name="new_mobile_no" class="form-control" required>

            <label>Company Contribution:</label>

            <input type="text" name="new_company_contribution" class="form-control" required>

        `);

    } else if (requestType.toLowerCase() === 'mobile') {

        $('#modalInputContainer').html(`

            <label>IMEI:</label>

            <input type="text" name="new_imei" class="form-control" required>

        `);

    } else {

        $('#modalInputContainer').html('<p>No additional information required.</p>');

    }



    var modal = new bootstrap.Modal(document.getElementById('acceptIssueModal'));

    modal.show();

}



function confirmReject(requestId) {

    $('#rejectRequestId').val(requestId);

    var modal = new bootstrap.Modal(document.getElementById('rejectConfirmModal'));

    modal.show();

}

</script>



<script>

function toggleMenu() {

    document.getElementById("sidebar").classList.toggle("active");

}



// On row click, show issued phones/connections

$(document).ready(function () {

  $(".clickable-row").click(function () {

    const hris = $(this).data("hris");

    const requestType = $(this).data("requesttype").toLowerCase();

    const requestId = $(this).find('td:first').text();



    // Reset modal

    $('#modalAcceptRequestId').val(requestId);

    $('#detailsBody').html("Loading...");

    $('#acceptFieldsContainer').html("");



    fetch('get-employee-details.php', {

      method: 'POST',

      headers: { 'Content-Type': 'application/json' },

      body: JSON.stringify({ hris: hris })

    })

      .then(response => response.json())

      .then(data => {

        let info = `

            <div><strong>Name:</strong> ${data.full_name || 'N/A'}</div>

            <div><strong>HRIS:</strong> ${hris}</div>

            <div><strong>Division:</strong> ${data.location || 'N/A'}</div>

            <div><strong>Branch:</strong> ${data.company_hierarchy || 'N/A'}</div>

            <hr>

        `;



        if (requestType === 'mobile' && data.phones?.length > 0) {

          info += '<strong>Issued Phones:</strong><ul>';

          data.phones.forEach(p => {

            info += `<li>IMEI: ${p.imei_number} (Issued: ${p.issue_date})</li>`;

          });

          info += '</ul>';

        } else if (requestType === 'sim' && data.mobiles?.length > 0) {

          info += '<strong>Issued SIMs:</strong><ul>';

          data.mobiles.forEach(m => {

            info += `<li>Number: ${m.mobile_no} (${m.voice_data})</li>`;

          });

          info += '</ul>';

        } else {

          info += '<em>No existing devices or numbers found.</em>';

        }



        $('#detailsBody').html(info);



        // Populate Accept fields

        if (requestType === 'sim') {

          $('#acceptFieldsContainer').html(`

            <div class="mb-3">

              <label class="form-label">Mobile Number</label>

              <input type="text" name="new_mobile_no" class="form-control" required>

            </div>

            <div class="mb-3">

              <label class="form-label">Company Contribution</label>

              <input type="text" name="new_company_contribution" class="form-control" required>

            </div>

          `);

        } else if (requestType === 'mobile') {

          $('#acceptFieldsContainer').html(`

            <div class="mb-3">

              <label class="form-label">IMEI</label>

              <input type="text" name="new_imei" class="form-control" required>

            </div>

          `);

        } else {

          $('#acceptFieldsContainer').html('<p>No additional fields required.</p>');

        }



        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));

        modal.show();

      })

      .catch(() => {

        $('#detailsBody').html('Failed to load details.');

        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));

        modal.show();

      });

  });

});



</script>

</body>

</html>

