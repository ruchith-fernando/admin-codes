<?php
// process-bill.php (layout-only edits to alerts; logic unchanged)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload-dialog-bill.log');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
use Smalot\PdfParser\Parser;

include 'connections/connection.php';
include 'includes/sr-generator.php';

function sanitizeAmount($val) {
    return is_numeric(str_replace(',', '', $val)) ? floatval(str_replace(',', '', $val)) : 0.00;
}

function logSkippedEntry($conn, $mobile, $reason, $update_date, $file_name) {
    $stmt = $conn->prepare("INSERT INTO tbl_admin_mobile_bill_skipped (mobile_number, reason, update_date, file_name) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $mobile, $reason, $update_date, $file_name);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Failed to log skipped entry: $mobile, reason: $reason");
    }
}

if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== 0) {
    error_log("File upload failed or missing.");
    echo "<div class='alert alert-danger fw-bold'>❌ Error: Please upload a valid PDF file.</div>";
    exit;
}

$uploadedFilePath = $_FILES['pdf_file']['tmp_name'];
$uploadedFileName = $_FILES['pdf_file']['name'] ?? 'N/A';

try {
    $parser = new Parser();
    $pdf = $parser->parseFile($uploadedFilePath);
} catch (Exception $e) {
    error_log("PDF parsing failed: " . $e->getMessage());
    echo "<div class='alert alert-danger fw-bold'>❌ Error: Failed to parse the uploaded PDF.</div>";
    exit;
}

$text = $pdf->getText();
$lines = explode("\n", $text);

$update_date = '';
foreach ($lines as $line) {
    if (stripos($line, 'BILL PERIOD') !== false) {
        if (preg_match('/\d{2}\/\d{2}\/\d{4}\s*-\s*(\d{2})\/(\d{2})\/(\d{4})/', $line, $matches)) {
            $monthName = date('F', mktime(0, 0, 0, (int)$matches[2], 1));
            $update_date = "$monthName-$matches[3]";
        }
        break;
    }
}

if (!$update_date) {
    error_log("Billing month not found in PDF.");
    echo "<div class='alert alert-danger fw-bold'>❌ Error: Could not detect billing month.</div>";
    exit;
}

$check = $conn->prepare("SELECT COUNT(*) AS count FROM tbl_admin_mobile_bill_data WHERE Update_date = ?");
$check->bind_param("s", $update_date);
$check->execute();
$result = $check->get_result();
$row = $result->fetch_assoc();
if ($row['count'] > 0) {
    echo "<div class='alert alert-warning fw-bold'>⚠️ Data for <strong>" . htmlspecialchars($update_date) . "</strong> already exists. Import aborted.</div>";
    exit;
}

$skip_header = true;
$processed_rows = 0;
$skipped_rows = 0;

foreach ($lines as $line) {
    $line = trim($line);
    if (
        $line === '' ||
        stripos($line, 'MOBILE / ACCOUNT') !== false ||
        stripos($line, 'Summary Corporate Code') !== false ||
        stripos($line, 'Charges for Bill Period') !== false ||
        preg_match('/TOTAL/i', $line)
    ) {
        continue;
    }

    if ($skip_header && preg_match('/^\d{9}\s+[\d,\.]+\s+[\d,\.]+/', $line)) {
        $skip_header = false;
    }
    if ($skip_header) continue;

    $cols = array_values(array_filter(preg_split('/\s+/', $line)));
    if (count($cols) < 16) {
        $skipped_rows++;
        logSkippedEntry($conn, 'N/A', 'Malformed line: not enough columns', $update_date, $uploadedFileName);
        continue;
    }

    list($mobile, $prev_due, $payments, $usage, $idd, $roaming, $vas,
         $discounts, $bal_adj, $commit, $late, $tax, $vat, $add_to_bill,
         $charges, $total) = array_slice($cols, 0, 16);

    if (!is_numeric($mobile)) {
        $skipped_rows++;
        logSkippedEntry($conn, $mobile, 'Invalid mobile number format', $update_date, $uploadedFileName);
        continue;
    }

    $dup = $conn->prepare("SELECT id FROM tbl_admin_mobile_bill_data WHERE MOBILE_Number = ? AND Update_date = ?");
    $dup->bind_param("ss", $mobile, $update_date);
    $dup->execute();
    $dup_result = $dup->get_result();
    if ($dup_result->num_rows > 0) {
        $skipped_rows++;
        logSkippedEntry($conn, $mobile, 'Duplicate entry for this month', $update_date, $uploadedFileName);
        continue;
    }

    $emp_stmt = $conn->prepare("SELECT name_of_employee, designation, company_hierarchy, voice_data, hris_no, nic_no, display_name 
                                FROM tbl_admin_mobile_issues WHERE mobile_no = ? LIMIT 1");
    $emp_stmt->bind_param("s", $mobile);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
    $emp_row = $emp_result->fetch_assoc();

    $employee_name = $emp_row['name_of_employee'] ?? '';
    $designation = $emp_row['designation'] ?? '';
    $hierarchy = $emp_row['company_hierarchy'] ?? '';
    $voice_data = $emp_row['voice_data'] ?? '';
    $hris_no = $emp_row['hris_no'] ?? '';
    $nic_no = $emp_row['nic_no'] ?? '';
    $display_name = $emp_row['display_name'] ?? '';

    $contribution = 0.00;
    $cont_stmt = $conn->prepare("SELECT contribution_amount FROM tbl_admin_hris_contributions 
                                 WHERE hris_no = ? AND mobile_no = ? 
                                 AND effective_from <= STR_TO_DATE(CONCAT('01-', ?), '%d-%M-%Y')
                                 ORDER BY effective_from DESC LIMIT 1");
    $cont_stmt->bind_param("sss", $hris_no, $mobile, $update_date);
    $cont_stmt->execute();
    $cont_result = $cont_stmt->get_result();
    if ($cont_row = $cont_result->fetch_assoc()) {
        $contribution = $cont_row['contribution_amount'];
    }

    // amounts
    $p1 = sanitizeAmount($prev_due);
    $p2 = sanitizeAmount($payments);
    $p3 = sanitizeAmount($usage);
    $p4 = sanitizeAmount($idd);
    $p5 = sanitizeAmount($roaming);
    $p6 = sanitizeAmount($vas);
    $p7 = sanitizeAmount($discounts);
    $p8 = sanitizeAmount($bal_adj);
    $p9 = sanitizeAmount($commit);
    $p10 = sanitizeAmount($late);
    $p11 = sanitizeAmount($tax);
    $p12 = sanitizeAmount($vat);
    $p13 = sanitizeAmount($add_to_bill);
    $p14 = sanitizeAmount($charges);
    $p15 = sanitizeAmount($total);

    $stmt = $conn->prepare("INSERT INTO tbl_admin_mobile_bill_data (
        MOBILE_Number, PREVIOUS_DUE_AMOUNT, PAYMENTS, TOTAL_USAGE_CHARGES, IDD, ROAMING,
        VALUE_ADDED_SERVICES, DISCOUNTS_BILL_ADJUSTMENTS, BALANCE_ADJUSTMENTS, COMMITMENT_CHARGES,
        LATE_PAYMENT_CHARGES, GOVERNMENT_TAXES_AND_LEVIES, VAT, ADD_TO_BILL,
        CHARGES_FOR_BILL_PERIOD, TOTAL_AMOUNT_PAYABLE, Update_date,
        name_of_employee, designation, company_hierarchy, voice_data, hris_no, nic_no, display_name, company_contribution
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "sdddddddddddddddssssssssd",
        $mobile, $p1, $p2, $p3, $p4, $p5, $p6, $p7, $p8, $p9,
        $p10, $p11, $p12, $p13, $p14, $p15,
        $update_date, $employee_name, $designation, $hierarchy,
        $voice_data, $hris_no, $nic_no, $display_name, $contribution
    );

    if (!$stmt->execute()) {
        $skipped_rows++;
        logSkippedEntry($conn, $mobile, 'Insert failed: ' . $stmt->error, $update_date, $uploadedFileName);
    } else {
        $inserted_id = $stmt->insert_id;
        $sr_generated = generate_sr_number($conn, 'tbl_admin_mobile_bill_data', $inserted_id);
        if (!$sr_generated) {
            error_log("SR number generation failed for record ID $inserted_id");
        }
        $processed_rows++;
    }

    $stmt->close();
}

$conn->close();

// $skipped_link = 'view-skipped.php?month=' . urlencode($update_date) . '&file=' . urlencode($uploadedFileName);

// Success summary styled to match your layout alerts
echo "
<div class='alert alert-success fw-bold'>
  ✅ Upload Successful!
</div>
<div class='result-block' style=\"border-left:4px solid #0d6efd\">
  <div><b>Billing Month:</b> " . htmlspecialchars($update_date) . "</div>
  <div><b>File Name:</b> " . htmlspecialchars($uploadedFileName) . "</div>
  <div><b>Total Records Added:</b> " . (int)$processed_rows . "</div>
  <div><b>Duplicate Check:</b> Enabled</div>
  </div>";
  
//   <div><b>Skipped Entries:</b> <a href=\"" . htmlspecialchars($skipped_link) . "\" target=\"_self\">" . (int)$skipped_rows . " (view details)</a></div>