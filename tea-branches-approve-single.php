<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
require_once 'fpdf/fpdf.php';
require_once 'includes/pdf_namer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$id   = (int)($_POST['id'] ?? 0);
$hris = trim($_SESSION['hris'] ?? '');
$name = trim($_SESSION['name'] ?? '');
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

if(!$id){
  echo json_encode(["status"=>"error","message"=>"Invalid record."]);
  exit;
}

// Fetch for dual control
$stmt = $conn->prepare("
  SELECT entered_hris, approval_status, month_applicable
  FROM tbl_admin_actual_tea_branches
  WHERE id=? LIMIT 1
");
$stmt->bind_param("i",$id);
$stmt->execute();
$res = $stmt->get_result();

if(!$res || $res->num_rows===0){
  echo json_encode(["status"=>"error","message"=>"Record not found."]);
  exit;
}

$row = $res->fetch_assoc();
$entered_hris = trim((string)($row['entered_hris'] ?? ''));
$status = strtolower(trim((string)($row['approval_status'] ?? 'pending')));
$month  = trim((string)($row['month_applicable'] ?? ''));

if($entered_hris !== '' && $hris !== '' && $entered_hris === $hris){
  echo json_encode(["status"=>"error","message"=>"You cannot approve a record you entered (dual control)."]);
  exit;
}
if(in_array($status, ['approved','rejected','deleted'], true)){
  echo json_encode(["status"=>"error","message"=>"Record is already processed."]);
  exit;
}

// Approve
$upd = $conn->prepare("
  UPDATE tbl_admin_actual_tea_branches
  SET approval_status='approved',
      approved_hris=?,
      approved_name=?,
      approved_by=?,
      approved_at=NOW()
  WHERE id=?
    AND (approval_status='pending' OR approval_status IS NULL)
  LIMIT 1
");
$approved_by = $name;
$upd->bind_param("sssi", $hris, $name, $approved_by, $id);
$upd->execute();

if($upd->affected_rows <= 0){
  echo json_encode(["status"=>"error","message"=>"Record already approved or not pending."]);
  exit;
}

// Fetch approved details for PDF
$info = mysqli_fetch_assoc(mysqli_query($conn, "
  SELECT branch_code, branch, total_amount, approved_at, month_applicable
  FROM tbl_admin_actual_tea_branches
  WHERE id=$id
"));

$branch_code_db = $info['branch_code'] ?? '';
$branch_db      = $info['branch'] ?? '';
$total_amount   = $info['total_amount'] ?? 0;
$approved_at    = $info['approved_at'] ?? '';
$month_db       = $info['month_applicable'] ?? $month;

// Create exports folder
$pdf_dir = __DIR__ . '/exports';
if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0777, true);

// Filename
$pdf_name = generate_pdf_filename("single", $branch_db);
$pdf_path = $pdf_dir . "/" . $pdf_name;

// PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,"Approved Tea Branch Record",0,1,'C');
$pdf->Ln(4);

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,7,"Approved By: $name ($hris)",0,1);
$pdf->Cell(0,7,"Approved At: ".$approved_at,0,1);
$pdf->Cell(0,7,"Month: ".$month_db,0,1);
$pdf->Ln(5);

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
$pdf->Cell(140,10,"Rs. ".number_format((float)$total_amount,2),1,1);

$pdf->Output('F',$pdf_path);

// PDF log (same table as water)
$module       = 'tea_branches';
$pdf_type     = 'single';
$record_ids   = (string)$id;
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
  $month_db,
  $entity_label,
  $hris,
  $name
);
$stmt3->execute();

// User log
userlog("✔️ Tea Branch approved | HRIS:$hris | User:$name | Record:$id | Month:$month_db | IP:$ip");

echo json_encode([
  "status"=>"success",
  "message"=>"Record approved successfully.",
  "pdf_url"=>"exports/".$pdf_name
]);
