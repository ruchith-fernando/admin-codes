<?php
// water-bulk-approve.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
require_once 'fpdf/fpdf.php';
require_once 'includes/pdf_namer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$current_hris  = $_SESSION['hris'] ?? '';
$current_name  = $_SESSION['name'] ?? '';
$ip            = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

$ids_raw = $_POST['ids'] ?? '';
$id_list = array_filter(array_map('intval', explode(',', $ids_raw)));
$id_list = array_values(array_unique($id_list));

if (!$id_list) {
    echo json_encode(["success"=>false,"message"=>"Invalid request"]);
    exit;
}

$id_str = implode(",", $id_list);

/* 1️⃣ Approve (dual control: skip own entries) */
$sql = "
    UPDATE tbl_admin_actual_water
    SET approval_status='approved',
        approved_hris='" . mysqli_real_escape_string($conn, $current_hris) . "',
        approved_name='" . mysqli_real_escape_string($conn, $current_name) . "',
        approved_at=NOW()
    WHERE id IN ($id_str)
      AND (approval_status='pending' OR approval_status IS NULL)
      AND (entered_hris IS NULL OR TRIM(entered_hris) <> '" . mysqli_real_escape_string($conn, $current_hris) . "')
";

mysqli_query($conn, $sql);
$count = mysqli_affected_rows($conn);

/* LOG */
userlog(sprintf(
    "✅ Water bulk approval completed | Approved By: %s (%s) | Record Count: %s | Record IDs: %s | IP: %s",
    $current_name,
    $current_hris,
    $count,
    $id_str,
    $ip
));

if ($count <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "No records approved. They may all be non-pending or your own entries."
    ]);
    exit;
}

/* 2️⃣ Fetch approved rows for PDF */
$res = mysqli_query($conn,"
    SELECT branch_code, branch, total_amount, month_applicable
    FROM tbl_admin_actual_water
    WHERE id IN ($id_str) AND approval_status='approved'
");

/* Create folder */
$pdf_dir = __DIR__ . '/exports';
if (!is_dir($pdf_dir)) mkdir($pdf_dir,0777,true);

/* Filename */
$pdf_name = generate_pdf_filename("bulk");
$pdf_path = $pdf_dir . "/" . $pdf_name;

/* 3️⃣ Generate PDF */
$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,"Bulk Approved Water Records",0,1,'C');
$pdf->Ln(4);

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,7,"Approved By: $current_name ($current_hris)",0,1);
$pdf->Cell(0,7,"Approved At: ".date("Y-m-d H:i:s"),0,1);
$pdf->Ln(6);

/* Table header */
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(230,230,230);
$pdf->Cell(30,9,"Code",1,0,"C",true);
$pdf->Cell(100,9,"Branch",1,0,"C",true);
$pdf->Cell(60,9,"Amount",1,1,"C",true);

$pdf->SetFont('Arial','',11);

$month_for_log = "";
while($row = mysqli_fetch_assoc($res)) {

    if ($month_for_log === "") {
        $month_for_log = $row['month_applicable'];
    }

    $pdf->Cell(30,9,$row['branch_code'],1);
    $pdf->Cell(100,9,$row['branch'],1);
    $pdf->Cell(60,9,"Rs. ".number_format($row['total_amount'],2),1,1);
}

$pdf->Output('F',$pdf_path);

/* 4️⃣ Log into common PDF table (module = 'water') */
$record_ids   = $id_str;
$module       = 'water';
$pdf_type     = 'bulk';
$entity_label = "Water bulk approval ({$count} record(s))";

$stmt = $conn->prepare("
    INSERT INTO tbl_admin_pdf_log 
    (module, pdf_name, pdf_type, record_ids, month_applicable, 
     entity_label, approved_by_hris, approved_by_name, generated_at)
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
    $current_hris,
    $current_name
);

$stmt->execute();

/* 5️⃣ Response */
echo json_encode([
    "success"=>true,
    "message"=>"Bulk approval completed ({$count} record(s)).",
    "pdf_url"=>"exports/".$pdf_name
]);
