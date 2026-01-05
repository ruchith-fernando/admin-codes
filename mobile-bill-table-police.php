<?php
include 'connections/connection.php';

$limit = 25;  // Define the number of results per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Ensure $page is a positive integer

// Ensure the page is valid (page cannot be less than 1)
if ($page < 1) {
    $page = 1;
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : ''; // Get the search term

$offset = ($page - 1) * $limit; // Calculate the offset based on the current page

// Add the search condition to the WHERE clause
$where = "WHERE (
            t1.MOBILE_Number LIKE '%$search%' 
            OR t2.name_of_employee LIKE '%$search%' 
            OR t1.Update_date LIKE '%$search%' 
            OR t2.nic_no LIKE '%$search%' 
            OR t2.hris_no LIKE '%$search%'
          )
          AND (
            LOWER(t2.hris_no) LIKE '%police%' 
            OR LOWER(t2.name_of_employee) LIKE '%police%' 
            OR LOWER(t2.voice_data) LIKE '%police%'
          )";

$sql = "SELECT t1.*, 
        t2.company_contribution, 
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
        LIMIT $limit OFFSET $offset";  // Apply LIMIT and OFFSET

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
                <th style="min-width: 300px;">HRIS</th>
                <th style="min-width: 300px;">Employee Name</th>
                <th>NIC</th>
                <th style="min-width: 75px;">Designation</th>
                <th style="min-width: 75px;">Hierarchy</th>
                <th style="min-width: 200px;">Mobile Number</th>
                <th style="min-width: 300px;">Voice Data</th>
                <th style="min-width: 100px;">Roaming</th>
                <th style="min-width: 100px;">VAS</th>
                <th style="min-width: 100px;">Add to Bill</th>
                <th style="min-width: 100px;">IDD</th>
                <th style="min-width: 100px;">Bill Charges</th>
                <th style="min-width: 100px;">Total Payable</th>
                <th style="min-width: 110px;">Company Contribution</th>
                <th style="min-width: 160px;">Salary Deduction</th> 
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()) { 

            // Salary Deduction Calculation
            $X = $row['TOTAL_AMOUNT_PAYABLE'] - $row['company_contribution'];
            $Y = $row['ROAMING'] + $row['VALUE_ADDED_SERVICES'] + $row['ADD_TO_BILL'];

            if ($X < $Y) {
                $salary_deduction = $Y;
            } else {
                $salary_deduction = $X;
            }
            ?>
            <tr class="table-row"
                data-mobile="<?php echo htmlspecialchars($row['MOBILE_Number']); ?>"
                data-employee="<?php echo htmlspecialchars($row['full_display_name']); ?>"
                data-nic="<?php echo htmlspecialchars($row['nic_no']); ?>"
                data-designation="<?php echo htmlspecialchars($row['designation']); ?>"
                data-hierarchy="<?php echo htmlspecialchars($row['company_hierarchy']); ?>"
                data-hris="<?php echo htmlspecialchars($row['hris_no']); ?>"
                data-total="<?php echo number_format($row['TOTAL_AMOUNT_PAYABLE'], 2); ?>"
                data-contribution="<?php echo number_format($row['company_contribution'], 2); ?>"
                data-deduction="<?php echo number_format($salary_deduction, 2); ?>"
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
                <td><?php echo number_format($row['company_contribution'], 2); ?></td>
                <td><?php echo number_format($salary_deduction, 2); ?></td> 
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <nav>
        <ul class="pagination">
            <?php 
            $prev = ($page > 1) ? $page - 1 : 1;
            $next = ($page < $total_pages) ? $page + 1 : $total_pages;

            // First and Previous
            if ($page > 1) {
                echo '<li class="page-item"><a href="#" class="page-link" data-page="1">« First</a></li>';
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . $prev . '">< Prev</a></li>';
            }

            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            for ($i = $start; $i <= $end; $i++) {
                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                echo '<a href="#" class="page-link" data-page="' . $i . '">' . $i . '</a>';
                echo '</li>';
            }

            if ($page < $total_pages) {
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . $next . '">Next ></a></li>';
                echo '<li class="page-item"><a href="#" class="page-link" data-page="' . $total_pages . '">Last »</a></li>';
            }
            ?>
        </ul>
    </nav>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Mobile Bill Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalBodyContent">
        Loading details...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
    $(".table-row").click(function(){
        let mobile = $(this).data("mobile");
        let employee = $(this).data("employee");
        let nic = $(this).data("nic");
        let designation = $(this).data("designation");
        let hierarchy = $(this).data("hierarchy");
        let hris = $(this).data("hris");
        let total = $(this).data("total");
        let contribution = $(this).data("contribution");
        let deduction = $(this).data("deduction");
        let date = $(this).data("date");

        // ✅ MUST USE LOWERCASE here
        let roaming = $(this).data("roaming");
        let vas = $(this).data("vas");
        let addtobill = $(this).data("addtobill");

        let html = `
            <strong>Mobile Number:</strong> ${mobile}<br>
            <strong>Employee:</strong> ${employee}<br>
            <strong>NIC:</strong> ${nic}<br>
            <strong>Designation:</strong> ${designation}<br>
            <strong>Hierarchy:</strong> ${hierarchy}<br>
            <strong>HRIS:</strong> ${hris}<br><br>
            <strong>Date:</strong> ${date}<br>
            <strong>Total Payable:</strong> Rs. ${total}<br>
            <strong>Total Roaming:</strong> Rs. ${roaming}<br>
            <strong>Total Value Added Services:</strong> Rs. ${vas}<br>
            <strong>Total Add to Bill:</strong> Rs. ${addtobill}<br>
            <strong>Company Contribution:</strong> Rs. ${contribution}<br>
            <strong>Salary Deduction:</strong> Rs. ${deduction}<br>
        `;

        $("#modalBodyContent").html(html);

        var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
        myModal.show();
    });
});

</script>
<script>
$(document).ready(function(){
    // Existing row click handler
    $(".table-row").click(function(){
        // ... your modal code remains unchanged ...
    });
});
</script>

</body>