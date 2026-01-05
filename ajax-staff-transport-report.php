    <?php
    session_start();
    include 'connections/connection.php';

    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    // Pagination setup
    $limit = 20;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Default to last month if no date filters
    $search = trim($_GET['search'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');

    if ($from === '' && $to === '') {
        $from = date("Y-m-01", strtotime("first day of last month"));
        $to = date("Y-m-t", strtotime("last day of last month"));
    }

    $whereKangaroo = " WHERE 1=1";
    $wherePickMe = " WHERE 1=1";

    if ($from !== '') {
        $safeFrom = $conn->real_escape_string($from);
        $whereKangaroo .= " AND date >= '$safeFrom'";
        $wherePickMe .= " AND STR_TO_DATE(pickup_time, '%W, %M %D %Y, %l:%i:%s %p') >= '{$safeFrom} 00:00:00'";
    }
    if ($to !== '') {
        $safeTo = $conn->real_escape_string($to);
        $whereKangaroo .= " AND date <= '$safeTo'";
        $wherePickMe .= " AND STR_TO_DATE(pickup_time, '%W, %M %D %Y, %l:%i:%s %p') <= '{$safeTo} 23:59:59'";
    }
    if ($search !== '') {
        $safeSearch = '%' . $conn->real_escape_string($search) . '%';
        $whereKangaroo .= " AND (voucher_no LIKE '$safeSearch' OR vehicle_no LIKE '$safeSearch')";
        $wherePickMe .= " AND (trip_id LIKE '$safeSearch' OR vehicle_number LIKE '$safeSearch')";
    }

    // Query to get total count for pagination
    $countSql = "
        SELECT COUNT(*) as total FROM (
            SELECT 1 FROM tbl_admin_kangaroo_transport $whereKangaroo
            UNION ALL
            SELECT 1 FROM tbl_admin_pickme_data $wherePickMe
        ) AS combined
    ";
    $countResult = $conn->query($countSql);
    $totalRecords = ($countResult && $row = $countResult->fetch_assoc()) ? (int)$row['total'] : 0;
    $totalPages = ceil($totalRecords / $limit);

    // Main data query
    $sqlKangaroo = "SELECT 
            'Kangaroo' AS source,
            date,
            voucher_no AS trip_id,
            vehicle_no AS vehicle_number,
            start_location AS `from`,
            end_location AS `to`,
            total_km AS km,
            additional_charges,
            total,
            NULL AS pickup_time
        FROM tbl_admin_kangaroo_transport $whereKangaroo";

    $sqlPickMe = "SELECT 
            'PickMe' AS source,
            NULL AS date,
            trip_id,
            vehicle_number,
            pickup_location AS `from`,
            drop_location AS `to`,
            REPLACE(trip_distance, ' km', '') AS km,
            '' AS additional_charges,
            total_fare AS total,
            pickup_time
        FROM tbl_admin_pickme_data $wherePickMe";

        $query = "SELECT * FROM (
            $sqlKangaroo
            UNION ALL
            $sqlPickMe
        ) AS combined
        ORDER BY COALESCE(
            date,
            STR_TO_DATE(pickup_time, '%W, %M %D %Y, %l:%i:%s %p')
        ) ASC
        LIMIT $limit OFFSET $offset";


    $result = $conn->query($query);

    if (!$result) {
        echo '<div class="alert alert-danger">Query Error: ' . $conn->error . '</div>';
        exit;
    }

    if ($result->num_rows === 0) {
        echo '<div class="alert alert-warning">No transport records found for the selected period.</div>';
        exit;
    }
    ?>

    <table class="table table-bordered table-hover table-sm align-middle">
    <thead class="table-light">
        <tr>
        <th>Date</th>
        <th>Voucher No</th>
        <th>Vehicle No</th>
        <th>From</th>
        <th>To</th>
        <th>Total KM</th>
        <th>Additional Charges</th>
        <th>Total</th>
        <th>Service</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td>
            <?php
                if ($row['source'] === 'PickMe') {
                    $ts = strtotime($row['pickup_time'] ?? '');
                    echo $ts ? date('Y-m-d', $ts) : '<span class="text-danger">Invalid</span>';
                } else {
                    echo htmlspecialchars($row['date']);
                }
            ?>
            </td>
            <td><?= htmlspecialchars($row['trip_id']) ?></td>
            <td><?= htmlspecialchars($row['vehicle_number']) ?></td>
            <td title="<?= htmlspecialchars($row['from']) ?>">
                <?php
                    $fromText = $row['from'];
                    echo htmlspecialchars(strlen($fromText) > 35 ? substr($fromText, 0, 35) . '...' : $fromText);
                ?>
                </td>
                <td title="<?= htmlspecialchars($row['to']) ?>">
                <?php
                    $toText = $row['to'];
                    echo htmlspecialchars(strlen($toText) > 35 ? substr($toText, 0, 35) . '...' : $toText);
                ?>
                </td>

            <td><?= number_format((float)$row['km'], 2) ?></td>
            <td><?= $row['source'] === 'Kangaroo' ? number_format((float)$row['additional_charges'], 2) : '' ?></td>
            <td><strong><?= number_format((float)$row['total'], 2) ?></strong></td>
            <td><?= $row['source'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
    </table>

    <!-- Pagination Controls -->
    <nav>
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
            <a class="page-link pagination-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
        </li>

        <?php endfor; ?>
    </ul>
    </nav>
