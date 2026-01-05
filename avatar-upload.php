<?php
// avatar-upload.php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['hris'])) {
  http_response_code(401);
  echo json_encode(['ok'=>0,'error'=>'Unauthorized']);
  exit;
}

require_once 'connections/connection.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
  if (isset($con) && $con instanceof mysqli) { $conn = $con; }
}
if (!($conn instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['ok'=>0,'error'=>'DB unavailable']);
  exit;
}
mysqli_set_charset($conn, 'utf8mb4');

$u_hris = $_SESSION['hris'];
$u_name = $_SESSION['name'] ?? 'User';

/* ---------- Validate upload ---------- */
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['ok'=>0,'error'=>'No file or upload error']); exit;
}

$allowed = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp'
];

$tmp  = $_FILES['avatar']['tmp_name'];
$size = (int)($_FILES['avatar']['size'] ?? 0);
if ($size <= 0 || $size > 2*1024*1024) {
  echo json_encode(['ok'=>0,'error'=>'Max size 2MB']); exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $tmp);
finfo_close($finfo);
if (!isset($allowed[$mime])) {
  echo json_encode(['ok'=>0,'error'=>'Only JPG/PNG/WEBP allowed']); exit;
}

/* ---------- Store pending file ---------- */
$ext      = $allowed[$mime];
$dirPublic = 'uploads/avatars';
$dirFs     = __DIR__ . '/uploads/avatars';
if (!is_dir($dirFs)) { @mkdir($dirFs, 0755, true); }

$safe  = preg_replace('/[^A-Za-z0-9_\-]/', '_', $u_hris);
$fname = $safe . '_P_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext; // P = pending
$dest  = $dirFs . '/' . $fname;
$web   = $dirPublic . '/' . $fname;

if (!move_uploaded_file($tmp, $dest)) {
  echo json_encode(['ok'=>0,'error'=>'Save failed']); exit;
}

/* ---------- Upsert profile row to PENDING ---------- */
$esc_hris = mysqli_real_escape_string($conn, $u_hris);
$esc_web  = mysqli_real_escape_string($conn, $web);
$esc_mime = mysqli_real_escape_string($conn, $mime);

$upsert = "
  INSERT INTO tbl_admin_user_profile
    (hris_id, pending_path, pending_mime, submitted_by, submitted_at, status, updated_at)
  VALUES
    ('$esc_hris', '$esc_web', '$esc_mime', '$esc_hris', NOW(), 'pending', NOW())
  ON DUPLICATE KEY UPDATE
    pending_path = VALUES(pending_path),
    pending_mime = VALUES(pending_mime),
    submitted_by = VALUES(submitted_by),
    submitted_at = VALUES(submitted_at),
    status       = 'pending',
    updated_at   = NOW()
";
if (!mysqli_query($conn, $upsert)) {
  echo json_encode(['ok'=>0,'error'=>'DB error: '.mysqli_error($conn)]); exit;
}

/* ======================================================================
   Special Note to APPROVER ALLOW-LIST ONLY (config-special-alerts.php)
   ====================================================================== */

$cfg = 'config-special-alerts.php';
$approverIds = [];
if (is_array($cfg) && isset($cfg['avatar_approvers']) && is_array($cfg['avatar_approvers'])) {
  $approverIds = $cfg['avatar_approvers'];
}
if (empty($approverIds)) {
  // Fallback to the single requested HRIS if config is missing/empty
  $approverIds = ['01006428'];
}

/* Normalize and dedupe; optionally avoid notifying the uploader */
$approverIds = array_values(array_unique(array_filter(array_map('strval', $approverIds))));
$approverIds = array_filter($approverIds, function($id) use ($u_hris) {
  // If you want approvers to also receive their own submissions, remove this check:
  return $id !== $u_hris;
});

if (!empty($approverIds)) {
  $category   = 'avatar_approval';
  $record_key = $u_hris; // approver UI will look up pending by HRIS
  $comment    = $u_name . " ($u_hris) uploaded a new profile photo. Please review.";
  $origin     = 'avatar-approvals.php';

  // Insert remark (use prepared statements)
  $sqlR = "INSERT INTO tbl_admin_remarks
           (category, record_key, sr_number, comment, commented_at, origin_page, origin_url, sender_hris, hris_id)
           VALUES (?, ?, NULL, ?, NOW(), ?, ?, ?, ?)";
  if ($stmtR = mysqli_prepare($conn, $sqlR)) {
    $origin_url = $origin; // same
    mysqli_stmt_bind_param($stmtR, 'sssssss',
      $category, $record_key, $comment, $origin, $origin_url, $u_hris, $u_hris
    );
    $okR = mysqli_stmt_execute($stmtR);
    $remarkId = $okR ? mysqli_insert_id($conn) : 0;
    mysqli_stmt_close($stmtR);

    if ($remarkId) {
      $sqlRc = "INSERT INTO tbl_admin_remarks_recipients (remark_id, recipient_hris, is_read, read_at)
                VALUES (?, ?, 'no', NULL)";
      if ($stmtRc = mysqli_prepare($conn, $sqlRc)) {
        foreach ($approverIds as $rid) {
          $ridStr = (string)$rid;
          mysqli_stmt_bind_param($stmtRc, 'is', $remarkId, $ridStr);
          mysqli_stmt_execute($stmtRc); // ignore per-recipient failures
        }
        mysqli_stmt_close($stmtRc);
      }
    }
  }
}

/* ---------- Done ---------- */
echo json_encode([
  'ok'        => 1,
  'submitted' => 1,
  'message'   => 'Submitted for approval'
]);
