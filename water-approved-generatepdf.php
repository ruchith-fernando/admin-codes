<?php
require_once 'connections/connection.php';
require_once __DIR__ . "/fpdf/fpdf.php";
if (session_status() === PHP_SESSION_NONE) session_start();

date_default_timezone_set('Asia/Colombo');

$ids_raw = $_GET['ids'] ?? '';
$id_list = array_filter(array_map('intval', explode(',', $ids_raw)));

if (empty($id_list)) {
    die("Invalid request.");
}

$id_str = implode(",", $id_list);

// Fetch approved records
$res = mysqli_query($conn, "
    SELECT branch_code, branch, total_amount, approved_at
    FROM tbl_admin_actual_water
    WHERE id IN ($id_str)
      AND approval_status='approved'
");

if (!$res || mysqli_num_rows($res) === 0) {
    die("No approved records found.");
}

// Build PDF
$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, "Approved Water Records", 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, "Generated At: " . date("Y-m-d H:i:s"), 0, 1);
$pdf->Ln(5);

// Header
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(30, 9, "Code", 1, 0, "C", true);
$pdf->Cell(60, 9, "Branch", 1, 0, "C", true);
$pdf->Cell(35, 9, "Amount", 1, 0, "C", true);
$pdf->Cell(50, 9, "Approved At", 1, 1, "C", true);

$pdf->SetFont('Arial', '', 11);

// Rows
while ($row = mysqli_fetch_assoc($res)) {
    $pdf->Cell(30, 9, $row['branch_code'], 1);
    $pdf->Cell(60, 9, $row['branch'], 1);
    $pdf->Cell(35, 9, number_format($row['total_amount'],2), 1);
    $pdf->Cell(50, 9, $row['approved_at'], 1, 1);
}

// Output PDF for download
$pdf->Output("D", "approved_records.pdf");
exit;
