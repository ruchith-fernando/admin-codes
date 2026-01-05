<!-- vehicle-asset-transfer.php -->
<?php include 'connections/connection.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transfer Vehicle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4">
        <h4 class="mb-3">Transfer Vehicle Asset</h4>
        <form method="POST" action="submit-vehicle-transfer.php">
            <div class="mb-3">
                <label for="file_ref" class="form-label">File Ref</label>
                <input type="text" name="file_ref" id="file_ref" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Assigned User</label>
                <input type="text" name="new_assigned_user" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New HRIS</label>
                <input type="text" name="new_hris" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New NIC</label>
                <input type="text" name="new_nic" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New TP No</label>
                <input type="text" name="new_tp_no" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Division</label>
                <input type="text" name="new_division" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Change Method</label>
                <select name="change_method" class="form-select" required>
                    <option value="System">System</option>
                    <option value="Email">Email</option>
                    <option value="Phone">Phone</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Submit Transfer</button>
        </form>
    </div>
</div>
</body>
</html>