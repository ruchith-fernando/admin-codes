<?php
session_start();
include 'connections/connection.php';

// --- Access control: Only issuer allowed ---
if (!isset($_SESSION['username']) || $_SESSION['user_level'] !== 'issuer') {
    echo "<div class='alert alert-danger'>Access denied. You do not have permission to access this page.</div>";
    exit;
}

// --- Form submission to mark as issued ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_request_id'])) {
    $request_id = intval($_POST['issue_request_id']);

    $update_sql = "UPDATE tbl_admin_sim_request 
                   SET issue_status = 'Issued' 
                   WHERE id = $request_id";

    if (mysqli_query($conn, $update_sql)) {
        $message = "<div class='alert alert-success'>Request ID $request_id marked as Issued successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to update request ID $request_id.</div>";
    }
}

// --- Fetch requests ready to be issued ---
$query = "SELECT id, hris, name, request_type, request_division, designation, branch_division, 
                 recommended_by, approved_by, accepted_by 
          FROM tbl_admin_sim_request 
          WHERE 
            (recommended_by IS NOT NULL AND recommended_by <> '') 
            AND (approved_by IS NOT NULL AND approved_by <> '') 
            AND (accepted_by IS NOT NULL AND accepted_by <> '') 
            AND (issue_status = 'Pending' OR issue_status IS NULL)";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issue SIM/Mobile/Transfer Requests</title>
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
                <h2 class="mb-4">Issue SIM/Mobile/Transfer Requests</h2>

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
                                <th style="min-width: 150px;">Issue</th>
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
                                    <form method="post" class="d-flex">
                                        <input type="hidden" name="issue_request_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Mark as Issued</button>
                                    </form>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <div class="alert alert-info">No requests pending issue.</div>
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
