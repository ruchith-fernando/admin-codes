<?php
include 'connections/connection.php';

$limit = 5; // Records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Get total records
$totalResult = $conn->query("SELECT COUNT(*) as total FROM tbl_admin_postage_cheques");
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch current page records
$query = "SELECT cheque_date, cheque_number, cheque_amount, remarks 
          FROM tbl_admin_postage_cheques 
          ORDER BY id DESC LIMIT $start, $limit";
$result = $conn->query($query);

// Output table rows
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['cheque_date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cheque_number']) . "</td>";
    echo "<td class='text-end'>" . number_format($row['cheque_amount'], 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
    echo "</tr>";
}

// Output pagination aligned right
echo "<tr><td colspan='4' class='pt-3'>";
echo "<div class='d-flex justify-content-end'>"; // Right alignment
echo "<nav><ul class='pagination pagination-sm mb-0'>";

// Previous button
if ($page > 1) {
    echo "<li class='page-item'><a class='page-link recent-page' href='#' data-page='" . ($page - 1) . "'>&laquo; Prev</a></li>";
}

// Page numbers range
$range = 2;
$startPage = max(1, $page - $range);
$endPage = min($totalPages, $page + $range);
for ($i = $startPage; $i <= $endPage; $i++) {
    $active = ($i == $page) ? 'active' : '';
    echo "<li class='page-item $active'><a class='page-link recent-page' href='#' data-page='$i'>$i</a></li>";
}

// Next button
if ($page < $totalPages) {
    echo "<li class='page-item'><a class='page-link recent-page' href='#' data-page='" . ($page + 1) . "'>Next &raquo;</a></li>";
}

echo "</ul></nav>";
echo "</div>";
echo "</td></tr>";
