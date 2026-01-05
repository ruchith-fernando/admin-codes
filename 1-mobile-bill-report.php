<!-- mobile-bill-report.php -->
<?php
include 'connections/connection.php';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Ensure $page is a positive integer
if ($page < 1) {
    $page = 1; // Set to 1 if it's less than 1
}

$limit = 25;  // Define the number of results per page
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : ''; // Get the search term

$offset = ($page - 1) * $limit; // Calculate the offset based on the current page


$where = "WHERE t1.MOBILE_Number LIKE '%$search%' 
          OR t2.name_of_employee LIKE '%$search%' 
          OR t1.Update_date LIKE '%$search%' 
          OR t2.nic_no LIKE '%$search%' 
          OR t2.hris_no LIKE '%$search%'";

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mobile Bill Report</title>
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
    <div class="container">
        <div class="card shadow bg-white rounded p-4">
        <h2 class="mb-4">Mobile Bill Report - HR</h2>

        <div class="mb-3 d-flex gap-2 flex-wrap">
            <input type="text" id="searchInput" class="form-control" placeholder="Search Mobile Number, Name, HRIS, NIC and Billing Month" style="max-width: 600px;">
            <button onclick="exportData('excel')" class="btn btn-primary btn-sm">Download Excel</button>
        </div>
        <input type="hidden" id="searchHidden" value="">

        <div id="tableContainer">
            <?php include 'mobile-bill-table.php'; ?>
        </div>
    </div>
</div>

<script>
function exportData(type) {
    var search = document.getElementById('searchInput').value;
    if (type === 'csv') {
        window.location.href = 'export-mobile-bill-csv.php?search=' + encodeURIComponent(search);
    } else if (type === 'excel') {
        window.location.href = 'export-mobile-bill-excel.php?search=' + encodeURIComponent(search);
    }
}

$(document).ready(function(){
    $('#searchInput').on('keyup', function(){
        let search = $(this).val();
        $.get('mobile-bill-table.php', { search: search }, function(data){
            $('#tableContainer').html(data);
        });
    });
});
</script>
<script>
$(document).ready(function(){
    let searchInput = $('#searchInput');
    let searchHidden = $('#searchHidden');

    // When typing in search bar
    searchInput.on('keyup', function(){
        let search = $(this).val();
        searchHidden.val(search); // Save for pagination
        loadTable(1, search); // Load first page with filter
    });

    // Load data function
    function loadTable(page, search) {
        $.get('mobile-bill-table.php', { page: page, search: search }, function(data){
            $('#tableContainer').html(data);
        });
    }

    // Pagination click (delegated)
    $('#tableContainer').on('click', '.pagination .page-link', function(e){
        e.preventDefault();
        let page = $(this).data('page');
        let search = searchHidden.val(); // Get stored search value
        loadTable(page, search); // Use stored value
    });
});
</script>


</body>
</html>
