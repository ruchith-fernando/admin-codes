<?php
session_start();
require_once 'connections/connection.php';

function deny($code=403, $msg='Forbidden'){
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  echo $msg;
  exit;
}

if (empty($_SESSION['hris'])) deny(401, 'Unauthorized');

$type  = $_GET['type'] ?? '';
$id    = (int)($_GET['id'] ?? 0);
$field = $_GET['field'] ?? '';
$idx   = isset($_GET['i']) ? (int)$_GET['i'] : null;

$map = [
  'maintenance' => 'tbl_admin_vehicle_maintenance',
  'service'     => 'tbl_admin_vehicle_service',
  'license'     => 'tbl_admin_vehicle_licensing_insurance',
];

$table = $map[$type] ?? '';
if (!$table || !$id) deny(400, 'Bad request');

$allowedFields = [
  'maintenance' => ['bill_upload', 'warranty_card_upload', 'image_path'],
  'service'     => ['bill_upload'],
  'license'     => [],
];

if (!in_array($field, $allowedFields[$type] ?? [], true)) deny(400, 'Invalid field');

$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("SELECT id, entered_by, {$field} AS f FROM {$table} WHERE id=? LIMIT 1");
if (!$stmt) deny(500, 'SQL error');
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || !$res->num_rows) deny(404, 'Not found');
$row = $res->fetch_assoc();
$stmt->close();

$val = (string)($row['f'] ?? '');
if ($val === '') deny(404, 'No file');

$path = '';

if ($field === 'image_path') {
  $arr = json_decode($val, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
    if ($idx === null || !isset($arr[$idx])) deny(404, 'Attachment not found');
    $path = (string)$arr[$idx];
  } else {
    // image_path might not be JSON (older records)
    $path = $val;
  }
} else {
  $path = $val;
}

$path = str_replace('\\','/', trim($path));
if ($path === '' || preg_match('#^[a-zA-Z]+:#', $path)) deny(400, 'Unsafe path');

// Build absolute file path (your uploads are inside /pages/uploads/...)
$abs = '';
$docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');

// Most common: saved as "uploads/maintenance/xxx.pdf" from /pages
if (strpos($path, 'uploads/') === 0) {
  $abs = __DIR__ . '/' . $path; // /pages/uploads/...
}
// Saved as "/pages/uploads/..."
elseif (strpos($path, '/pages/uploads/') === 0) {
  $abs = $docroot . $path;
}
// Saved as "pages/uploads/..."
elseif (strpos($path, 'pages/uploads/') === 0) {
  $abs = $docroot . '/' . $path;
}
// Saved as "/uploads/..." (site root uploads)
elseif (strpos($path, '/uploads/') === 0) {
  $abs = $docroot . $path;
}
// Saved as "../uploads/..." (from /pages)
elseif (strpos($path, '../uploads/') === 0) {
  $abs = $docroot . '/uploads/' . substr($path, strlen('../uploads/'));
}
else {
  deny(400, 'Unknown path style');
}

// realpath + allowed base protection
$real = realpath($abs);
if (!$real || !is_file($real)) deny(404, 'File missing');

$allowedBase1 = realpath(__DIR__ . '/uploads');         // /pages/uploads
$allowedBase2 = $docroot ? realpath($docroot . '/uploads') : false; // /uploads

$ok = false;
if ($allowedBase1 && strpos($real, $allowedBase1) === 0) $ok = true;
if ($allowedBase2 && strpos($real, $allowedBase2) === 0) $ok = true;
if (!$ok) deny(403, 'Outside allowed folders');

// Serve file
$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
$mimeMap = [
  'pdf' => 'application/pdf',
  'jpg' => 'image/jpeg',
  'jpeg'=> 'image/jpeg',
  'png' => 'image/png',
  'gif' => 'image/gif',
  'webp'=> 'image/webp',
];
if (isset($mimeMap[$ext])) $mime = $mimeMap[$ext];

$filename = basename($real);
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

header('Content-Type: '.$mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Disposition: inline; filename="'.$filename.'"');
header('Content-Length: '.filesize($real));

readfile($real);
exit;
