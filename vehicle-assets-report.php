<?php

include 'connections/connection.php';

$search = isset($_GET['search']) ? strtolower(trim($conn->real_escape_string($_GET['search']))) : '';

$vehicleType = isset($_GET['vehicle_type']) ? $conn->real_escape_string($_GET['vehicle_type']) : '';

$contractStatus = isset($_GET['contract_status']) ? $conn->real_escape_string($_GET['contract_status']) : '';

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

$limit = 10;

$offset = ($page - 1) * $limit;



$where = "1";

if (!empty($search)) {

    $where .= " AND (file_ref LIKE '%$search%' OR hris LIKE '%$search%' OR veh_no LIKE '%$search%' OR assigned_user LIKE '%$search%')";

}

if (!empty($vehicleType)) {

    $where .= " AND vehicle_type = '$vehicleType'";

}

if ($contractStatus === 'Signed') {

    $where .= " AND contract_file IS NOT NULL";

} elseif ($contractStatus === 'Sent') {

    $where .= " AND contract_file IS NULL AND (contract_sent_to IS NOT NULL OR contract_sent_where IS NOT NULL)";

} elseif ($contractStatus === 'Not Signed') {

    $where .= " AND contract_file IS NULL AND (contract_sent_to IS NULL OR contract_sent_to = '')";

}



// Count total records

$countSql = "SELECT COUNT(*) as total FROM tbl_admin_fixed_assets WHERE $where";

$totalResult = $conn->query($countSql);

$totalRow = $totalResult->fetch_assoc();

$totalRecords = $totalRow['total'];

$totalPages = ceil($totalRecords / $limit);



// Main query with limit

$sql = "SELECT * FROM tbl_admin_fixed_assets WHERE $where ORDER BY registration_date DESC LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);



// Export

if (isset($_GET['export']) && $_GET['export'] === 'excel') {

    header("Content-Type: application/vnd.ms-excel");

    header("Content-Disposition: attachment; filename=vehicle_assets_report.xls");

    echo "<table border='1'>";

    echo "<tr><th>File Ref</th><th>HRIS</th><th>Assigned User</th><th>Telephone Number</th><th>Vehicle No</th><th>Type</th><th>Make</th><th>Model</th><th>Division</th><th>Registration Date</th><th>Contract Status</th></tr>";

    $excelQuery = "SELECT * FROM tbl_admin_fixed_assets WHERE $where ORDER BY registration_date DESC";

    $excelResult = $conn->query($excelQuery);

    while ($row = $excelResult->fetch_assoc()) {

        $status = 'Not Signed';

        if (!empty($row['contract_file'])) {

            $status = 'Signed';

        } elseif (!empty($row['contract_sent_to']) || !empty($row['contract_sent_where'])) {

            $status = 'Sent for Signing';

        }

        echo "<tr>

                <td>{$row['file_ref']}</td>

                <td>{$row['hris']}</td>

                <td>{$row['assigned_user']}</td>

                <td>{$row['tp_no']}</td>

                <td>{$row['veh_no']}</td>

                <td>{$row['vehicle_type']}</td>

                <td>{$row['make']}</td>

                <td>{$row['model']}</td>

                <td>{$row['division']}</td>

                <td>{$row['registration_date']}</td>

                <td>{$status}</td>

              </tr>";

    }

    echo "</table>";

    exit;

}

?>

<div class="content font-size px-3">

    <div class="container-fluid">

        <div class="card shadow bg-white rounded p-4">

            <h5 class="mb-4 text-primary">Company Vehicle Assets Report</h5>

            <form class="row g-3 mb-4" method="GET">

                <div class="col-md-3">

                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search File Ref, HRIS, Vehicle No">

                </div>

                <div class="col-md-3">

                    <select name="vehicle_type" class="form-select">

                        <option value="">All Vehicle Types</option>

                        <option value="Car" <?= $vehicleType == 'Car' ? 'selected' : '' ?>>Car</option>

                        <option value="Van" <?= $vehicleType == 'Van' ? 'selected' : '' ?>>Van</option>

                        <option value="Bike" <?= $vehicleType == 'Bike' ? 'selected' : '' ?>>Bike</option>

                    </select>

                </div>

                <div class="col-md-3">

                    <select name="contract_status" class="form-select">

                        <option value="">All Contract Status</option>

                        <option value="Signed" <?= $contractStatus == 'Signed' ? 'selected' : '' ?>>Signed</option>

                        <option value="Sent" <?= $contractStatus == 'Sent' ? 'selected' : '' ?>>Sent for Signing</option>

                        <option value="Not Signed" <?= $contractStatus == 'Not Signed' ? 'selected' : '' ?>>Not Signed</option>

                    </select>

                </div>

                <div class="col-md-2">

                    <button type="submit" class="btn btn-primary w-100">Filter Report</button>

                </div>

                <div class="col-md-1">

                    <button type="button" class="btn btn-success w-100" onclick="downloadExcel()">Excel</button>

                    <iframe id="downloadFrame" style="display:none;"></iframe>

                </div>

            </form>



            <div class="table-responsive" style="max-width: 100%; overflow-x: auto;">
                <table class="table table-bordered">
                    <thead class="table-light">

                        <tr>

                            <th>File Ref</th>

                            <th>HRIS</th>

                            <th>Assigned User</th>

                            <th>Telephone Number</th>

                            <th>Vehicle No</th>

                            <th>Type</th>

                            <th>Make</th>

                            <th>Model</th>

                            <th>Division</th>

                            <th>Registration Date</th>

                            <th>Contract Status</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($result && $result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <?php

                                $status = 'Not Signed';

                                $badge = 'danger';

                                if (!empty($row['contract_file'])) {

                                    $status = 'Signed';

                                    $badge = 'success';

                                } elseif (!empty($row['contract_sent_to']) || !empty($row['contract_sent_where'])) {

                                    $status = 'Sent for Signing';

                                    $badge = 'warning';

                                }

                            ?>

                            <tr>

                                <td><?= htmlspecialchars($row['file_ref']) ?></td>

                                <td><?= htmlspecialchars($row['hris']) ?></td>

                                <td><?= htmlspecialchars($row['assigned_user']) ?></td>

                                <td><?= htmlspecialchars($row['tp_no']) ?></td>

                                <td><?= htmlspecialchars($row['veh_no']) ?></td>

                                <td><?= htmlspecialchars($row['vehicle_type']) ?></td>

                                <td><?= htmlspecialchars($row['make']) ?></td>

                                <td><?= htmlspecialchars($row['model']) ?></td>

                                <td><?= htmlspecialchars($row['division']) ?></td>

                                <td><?= htmlspecialchars($row['registration_date']) ?></td>

                                <td><span class="badge bg-<?= $badge ?>"><?= $status ?></span></td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr><td colspan="11" class="text-center">No records found.</td></tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>



            <!-- Pagination -->

            <?php if ($totalPages > 1): ?>

                <nav>

                    <ul class="pagination justify-content-end">

                        <!-- Previous Button -->

                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">

                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>">Previous</a>

                        </li>



                        <?php

                        // Calculate visible page range

                        $start = max(1, $page - 1);

                        $end = min($totalPages, $page + 1);



                        if ($page == 1) {

                            $end = min(3, $totalPages);

                        } elseif ($page == $totalPages) {

                            $start = max(1, $totalPages - 2);

                        }



                        for ($i = $start; $i <= $end; $i++): ?>

                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">

                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>

                            </li>

                        <?php endfor; ?>



                        <!-- Next Button -->

                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">

                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])) ?>">Next</a>

                        </li>

                    </ul>

                </nav>

                <?php endif; ?>

        </div>

    </div>

    <?php include 'footer.php'; ?>

</div>

<script>

function downloadExcel() {

    const params = new URLSearchParams(window.location.search);

    params.set('export', 'excel');

    document.getElementById('downloadFrame').src = '?' + params.toString();

}

</script>


