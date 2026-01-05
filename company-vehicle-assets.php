<!-- company-vehicle-assets.php -->

<?php

include 'connections/connection.php';



$limit = 10;

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

$search = isset($_GET['search']) ? strtolower(trim($conn->real_escape_string($_GET['search']))) : '';

$offset = ($page - 1) * $limit;



// Build WHERE conditions

$where = "1";

if (!empty($search)) {

    $statusFilter = '';

    if (strpos($search, 'signed') !== false && strpos($search, 'not') === false) {

        $statusFilter = "(contract_file IS NOT NULL)";

    } elseif (strpos($search, 'sent') !== false) {

        $statusFilter = "(contract_file IS NULL AND (contract_sent_to != '' OR contract_sent_where != '' OR contract_sent_date IS NOT NULL))";

    } elseif (strpos($search, 'not') !== false && strpos($search, 'signed') !== false) {

        $statusFilter = "(contract_file IS NULL AND (contract_sent_to IS NULL OR contract_sent_to = ''))";

    }



    $textSearch = "(file_ref LIKE '%$search%' 

                OR hris LIKE '%$search%' 

                OR veh_no LIKE '%$search%' 

                OR assigned_user LIKE '%$search%')";



    if ($statusFilter) {

        $where = "($textSearch OR $statusFilter)";

    } else {

        $where = $textSearch;

    }

}



$sql = "SELECT * FROM tbl_admin_fixed_assets 

        WHERE $where 

        ORDER BY registration_date DESC 

        LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);



// Count total

$count_sql = "SELECT COUNT(*) as total FROM tbl_admin_fixed_assets WHERE $where";

$count_result = $conn->query($count_sql);

$total_rows = ($count_result && $count_result->num_rows > 0) ? $count_result->fetch_assoc()['total'] : 0;

$total_pages = ceil($total_rows / $limit);

?>
<div class="content font-size">

    <div class="container-fluid">

        <div class="card shadow bg-white rounded p-4">  

            <h5 class="mb-4 text-primary">Company Vehicle Assets</h5>



            <!-- Search Input -->

            <div class="mb-3">

                <input type="text" id="searchInput" class="form-control" placeholder="Search File Ref, HRIS, Vehicle No, Assigned User">

            </div>



            <!-- Table Container -->

           <div id="tableContainer" class="font-size">

                <div class="text-center p-3">Loading vehicle asset data...</div>

            </div>

        </div>

    </div>

</div>



<!-- Vehicle Modal -->

<div class="modal fade" id="vehicleDetailsModal" tabindex="-1" data-bs-backdrop="static">

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title">Vehicle Asset Details</h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

            </div>

            <div class="modal-body">

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <div id="modalDetailsContent" class="row g-4"></div>



                        <hr class="my-4">

                        <div class="row">

                            <div class="col-md-12">

                                <h6 class="text-primary border-bottom pb-1">Signed Contract Upload</h6>

                                <form id="contractUploadForm" enctype="multipart/form-data">

                                    <input type="hidden" name="file_ref" id="uploadFileRef">

                                    <div class="mb-3">

                                        <label for="contractFile" class="form-label">Upload Contract (PDF only)</label>

                                        <input class="form-control" type="file" name="contract_file" id="contractFile" accept="application/pdf" required>

                                    </div>

                                    <button type="submit" class="btn btn-success">Upload Contract</button>

                                </form>

                                <div id="contractActions" class="mt-3" style="display: none;">

                                    <a id="viewContractBtn" class="btn btn-primary me-2" target="_blank">View Contract</a>

                                    <a id="downloadContractBtn" class="btn btn-outline-secondary" download>Download Contract</a>

                                </div>

                            </div>

                        </div>



                        <hr class="my-4">

                        <div class="row">

                            <div class="col-md-12">

                                <h6 class="text-primary border-bottom pb-1">Mark as Sent for Signing</h6>

                                <form id="contractSentForm">

                                    <input type="hidden" name="file_ref" id="sentFileRef">

                                    <div class="mb-2">

                                        <label class="form-label">Sent To (Name)</label>

                                        <input type="text" name="contract_sent_to" class="form-control" required>

                                    </div>

                                    <div class="mb-2">

                                        <label class="form-label">Sent Where (Location)</label>

                                        <input type="text" name="contract_sent_where" class="form-control" required>

                                    </div>

                                    <div class="mb-2">

                                        <label class="form-label">Date Sent</label>

                                        <input type="date" name="contract_sent_date" class="form-control" required>

                                    </div>

                                    <button type="submit" class="btn btn-warning">Save Sent Info</button>

                                </form>

                            </div>

                        </div>



                        <hr class="my-4">

                        <div class="row">

                            <div class="col-md-12">

                                <h6 class="text-primary border-bottom pb-1">Transfer Assigned User</h6>

                                <form id="userTransferForm" enctype="multipart/form-data">

                                    <input type="hidden" name="file_ref" id="transferFileRef">

                                    <div class="row g-2">

                                        <div class="col-md-6">

                                            <label class="form-label">New Assigned User</label>

                                            <input type="text" name="new_assigned_user" class="form-control" required>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label">New HRIS</label>

                                            <input type="text" name="new_hris" class="form-control" required>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label">New NIC</label>

                                            <input type="text" name="new_nic" class="form-control" required>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label">New Telephone Number</label>

                                            <input type="text" name="new_tp_no" class="form-control" required>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label">New Division</label>

                                            <input type="text" name="new_division" class="form-control" required>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label">Change Method</label>

                                            <select name="change_method" class="form-select">

                                                <option value="System">System</option>

                                                <option value="Email">Email</option>

                                                <option value="Phone">Phone</option>

                                                <option value="Other">Other</option>

                                            </select>

                                        </div>

                                        <div class="col-md-12">

                                            <label class="form-label">Reason for Transfer</label>

                                            <textarea name="reason" class="form-control" required></textarea>

                                        </div>



                                        <div class="col-md-12">

                                            <label class="form-label">Upload Email (PDF/EML)</label>

                                            <input type="file" name="email_file" class="form-control" accept=".pdf,.eml,.msg">

                                        </div>



                                    </div>

                                    <button type="submit" class="btn btn-danger mt-3">Submit Transfer</button>

                                </form>

                            </div>

                        </div>



                        <hr class="my-4">

                        <div>

                            <h6 class="text-primary border-bottom pb-1">Previous Assignments</h6>

                            <div id="assignmentHistory"></div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="modal-footer bg-light">

                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>

            </div>

        </div>

    </div>

</div>

<!-- Success/Error Modal -->

<div class="modal fade" id="responseModal" tabindex="-1">

  <div class="modal-dialog modal-dialog-centered">

    <div class="modal-content">

      <div class="modal-header bg-success text-white">

        <h5 class="modal-title">Transfer Status</h5>

        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>

      <div class="modal-body" id="responseMessage">

        <!-- Message will be inserted here -->

      </div>

      <div class="modal-footer">

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

      </div>

    </div>

  </div>

</div>

<!-- Contract Sent Modal -->

<div class="modal fade" id="sentSuccessModal" tabindex="-1">

  <div class="modal-dialog modal-dialog-centered">

    <div class="modal-content">

      <div class="modal-header bg-warning text-dark">

        <h5 class="modal-title">Contract Sent Info</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>

      <div class="modal-body" id="sentSuccessMessage">

        <!-- Message gets injected here -->

      </div>

      <div class="modal-footer">

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

      </div>

    </div>

  </div>

</div>



<!-- Contract Upload Success Modal -->

<div class="modal fade" id="uploadSuccessModal" tabindex="-1">

  <div class="modal-dialog modal-dialog-centered">

    <div class="modal-content">

      <div class="modal-header bg-success text-white">

        <h5 class="modal-title">Contract Upload</h5>

        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>

      <div class="modal-body" id="uploadSuccessMessage">

        <!-- Success message goes here -->

      </div>

      <div class="modal-footer">

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

      </div>

    </div>

  </div>

</div>



<!-- Transfer Success Modal -->

<div class="modal fade" id="transferSuccessModal" tabindex="-1">

  <div class="modal-dialog modal-dialog-centered">

    <div class="modal-content">

      <div class="modal-header bg-info text-white">

        <h5 class="modal-title">User Transfer</h5>

        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>

      <div class="modal-body" id="transferSuccessMessage">

        <!-- Transfer confirmation goes here -->

      </div>

      <div class="modal-footer">

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

      </div>

    </div>

  </div>

</div>





<script>

$(document).ready(function () {

    let typingTimer;

    const delay = 150;



    // Live search input

    $("#searchInput").on("keyup", function () {

        clearTimeout(typingTimer);

        typingTimer = setTimeout(function () {

            loadTable();

        }, delay);

    });



    // Pagination click

    $(document).on('click', '.page-link', function (e) {

        e.preventDefault();

        const page = $(this).data('page');

        loadTable(page);

    });



    // Load table via AJAX

    function loadTable(page = 1) {

        const search = $("#searchInput").val();

        $.ajax({

            url: "vehicle-assets-table.php",

            type: "GET",

            data: { search: search, page: page },

            success: function (data) {

                $("#tableContainer").html(data);

            },

            error: function () {

                $("#tableContainer").html(`<div class="alert alert-danger text-center">Failed to load data. Please try again.</div>`);

            }

        });

    }



    // Initial load

    loadTable();



    // Record row click

    $(document).on('click', '.record-row', function () {

        const rowData = $(this).data('row');

        $('#uploadFileRef').val(rowData.file_ref);

        $('#sentFileRef').val(rowData.file_ref);

        $('#transferFileRef').val(rowData.file_ref);

        $('#userTransferForm')[0].reset();



        // Populate sent fields

        $('#contractSentForm input[name="contract_sent_to"]').val(rowData.contract_sent_to || '');

        $('#contractSentForm input[name="contract_sent_where"]').val(rowData.contract_sent_where || '');

        $('#contractSentForm input[name="contract_sent_date"]').val(rowData.contract_sent_date || '');



        // Show contract view/download if exists

        if (rowData.contract_file) {

            const fileUrl = 'uploads/contracts/' + rowData.contract_file;

            $('#viewContractBtn').attr('href', fileUrl);

            $('#downloadContractBtn').attr('href', fileUrl);

            $('#contractActions').show();

        } else {

            $('#contractActions').hide();

        }



        // Details content

        const contentMap = {

            'Vehicle Info': ['veh_no', 'vehicle_type', 'make', 'model', 'yom', 'registration_date'],

            'User Info': ['assigned_user', 'hris', 'nic', 'tp_no', 'division'],

            'Ownership & Status': ['file_ref', 'book_owner', 'cr_available', 'asset_condition', 'agreement', 'new_comments']

        };



        const labelMap = {

            tp_no: 'Telephone Number',

            telephone_number: 'Telephone Number',

            assigned_user: 'Assigned User',

            hris: 'HRIS',

            nic: 'NIC',

            division: 'Division',

            veh_no: 'Vehicle No',

            vehicle_type: 'Vehicle Type',

            make: 'Make',

            model: 'Model',

            yom: 'Year of Manufacture',

            registration_date: 'Registration Date',

            file_ref: 'File Ref',

            book_owner: 'Book Owner',

            cr_available: 'CR Available',

            asset_condition: 'Asset Condition',

            agreement: 'Agreement',

            new_comments: 'Comments'

        };



        let html = '';

        for (const section in contentMap) {

            html += `<div class="col-12"><h6 class="text-primary border-bottom pb-1 mt-2">${section}</h6></div>`;

            contentMap[section].forEach(key => {

                const label = labelMap[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

                const value = rowData[key] ? rowData[key] : '-';

                html += `

                    <div class="col-md-6">

                        <div class="border rounded p-2 bg-light">

                            <strong>${label}</strong><br>

                            <span class="text-dark">${value}</span>

                        </div>

                    </div>

                `;

            });

        }



        $('#modalDetailsContent').html(html);



        // Load assignment history

        $.ajax({

            url: 'get-vehicle-asset-history.php',

            method: 'GET',

            data: { file_ref: rowData.file_ref },

            success: function (data) {

                $('#assignmentHistory').html(data);

            }

        });



        new bootstrap.Modal(document.getElementById('vehicleDetailsModal')).show();

    });



    // Upload contract form

    $('#contractUploadForm').on('submit', function (e) {

        e.preventDefault();

        const formData = new FormData(this);

        $.ajax({

            url: 'upload-contract.php',

            method: 'POST',

            data: formData,

            contentType: false,

            processData: false,

            success: function (response) {

                try {

                    const res = typeof response === 'string' ? JSON.parse(response) : response;

                    if (res.status === 'success') {

                        $('#uploadSuccessMessage').html('Contract uploaded successfully.');

                        new bootstrap.Modal(document.getElementById('uploadSuccessModal')).show();

                        $('#viewContractBtn').attr('href', res.file);

                        $('#downloadContractBtn').attr('href', res.file);

                        $('#contractActions').show();

                    } else {

                        $('#responseMessage').html('Upload failed: ' + (res.message || 'Please try again.'));

                        $('#responseModal .modal-header').removeClass('bg-success').addClass('bg-danger');

                        new bootstrap.Modal(document.getElementById('responseModal')).show();

                    }

                } catch (e) {

                    $('#responseMessage').html('Unexpected error occurred during upload.');

                    $('#responseModal .modal-header').removeClass('bg-success').addClass('bg-danger');

                    new bootstrap.Modal(document.getElementById('responseModal')).show();

                }

                $('#vehicleDetailsModal').modal('hide');

                loadTable();

            },

            error: function () {

                $('#responseMessage').html('An unexpected error occurred during upload.');

                $('#responseModal .modal-header').removeClass('bg-success').addClass('bg-danger');

                $('#vehicleDetailsModal').modal('hide');

                new bootstrap.Modal(document.getElementById('responseModal')).show();

            }

        });

    });



    // Mark contract as sent form

    $('#contractSentForm').on('submit', function (e) {

        e.preventDefault();

        const formData = new FormData(this);

        $.ajax({

            url: 'mark-contract-sent.php',

            method: 'POST',

            data: formData,

            contentType: false,

            processData: false,

            success: function (response) {

                try {

                    const res = typeof response === 'string' ? JSON.parse(response) : response;

                    if (res.status === 'success') {

                        $('#sentSuccessMessage').html('Sent contract information saved successfully.');

                        new bootstrap.Modal(document.getElementById('sentSuccessModal')).show();

                    } else {

                        $('#responseMessage').html('Failed to save sent info: ' + (res.message || 'Please try again.'));

                        $('#responseModal .modal-header').removeClass('bg-success').addClass('bg-danger');

                        new bootstrap.Modal(document.getElementById('responseModal')).show();

                    }

                } catch (e) {

                    $('#responseMessage').html('Unexpected response. Please contact IT.');

                    $('#responseModal .modal-header').removeClass('bg-success').addClass('bg-danger');

                    new bootstrap.Modal(document.getElementById('responseModal')).show();

                }

                $('#vehicleDetailsModal').modal('hide');

                loadTable();

            },

            error: function () {

                $('#responseMessage').html('Unexpected error. Please contact IT.');

                $('#responseModal .modal-header').removeClass('bg-success').addClass('bg-danger');

                $('#vehicleDetailsModal').modal('hide');

                new bootstrap.Modal(document.getElementById('responseModal')).show();

            }

        });

    });



    // User transfer form

    $('#userTransferForm').on('submit', function (e) {

        e.preventDefault();

        const formData = $(this).serialize();

        $.ajax({

            url: 'submit-vehicle-transfer.php',

            method: 'POST',

            data: formData,

            dataType: 'json',

            success: function (res) {

                if (res.status === 'success') {

                    $('#transferSuccessMessage').html('User transfer recorded successfully.');

                    new bootstrap.Modal(document.getElementById('transferSuccessModal')).show();

                } else {

                    $('#responseMessage').html('Error saving transfer: ' + (res.message || 'Please try again.'));

                    $('#responseModal .modal-header').removeClass('bg-success').addClass('bg-danger');

                    new bootstrap.Modal(document.getElementById('responseModal')).show();

                }

                $('#vehicleDetailsModal').modal('hide');

                loadTable();

            },

            error: function () {

                $('#responseMessage').html('Transfer request failed. Please try again.');

                $('#responseModal .modal-header').removeClass('bg-success').addClass('bg-danger');

                $('#vehicleDetailsModal').modal('hide');

                new bootstrap.Modal(document.getElementById('responseModal')).show();

            }

        });

    });

});

</script>

