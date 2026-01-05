<?php
session_start();
include 'connections/connection.php';

// --- Access control: Only admin allowed ---
if (!isset($_SESSION['username']) || $_SESSION['user_level'] !== 'admin') {
    header("Location: access-denied.php");
    exit;
}

// if (!isset($_SESSION['username']) || !in_array($_SESSION['user_level'], $allowed_levels)) {
//     header("Location: access-denied.php");
//     exit;
// }

// --- Form submission to close request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_request_id'])) {
    $request_id = intval($_POST['close_request_id']);
    $new_mobile_no = mysqli_real_escape_string($conn, $_POST['new_mobile_no']);
    $new_imei = mysqli_real_escape_string($conn, $_POST['new_imei']);

    // Get HRIS from the request
    $hris_sql = "SELECT hris FROM tbl_admin_sim_request WHERE id = $request_id";
    $hris_result = mysqli_query($conn, $hris_sql);
    $hris_row = mysqli_fetch_assoc($hris_result);
    $hris = $hris_row['hris'];

    // Update employee full details (if mobile_no or IMEI provided)
    if (!empty($new_mobile_no) || !empty($new_imei)) {
        $update_employee_sql = "UPDATE tbl_admin_employee_full_details SET ";

        $updates = [];
        if (!empty($new_mobile_no)) {
            $updates[] = "mobile_no = '$new_mobile_no'";
        }
        if (!empty($new_imei)) {
            $updates[] = "imei_number = '$new_imei'";
        }

        $update_employee_sql .= implode(", ", $updates);
        $update_employee_sql .= " WHERE hris_no = '$hris'";

        mysqli_query($conn, $update_employee_sql);
    }

    // Update the request close_status
    $update_request_sql = "UPDATE tbl_admin_sim_request SET close_status = 'Closed' WHERE id = $request_id";
    if (mysqli_query($conn, $update_request_sql)) {
        $message = "<div class='alert alert-success'>Request ID $request_id closed successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to close request ID $request_id.</div>";
    }
}

// --- Fetch requests ready for closing ---
$query = "SELECT id, hris, name, request_type, request_division, designation, branch_division, 
                 recommended_by, approved_by, accepted_by 
          FROM tbl_admin_sim_request 
          WHERE 
            (issue_status = 'Issued') 
            AND (close_status = 'Open' OR close_status IS NULL)";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Close SIM/Mobile/Transfer Requests</title>
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
<body class="bg-light">
    <button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>
    <div class="sidebar" id="sidebar">
        <?php include 'side-menu.php'; ?>
    </div>

    <div class="content font-size" id="contentArea">
        <div class="container">
            <div class="card shadow bg-white rounded p-4">
                <h2 class="mb-4">Close SIM/Mobile/Transfer Requests</h2>

                <?php if (isset($message)) { echo $message; } ?>

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
                                <th>Accepted By</th>
                                <th style="min-width: 350px;">Close Request</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                            <tr>
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
                                <td>
                                    <form method="post" class="d-flex flex-wrap gap-2">
                                        <input type="hidden" name="close_request_id" value="<?php echo $row['id']; ?>">
                                        <input type="text" name="new_mobile_no" class="form-control" placeholder="New Mobile No" style="min-width: 150px;">
                                        <input type="text" name="new_imei" class="form-control" placeholder="New IMEI" style="min-width: 150px;">
                                        <button type="submit" class="btn btn-danger btn-sm">Close Request</button>
                                    </form>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <div class="alert alert-info">No requests pending closing.</div>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
    function toggleMenu() {
        document.getElementById("sidebar").classList.toggle("active");
    }
    </script>
</body>
</html>
