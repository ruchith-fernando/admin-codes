<?php
include 'connections/connection.php';  // Include the database connection

// Fetch all branch names and IDs
$branch_query = "SELECT branch_id, branch_name FROM tbl_admin_branch_information ORDER BY branch_name ASC";
$branch_result = mysqli_query($conn, $branch_query);

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $branch_name = $_POST['branch_name'];
    $units = $_POST['units'];
    $amount = $_POST['amount'];
    $payment_month = $_POST['payment_month'];

    // Retrieve branch ID based on branch name
    $branch_id_query = "SELECT branch_id FROM tbl_admin_branch_information WHERE branch_name = '$branch_name'";
    $branch_id_result = mysqli_query($conn, $branch_id_query);
    $branch_id_row = mysqli_fetch_assoc($branch_id_result);
    $branch_id = $branch_id_row['branch_id'];

    // Insert data into tbl_admin_electricity_cost
    $insert_query = "INSERT INTO tbl_admin_electricity_cost (branch_id, branch_name, units, amount, payment_month) 
                     VALUES ('$branch_id', '$branch_name', $units, $amount, '$payment_month')";

    if (mysqli_query($conn, $insert_query)) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                showSuccessAlert();
            });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electricity Consumption Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>
<div class="content font-size" id="contentArea">
    <div class="container">
        <div class="card shadow rounded-4">
            <div class="card-header bg-primary text-white text-center rounded-top-4">
                <h4 class="mb-0">Electricity Consumption Entry</h4>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label for="branch_name" class="form-label">Branch Name:</label>
                        <select class="form-select" id="branch_name" name="branch_name" required>
                            <option value="">Select Branch</option>
                            <?php
                            while ($row = mysqli_fetch_assoc($branch_result)) {
                                echo "<option value='" . $row['branch_name'] . "'>" . $row['branch_name'] . " (" . $row['branch_id'] . ")</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="payment_month" class="form-label">Payment Month:</label>
                        <select class="form-select" id="payment_month" name="payment_month" required>
                            <option value="">Select Month</option>
                            <?php
                            $months = [
                                "January", "February", "March", "April", "May", "June", 
                                "July", "August", "September", "October", "November", "December"
                            ];
                            $years = [2024, 2025];
                            foreach ($years as $year) {
                                foreach ($months as $month) {
                                    echo "<option value='" . $month . " " . $year . "'>" . $month . " " . $year . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="units" class="form-label">Units Consumed:</label>
                            <input type="number" class="form-control" id="units" name="units" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount (Rs.):</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-success btn-lg rounded-pill">
                            <i class="bi bi-save me-2"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script>
        function showSuccessAlert() {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Electricity cost saved successfully!',
                position: 'center',
                allowOutsideClick: false
            });
        }
    </script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script>
    function toggleMenu() {
        var sidebar = document.getElementById("sidebar");
        sidebar.classList.toggle("hidden");

        // Optionally toggle body overflow to prevent scrolling when sidebar is visible
        document.body.classList.toggle("no-scroll", sidebar.classList.contains("hidden"));
    }
</script>
</body>
</html>
