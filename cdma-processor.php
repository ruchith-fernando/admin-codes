<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use Smalot\PdfParser\Parser;
include 'connections/connection.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload-cdma-bill.log');
error_reporting(E_ALL);

function clean($val){
    $val = str_replace([",", "Rs.", "(", ")", " "], "", $val);
    return is_numeric($val) ? floatval($val) : 0.00;
}

function logSkipped($conn, $subscription, $reason, $update_date, $file){
    $stmt = $conn->prepare("INSERT INTO tbl_admin_cdma_skipped (subscription_number, reason, update_date, file_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $subscription, $reason, $update_date, $file);
    $stmt->execute();
    $stmt->close();
}

if (!isset($_SESSION['hris'])) {
    echo "<div class='text-danger'>Session expired. Login again.</div>"; exit;
}

$hris = $_SESSION['hris'];
$file_name = $_FILES['cdma_pdf']['name'] ?? 'N/A';
$file_tmp = $_FILES['cdma_pdf']['tmp_name'];

if (!is_uploaded_file($file_tmp)) {
    echo "<div class='text-danger'>No valid file uploaded.</div>"; exit;
}

try {
    $parser = new Parser();
    $pdf = $parser->parseFile($file_tmp);
} catch (Exception $e) {
    error_log("PDF parsing error: " . $e->getMessage());
    echo "<div class='text-danger'>Failed to parse PDF file.</div>"; exit;
}

$text = $pdf->getText();
$lines = explode("\n", $text);

// ✅ Extract Billing Month
$update_date = "";
foreach ($lines as $line) {
    if (stripos($line, 'BILL PERIOD') !== false) {
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})\s*-\s*(\d{2})\/(\d{2})\/(\d{4})/', $line, $m)) {
            $month = (int)$m[5];
            $year = $m[6];
            $month_name = date('F', mktime(0,0,0,$month,1));
            $update_date = "$month_name-$year";
        }
    }
}
if (!$update_date) {
    error_log("Billing month not found for $file_name");
    echo "<div class='text-danger'>Billing period not found.</div>"; exit;
}

// ✅ Duplicate Check
$check = $conn->prepare("SELECT COUNT(*) as count FROM tbl_admin_cdma_monthly_data WHERE Update_date = ?");
$check->bind_param("s", $update_date);
$check->execute();
$result = $check->get_result()->fetch_assoc();
if ($result['count'] > 0) {
    echo "<div class='text-warning'>Data for <strong>$update_date</strong> already uploaded.</div>"; exit;
}

// ✅ Process Lines - Only summary table
$added = 0; $skipped = 0;
$subscription_data = [];
$total_late = 0; $total_govt = 0; $total_vat = 0;
$valid_subs = 0;

foreach ($lines as $line) {
    $line = trim($line);

    // ✅ Stop parsing after Contract Charges
    if (stripos($line, 'CONTRACT CHARGES') !== false) break;

    if (preg_match('/^\d{9,}/', $line)) {
        $cols = preg_split('/\s+/', $line);

        // ✅ Clean subscription number
        preg_match('/(\d{9,})/', $cols[0], $match);
        $sub = $match[1] ?? $cols[0];

        while(count($cols) < 15) $cols[] = "0.00";
        list($junk, $data, $voice, $rent, $local, $national, $mobile, $sms, $idd, $vas, $discount, $late, $govt, $vat, $total) = array_slice($cols, 0, 15);
        $subscription_data[] = [$sub, $data, $voice, $rent, $local, $national, $mobile, $sms, $idd, $vas, $discount, $late, $govt, $vat, $total];
        $valid_subs++;
    } elseif (stripos($line, 'LATE PAYMENT CHARGES') !== false) {
        preg_match('/([\d,.]+)$/', $line, $match);
        $total_late += clean($match[1] ?? "0");
    } elseif (stripos($line, 'GOVERNMENT TAXES') !== false) {
        preg_match('/([\d,.]+)$/', $line, $match);
        $total_govt += clean($match[1] ?? "0");
    } elseif (stripos($line, 'VAT') !== false) {
        preg_match('/([\d,.]+)$/', $line, $match);
        $total_vat += clean($match[1] ?? "0");
    }
}

// ✅ Redistribute Taxes per Subscription
$late_share = $valid_subs ? $total_late / $valid_subs : 0;
$govt_share = $valid_subs ? $total_govt / $valid_subs : 0;
$vat_share = $valid_subs ? $total_vat / $valid_subs : 0;

// ✅ Final Insert
foreach ($subscription_data as $cols) {
    list($sub, $data, $voice, $rent, $local, $national, $mobile, $sms, $idd, $vas, $discount, $late, $govt, $vat, $total) = $cols;

    $late = clean($late) + $late_share;
    $govt = clean($govt) + $govt_share;
    $vat = clean($vat) + $vat_share;

    $stmt = $conn->prepare("INSERT INTO tbl_admin_cdma_monthly_data 
    (subscription_number, data, voice_vpn, rental, local_calls, national_calls, mobile_calls, sms, idd, vas, discounts, late_payment_charges, govt_taxes_levies, vat_non_taxable, total, file_name, uploaded_by, Update_date) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sddddddddddddddsss",
        $sub, clean($data), clean($voice), clean($rent), clean($local), clean($national), clean($mobile), clean($sms), clean($idd), clean($vas), clean($discount), $late, $govt, $vat, clean($total), $file_name, $hris, $update_date
    );

    if ($stmt->execute()) $added++;
    else {
        $skipped++;
        logSkipped($conn, $sub, 'Insert error: '.$stmt->error, $update_date, $file_name);
    }
    $stmt->close();
}

echo "<div class='text-success'>✅ Billing Month: $update_date<br>Records Added: $added<br>Skipped: $skipped<br>Late/Record: ".number_format($late_share,2)." | Govt/Record: ".number_format($govt_share,2)." | VAT/Record: ".number_format($vat_share,2)."</div>";
?>
