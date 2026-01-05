<!-- vehicle-asset-table.php -->



<?php

include 'connections/connection.php';



$limit = 10;

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

$search = isset($_GET['search']) ? strtolower(trim($conn->real_escape_string($_GET['search']))) : '';

$offset = ($page - 1) * $limit;



// Build WHERE conditions

$where = "1";

if (!empty($search)) {

    $statusFilter = '';

    if (strpos($search, 'signed') !== false && strpos($search, 'not') === false) {

        $statusFilter = "(contract_file IS NOT NULL)";

    } elseif (strpos($search, 'sent') !== false) {

        $statusFilter = "(contract_file IS NULL AND (contract_sent_to != '' OR contract_sent_where != '' OR contract_sent_date IS NOT NULL))";

    } elseif (strpos($search, 'not') !== false && strpos($search, 'signed') !== false) {

        $statusFilter = "(contract_file IS NULL AND (contract_sent_to IS NULL OR contract_sent_to = ''))";

    }



    $textSearch = "(file_ref LIKE '%$search%' 

                OR hris LIKE '%$search%' 

                OR veh_no LIKE '%$search%' 

                OR assigned_user LIKE '%$search%')";



    if ($statusFilter) {

        $where = "($textSearch OR $statusFilter)";

    } else {

        $where = $textSearch;

    }

}



$sql = "SELECT * FROM tbl_admin_fixed_assets 

        WHERE $where 

        ORDER BY registration_date DESC 

        LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);



// Count total

$count_sql = "SELECT COUNT(*) as total FROM tbl_admin_fixed_assets WHERE $where";

$count_result = $conn->query($count_sql);

$total_rows = ($count_result && $count_result->num_rows > 0) ? $count_result->fetch_assoc()['total'] : 0;

$total_pages = ceil($total_rows / $limit);

?>



    <div class="table-responsive">

        <table class="table table-bordered table-striped align-middle text-start">

            <thead class="table-primary text-start">

                <tr>

                    <th>File Ref</th>

                    <th>HRIS</th>

                    <th>Vehicle No</th>

                    <th>Type</th>

                    <th>Assigned User</th>

                    <th>Contract Status</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()):

                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');



                        $contractStatus = 'Not Signed';

                        $badgeClass = 'bg-danger';

                        if (!empty($row['contract_file'])) {

                            $contractStatus = 'Signed';

                            $badgeClass = 'bg-success';

                        } elseif (!empty($row['contract_sent_to']) || !empty($row['contract_sent_where']) || !empty($row['contract_sent_date'])) {

                            $contractStatus = 'Sent for Signing';

                            $badgeClass = 'bg-warning text-dark';

                        }

                    ?>

                        <tr class="record-row" data-row="<?= $rowJson ?>">

                            <td><?= htmlspecialchars($row['file_ref']) ?></td>

                            <td><?= htmlspecialchars($row['hris']) ?></td>

                            <td><?= htmlspecialchars($row['veh_no']) ?></td>

                            <td><?= htmlspecialchars($row['vehicle_type']) ?></td>

                            <td><?= htmlspecialchars($row['assigned_user']) ?></td>

                            <td><span class="badge <?= $badgeClass ?> badge-status"><?= $contractStatus ?></span></td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr><td colspan="6" class="text-center">No results found.</td></tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

<?php if ($total_pages > 1): ?>

<nav>

    <ul class="pagination justify-content-end">



        <!-- Previous button -->

        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">

            <a class="page-link" href="#" data-page="<?= $page - 1 ?>">Previous</a>

        </li>



        <?php

        // Determine start and end of page range (3 pages max)

        $start = max(1, $page - 1);

        $end = min($total_pages, $page + 1);



        if ($page == 1) {

            $end = min(3, $total_pages);

        } elseif ($page == $total_pages) {

            $start = max(1, $total_pages - 2);

        }



        for ($i = $start; $i <= $end; $i++): ?>

            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">

                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>

            </li>

        <?php endfor; ?>



        <!-- Next button -->

        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">

            <a class="page-link" href="#" data-page="<?= $page + 1 ?>">Next</a>

        </li>

    </ul>

</nav>

<?php endif; ?>

