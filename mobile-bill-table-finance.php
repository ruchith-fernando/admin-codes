<!-- mobile-bill-table-finance.php -->
<?php

include 'connections/connection.php';



$limit = 10;

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

$update_date = isset($_GET['update_date']) ? $conn->real_escape_string($_GET['update_date']) : ''; // Get selected date from the dropdown



$offset = ($page - 1) * $limit;



// Prepare the WHERE clause based on the selected date

$where = '';

if ($update_date) {

    $where = "WHERE t1.Update_date = '$update_date'";

}



$sql = "SELECT t1.*, 

       (
            SELECT c.contribution_amount
            FROM tbl_admin_hris_contributions c
            WHERE c.hris_no = t2.hris_no
            AND c.mobile_no = t1.MOBILE_Number
            AND c.effective_from <= STR_TO_DATE(CONCAT('01-', t1.Update_date), '%d-%M-%Y')
            ORDER BY c.effective_from DESC
            LIMIT 1
        ) AS company_contribution, 

       t2.voice_data, 

       t2.name_of_employee, 

       t2.designation, 

       t2.company_hierarchy, 

       t2.nic_no, 

       t2.hris_no, 

       t1.Update_date, 

       CONCAT(t2.name_of_employee, ' - ', t2.display_name) AS full_display_name

        FROM tbl_admin_mobile_bill_data t1 

        LEFT JOIN tbl_admin_mobile_issues t2 

        ON t1.MOBILE_Number = t2.mobile_no 

        $where 

        LIMIT $limit OFFSET $offset";



$result = $conn->query($sql);



// Count total rows for pagination

$count_sql = "SELECT COUNT(*) as total 

              FROM tbl_admin_mobile_bill_data t1 

              LEFT JOIN tbl_admin_mobile_issues t2 

              ON t1.MOBILE_Number = t2.mobile_no 

              $where";

$count_result = $conn->query($count_sql);

$total_rows = $count_result->fetch_assoc()['total'];

$total_pages = ceil($total_rows / $limit);

?>



<div class="table-responsive font-size">

    <table class="table table-bordered table-striped align-middle text-start">

        <thead class="table-primary text-start">

            <tr>

                <th style="min-width: 100px;">Billing Month</th>

                <th>HRIS</th>

                <th style="min-width: 300px;">Employee Name</th>

                <th style="min-width: 200px;">NIC</th>

                <th style="min-width: 300px;">Designation</th>

                <th style="min-width: 300px;">Hierarchy</th>

                <th style="min-width: 150px;">Mobile Number</th>

                <th style="min-width: 200px;">Voice Data</th>

                <th style="min-width: 100px;">Roaming</th>

                <th style="min-width: 100px;">VAS</th>

                <th style="min-width: 100px;">Add to Bill</th>

                <th style="min-width: 100px;">IDD</th>

                <th style="min-width: 100px;">Bill Charges</th>

                <th style="min-width: 100px;">Total Payable</th>

            </tr>

        </thead>

        <tbody>

        <?php while($row = $result->fetch_assoc()) { ?>

            <tr class="table-row"

                data-mobile="<?php echo htmlspecialchars($row['MOBILE_Number']); ?>"

                data-employee="<?php echo htmlspecialchars($row['full_display_name']); ?>"

                data-nic="<?php echo htmlspecialchars($row['nic_no']); ?>"

                data-designation="<?php echo htmlspecialchars($row['designation']); ?>"

                data-hierarchy="<?php echo htmlspecialchars($row['company_hierarchy']); ?>"

                data-hris="<?php echo htmlspecialchars($row['hris_no']); ?>"

                data-total="<?php echo number_format($row['TOTAL_AMOUNT_PAYABLE'], 2); ?>"

                data-roaming="<?php echo number_format($row['ROAMING'], 2); ?>"

                data-vas="<?php echo number_format($row['VALUE_ADDED_SERVICES'], 2); ?>"

                data-addtobill="<?php echo number_format($row['ADD_TO_BILL'], 2); ?>"

                data-date="<?php echo htmlspecialchars($row['Update_date']); ?>"

                style="cursor:pointer;">

                <td><?php echo $row['Update_date']; ?></td>

                <td><?php echo $row['hris_no']; ?></td>

                <td><?php echo $row['full_display_name']; ?></td>

                <td><?php echo $row['nic_no']; ?></td>

                <td><?php echo $row['designation']; ?></td>

                <td><?php echo $row['company_hierarchy']; ?></td>

                <td><?php echo htmlspecialchars($row['MOBILE_Number']); ?></td>

                <td><?php echo $row['voice_data']; ?></td>

                <td><?php echo number_format($row['ROAMING'], 2); ?></td>

                <td><?php echo number_format($row['VALUE_ADDED_SERVICES'], 2); ?></td>

                <td><?php echo number_format($row['ADD_TO_BILL'], 2); ?></td>

                <td><?php echo number_format($row['IDD'], 2); ?></td>

                <td><?php echo number_format($row['CHARGES_FOR_BILL_PERIOD'], 2); ?></td>

                <td><?php echo number_format($row['TOTAL_AMOUNT_PAYABLE'], 2); ?></td>

            </tr>

        <?php } ?>

        </tbody>

    </table>



    <!-- Pagination -->

    <nav>

        <ul class="pagination justify-content-end">

            <?php 

            // Calculate previous and next page numbers

            $prev = ($page > 1) ? $page - 1 : 1;

            $next = ($page < $total_pages) ? $page + 1 : $total_pages;



            // First and Previous

            if ($page > 1) {

                echo '<li class="page-item"><a href="?page=1&update_date=' . urlencode($update_date) . '" class="page-link">« First</a></li>';

                echo '<li class="page-item"><a href="?page=' . $prev . '&update_date=' . urlencode($update_date) . '" class="page-link">< Prev</a></li>';

            }



            // Display up to 5 pages around the current page

            $start = max(1, $page - 2);

            $end = min($total_pages, $page + 2);



            for ($i = $start; $i <= $end; $i++) {

                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';

                echo '<a href="?page=' . $i . '&update_date=' . urlencode($update_date) . '" class="page-link">' . $i . '</a>';

                echo '</li>';

            }



            // Next and Last

            if ($page < $total_pages) {

                echo '<li class="page-item"><a href="?page=' . $next . '&update_date=' . urlencode($update_date) . '" class="page-link">Next ></a></li>';

                echo '<li class="page-item"><a href="?page=' . $total_pages . '&update_date=' . urlencode($update_date) . '" class="page-link">Last »</a></li>';

            }

            ?>

        </ul>

    </nav>

</div>

