<?php
// fetch-electricity-existing.php
require_once 'connections/connection.php';

$month = $_GET['month'] ?? '';
$month = trim($month);

if ($month === '') {
    echo '<div class="alert alert-secondary">Select a month to view existing records.</div>';
    exit;
}

$stmt = $conn->prepare("
    SELECT id, branch_code, branch, actual_units, total_amount, account_no, bank_paid_to,
           bill_from_date, bill_to_date, number_of_days, uploaded_at
    FROM tbl_admin_actual_electricity
    WHERE month_applicable = ?
    ORDER BY branch_code ASC
");
$stmt->bind_param("s", $month);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning mb-0">No existing records found for <strong>'
         . htmlspecialchars($month) . '</strong>. You can add entries below.</div>';
    $stmt->close();
    exit;
}

echo '<div class="table-responsive">';
echo '<table class="table table-sm table-striped table-bordered mb-0">';
echo '<thead class="table-light">
        <tr>
            <th>#</th>
            <th>Branch Code</th>
            <th>Branch Name</th>
            <th>Units</th>
            <th>Total Amount</th>
            <th>Account No</th>
            <th>Paid By</th>
            <th>Bill From</th>
            <th>Bill To</th>
            <th>No. of Days</th>
            <th>Uploaded At</th>
        </tr>
      </thead><tbody>';

$ctr = 1;
while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    echo '<td>'. $ctr++ .'</td>';
    echo '<td>'. htmlspecialchars($row['branch_code']) .'</td>';
    echo '<td>'. htmlspecialchars($row['branch']) .'</td>';
    echo '<td>'. htmlspecialchars($row['actual_units']) .'</td>';
    echo '<td>'. htmlspecialchars($row['total_amount']) .'</td>';
    echo '<td>'. htmlspecialchars($row['account_no']) .'</td>';
    echo '<td>'. htmlspecialchars($row['bank_paid_to']) .'</td>';
    echo '<td>'. htmlspecialchars($row['bill_from_date']) .'</td>';
    echo '<td>'. htmlspecialchars($row['bill_to_date']) .'</td>';
    echo '<td>'. htmlspecialchars($row['number_of_days']) .'</td>';
    echo '<td>'. htmlspecialchars($row['uploaded_at']) .'</td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

$stmt->close();
