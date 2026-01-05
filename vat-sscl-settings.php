<?php
include 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_vat_sscl'])) {
    $vat = floatval($_POST['vat_percentage']);
    $sscl = floatval($_POST['sscl_percentage']);

    $insert_query = "INSERT INTO tbl_vat_sscl_rates (vat_percentage, sscl_percentage) 
                     VALUES ($vat, $sscl)";
    mysqli_query($conn, $insert_query);

    echo "<div class='alert alert-success'>VAT and SSCL rates updated!</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update VAT and SSCL Rates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        display: flex;
        min-height: 100vh;
        flex-direction: row;
    }
    .sidebar {
        width: 250px;
        background-color: #f8f9fa;
        position: fixed;
        height: 100%;
        padding: 20px;
    }
    .content {
        margin-left: 250px;
        padding: 20px;
        width: calc(100% - 250px);
    }
    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            width: 100%;
        }
    }
    </style>
</head>
<body>

<div class="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content">
    <h2 class="mb-4">Update VAT and SSCL Rates</h2>

    <form method="POST" class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">VAT Percentage</label>
            <input type="number" step="0.01" name="vat_percentage" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">SSCL Percentage</label>
            <input type="number" step="0.01" name="sscl_percentage" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3 align-self-end">
            <button type="submit" name="submit_vat_sscl" class="btn btn-primary">Save Rates</button>
        </div>
    </form>
</div>

</body>
</html>
