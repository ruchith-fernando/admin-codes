<?php
// table-finance-report-dialog-detail.php
include 'connections/connection.php';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$bucketNumber = '765055020';  // The bucket mobile number
$negativeTotal = 0;

$sql = "
    SELECT 
        i.period AS billing_month,
        i.invoice_no,
        d.mobile_number,
        d.previous_due_amount,
        d.payments,
        d.total_usage_charges,
        d.idd,
        d.roaming,
        d.value_added_services,
        d.discounts_bill_adjustments,
        d.balance_adjustments,
        d.commitment_charges,
        d.late_payment_charges,
        d.government_taxes_levies,
        d.vat,
        d.add_to_bill,
        d.charges_for_bill_period,
        d.total_amount_payable
    FROM tbl_admin_dialog_invoices i
    INNER JOIN tbl_admin_dialog_invoice_details d 
        ON i.id = d.invoice_id
    WHERE (? = '' OR 
           d.mobile_number LIKE CONCAT('%', ?, '%') OR
           i.period LIKE CONCAT('%', ?, '%') OR
           i.invoice_no LIKE CONCAT('%', ?, '%'))
    ORDER BY 
        CASE 
            WHEN i.period REGEXP '^[A-Za-z]+-[0-9]{4}$' 
            THEN STR_TO_DATE(CONCAT('01-', i.period), '%d-%M-%Y')
            ELSE NULL
        END DESC,
        i.period DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=invoices_export.csv');

$output = fopen('php://output', 'w');

// headers (HRIS + Employee removed)
fputcsv($output, [
    'Billing Month', 'Invoice No', 'Mobile Number',
    'Previous Due Amount', 'Payments', 'Total Usage Charges', 'IDD', 'Roaming',
    'Value Added Services', 'Discounts/Bill Adjustments', 'Balance Adjustments',
    'Commitment Charges', 'Late Payment Charges', 'Govt Taxes & Levies', 'VAT',
    'Add To Bill', 'Charges For Bill Period', 'Total Amount Payable'
]);

$rows = [];
$bucketRow = null;
$totalPayableSum = 0;
$negativeRows = [];   // ✅ always defined

while ($row = $result->fetch_assoc()) {
    $total = (float)$row['total_amount_payable'];

    // Skip 0
    if ($total == 0) {
        continue;
    }

    // Collect negatives
    if ($total < 0) {
        $negativeTotal += abs($total);
        $negativeRows[] = $row;
        continue;
    }

    // Identify bucket row
    if ($row['mobile_number'] === $bucketNumber) {
        $bucketRow = $row;
    } else {
        $rows[] = $row; // Save normal row
    }
}

// Adjust bucket total
if ($bucketRow) {
    $bucketRow['total_amount_payable'] = (float)$bucketRow['total_amount_payable'] - $negativeTotal;
}

// ✅ Format numbers but skip mobile_number
function formatRow($row) {
    foreach ($row as $key => $val) {
        if (is_numeric($val)) {
            if ($key === 'mobile_number') {
                $row[$key] = (string)$val; // keep as text
            } else {
                $row[$key] = number_format((float)$val, 2, '.', ',');
            }
        }
    }
    return $row;
}

// Write normal rows
foreach ($rows as $r) {
    $totalPayableSum += (float)$r['total_amount_payable'];
    fputcsv($output, formatRow($r));
}

// Write bucket row last
if ($bucketRow) {
    $totalPayableSum += (float)$bucketRow['total_amount_payable'];
    fputcsv($output, formatRow($bucketRow));
}

// Final totals row
$totalsRow = array_fill(0, 17, ''); 
$totalsRow[] = number_format($totalPayableSum, 2, '.', ',');
fputcsv($output, $totalsRow);

// ✅ Insert Negative Adjustments section
if (!empty($negativeRows)) {
    // Blank row for spacing
    fputcsv($output, []);

    // Section header
    fputcsv($output, ['--- Negative Adjustments ---']);
    fputcsv($output, ['Mobile Number', 'Negative Value']);

    $negativeSum = 0;
    foreach ($negativeRows as $neg) {
        $negativeSum += (float)$neg['total_amount_payable'];
        fputcsv($output, [
            $neg['mobile_number'], 
            number_format((float)$neg['total_amount_payable'], 2, '.', ',')
        ]);
    }

    // Total negative row
    fputcsv($output, ['TOTAL NEGATIVE', number_format($negativeSum, 2, '.', ',')]);
}

fclose($output);

// ✅ Log successful export
try {
    require_once 'includes/userlog.php';
    $hris = $_SESSION['hris'] ?? 'UNKNOWN';
    $username = $_SESSION['name'] ?? getUserInfo();
    $searchText = $search !== '' ? $search : 'All Records';
    $actionMessage = sprintf(
        '✅ Exported Finance Dialog Invoice CSV | Search: %s | Rows exported: %d',
        $searchText,
        $result->num_rows ?? 0
    );
    userlog($actionMessage);
} catch (Throwable $e) {}

exit;
?>
