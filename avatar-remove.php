<?php
// avatar-remove.php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['loggedin'])) {
  http_response_code(401);
  echo json_encode(['ok'=>0,'error'=>'Unauthorized']); exit;
}

require_once 'connections/connection.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
  if (isset($con) && $con instanceof mysqli) { $conn = $con; }
}
if (!($conn instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['ok'=>0,'error'=>'DB connection not available']); exit;
}

$hris = $_SESSION['hris'] ?? '';
if ($hris === '') { echo json_encode(['ok'=>0,'error'=>'Missing HRIS']); exit; }

$esc = mysqli_real_escape_string($conn, (string)$hris);

// delete existing file if present
$old = null;
$qOld = "SELECT avatar_path FROM tbl_admin_user_profile WHERE hris_id='$esc' LIMIT 1";
if ($rs = mysqli_query($conn, $qOld)) {
  if (mysqli_num_rows($rs) > 0) {
    $row = mysqli_fetch_assoc($rs);
    $old = $row['avatar_path'] ?? null;
  }
}
if ($old) {
  $oldFs = __DIR__ . '/' . ltrim($old, '/');
  if (is_file($oldFs) && strpos($oldFs, __DIR__ . '/uploads/avatars') === 0) { @unlink($oldFs); }
}

$q = "INSERT INTO tbl_admin_user_profile (hris_id, avatar_path, avatar_mime, updated_at)
      VALUES ('$esc', NULL, NULL, NOW())
      ON DUPLICATE KEY UPDATE avatar_path=NULL, avatar_mime=NULL, updated_at=NOW()";
if (!mysqli_query($conn, $q)) {
  echo json_encode(['ok'=>0,'error'=>'DB error: '.mysqli_error($conn)]); exit;
}

echo json_encode(['ok'=>1]);
