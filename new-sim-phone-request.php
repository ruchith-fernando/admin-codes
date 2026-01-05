<?php include 'connections/connection.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SIM/Mobile/Transfer Request System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">

<div class="container">
    <div class="card shadow bg-white rounded p-4">
        <h2 class="mb-4">New SIM / Mobile Phone / Transfer Request</h2>

        <div class="mb-3">
            <label for="requestType">Select Request Type:</label>
            <select id="requestType" class="form-select" onchange="showForm()">
                <option value="">-- Select --</option>
                <option value="sim">SIM Request</option>
                <option value="transfer">User Transfer</option>
                <option value="mobile">Mobile Phone Request</option>
            </select>
        </div>

        <!-- SIM/Transfer Form -->
        <div id="simForm" style="display:none;">
            <form id="simRequestForm">
                <input type="hidden" name="request_type" id="simRequestTypeHidden">

                <div class="mb-3">
                    <label>HRIS:</label>
                    <input type="text" name="hris" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Name:</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>NIC:</label>
                    <input type="text" name="nic" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Designation:</label>
                    <input type="text" name="designation" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Branch/Division:</label>
                    <input type="text" name="branch_division" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Employee Category:</label>
                    <input type="text" name="employee_category" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Voice or Data:</label>
                    <input type="text" name="voice_data" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Voice Package:</label>
                    <input type="text" name="voice_package" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Data Package:</label>
                    <input type="text" name="data_package" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Email:</label>
                    <input type="email" name="email" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary">Submit SIM/Transfer Request</button>
            </form>
        </div>

        <!-- Mobile Phone Form -->
        <div id="phoneForm" style="display:none;">
            <form id="phoneRequestForm">
                <input type="hidden" name="request_type" value="mobile">

                <div class="mb-3">
                    <label>HRIS:</label>
                    <input type="text" name="hris" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Name:</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>NIC:</label>
                    <input type="text" name="nic" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Designation:</label>
                    <input type="text" name="designation" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Branch/Division:</label>
                    <input type="text" name="branch_division" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Employee Category:</label>
                    <input type="text" name="employee_category" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Email:</label>
                    <input type="email" name="email" class="form-control">
                </div>

                <button type="submit" class="btn btn-success">Submit Phone Request</button>
            </form>
        </div>
</div>

</div>

<script>
function showForm() {
    const type = document.getElementById('requestType').value;
    document.getElementById('simForm').style.display = 'none';
    document.getElementById('phoneForm').style.display = 'none';

    if (type === 'sim' || type === 'transfer') {
        document.getElementById('simForm').style.display = 'block';
        document.getElementById('simRequestTypeHidden').value = type;
    }
    if (type === 'mobile') {
        document.getElementById('phoneForm').style.display = 'block';
    }
}

document.getElementById('simRequestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('submit-sim-request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        this.reset();
        document.getElementById('simForm').style.display = 'none';
    });
});

document.getElementById('phoneRequestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('submit-phone-request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        this.reset();
        document.getElementById('phoneForm').style.display = 'none';
    });
});
</script>

</body>
</html>
