<?php
include 'connections/connection.php';
$showModal = false;
$modalMessage = '';

if (isset($_POST['apply_single_rule'])) {
    $from = isset($_POST['from']) ? floatval($_POST['from']) : null;
    $to = floatval($_POST['to']);
    $filter = trim($_POST['filter'] ?? '');

    $inserted = 0;

    if ($to > 0) {
        $sql = "SELECT hris_no, mobile_no FROM tbl_admin_mobile_issues WHERE 1";
        $params = [];
        $types = "";

        if (!empty($from)) {
            $sql .= " AND company_contribution = ?";
            $params[] = $from;
            $types .= "d";
        }

        if (!empty($filter)) {
            $sql .= " AND designation LIKE ?";
            $params[] = "%$filter%";
            $types .= "s";
        }

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $insert = $conn->prepare("INSERT INTO tbl_admin_hris_contributions 
                (hris_no, mobile_no, contribution_amount, effective_from) 
                VALUES (?, ?, ?, CURDATE())");
            $insert->bind_param("ssd", $row['hris_no'], $row['mobile_no'], $to);
            $insert->execute();
            $inserted++;
        }

        $showModal = true;
        $modalMessage = "$inserted contribution entries inserted for rule: " .
                        (!empty($from) ? "$from → " : "") . "$to" .
                        (!empty($filter) ? " (Filter: $filter)" : "");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Apply Contribution Rules</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">

<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Apply Contribution Rules (One Per Row)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Old Contribution (optional)</th>
                                            <th>New Contribution</th>
                                            <th>Optional Designation Filter</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                        <tr>
                                            <form method="POST">
                                                <td>
                                                    <input type="number" step="0.01" name="from" class="form-control" placeholder="e.g. 250">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="to" class="form-control" placeholder="e.g. 325" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="filter" class="form-control" placeholder="e.g. Branch (optional)">
                                                </td>
                                                <td>
                                                    <button type="submit" name="apply_single_rule" class="btn btn-success">Apply</button>
                                                </td>
                                            </form>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <?php if ($showModal): ?>
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-success">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= $modalMessage ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        window.addEventListener('load', () => {
            successModal.show();
        });
    </script>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
