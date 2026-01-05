<?php
require_once 'connections/connection.php';
// fetch-previous-month.php
$branch_code = $_POST['branch_code'];
$current_month = $_POST['month'];
$current_time = strtotime("first day of " . $current_month);

// Fix: only select strictly before current month
$query = mysqli_query($conn, "
    SELECT actual_shifts, total_amount, month_applicable 
    FROM tbl_admin_actual_security 
    WHERE branch_code = '$branch_code' 
      AND STR_TO_DATE(month_applicable, '%M %Y') < FROM_UNIXTIME($current_time)
    ORDER BY STR_TO_DATE(month_applicable, '%M %Y') DESC 
    LIMIT 1
");

if(mysqli_num_rows($query) > 0){
    $data = mysqli_fetch_assoc($query);
    echo json_encode([
        'found' => true,
        'shifts' => $data['actual_shifts'],
        'amount' => number_format($data['total_amount'], 2),
        'month' => $data['month_applicable']
    ]);
} else {
    echo json_encode(['found' => false]);
}
