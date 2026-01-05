<?php
// invoice-process.php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use Smalot\PdfParser\Parser;
include 'connections/connection.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload-dialog-invoice.log');
error_reporting(E_ALL);

function cleanAmount($val) {
    $val = str_replace([",", "(", ")", "Rs.", " "], "", $val);
    return is_numeric($val) ? floatval($val) : 0.00;
}

$file_name = $_FILES['pdf_file']['name'] ?? 'N/A';
$file_tmp  = $_FILES['pdf_file']['tmp_name'];

if (!is_uploaded_file($file_tmp)) {
    echo "<div class='alert alert-danger'>❌ No valid file uploaded.</div>";
    exit;
}

// ✅ Begin transaction
$conn->begin_transaction();

try {
    // ✅ Parse PDF
    $parser = new Parser();
    $pdf    = $parser->parseFile($file_tmp);
    $text   = $pdf->getText();
    $lines  = explode("\n", $text);

    // ✅ Extract Header Info
    $invoice_no = $invoice_date = $bill_start = $bill_end = $period = '';

    foreach ($lines as $line) {
        if (stripos($line, 'INVOICE NUMBER') !== false && preg_match('/INVOICE NUMBER\s*:\s*(\S+)/i', $line, $m)) {
            $invoice_no = $m[1];
        }
        if (stripos($line, 'INVOICE DATE') !== false && preg_match('/INVOICE DATE\s*:\s*(\d{2}\/\d{2}\/\d{4})/', $line, $m)) {
            $invoice_date = date("Y-m-d", strtotime(str_replace('/', '-', $m[1])));
        }
        if (stripos($line, 'BILL PERIOD') !== false && preg_match('/(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/', $line, $m)) {
            $bill_start = date("Y-m-d", strtotime(str_replace('/', '-', $m[1])));
            $bill_end   = date("Y-m-d", strtotime(str_replace('/', '-', $m[2])));
            $period     = date('F-Y', strtotime($bill_end));
        }
    }

    // ✅ Insert Invoice Header
    $stmt = $conn->prepare("INSERT INTO tbl_admin_dialog_invoices
        (original_name, stored_path, invoice_no, invoice_date, bill_period_text, bill_period_start, bill_period_end, period, file_name, uploader_hris, sr_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stored_path = $file_tmp;
    $bill_period_text = $bill_start . " - " . $bill_end;
    $uploader_hris = $_SESSION['hris'] ?? 'system';
    $sr_number = uniqid('SR');

    $stmt->bind_param("sssssssssss",
        $file_name, $stored_path, $invoice_no, $invoice_date, $bill_period_text,
        $bill_start, $bill_end, $period, $file_name, $uploader_hris, $sr_number
    );
    if (!$stmt->execute()) {
        throw new Exception("❌ Failed to save invoice header: " . $stmt->error);
    }
    $invoice_id = $stmt->insert_id;
    $stmt->close();

    // ✅ Process Detail Rows
    $added = 0; 
    $skipped = 0;

    foreach ($lines as $line) {
        $line = trim($line);

        // ✅ Updated to include alphanumeric account IDs (A72016123, C60542364, etc.)
        if (preg_match('/^[A-Z]?\d{7,}/i', $line)) {
            $cols = preg_split('/\s+/', $line);

            if (count($cols) < 16) {
                $skipped++;
                error_log("SKIPPED (not enough cols): $line");
                continue;
            }

            list($mobile, $prev, $pay, $usage, $idd, $roam, $vas,
                $disc, $bal, $commit, $late, $govt, $vat, $add,
                $charges, $total) = array_slice($cols, 0, 16);

            // ✅ Normalize mobile/account code
            $mobile = strtoupper(trim($mobile));

            // ✅ Assign cleaned values
            $prev_val     = cleanAmount($prev);
            $pay_val      = cleanAmount($pay);
            $usage_val    = cleanAmount($usage);
            $idd_val      = cleanAmount($idd);
            $roam_val     = cleanAmount($roam);
            $vas_val      = cleanAmount($vas);
            $disc_val     = cleanAmount($disc);
            $bal_val      = cleanAmount($bal);
            $commit_val   = cleanAmount($commit);
            $late_val     = cleanAmount($late);
            $govt_val     = cleanAmount($govt);
            $vat_val      = cleanAmount($vat);
            $add_val      = cleanAmount($add);
            $charges_val  = cleanAmount($charges);
            $total_val    = cleanAmount($total);

            $stmt = $conn->prepare("INSERT INTO tbl_admin_dialog_invoice_details 
                (invoice_id, mobile_number, previous_due_amount, payments, total_usage_charges, idd, roaming, value_added_services, discounts_bill_adjustments, balance_adjustments, commitment_charges, late_payment_charges, government_taxes_levies, vat, add_to_bill, charges_for_bill_period, total_amount_payable) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "isddddddddddddddd",
                $invoice_id, $mobile,
                $prev_val, $pay_val, $usage_val,
                $idd_val, $roam_val, $vas_val,
                $disc_val, $bal_val, $commit_val,
                $late_val, $govt_val, $vat_val,
                $add_val, $charges_val, $total_val
            );

            if ($stmt->execute()) {
                $added++;
            } else {
                $skipped++;
                error_log("INSERT FAIL {$mobile}: " . $stmt->error);
            }
            $stmt->close();
        }
    }

    // ✅ Populate Summary Table with allocation logic
    $sql_summary = "
        INSERT IGNORE INTO tbl_admin_hr_report_dialog_summary (
            invoice_id,
            billing_month,
            mobile_number,
            hris_no,
            employee_name,
            designation,
            nic_no,
            total_amount_payable,
            contribution_amount,
            salary_deduction
        )
        SELECT 
            inv.id AS invoice_id,
            inv.period AS billing_month,
            det.mobile_number,
            alloc.hris_no,
            alloc.owner_name,
            mi.designation,
            mi.nic_no,
            det.total_amount_payable,
            COALESCE(c.contribution_amount, 0) AS contribution_amount,
            CASE
                WHEN det.total_amount_payable < 0 THEN 0
                WHEN COALESCE(c.contribution_amount, 0) > det.total_amount_payable THEN 0
                ELSE det.total_amount_payable - COALESCE(c.contribution_amount, 0)
            END AS salary_deduction
        FROM tbl_admin_dialog_invoice_details det
        JOIN tbl_admin_dialog_invoices inv 
          ON inv.id = det.invoice_id
        JOIN tbl_admin_mobile_allocations alloc 
          ON alloc.mobile_number = det.mobile_number
         AND inv.bill_period_end >= alloc.effective_from
         AND (alloc.effective_to IS NULL OR inv.bill_period_start < alloc.effective_to)
        LEFT JOIN tbl_admin_hris_contributions c
          ON c.hris_no = alloc.hris_no
         AND c.mobile_no = det.mobile_number
         AND inv.bill_period_end >= c.effective_from
         AND (c.effective_to IS NULL OR inv.bill_period_start < c.effective_to)
        LEFT JOIN tbl_admin_mobile_issues mi 
          ON mi.hris_no = alloc.hris_no
        WHERE inv.id = ?
    ";
    $stmt = $conn->prepare($sql_summary);
    $stmt->bind_param("i", $invoice_id);
    if (!$stmt->execute()) {
        throw new Exception("❌ Failed to insert into summary: " . $stmt->error);
    }
    $stmt->close();

    // ✅ NEW: Calculate the true monthly Dialog figure (positives + negatives)
    $sql_total = "SELECT SUM(total_amount_payable) AS net_total
                  FROM tbl_admin_dialog_invoice_details
                  WHERE invoice_id = ?";
    $stmt = $conn->prepare($sql_total);
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $stmt->bind_result($net_total);
    $stmt->fetch();
    $stmt->close();

    if ($net_total === null) {
        $net_total = 0.0;
    }

    // ✅ Insert/Update into tbl_admin_dialog_figures
    $stmt = $conn->prepare("
        INSERT INTO tbl_admin_dialog_figures (billing_month, dialog_bill_amount)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE dialog_bill_amount = VALUES(dialog_bill_amount)
    ");
    $stmt->bind_param("sd", $period, $net_total);
    if (!$stmt->execute()) {
        throw new Exception("❌ Failed to update dialog figures: " . $stmt->error);
    }
    $stmt->close();

    // ✅ Commit transaction
    $conn->commit();

    // ✅ Final Output
    echo "
    <div class='alert alert-success fw-bold'>✅ Invoice Imported Successfully</div>
    <div class='result-block'>
      <div><b>Invoice No:</b> " . htmlspecialchars($invoice_no) . "</div>
      <div><b>Invoice Date:</b> " . htmlspecialchars($invoice_date) . "</div>
      <div><b>Billing Period:</b> " . htmlspecialchars($bill_period_text) . "</div>
      <div><b>Records Added:</b> " . (int)$added . "</div>
      <div><b>Records Skipped:</b> " . (int)$skipped . "</div>
      <div><b>Final Dialog Bill:</b> " . number_format($net_total, 2) . "</div>
    </div>";

} catch (Exception $e) {
    $conn->rollback();
    error_log("UPLOAD FAIL: " . $e->getMessage());
    echo "<div class='alert alert-danger'>❌ Failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

$conn->close();
?>
