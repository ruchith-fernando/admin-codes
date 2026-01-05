<?php
// ajax-submit-kangaroo-entry.php
session_start();
header('Content-Type: application/json');
include 'connections/connection.php';

const K_UPLOAD_DIR   = 'uploads/kangaroo';
const K_MAX_BYTES    = 5 * 1024 * 1024; // 5 MB
const K_ALLOW_EXT    = ['pdf','jpg','jpeg','png','gif'];
const K_ALLOW_MIME   = ['application/pdf','image/jpeg','image/png','image/gif'];

function save_location_if_new($conn, $location) {
  $check = $conn->prepare("SELECT id FROM tbl_admin_locations WHERE location_name = ?");
  $check->bind_param("s", $location);
  $check->execute();
  $check->store_result();
  if ($check->num_rows == 0) {
    $insert = $conn->prepare("INSERT INTO tbl_admin_locations (location_name) VALUES (?)");
    $insert->bind_param("s", $location);
    $insert->execute();
    $insert->close();
  }
  $check->close();
}
function save_department_if_new($conn, $department) {
  $check = $conn->prepare("SELECT id FROM tbl_admin_departments WHERE department_name = ?");
  $check->bind_param("s", $department);
  $check->execute();
  $check->store_result();
  if ($check->num_rows == 0) {
    $insert = $conn->prepare("INSERT INTO tbl_admin_departments (department_name) VALUES (?)");
    $insert->bind_param("s", $department);
    $insert->execute();
    $insert->close();
  }
  $check->close();
}

function validate_upload(array $f): array {
  if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) {
    $err = [
      UPLOAD_ERR_INI_SIZE=>'upload_max_filesize exceeded',
      UPLOAD_ERR_FORM_SIZE=>'MAX_FILE_SIZE exceeded',
      UPLOAD_ERR_PARTIAL=>'File partially uploaded',
      UPLOAD_ERR_NO_FILE=>'No file uploaded',
      UPLOAD_ERR_NO_TMP_DIR=>'Missing temp folder',
      UPLOAD_ERR_CANT_WRITE=>'Failed to write file to disk',
      UPLOAD_ERR_EXTENSION=>'PHP extension blocked upload'
    ][$f['error']] ?? 'Upload failed';
    return [false, '', "Upload error: $err"];
  }
  if (!isset($f['size']) || $f['size'] <= 0 || $f['size'] > K_MAX_BYTES) {
    return [false, '', 'Invalid file size. Max is '.number_format(K_MAX_BYTES/1024/1024,1).' MB'];
  }
  $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
  if (!in_array($ext, K_ALLOW_EXT, true)) return [false, '', 'Unsupported file type'];
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($f['tmp_name']) ?: '';
  if (!in_array($mime, K_ALLOW_MIME, true)) return [false, '', 'Invalid content type'];

  $fh = @fopen($f['tmp_name'], 'rb'); if (!$fh) return [false,'','Could not read upload'];
  $head = fread($fh, 8); fclose($fh);
  $isPdf = ($ext==='pdf')  && ($mime==='application/pdf') && (substr($head,0,5)==='%PDF-');
  $isJpg = (in_array($ext,['jpg','jpeg'],true)) && ($mime==='image/jpeg') && (substr($head,0,3)==="\xFF\xD8\xFF");
  $isPng = ($ext==='png')  && ($mime==='image/png')  && ($head === "\x89PNG\r\n\x1A\n");
  $isGif = ($ext==='gif')  && ($mime==='image/gif')  && (substr($head,0,3)==="GIF");
  if (!($isPdf||$isJpg||$isPng||$isGif)) return [false,'','File signature mismatch'];
  return [true,$ext,''];
}

try {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status'=>'danger','message'=>'Invalid request method']); exit;
  }

  // Pick HRIS from session (flexible keys)
  $hris = trim($_SESSION['hris'] ?? $_SESSION['HRIS'] ?? $_SESSION['HRIS_ID'] ?? '');
  if ($hris === '') { $hris = 'NA'; } // fallback if not logged

  $voucher_no     = trim($_POST['voucher_no'] ?? '');
  $date           = $_POST['date'] ?? '';
  $start_location = trim($_POST['start_location'] ?? '');
  $end_location   = trim($_POST['end_location'] ?? '');
  $total_km       = isset($_POST['total_km']) ? (float)$_POST['total_km'] : 0.0;

  // strip commas just in case
  $additional_charges = isset($_POST['additional_charges']) ? (float)str_replace(',', '', $_POST['additional_charges']) : 0.0;
  $total              = isset($_POST['total']) ? (float)str_replace(',', '', $_POST['total']) : 0.0;

  $passengers   = trim($_POST['passengers'] ?? '');
  $vehicle_no   = trim($_POST['vehicle_no'] ?? '');
  $cab_number   = trim($_POST['cab_number'] ?? '');
  $department   = trim($_POST['department'] ?? '');

  if (!$voucher_no || !$date || !$start_location || !$end_location || $total_km <= 0 || $total <= 0 || !$vehicle_no || !$department) {
    echo json_encode(['status'=>'danger','message'=>"Please fill in all required fields."]); exit;
  }

  if (!isset($_FILES['chit'])) {
    echo json_encode(['status'=>'danger','message'=>"Please upload a chit/invoice file."]); exit;
  }

  [$ok, $safeExt, $err] = validate_upload($_FILES['chit']);
  if (!$ok) { echo json_encode(['status'=>'danger','message'=>$err]); exit; }

  if (!is_dir(K_UPLOAD_DIR)) { if (!@mkdir(K_UPLOAD_DIR, 0775, true)) { echo json_encode(['status'=>'danger','message'=>"Failed to create upload directory"]); exit; } }
  if (!is_writable(K_UPLOAD_DIR)) { echo json_encode(['status'=>'danger','message'=>"Upload directory not writable"]); exit; }

  $new_file_name = 'chit_' . bin2hex(random_bytes(8)) . '.' . $safeExt;
  $target_path   = rtrim(K_UPLOAD_DIR, '/').'/'.$new_file_name;
  if (!@move_uploaded_file($_FILES['chit']['tmp_name'], $target_path)) {
    echo json_encode(['status'=>'danger','message'=>"File upload failed. Please try again."]); exit;
  }
  @chmod($target_path, 0640);

  save_location_if_new($conn, $start_location);
  save_location_if_new($conn, $end_location);
  save_department_if_new($conn, $department);

  // IMPORTANT: Table must have columns created_by_hris (VARCHAR) and created_at (DATETIME default NOW())
  $stmt = $conn->prepare("INSERT INTO tbl_admin_kangaroo_transport 
    (voucher_no, date, start_location, end_location, total_km, additional_charges, total, passengers, vehicle_no, cab_number, department, chit_file, created_by_hris, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
  if (!$stmt) { echo json_encode(['status'=>'danger','message'=>"Database error (prepare): ". $conn->error]); exit; }

  $stmt->bind_param(
    "ssssdddssssss",
    $voucher_no, $date, $start_location, $end_location, $total_km, $additional_charges, $total,
    $passengers, $vehicle_no, $cab_number, $department, $new_file_name, $hris
  );

  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Kangaroo transport entry submitted successfully!']); 
  } else {
    echo json_encode(['status'=>'danger','message'=>"Database error: ".$stmt->error]);
  }
  $stmt->close();
  $conn->close();
  exit;

} catch (Throwable $e) {
  echo json_encode(['status'=>'danger','message'=>'Server error: '.$e->getMessage()]);
  exit;
}
