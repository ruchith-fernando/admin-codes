<?php
// table-hr-report-dialog-detail.php
include 'connections/connection.php';

$mobile = isset($_GET['mobile']) ? trim($_GET['mobile']) : '';
$billing_month = isset($_GET['billing_month']) ? trim($_GET['billing_month']) : '';

if ($mobile === '' || $billing_month === '') {
    echo '<div class="alert alert-warning">Invalid request.</div>';
    exit;
}

$summary_sql = "
    SELECT invoice_id, total_amount_payable, contribution_amount, salary_deduction,
           nic_no, employee_name, hris_no, mobile_number, billing_month, voice_data, designation
    FROM tbl_admin_hr_report_dialog_summary
    WHERE mobile_number = ? AND billing_month = ? LIMIT 1
";
$sum_stmt = $conn->prepare($summary_sql);
$sum_stmt->bind_param("ss", $mobile, $billing_month);
$sum_stmt->execute();
$sum_res = $sum_stmt->get_result();

if ($sum_row = $sum_res->fetch_assoc()) {
    $invoice_id = $sum_row['invoice_id'];
    $total_payable = (float)$sum_row['total_amount_payable'];
    $contribution = (float)$sum_row['contribution_amount'];
    $deduction = (float)$sum_row['salary_deduction'];
    $employee_name = $sum_row['employee_name'];

    $sql = "
        SELECT inv.invoice_no, inv.invoice_date, inv.bill_period_start, inv.bill_period_end,
               det.previous_due_amount, det.payments, det.total_usage_charges, det.idd,
               det.roaming, det.value_added_services, det.discounts_bill_adjustments,
               det.balance_adjustments, det.commitment_charges, det.late_payment_charges,
               det.government_taxes_levies, det.vat, det.add_to_bill
        FROM tbl_admin_dialog_invoice_details det
        INNER JOIN tbl_admin_dialog_invoices inv ON inv.id = det.invoice_id
        WHERE det.invoice_id = ? AND det.mobile_number = ? LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $invoice_id, $mobile);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        ?>
        <table class="table table-sm table-bordered mt-3">
            <tr><th>Invoice No</th><td><?= htmlspecialchars($row['invoice_no']); ?></td></tr>
            <tr><th>Invoice Date</th><td><?= htmlspecialchars($row['invoice_date']); ?></td></tr>
            <tr><th>Bill Period</th><td><?= htmlspecialchars($row['bill_period_start']) . ' to ' . htmlspecialchars($row['bill_period_end']); ?></td></tr>
            <tr><th>Total Usage Charges</th><td><?= number_format($row['total_usage_charges'], 2); ?></td></tr>
            <tr><th>VAT</th><td><?= number_format($row['vat'], 2); ?></td></tr>
            <tr class="table-primary"><th>Total Payable</th><td><?= number_format($total_payable, 2); ?></td></tr>
            <tr class="table-info"><th>Contribution</th><td><?= number_format($contribution, 2); ?></td></tr>
            <tr class="table-danger"><th>Salary Deduction</th><td><?= number_format($deduction, 2); ?></td></tr>
        </table>
        <?php
        // ✅ Log detail view success
        try {
            require_once 'includes/userlog.php';
            $hris = $_SESSION['hris'] ?? 'UNKNOWN';
            $username = $_SESSION['name'] ?? getUserInfo();
            $actionMessage = sprintf(
                '✅ Viewed HR Dialog detail | Mobile: %s | Billing Month: %s | Employee: %s',
                $mobile,
                $billing_month,
                $employee_name ?? 'N/A'
            );
            userlog($actionMessage);
        } catch (Throwable $e) {}
    }
} else {
    echo '<div class="alert alert-info">No detailed bill found for this record.</div>';
}
?>
