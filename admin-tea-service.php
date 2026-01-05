<?php
session_start();
include 'connections/connection.php';

// --- Selected month to view data ---
$selected_month = filter_input(INPUT_POST, 'month_year', FILTER_SANITIZE_STRING) ?? date('Y-m');
$selected_month_year = date('F Y', strtotime($selected_month));

// --- Load data for selected month using prepared statement ---
$data_query = $conn->prepare("SELECT * FROM tbl_admin_tea_service WHERE month_year = ?");
$data_query->bind_param("s", $selected_month_year);
$data_query->execute();
$data_result = $data_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Tea Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">

<div class="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card p-4 shadow-sm mt-2">
            <h2 class="mb-4">Admin Tea Service Entry</h2>

            <!-- Tea Entry Form -->
            <form method="POST" action="submit-tea-service.php" class="row mb-4" id="teaForm">
                <div class="col-md-4 mb-3">
                    <label for="month_year" class="form-label">Select Month</label>
                    <input type="month" name="month_year" id="month_year" class="form-control" value="<?= htmlspecialchars($selected_month) ?>" required>
                </div>
                <div class="w-100"></div>

                <?php
                $items = [
                    'Milk Tea' => 50,
                    'Plain Tea' => 23,
                    'Plain Coffee' => 23,
                    'Milk Coffee' => 50,
                    'Green Tea' => 25,
                    'Tea Pot' => 85
                ];
                foreach ($items as $item_name => $price) {
                    $input_name = str_replace(' ', '_', strtolower($item_name));
                    ?>
                    <div class="col-md-2 mb-3">
                        <label class="form-label"><?= htmlspecialchars($item_name) ?> (LKR <?= $price ?>)</label>
                        <input type="number" min="0" name="<?= $input_name ?>" class="form-control" value="0">
                    </div>
                <?php } ?>

                <div class="w-100 mt-3"></div>

                <div class="col-md-2">
                    <button type="submit" name="submit_tea_service" class="btn btn-primary mt-4">Save Tea Service</button>
                </div>
                <div class="col-12 mt-3" id="responseMessage"></div>
            </form>

            <!-- Month Dropdown ABOVE entries -->
            <form method="POST" class="row mb-3">
                <div class="col-md-4">
                    <label for="view_month" class="form-label">Select Month to View Entries</label>
                    <select name="month_year" id="view_month" class="form-select" onchange="this.form.submit()">
                        <option value="" disabled <?= empty($_POST['month_year']) ? 'selected' : '' ?>>-- Select Month --</option>
                        <?php
                        $month_stmt = $conn->prepare("SELECT DISTINCT month_year FROM tbl_admin_tea_service ORDER BY STR_TO_DATE(month_year, '%M %Y') DESC");
                        $month_stmt->execute();
                        $month_result = $month_stmt->get_result();
                        while ($row = $month_result->fetch_assoc()) {
                            $month_value = date('Y-m', strtotime($row['month_year']));
                            $selected = ($month_value == $selected_month) ? 'selected' : '';
                            echo "<option value='$month_value' $selected>{$row['month_year']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </form>

            <h4>Entries for <?= htmlspecialchars($selected_month_year) ?></h4>
            <div class="table-responsive font-size">
                <table class="table table-bordered table-striped align-middle text-start">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Units</th>
                            <th>Unit Price</th>
                            <th>Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($data_result->num_rows > 0) {
                        $count = 1;
                        while ($row = $data_result->fetch_assoc()) {
                            echo "<tr>
                                    <td>" . $count++ . "</td>
                                    <td>" . htmlspecialchars($row['item_name']) . "</td>
                                    <td>" . intval($row['units']) . "</td>
                                    <td>" . number_format($row['unit_price'], 2) . "</td>
                                    <td>" . number_format($row['total_price'], 2) . "</td>
                                </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center'>No data found for this month.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" id="modalHeader">
        <h5 class="modal-title" id="feedbackModalLabel">Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalMessage"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
    $(document).ready(function () {
        $('#teaForm').submit(function (e) {
            e.preventDefault();

            $.ajax({
                url: 'submit-tea-service.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    let modalClass = '';
                    if (res.status === 'success') {
                        modalClass = 'bg-success text-white';
                    } else if (res.status === 'warning') {
                        modalClass = 'bg-warning';
                    } else if (res.status === 'duplicate') {
                        modalClass = 'bg-danger text-white';
                    } else {
                        modalClass = 'bg-danger text-white';
                    }

                    $('#modalHeader').removeClass().addClass('modal-header ' + modalClass);
                    $('#modalMessage').html(res.message);
                    var modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
                    modal.show();
                },
                error: function () {
                    $('#modalHeader').removeClass().addClass('modal-header bg-danger text-white');
                    $('#modalMessage').html('An unexpected error occurred.');
                    var modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
                    modal.show();
                }
            });
        });
    });
</script>

</body>
</html>
