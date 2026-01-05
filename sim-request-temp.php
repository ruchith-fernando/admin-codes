    <?php
        include 'connections/connection.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>SIM/Mobile/Transfer Request System</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="styles.css">
        <style>
            label {
                display: block !important;
                margin-bottom: 0.5rem;
            }
            select.form-select, input.form-control {
                display: block !important;
                width: 100%;
            }
        </style>
    </head>
    <body class="bg-light p-4">
        <button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>
        <div class="sidebar" id="sidebar">
            <?php include 'side-menu.php'; ?>
        </div>

        <div class="content font-size" id="contentArea">
            <div class="container mt-5">
                <h2 class="mb-4">New SIM/Mobile/Transfer Request</h2>

                <div class="mb-3">
                    <label for="requestType">Select Request Type:</label>
                    <select id="requestType" class="form-select" onchange="showForm()">
                        <option value="">-- Select --</option>
                        <option value="sim">SIM Request</option>
                        <option value="mobile">Mobile Phone Request</option>
                        <option value="transfer">User Transfer</option>
                    </select>
                </div>

                <div id="requestForm" class="form-section" style="display:none;">
                    <h4>Request Details</h4>
                    <form id="requestDetails">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label>HRIS:</label>
                                <input type="text" class="form-control" name="hris" id="hrisInput" required>
                            </div>
                            <div class="col-md-6">
                                <label>Name:</label>
                                <input type="text" class="form-control" name="name" id="fullName" readonly>
                            </div>
                            <div class="col-md-6">
                                <label>NIC:</label>
                                <input type="text" class="form-control" name="nic" id="nic" readonly>
                            </div>
                            <div class="col-md-6">
                                <label>Designation:</label>
                                <input type="text" class="form-control" name="designation" id="designation" readonly>
                            </div>
                            <div class="col-md-6">
                                <label>Branch or Division:</label>
                                <input type="text" class="form-control" name="branch_division" id="branchDivision" readonly>
                            </div>
                            <div class="col-md-6">
                                <label>Employee Category:</label>
                                <input type="text" class="form-control" name="employee_category" id="employeeCategory" readonly>
                            </div>
                            <div class="col-md-6">
                                <label>Requesting Division</label>
                                <input type="text" class="form-control" name="request_division" id="requestDivision" readonly>
                            </div>
                            
                            <!-- <div class="row g-3"> -->
                                <div class="col-md-6">
                                    <label>Email:</label>
                                    <input type="email" class="form-control" name="email" id="email" required>
                                </div>
                            <!-- </div> -->
                            <!-- BEGIN extra fields that should be hidden for Mobile Request -->
                            <div id="extraFields">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Voice or Data:</label>
                                        <select name="voice_data" class="form-select" id="voiceData">
                                            <option value="">-- Select Option --</option>
                                            <option value="Voice">Voice</option>
                                            <option value="Data">Data</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="voicePackageDiv" style="display:none;">
                                        <label>Voice Package:</label>
                                        <select name="voice_package" class="form-select" id="voicePackage">
                                            <option value="">-- Select Voice Package --</option>
                                            <option value="Unlimited Voice Package / 10GB Data / WhatsApp">Unlimited Voice Package / 10GB Data / WhatsApp</option>
                                            <option value="Basic Package - Free Calling Within Company Numbers">Basic Package - Free Calling Within Company Numbers</option>
                                            <option value="500 Credit Limit">500 Credit Limit</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="dataPackageDiv" style="display:none;">
                                        <label>Data Package:</label>
                                        <select name="data_package" class="form-select" id="dataPackage">
                                            <option value="">-- Select Data Package --</option>
                                            <option value="4GB">4GB</option>
                                            <option value="8GB">8GB</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="otherAmountDiv" style="display:none;">
                                        <label>Enter Other Amount of GB:</label>
                                        <input type="number" class="form-control" name="other_amount" id="otherAmount" placeholder="Enter amount">
                                    </div>
                                    <!-- <div class="col-md-6">
                                        <label>Email:</label>
                                        <input type="email" class="form-control" name="email" id="email" required>
                                    </div> -->
                                    </div>
                                    <!-- END extra fields -->
                                </div>
                            </div>
                        <input type="hidden" name="request_type" id="hidden_request_type">

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Submit Request</button>
                        </div>
                    </form>
                </div>

                <hr class="my-5">
<!-- 
                <h4 class="mb-3">Pending Approvals</h4>
                <div id="pendingRequests"></div> -->
            </div>
        </div>
        <!-- Modal to show Mobile Numbers -->
        <div class="modal fade" id="mobileDetailsModal" tabindex="-1" aria-labelledby="mobileDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mobileDetailsModalLabel">Mobile Numbers Issued</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="mobileDetailsBody">
                <!-- Mobile numbers will be inserted dynamically -->
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-success" id="yesNewConnection"><i class="bi bi-check-circle-fill me-2"></i> Yes, Need New</button>
                <!-- <button type="button" class="btn btn-success" id="yesNewConnection">✅ Yes, Need New</button> -->
                <!-- <button type="button" class="btn btn-danger" id="noNewConnection">❌ No, Don't Need</button> -->
                <button type="button" class="btn btn-danger" id="noNewConnection"><i class="bi bi-x-circle-fill me-2"></i> No, Don't Need</button>
            </div>
            </div>
        </div>
        </div>

        <!-- Modal to show Issued Phones -->
        <div class="modal fade" id="phoneDetailsModal" tabindex="-1" aria-labelledby="phoneDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="phoneDetailsModalLabel">Issued Phone Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="phoneDetailsBody">
                <!-- Issued phones will be dynamically loaded here -->
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-success" id="yesNewPhone"><i class="bi bi-check-circle-fill me-2"></i> Yes, Need New</button>
                <button type="button" class="btn btn-danger" id="noNewPhone"><i class="bi bi-x-circle-fill me-2"></i> No, Don't Need</button>

                <!-- <button type="button" class="btn btn-success" id="yesNewPhone">✅ Yes, Need New Phone</button>
                <button type="button" class="btn btn-danger" id="noNewPhone">❌ No, Don't Need</button> -->
            </div>
            </div>
        </div>
        </div>
        <!-- Invalid HRIS Modal -->
        <div class="modal fade" id="invalidHrisModal" tabindex="-1" aria-labelledby="invalidHrisModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="invalidHrisModalLabel">Invalid HRIS Number</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p>Please enter a valid <strong>6-digit HRIS number</strong>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
            </div>
            </div>
        </div>
        </div>

        <script>
function toggleMenu() {
    document.getElementById("sidebar").classList.toggle("active");
}

function showForm() {
    const type = document.getElementById('requestType').value;
    const form = document.getElementById('requestForm');
    document.getElementById('hidden_request_type').value = type;
    form.style.display = type ? 'block' : 'none';

    const extraFields = document.getElementById('extraFields');
    if (type === 'mobile') {
        extraFields.style.display = 'none';
    } else {
        extraFields.style.display = 'block';
    }
    resetEmployeeFields();
}

function clearAllFields() {
    $('#requestDetails')[0].reset();
    $('#fullName, #nic, #designation, #branchDivision, #employeeCategory, #hrisInput, #requestDivision').val('');
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('hrisInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.blur();
        }
    });

    document.getElementById('requestDetails').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('submit-request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            this.reset();
            document.getElementById('requestForm').style.display = 'none';
            loadPendingRequests();
        })
        .catch(error => {
            alert('Submission failed: ' + error);
            console.error('Submit error:', error);
        });
    });
});

function loadPendingRequests() {
    fetch('pending-request.php')
    .then(response => response.text())
    .then(data => {
        document.getElementById('pendingRequests').innerHTML = data;
    });
}

function approveReject(id, action) {
    fetch(`approve-reject.php?id=${id}&action=${action}`)
    .then(response => response.text())
    .then(data => {
        alert(data);
        loadPendingRequests();
    });
}

window.onload = loadPendingRequests;

function resetEmployeeFields() {
    $('#fullName, #nic, #designation, #branchDivision, #employeeCategory, #hrisInput, #requestDivision').val('');
}

$('#hrisInput').on('blur', function () {
    const hris = $(this).val().trim();
    const hrisRegex = /^\d{6}$/;

    if (!hrisRegex.test(hris)) {
        const invalidModal = new bootstrap.Modal(document.getElementById('invalidHrisModal'), {
            backdrop: 'static',
            keyboard: false
        });
        invalidModal.show();

        resetEmployeeFields();

        document.getElementById('invalidHrisOkBtn').onclick = function() {
            invalidModal.hide();
            document.getElementById('hrisInput').focus();
        };
        return;
    }

    const requestType = document.getElementById('requestType').value;

    fetch('get-employee-details.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ hris: hris })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network error');
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            $('#fullName').val(data.full_name);
            $('#nic').val(data.nic_no);
            $('#designation').val(data.designation);
            $('#branchDivision').val(data.location);
            $('#requestDivision').val(data.department_route);
            $('#employeeCategory').val(data.company_hierarchy);
            // console.log('Department Route:', data.department_route);
            // alert('Department Route: ' + data.department_route);


            $('#fullName, #nic, #designation, #branchDivision, #employeeCategory, #requestDivision').attr('readonly', true);

            if (requestType === 'mobile' && data.phones && data.phones.length > 0) {
                let phoneBody = '<p><strong>Phone(s) already issued:</strong></p><ul class="list-group">';
                data.phones.forEach(function(phone) {
                    phoneBody += `
                        <li class="list-group-item">
                            Issue Date: <strong>${phone.issue_date}</strong><br>
                            IMEI Number: ${phone.imei_number}
                        </li>`;
                });
                phoneBody += '</ul><hr><p><strong>Do you need a new phone?</strong></p>';

                document.getElementById('phoneDetailsBody').innerHTML = phoneBody;

                const myPhoneModal = new bootstrap.Modal(document.getElementById('phoneDetailsModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                myPhoneModal.show();

                document.getElementById('yesNewPhone').onclick = function () {
                    myPhoneModal.hide();
                    document.getElementById('email').focus();
                };

                document.getElementById('noNewPhone').onclick = function () {
                    myPhoneModal.hide();
                    clearAllFields();
                };
            }

            if (requestType !== 'mobile' && data.mobiles && data.mobiles.length > 0) {
                let modalBody = '<p><strong>Mobile number(s) already issued:</strong></p><ul class="list-group">';
                data.mobiles.forEach(function(mobile) {
                    modalBody += `
                        <li class="list-group-item">
                            Mobile No: <strong>${mobile.mobile_no}</strong><br>
                            Voice/Data: ${mobile.voice_data}<br>
                            Company Contribution: ${mobile.company_contribution}
                        </li>`;
                });
                modalBody += '</ul><hr><p><strong>Do you need a new connection?</strong></p>';

                document.getElementById('mobileDetailsBody').innerHTML = modalBody;

                const myModal = new bootstrap.Modal(document.getElementById('mobileDetailsModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                myModal.show();

                document.getElementById('yesNewConnection').onclick = function () {
                    myModal.hide();
                    document.getElementById('email').focus();
                };

                document.getElementById('noNewConnection').onclick = function () {
                    myModal.hide();
                    clearAllFields();
                };
            }
        } else {
            alert('No employee found for the given HRIS.');
            resetEmployeeFields();
        }
    })
    .catch(error => {
        alert('Network error while fetching employee details.');
        console.error('Error:', error);
        resetEmployeeFields();
    });
});

document.getElementById('voiceData').addEventListener('change', function() {
    const selected = this.value;
    const voiceDiv = document.getElementById('voicePackageDiv');
    const dataDiv = document.getElementById('dataPackageDiv');
    const otherAmountDiv = document.getElementById('otherAmountDiv');

    if (selected === 'Voice') {
        voiceDiv.style.display = 'block';
        dataDiv.style.display = 'none';
        otherAmountDiv.style.display = 'none';
        document.getElementById('dataPackage').value = '';
        document.getElementById('otherAmount').value = '';
    } else if (selected === 'Data') {
        dataDiv.style.display = 'block';
        voiceDiv.style.display = 'none';
    } else {
        voiceDiv.style.display = 'none';
        dataDiv.style.display = 'none';
        otherAmountDiv.style.display = 'none';
    }
});

document.getElementById('dataPackage').addEventListener('change', function() {
    const selected = this.value;
    const otherAmountDiv = document.getElementById('otherAmountDiv');
    if (selected === 'Other') {
        otherAmountDiv.style.display = 'block';
    } else {
        otherAmountDiv.style.display = 'none';
        document.getElementById('otherAmount').value = '';
    }
});

function logError(message) {
    fetch('log-error.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: message })
    });
}
</script>

    </body>
</html>
