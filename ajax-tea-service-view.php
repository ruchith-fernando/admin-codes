<?php
include 'connections/connection.php';

// Get selected month from POST
$selected_month = $_POST['view_month'] ?? '';
$selected_month_year = $selected_month ? date('F Y', strtotime($selected_month)) : '';

// Fetch data for selected month
$data_result = null;
if ($selected_month_year) {
    $escaped_month = mysqli_real_escape_string($conn, $selected_month_year);
    $data_result = mysqli_query($conn, "SELECT * FROM tbl_admin_tea_service WHERE month_year = '$escaped_month'");
}

// Fetch all available months
$month_options = mysqli_query($conn, "SELECT DISTINCT month_year FROM tbl_admin_tea_service ORDER BY STR_TO_DATE(month_year, '%M %Y') DESC");
?>

<!-- Month Selector -->
<form id="viewForm" class="row mb-3">
    <div class="col-md-4">
        <label>Select Month (to View)</label>
        <select name="view_month" class="form-select" id="viewMonth">
            <option value="">-- Select Month --</option>
            <?php
            while ($row = mysqli_fetch_assoc($month_options)) {
                $month_val = date('Y-m', strtotime($row['month_year']));
                $selected = ($month_val == $selected_month) ? 'selected' : '';
                echo "<option value='$month_val' $selected>{$row['month_year']}</option>";
            }
            ?>
        </select>
    </div>
</form>

<!-- Data Table -->
<div id="entryResults">
    <?php if ($selected_month_year): ?>
        <h5 class="mb-4 text-primary">Entries for <?= htmlspecialchars($selected_month_year) ?></h5>
        <table class="table table-bordered mt-2">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Units</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    <th>SSCL</th>
                    <th>VAT</th>
                    <th>Grand Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                $sum_total = 0;
                $sum_sscl = 0;
                $sum_vat = 0;
                $sum_grand = 0;

                if ($data_result && mysqli_num_rows($data_result) > 0) {
                    while ($row = mysqli_fetch_assoc($data_result)) {
                        $sum_total += (float)$row['total_price'];
                        $sum_sscl += (float)$row['sscl_amount'];
                        $sum_vat += (float)$row['vat_amount'];
                        $sum_grand += (float)$row['grand_total'];

                        echo "<tr>
                                <td>" . $i++ . "</td>
                                <td>" . htmlspecialchars($row['item_name']) . "</td>
                                <td>" . (int)$row['units'] . "</td>
                                <td>" . number_format($row['unit_price'], 2) . "</td>
                                <td>" . number_format($row['total_price'], 2) . "</td>
                                <td>" . number_format($row['sscl_amount'], 2) . "</td>
                                <td>" . number_format($row['vat_amount'], 2) . "</td>
                                <td>" . number_format($row['grand_total'], 2) . "</td>
                            </tr>";
                    }

                    // Totals row
                    echo "<tr class='table-secondary fw-bold'>
                            <td colspan='4' class='text-end'>Total:</td>
                            <td>" . number_format($sum_total, 2) . "</td>
                            <td>" . number_format($sum_sscl, 2) . "</td>
                            <td>" . number_format($sum_vat, 2) . "</td>
                            <td>" . number_format($sum_grand, 2) . "</td>
                        </tr>";
                } else {
                    echo "<tr><td colspan='8' class='text-center'>No data found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info text-center">
            Please select a month to view records.
        </div>
    <?php endif; ?>
</div>

<script>
// Reload table when month changes
$('#viewMonth').on('change', function () {
    const selected = $(this).val();
    $.post('ajax-tea-service-view.php', { view_month: selected }, function (res) {
        $('#teaServiceContent').html(res);
    });
});
</script>
