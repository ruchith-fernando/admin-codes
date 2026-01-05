<!-- view-employee-upload-log.php -->

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
    <title>Employee Upload Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">

<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded mt-4 p-4">
            <h5 class="mb-4 text-primary fw-bold">
                Upload Summary for <?= htmlspecialchars($month) ?> (<?= htmlspecialchars($file) ?>)
            </h5>

            <?php
            $stmt = $conn->prepare("SELECT hris, action_type, uploaded_at 
                                    FROM tbl_admin_employee_upload_log 
                                    WHERE upload_month = ? AND file_name = ?");
            $stmt->bind_param("ss", $month, $file);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo "<div class='table-responsive'>
                        <table id='uploadLogTable' class='table table-bordered table-striped table-sm'>
                            <thead class='table-light'>
                                <tr>
                                    <th>#</th>
                                    <th>HRIS</th>
                                    <th>Action</th>
                                    <th>Timestamp</th>
                                </tr>
                                <tr class='filters'>
                                    <th></th>
                                    <th></th>
                                    <th><input type='text' class='form-control form-control-sm' placeholder='Search Action'></th>
                                    <th><input type='text' class='form-control form-control-sm' placeholder='Search Timestamp'></th>
                                </tr>
                            </thead>
                            <tbody>";

                $i = 1;
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                            <td>{$i}</td>
                            <td>{$row['hris']}</td>
                            <td>{$row['action_type']}</td>
                            <td>{$row['uploaded_at']}</td>
                          </tr>";
                    $i++;
                }

                echo "</tbody></table></div>";
            } else {
                echo "<div class='alert alert-warning'>No upload log found for this file and month.</div>";
            }

            $stmt->close();
            $conn->close();
            ?>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    const table = $('#uploadLogTable').DataTable({
        orderCellsTop: true,
        fixedHeader: true,
        pageLength: 25
    });

    // Apply keystroke search to HRIS, Action, and Timestamp columns
    $('#uploadLogTable thead tr.filters th').each(function (index) {
        // 🔹 Added index === 1 (HRIS column)
        if (index === 1 || index === 2 || index === 3) {
            $('input', this).on('keyup change', function () {
                if (table.column(index).search() !== this.value) {
                    table.column(index).search(this.value).draw();
                }
            });
        }
    });
});
</script>


</body>
</html>
