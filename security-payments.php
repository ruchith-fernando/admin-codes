<?php
include 'connections/connection.php';  // Include the connection

// Fetch all branch names and IDs
$branch_query = "SELECT branch_id, branch_name FROM tbl_admin_branch_information ORDER BY branch_name ASC";
$branch_result = mysqli_query($conn, $branch_query);

// Fetch the first security rates (sec_rate and sec_rate_vat) - Common for all branches
$rate_query = "SELECT sec_rate, sec_rate_vat FROM tbl_admin_security_rates WHERE id = 1 LIMIT 1";
$rate_result = mysqli_query($conn, $rate_query);

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $branch_name = $_POST['branch_name'];
    $payment_month = $_POST['payment_month'];
    $no_of_shifts = $_POST['no_of_shifts'];

    // Retrieve branch ID based on branch name
    $branch_id_query = "SELECT branch_id FROM tbl_admin_branch_information WHERE branch_name = '$branch_name'";
    $branch_id_result = mysqli_query($conn, $branch_id_query);
    $branch_id_row = mysqli_fetch_assoc($branch_id_result);
    $branch_id = $branch_id_row['branch_id'];

    // Fetch the correct security rate based on the branch selected
    if ($branch_name == 'Colombo') {
        // Fetch rates for Colombo (id = 2)
        $rate_query = "SELECT sec_rate, sec_rate_vat FROM tbl_admin_security_rates WHERE id = 2 LIMIT 1";
    } else {
        // Fetch rates for all other branches (id = 1)
        $rate_query = "SELECT sec_rate, sec_rate_vat FROM tbl_admin_security_rates WHERE id = 1 LIMIT 1";
    }
    
    $rate_result = mysqli_query($conn, $rate_query);

    if ($rate_row = mysqli_fetch_assoc($rate_result)) {
        $shift_rate = $rate_row['sec_rate'];
        $shift_rate_vat = $rate_row['sec_rate_vat'];
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No rates found in the rates table.',
                position: 'center',
                allowOutsideClick: false
            });
        </script>";
        exit;
    }

    // Calculate the total cost
    $shift_total_cost = ($shift_rate_vat) * $no_of_shifts;

    // Insert data into tbl_admin_security_cost
    $insert_query = "INSERT INTO tbl_admin_security_cost (branch_id, branch_name, payment_month, no_of_shifts, shift_rate, shift_rate_vat, shift_total_cost) 
                     VALUES ('$branch_id', '$branch_name', '$payment_month', $no_of_shifts, $shift_rate, $shift_rate_vat, $shift_total_cost)";

    if (mysqli_query($conn, $insert_query)) {
        // If the insert was successful, trigger the success message via JavaScript
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                showSuccessAlert(); // Call the external function
            });
        </script>";
    }
}

// Pagination
$limit = 5; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch the latest records ordered by id desc
$query = "SELECT * FROM tbl_admin_security_cost ORDER BY id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);

// Fetch the total number of records
$total_query = "SELECT COUNT(*) as total FROM tbl_admin_security_cost";
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Rates Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa; /* Subtle light grayish-blue */
            padding: 20px;
            font-size: 14px;
        }
        .form-container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .table-container {
            margin-top: 30px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2, h3 {
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: center;
        }
        table th {
            background-color: #f8f9fa;
        }
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .btn-danger {
            padding: 5px 10px;
        }
    </style>

</head>
<body>

    <div class="form-container">
        <h2 class="text-center mb-4">Monthly Security Charges</h2>

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
            <!-- Number of Shifts -->
            <div class="mb-3">
                <label for="no_of_shifts" class="form-label">Number of Shifts:</label>
                <input type="number" class="form-control" id="no_of_shifts" name="no_of_shifts" min="1" required>
            </div>

            <!-- Save Button -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>

    <!-- Table to show records -->
    <div class="table-container mt-4">
        <div class="table-responsive">
            <h3>Security Charges Records</h3>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Branch Code</th>
                        <th>Branch Name</th>
                        <th>Payment Month</th>
                        <th>No of Shifts</th>
                        <th>Rate</th>
                        <th>Total Cost</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td><?php echo $row['branch_id']; ?></td>
                            <td><?php echo $row['branch_name']; ?></td>
                            <td><?php echo $row['payment_month']; ?></td>
                            <td><?php echo $row['no_of_shifts']; ?></td>
                            <td><?php echo number_format($row['shift_rate_vat'], 2); ?></td>
                            <td><?php echo number_format($row['shift_total_cost'], 2); ?></td>
                            <td>
                                <button class="btn btn-danger" onclick="alert('Still in development')">Delete</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?php if ($page == 1) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php } ?>
                <li class="page-item <?php if ($page == $total_pages) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <!-- Custom JS -->
    <script>
        function showSuccessAlert() {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Security charges data saved successfully!',
                position: 'center',
                allowOutsideClick: false
            });
        }
    </script>

</body>
</html>
