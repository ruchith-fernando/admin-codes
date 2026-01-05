<?php
// water-approve-single.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
require_once 'fpdf/fpdf.php';
require_once 'includes/pdf_namer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$id     = intval($_POST['id'] ?? 0);
$branch = trim($_POST['branch'] ?? '');
$month  = trim($_POST['month'] ?? '');
$hris   = $_SESSION['hris'] ?? '';
$name   = $_SESSION['name'] ?? '';
$ip     = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

if (!$id) {
    echo json_encode(["status" => "error", "message" => "Invalid record."]);
    exit;
}

/* 1️⃣ Fetch record to enforce dual control */
$stmt = $conn->prepare("
    SELECT entered_hris, approval_status
    FROM tbl_admin_actual_water
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Record not found."]);
    exit;
}

$row             = $res->fetch_assoc();
$entered_hris    = trim((string)($row['entered_hris'] ?? ''));
$approval_status = $row['approval_status'] ?? 'pending';

if ($entered_hris !== '' && $hris !== '' && $entered_hris === $hris) {
    echo json_encode([
        "status"  => "error",
        "message" => "You cannot approve a record you entered (dual control)."
    ]);
    exit;
}

if (in_array($approval_status, ['approved','rejected','deleted'], true)) {
    echo json_encode([
        "status"  => "error",
        "message" => "Record is already processed."
    ]);
    exit;
}

/* 2️⃣ Approve the record */
$stmt2 = $conn->prepare("
    UPDATE tbl_admin_actual_water
    SET approval_status='approved',
        approved_hris=?,
        approved_name=?,
        approved_at=NOW()
    WHERE id=? 
      AND (approval_status='pending' OR approval_status IS NULL)
    LIMIT 1
");
$stmt2->bind_param("ssi", $hris, $name, $id);
$stmt2->execute();

if ($stmt2->affected_rows <= 0) {
    echo json_encode(["status" => "error", "message" => "Record already approved or not pending."]);
    exit;
}

/* 3️⃣ Fetch approved record details */
$info = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT branch_code, branch, total_amount, approved_at
    FROM tbl_admin_actual_water
    WHERE id=$id
"));

$branch_code_db = $info['branch_code'] ?? '';
$branch_db      = $info['branch'] ?? $branch;
$total_amount   = $info['total_amount'] ?? 0;

/* 4️⃣ Create folder for PDF */
$pdf_dir = __DIR__ . '/exports';
if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0777, true);

/* 5️⃣ Build filename */
$pdf_name = generate_pdf_filename("single", $branch_db);
$pdf_path = $pdf_dir . "/" . $pdf_name;

/* 6️⃣ Generate PDF */
$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,"Approved Water Record",0,1,'C');
$pdf->Ln(4);

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,7,"Approved By: $name ($hris)",0,1);
$pdf->Cell(0,7,"Approved At: ".$info['approved_at'],0,1);
$pdf->Ln(5);

/* Details */
$pdf->SetFont('Arial','B',12);
$pdf->Cell(50,10,"Branch Code:",1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(140,10,$branch_code_db,1,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(50,10,"Branch:",1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(140,10,$branch_db,1,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(50,10,"Amount:",1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(140,10,"Rs. ".number_format($total_amount,2),1,1);

$pdf->Output('F',$pdf_path);

/* 7️⃣ Log into common PDF log (module = 'water') */
$record_ids   = (string)$id;
$module       = 'water';
$pdf_type     = 'single';
$entity_label = $branch_db;

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
    $month,
    $entity_label,
    $hris,
    $name
);
$stmt3->execute();

/* 8️⃣ USER LOG */
userlog(sprintf(
    "✔️ Water record approved | HRIS: %s | User: %s | Record ID: %s | Branch: %s | Month: %s | IP: %s",
    $hris,
    $name,
    $id,
    $branch_db ?: 'N/A',
    $month ?: 'N/A',
    $ip
));

/* 9️⃣ Response */
echo json_encode([
    "status"=>"success",
    "message"=>"Record approved successfully.",
    "pdf_url"=>"exports/".$pdf_name
]);
