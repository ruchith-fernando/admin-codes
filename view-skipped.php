<?php
session_start();
include 'connections/connection.php';

$month = $_GET['month'] ?? '';
$file = $_GET['file'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Skipped Entries Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">

<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded mt-4 p-4">
            <h5 class="mb-4 text-primary fw-bold">Skipped Entries for <?= htmlspecialchars($month) ?> (<?= htmlspecialchars($file) ?>)</h5>

            <?php
            $stmt = $conn->prepare("SELECT mobile_number, reason, uploaded_at 
                                    FROM tbl_admin_mobile_bill_skipped 
                                    WHERE update_date = ? AND file_name = ?");
            $stmt->bind_param("ss", $month, $file);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo "<div class='table-responsive'>
                        <table class='table table-bordered table-sm'>
                            <thead class='table-light'>
                                <tr>
                                    <th>#</th>
                                    <th>Mobile Number</th>
                                    <th>Reason</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>";

                $i = 1;
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                            <td>{$i}</td>
                            <td>{$row['mobile_number']}</td>
                            <td>{$row['reason']}</td>
                            <td>{$row['uploaded_at']}</td>
                          </tr>";
                    $i++;
                }

                echo "  </tbody>
                        </table>
                      </div>";
            } else {
                echo "<div class='alert alert-warning'>No skipped records found for this file and month.</div>";
            }

            $stmt->close();
            $conn->close();
            ?>
        </div>
    </div>
</div>
</body>
</html>
