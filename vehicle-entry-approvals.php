<?php 
date_default_timezone_set('Asia/Colombo');

// Enable PHP error logging
ini_set('log_errors', 1);
ini_set('error_log', 'php-error.log');
error_reporting(E_ALL);

require_once 'connections/connection.php';

?>

<div class="card shadow p-4">
    <h5 class="mb-4 text-primary">Approve Paid Vehicle Bills</h5>
    <div id="approvalResults"></div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>Type</th>
                    <th>Vehicle</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Driver</th>
                    <th>Bill</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $queries = [
                    'maintenance' => "SELECT id, vehicle_number, maintenance_type AS type, purchase_date AS date, price AS amount, driver_name AS driver, bill_upload AS bill FROM tbl_admin_vehicle_maintenance WHERE status = 'pending'",
                    'service'     => "SELECT id, vehicle_number, 'service' AS type, service_date AS date, amount, driver_name AS driver, bill_upload AS bill FROM tbl_admin_vehicle_service WHERE status = 'pending'",
                    'license'     => "SELECT id, vehicle_number, 'license' AS type, revenue_license_date AS date, insurance_amount AS amount, person_handled AS driver, '' AS bill FROM tbl_admin_vehicle_licensing_insurance WHERE status = 'pending'",
                ];

                foreach ($queries as $entryType => $sql) {
                    $res = $conn->query($sql);
                    while ($row = $res->fetch_assoc()) {
                        echo "<tr>
                            <td>" . ucfirst($entryType) . "</td>
                            <td>{$row['vehicle_number']}</td>
                            <td>{$row['date']}</td>
                            <td>" . number_format($row['amount'], 2) . "</td>
                            <td>{$row['driver']}</td>
                            <td>";
                        echo $row['bill'] ? "<a href='{$row['bill']}' target='_blank'>View</a>" : "-";
                        echo "</td>
                            <td>
                                <button class='btn btn-success btn-sm approve-btn' data-id='{$row['id']}' data-type='{$entryType}'>Approve & Generate Advice</button>
                            </td>
                        </tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function () {
    $('.approve-btn').on('click', function () {
        const id = $(this).data('id');
        const type = $(this).data('type');

        $.post('ajax-approve-vehicle-entry.php', {
            entry_id: id,
            entry_type: type
        }, function (response) {
            $('#approvalResults').html(response);
            setTimeout(() => loadPage('vehicle-entry-approvals.php'), 1000);
        });
    });
});
</script>
