<?php
// security-approve-single.php
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

$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$branch      = trim($_POST['branch'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');

if ($id <= 0) {
    echo json_encode([
        "status"  => "error",
        "message" => "Invalid record ID."
    ]);
    exit;
}

// 1️⃣ Fetch record to enforce dual control
$stmt = $conn->prepare("
    SELECT entered_hris, approval_status
    FROM tbl_admin_actual_security_firmwise
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo json_encode([
        "status"  => "error",
        "message" => "Record not found."
    ]);
    exit;
}

$row             = $res->fetch_assoc();
$entered_hris    = trim((string)($row['entered_hris'] ?? ''));
$approval_status = $row['approval_status'] ?? 'pending';

// dual control – cannot approve own entry
if ($entered_hris !== '' && $hris !== '' && $entered_hris === $hris) {
    echo json_encode([
        "status"  => "error",
        "message" => "You cannot approve a record you entered (dual control)."
    ]);
    exit;
}

// Already processed?
if (in_array($approval_status, ['approved','rejected','deleted'], true)) {
    echo json_encode([
        "status"  => "error",
        "message" => "Record is already processed."
    ]);
    exit;
}

// 2️⃣ Approve
$stmt2 = $conn->prepare("
    UPDATE tbl_admin_actual_security_firmwise
    SET approval_status = 'approved',
        approved_hris   = ?,
        approved_name   = ?,
        approved_by     = ?,
        approved_at     = NOW()
    WHERE id = ?
      AND (approval_status = 'pending' OR approval_status IS NULL)
    LIMIT 1
");
$stmt2->bind_param("sssi", $hris, $name, $name, $id);
$stmt2->execute();

if ($stmt2->affected_rows <= 0) {
    echo json_encode([
        "status"  => "error",
        "message" => "Record already approved or not pending."
    ]);
    exit;
}

// 3️⃣ Fetch full info for PDF & log
$info_q = $conn->query("
    SELECT a.branch_code,
           a.branch,
           a.actual_shifts,
           a.total_amount,
           a.month_applicable,
           a.entered_name,
           a.entered_hris,
           a.approved_at,
           f.firm_name
    FROM tbl_admin_actual_security_firmwise a
    LEFT JOIN tbl_admin_security_firms f ON f.id = a.firm_id
    WHERE a.id = {$id}
    LIMIT 1
");
$info = $info_q ? $info_q->fetch_assoc() : null;

$branch_code_db = $info['branch_code']       ?? $branch_code;
$branch_db      = $info['branch']            ?? $branch;
$month_db       = $info['month_applicable']  ?? $month;
$firm_name      = $info['firm_name']         ?? '';
$total_amount   = (float)($info['total_amount'] ?? 0);
$approved_at    = $info['approved_at']       ?? date("Y-m-d H:i:s");
$entered_name   = $info['entered_name']      ?? '';
$entered_hris   = $info['entered_hris']      ?? '';

// 4️⃣ Generate PDF
$pdf_dir = __DIR__ . '/exports';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0777, true);
}

$pdf_name = generate_pdf_filename("single", $branch_db);
$pdf_path = $pdf_dir . "/" . $pdf_name;

$pdf = new FPDF();
$pdf->AddPage();

// Title
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,"Approved Security Record",0,1,'C');
$pdf->Ln(2);

// Header info
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,7,"Approved By: $name ($hris)",0,1);
$pdf->Cell(0,7,"Approved At: ".$approved_at,0,1);
if ($month_db !== '') {
    $pdf->Cell(0,7,"Applicable Month: ".$month_db,0,1);
}
if ($entered_name !== '' || $entered_hris !== '') {
    $by = $entered_name;
    if ($entered_hris !== '') {
        $by .= $by !== '' ? " ($entered_hris)" : $entered_hris;
    }
    // 🔹 wording changed here
    $pdf->Cell(0,7,"Added / Entered By: ".$by,0,1);
}
$pdf->Ln(5);

// Details table
$pdf->SetFont('Arial','B',12);
$pdf->Cell(50,10,"Branch Code:",1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(140,10,$branch_code_db,1,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(50,10,"Branch:",1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(140,10,$branch_db,1,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(50,10,"Security Firm:",1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(140,10,($firm_name !== '' ? $firm_name : '-'),1,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(50,10,"Actual Shifts:",1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(140,10,(string)($info['actual_shifts'] ?? 0),1,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(50,10,"Amount:",1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(140,10,"Rs. ".number_format($total_amount,2),1,1);

$pdf->Output('F', $pdf_path);

// 5️⃣ Log into common PDF log
$module      = 'security';
$pdf_type    = 'single';
$record_ids  = (string)$id;
$month_log   = $month_db;
$entity_label = ($firm_name !== '' ? $firm_name.' - ' : '') .
                $branch_code_db.' '.$branch_db;

$stmt3 = $conn->prepare("
    INSERT INTO tbl_admin_pdf_log 
    (module, pdf_name, pdf_type, record_ids, month_applicable, entity_label,
     approved_by_hris, approved_by_name, generated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt3->bind_param(
    "ssssssss",
    $module,
    $pdf_name,
    $pdf_type,
    $record_ids,
    $month_log,
    $entity_label,
    $hris,
    $name
);
$stmt3->execute();

// 6️⃣ User log + JSON
userlog(sprintf(
    "✔️ Security record approved | HRIS: %s | User: %s | Record ID: %d | Branch: %s (%s) | Month: %s | IP: %s",
    $hris ?: 'N/A',
    $name ?: 'Unknown',
    $id,
    $branch_db ?: 'N/A',
    $branch_code_db ?: 'N/A',
    $month_db ?: 'N/A',
    $ip
));

echo json_encode([
    "status"  => "success",
    "message" => "Security record approved successfully.",
    "pdf_url" => "exports/" . $pdf_name
]);
