<?php
require 'vendor/autoload.php';
require 'connections/connection.php'; // <-- use $conn from here

use Smalot\PdfParser\Parser;

// Upload
$targetDir = "uploads/";
$fileName = basename($_FILES["pdfFile"]["name"]);
$targetFilePath = $targetDir . $fileName;
move_uploaded_file($_FILES["pdfFile"]["tmp_name"], $targetFilePath);

// Parse PDF
$parser = new Parser();
$pdf    = $parser->parseFile($targetFilePath);
$text   = $pdf->getText();

// --- Extract header info ---
preg_match('/INVOICE NUMBER\s*:\s*(\S+)/', $text, $m1);
preg_match('/INVOICE DATE\s*:\s*(\d{2}\/\d{2}\/\d{4})/', $text, $m2);
preg_match('/BILL PERIOD\s*(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/', $text, $m3);
preg_match('/Total Amount Payable\s*([\d,]+\.\d{2})/', $text, $m4);

$invoice_number = $m1[1] ?? '';
$invoice_date   = isset($m2[1]) ? date("Y-m-d", strtotime(str_replace('/', '-', $m2[1]))) : null;
$bill_start     = isset($m3[1]) ? date("Y-m-d", strtotime(str_replace('/', '-', $m3[1]))) : null;
$bill_end       = isset($m3[2]) ? date("Y-m-d", strtotime(str_replace('/', '-', $m3[2]))) : null;
$total_payable  = isset($m4[1]) ? str_replace(',', '', $m4[1]) : 0;

// Insert invoice
$stmt = $conn->prepare("INSERT INTO invoices (invoice_number, invoice_date, bill_period_start, bill_period_end, total_payable) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssssd", $invoice_number, $invoice_date, $bill_start, $bill_end, $total_payable);
$stmt->execute();
$invoice_id = $stmt->insert_id;

// --- Extract per-account rows ---
$pattern = '/(\d{9})\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)\s+([\d\.\-]+)/';

preg_match_all($pattern, $text, $rows, PREG_SET_ORDER);

foreach ($rows as $row) {
    $stmt = $conn->prepare("INSERT INTO invoice_details 
        (invoice_id, account_number, prev_due, payments, total_usage, idd, roaming, vas, adjustments, late_charges, govt_taxes, vat, add_to_bill, charges, total_payable) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isddddddddddddd", 
        $invoice_id, $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], 
        $row[9], $row[10], $row[11], $row[12], $row[13], $row[14]);
    $stmt->execute();
}

echo "Invoice imported successfully!";
