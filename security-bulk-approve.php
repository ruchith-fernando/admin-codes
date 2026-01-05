<?php
// security-bulk-approve.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
require_once 'fpdf/fpdf.php';
require_once 'includes/pdf_namer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Colombo');

$hris = $_SESSION['hris'] ?? '';
$name = $_SESSION['name'] ?? '';
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

$ids_raw = $_POST['ids'] ?? '';

// ─────────────────────────────────────
// 1. Sanitise / validate IDs
// ─────────────────────────────────────
$id_arr = array_filter(array_map('intval', explode(',', $ids_raw)));
$id_arr = array_values(array_unique($id_arr));

if (empty($id_arr)) {
    echo json_encode([
        "success" => false,
        "message" => "No valid record IDs."
    ]);
    exit;
}

if ($hris === '') {
    echo json_encode([
        "success" => false,
        "message" => "Session expired or HRIS not found."
    ]);
    exit;
}

$id_str   = implode(',', $id_arr);
$hris_esc = mysqli_real_escape_string($conn, $hris);
$name_esc = mysqli_real_escape_string($conn, $name);

// ─────────────────────────────────────
// 2. Approve in DB (dual control)
//    - Only pending
//    - Not your own entries
// ─────────────────────────────────────
$sql = "
    UPDATE tbl_admin_actual_security_firmwise
    SET approval_status = 'approved',
        approved_hris   = '{$hris_esc}',
        approved_name   = '{$name_esc}',
        approved_by     = '{$name_esc}',
        approved_at     = NOW()
    WHERE id IN ({$id_str})
      AND (approval_status = 'pending' OR approval_status IS NULL)
      AND (entered_hris IS NULL OR TRIM(entered_hris) <> '{$hris_esc}')
";

if (!mysqli_query($conn, $sql)) {
    $err = mysqli_error($conn);
    userlog("❌ Security bulk approve DB error | HRIS: $hris | User: $name | IDs: $id_str | Error: $err | IP: $ip");

    echo json_encode([
        "success" => false,
        "message" => "Database error during bulk approve: " . $err
    ]);
    exit;
}

$count = mysqli_affected_rows($conn);

if ($count <= 0) {
    userlog("⚠️ Security bulk approve — no rows updated | HRIS: $hris | User: $name | IDs: $id_str | IP: $ip");
    echo json_encode([
        "success" => false,
        "message" => "No records approved. They may all be non-pending or your own entries."
    ]);
    exit;
}

// ─────────────────────────────────────
// 3. Fetch data for PDF
// ─────────────────────────────────────
$res = mysqli_query($conn, "
    SELECT 
        a.branch_code,
        a.branch,
        a.actual_shifts,
        a.total_amount,
        a.month_applicable,
        a.entered_name,
        a.entered_hris,
        f.firm_name
    FROM tbl_admin_actual_security_firmwise a
    LEFT JOIN tbl_admin_security_firms f ON f.id = a.firm_id
    WHERE a.id IN ($id_str)
      AND a.approval_status = 'approved'
    ORDER BY CAST(a.branch_code AS UNSIGNED), a.branch_code
");

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Nothing to print. Records may have been changed."
    ]);
    exit;
}

$month_for_log = '';
$rows_for_pdf  = [];
$grand_total   = 0.0;

while ($row = mysqli_fetch_assoc($res)) {
    if ($month_for_log === '' && !empty($row['month_applicable'])) {
        $month_for_log = $row['month_applicable'];
    }

    $rows_for_pdf[] = $row;
    $grand_total   += (float)$row['total_amount'];
}

// ─────────────────────────────────────
// 4. Generate PDF
// ─────────────────────────────────────
$pdf_dir = __DIR__ . '/exports';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0777, true);
}

$pdf_name = generate_pdf_filename("bulk");
$pdf_path = $pdf_dir . "/" . $pdf_name;

$pdf = new FPDF();
$pdf->AddPage();

// Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, "Bulk Approved Security Records", 0, 1, 'C');
$pdf->Ln(2);

// Header info
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 7, "Approved By: $name ($hris)", 0, 1);
$pdf->Cell(0, 7, "Approved At: " . date("Y-m-d H:i:s"), 0, 1);
if ($month_for_log !== '') {
    $pdf->Cell(0, 7, "Applicable Month: " . $month_for_log, 0, 1);
}
$pdf->Ln(4);

// Table header
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(230,230,230);
/*
   Column widths sum to 190:
   Code(18) + Branch(45) + Firm(45) + Shifts(12) + Amount(35) + Entered By(35)
*/
$pdf->Cell(18, 9, "Code",        1, 0, "C", true);
$pdf->Cell(45, 9, "Branch",      1, 0, "C", true);
$pdf->Cell(45, 9, "Security Firm",1,0,"C", true);
$pdf->Cell(12, 9, "Shifts",      1, 0, "C", true);
$pdf->Cell(35, 9, "Amount",      1, 0, "C", true);
$pdf->Cell(35, 9, "Entered By",  1, 1, "C", true);

$pdf->SetFont('Arial','',11);

foreach ($rows_for_pdf as $r) {
    $code   = (string)$r['branch_code'];
    $branch = (string)$r['branch']; // actual-table branch name
    $firm   = (string)($r['firm_name'] ?? '');
    $shifts = (int)$r['actual_shifts'];
    $amount = (float)$r['total_amount'];

    $entered_name = trim((string)($r['entered_name'] ?? ''));
    $entered_hris = trim((string)($r['entered_hris'] ?? ''));

    // Show ONLY HRIS for the PDF; fall back to name if HRIS is missing
    if ($entered_hris !== '') {
        $entered_disp = $entered_hris;
    } elseif ($entered_name !== '') {
        $entered_disp = $entered_name;
    } else {
        $entered_disp = '';
    }


    $pdf->Cell(18, 8, $code,   1);
    $pdf->Cell(45, 8, $branch, 1);
    $pdf->Cell(45, 8, ($firm !== '' ? $firm : '-'), 1);
    $pdf->Cell(12, 8, $shifts, 1, 0, 'R');
    $pdf->Cell(35, 8, "Rs. " . number_format($amount,2), 1, 0, 'R');
    $pdf->Cell(35, 8, ($entered_disp !== '' ? $entered_disp : '-'), 1, 1);
}

// Grand total only (per your request)
$pdf->Ln(4);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 8, "Grand Total: Rs. " . number_format($grand_total, 2), 0, 1, 'R');

$pdf->Output('F', $pdf_path);

// ─────────────────────────────────────
// 5. Log into common PDF log
// ─────────────────────────────────────
$module       = 'security';
$pdf_type     = 'bulk';
$record_ids   = $id_str;
$entity_label = "Security bulk approval ({$count} record(s))";

$stmt = $conn->prepare("
    INSERT INTO tbl_admin_pdf_log 
    (module, pdf_name, pdf_type, record_ids, month_applicable, entity_label,
     approved_by_hris, approved_by_name, generated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param(
    "ssssssss",
    $module,
    $pdf_name,
    $pdf_type,
    $record_ids,
    $month_for_log,
    $entity_label,
    $hris,
    $name
);
$stmt->execute();

// Userlog
userlog(sprintf(
    "✔️ Security bulk approve | HRIS: %s | User: %s | Count: %d | IDs: %s | Month: %s | IP: %s",
    $hris ?: 'N/A',
    $name ?: 'Unknown',
    $count,
    $record_ids,
    $month_for_log ?: 'N/A',
    $ip
));

// JSON response
echo json_encode([
    "success" => true,
    "message" => "Bulk approval completed ({$count} record(s)).",
    "pdf_url" => "exports/" . $pdf_name
]);
