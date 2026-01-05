<?php
// Start the session to access user authentication info
session_start();

// Include DB connection
include 'connections/connection.php';

// Check if the user is logged in and has the 'acceptor' role
// If not, show an error and stop further execution
if (!isset($_SESSION['username']) || 
    ($_SESSION['user_level'] !== 'acceptor' && $_SESSION['user_level'] !== 'super-admin')) {
    
    echo "<div class='alert alert-danger'>Access denied. You do not have permission to access this page.</div>";
    return;
}

// If the form was submitted (someone clicked "Accept")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_request_id'])) {
    $request_id = intval($_POST['accept_request_id']); // Convert request ID to integer (safety check)
    $accepted_by = trim($_POST['accepted_by']); // Sanitize input

    // Use a prepared statement to safely update the database with the acceptor's name
    $stmt = $conn->prepare("UPDATE tbl_admin_sim_request SET accepted_by = ? WHERE id = ?");
    $stmt->bind_param("si", $accepted_by, $request_id);

    // Show success or failure message depending on whether the update worked
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Request ID $request_id accepted successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to accept request ID $request_id.</div>";
    }

    $stmt->close(); // Always good practice to close the statement
}

// Now fetch all requests that are ready to be accepted
// Only show requests that were already recommended and approved but not yet accepted
$query = "
    SELECT id, hris, name, request_type, request_division, designation, branch_division, recommended_by, approved_by 
    FROM tbl_admin_sim_request 
    WHERE 
        (recommended_by IS NOT NULL AND recommended_by <> '') 
        AND (approved_by IS NOT NULL AND approved_by <> '') 
        AND (accepted_by IS NULL OR accepted_by = '')
";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accept SIM/Mobile/Transfer Requests</title>

    <!-- Load Bootstrap and other libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">

    <!-- Minor styling tweaks -->
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
<body class="bg-light">

    <!-- Sidebar toggle button -->
    <button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>

    <!-- Sidebar menu -->
    <div class="sidebar" id="sidebar">
        <?php include 'side-menu.php'; ?>
    </div>

    <!-- Main content area -->
    <div class="content font-size" id="contentArea">
        <div class="container">
            <div class="card shadow bg-white rounded p-4">
                <h2 class="mb-4">Accept SIM/Mobile/Transfer Requests</h2>

                <!-- Display success or error message -->
                <?php if (isset($message)) echo $message; ?>

                <!-- If there are pending requests, display them in a table -->
                <?php if (mysqli_num_rows($result) > 0) { ?>
                    <table class="table table-bordered table-striped table-hover w-100">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>HRIS</th>
                                <th>Name</th>
                                <th>Request Type</th>
                                <th>Request Division</th>
                                <th>Designation</th>
                                <th>Branch/Division</th>
                                <th>Recommended By</th>
                                <th>Approved By</th>
                                <th style="min-width: 250px;">Accept</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loop through each row and display it -->
                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['hris']); ?></td>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= htmlspecialchars($row['request_type']); ?></td>
                                <td><?= htmlspecialchars($row['request_division']); ?></td>
                                <td><?= htmlspecialchars($row['designation']); ?></td>
                                <td><?= htmlspecialchars($row['branch_division']); ?></td>
                                <td><?= htmlspecialchars($row['recommended_by']); ?></td>
                                <td><?= htmlspecialchars($row['approved_by']); ?></td>
                                <td>
                                    <!-- Form to accept the request -->
                                    <form method="post" class="d-flex">
                                        <input type="hidden" name="accept_request_id" value="<?= $row['id']; ?>">
                                        <input type="text" name="accepted_by" class="form-control me-2" placeholder="Your Name/HRIS" style="min-width:180px;" required>
                                        <button type="submit" class="btn btn-warning btn-sm">Accept</button>
                                    </form>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <!-- If there are no records to accept -->
                    <div class="alert alert-info">No requests pending acceptance.</div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Sidebar toggle script -->
    <script>
    function toggleMenu() {
        document.getElementById("sidebar").classList.toggle("active");
    }
    </script>
</body>
</html>
