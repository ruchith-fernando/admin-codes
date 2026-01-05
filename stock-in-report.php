<?php
// Filename: stock-in-report.php
include 'connections/connection.php';

$month = $_GET['month'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$item_code = $_GET['item_code'] ?? '';

$query = "SELECT si.*, pm.item_description 
          FROM tbl_admin_stationary_stock_in si
          LEFT JOIN tbl_admin_print_stationary_master pm ON si.item_code = pm.item_code
          WHERE 1";

$params = [];
if ($month) {
    $query .= " AND DATE_FORMAT(si.received_date, '%Y-%m') = ?";
    $params[] = $month;
}
if ($date_from && $date_to) {
    $query .= " AND si.received_date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}
if ($item_code) {
    $query .= " AND si.item_code = ?";
    $params[] = $item_code;
}

$stmt = $conn->prepare($query);
if ($params) {
    $types = str_repeat("s", count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock In Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
    .select2-container--default .select2-selection--single {
        height: 38px !important;
        padding: 0 12px !important;
        font-size: 1rem;
        line-height: 38px !important;
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
        background-color: #fff !important;
        display: flex;
        align-items: center;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px !important;
        top: 0 !important;
        right: 10px !important;
    }

    .select2-container {
        width: 100% !important;
    }
    </style>
</head>
<body class="bg-light">
<div class="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Stock In Report</h5>
            <form method="get" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="text" name="from" id ="from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="text" name="to" id = "to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Item Code</label>
                    <select name="item_code" id="item_code" class="form-select"></select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Filter</button>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Remaining</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['item_code'] ?></td>
                            <td><?= $row['item_description'] ?></td>
                            <td><?= $row['quantity'] ?></td>
                            <td><?= number_format($row['unit_price'], 2) ?></td>
                            <td><?= $row['remaining_quantity'] ?></td>
                            <td><?= $row['received_date'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#item_code').select2({
        placeholder: 'Search Item Code',
        ajax: {
            url: 'fetch-items.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { term: params.term };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        },
        minimumInputLength: 1,
        width: '100%'
    });
    $('#from').datepicker({
        format: 'yyyy-mm-dd',
        endDate: new Date(),
        autoclose: true,
        todayHighlight: true
    }).datepicker('setDate', new Date());
    $('#to').datepicker({
        format: 'yyyy-mm-dd',
        endDate: new Date(),
        autoclose: true,
        todayHighlight: true
    }).datepicker('setDate', new Date());
});
</script>
</body>
</html>
