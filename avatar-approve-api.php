<?php
// pages/avatar_approve_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}
function logline($msg){
  @file_put_contents('avatar_approve.log', '['.date('c').'] '.$msg."\n", FILE_APPEND);
}

if (empty($_SESSION['hris'])) { logline('401 Unauthorized'); out(['ok'=>0,'error'=>'Unauthorized'], 401); }

require_once 'connections/connection.php';
if (!isset($conn) || !($conn instanceof mysqli)) { if (isset($con) && $con instanceof mysqli) { $conn = $con; } }
if (!($conn instanceof mysqli)) { logline('500 DB unavailable'); out(['ok'=>0,'error'=>'DB unavailable'], 500); }
mysqli_set_charset($conn, 'utf8mb4');

/* ---------- Approver gating (same whitelist) ---------- */
$APPROVER_WHITELIST = ['01006428'];   // add more HRIS ids here later
$me = $_SESSION['hris'];
if (!in_array($me, $APPROVER_WHITELIST, true)) { logline("403 Forbidden actor=$me"); out(['ok'=>0,'error'=>'Forbidden'], 403); }

/* ---------- inputs ---------- */
$action = trim($_POST['action'] ?? '');
$target = trim($_POST['hris_id'] ?? '');
$reason = trim($_POST['reason'] ?? '');

if (!in_array($action, ['approve','reject'], true) || $target===''){
  out(['ok'=>0,'error'=>'Bad request'], 400);
}
if ($action==='reject' && $reason===''){
  out(['ok'=>0,'error'=>'Reason required'], 400);
}

$esc_t = mysqli_real_escape_string($conn, $target);
$esc_m = mysqli_real_escape_string($conn, $me);

/* ---------- fetch pending ---------- */
$q = "SELECT pending_path, pending_mime, submitted_by FROM tbl_admin_user_profile
      WHERE hris_id='$esc_t' AND pending_path IS NOT NULL LIMIT 1";
$r = mysqli_query($conn, $q);
if (!$r || mysqli_num_rows($r)===0){
  out(['ok'=>0,'error'=>'No pending record'], 404);
}
$row = mysqli_fetch_assoc($r);
$pending  = $row['pending_path'] ?? '';
$pmime    = $row['pending_mime'] ?? '';
$uploader = $row['submitted_by'] ?: $target;

/* ---------- column detection (so we don't error on missing fields) ---------- */
$have = [];
$cr = mysqli_query($conn, "SHOW COLUMNS FROM tbl_admin_user_profile");
if ($cr) while ($c = mysqli_fetch_assoc($cr)) $have[$c['Field']] = true;

function starts_with($h,$n){ return strncmp($h,$n,strlen($n))===0; }

/* ---------- approve ---------- */
if ($action==='approve'){
  $set = [];
  if (!empty($have['avatar_path']))      $set[] = "avatar_path = pending_path";
  if (!empty($have['avatar_mime']))      $set[] = "avatar_mime = pending_mime";
  if (!empty($have['pending_path']))     $set[] = "pending_path = NULL";
  if (!empty($have['pending_mime']))     $set[] = "pending_mime = NULL";
  if (!empty($have['status']))           $set[] = "status = 'approved'";
  if (!empty($have['reviewed_by']))      $set[] = "reviewed_by = '$esc_m'";
  if (!empty($have['reviewed_at']))      $set[] = "reviewed_at = NOW()";
  if (!empty($have['rejection_reason'])) $set[] = "rejection_reason = NULL";
  if (!empty($have['updated_at']))       $set[] = "updated_at = NOW()";

  if (!$set) out(['ok'=>0,'error'=>'Server config error'], 500);

  $u = "UPDATE tbl_admin_user_profile SET ".implode(', ',$set)." WHERE hris_id='$esc_t' AND pending_path IS NOT NULL LIMIT 1";
  if (!mysqli_query($conn,$u)) out(['ok'=>0,'error'=>'DB error: '.mysqli_error($conn)], 500);
  if (mysqli_affected_rows($conn) < 1) out(['ok'=>0,'error'=>'Nothing to update'], 409);

  logline("APPROVED by $me for $target");
  out(['ok'=>1,'message'=>'Approved']);
}

/* ---------- reject ---------- */
if ($action==='reject'){
  // delete file safely inside /uploads/avatars
  if ($pending){
    $abs = realpath(__DIR__ . '/../' . ltrim($pending,'/'));
    $allowed = realpath('uploads/avatars');
    if ($abs && $allowed && (starts_with($abs, $allowed . DIRECTORY_SEPARATOR) || $abs===$allowed) && is_file($abs)){
      @unlink($abs);
    }
  }

  $esc_reason = mysqli_real_escape_string($conn, $reason);

  $set = [];
  if (!empty($have['pending_path']))     $set[] = "pending_path = NULL";
  if (!empty($have['pending_mime']))     $set[] = "pending_mime = NULL";
  if (!empty($have['status']))           $set[] = "status = 'rejected'";
  if (!empty($have['reviewed_by']))      $set[] = "reviewed_by = '$esc_m'";
  if (!empty($have['reviewed_at']))      $set[] = "reviewed_at = NOW()";
  if (!empty($have['rejection_reason'])) $set[] = "rejection_reason = '$esc_reason'";
  if (!empty($have['updated_at']))       $set[] = "updated_at = NOW()";

  if (!$set) out(['ok'=>0,'error'=>'Server config error'], 500);

  $u = "UPDATE tbl_admin_user_profile SET ".implode(', ',$set)." WHERE hris_id='$esc_t' AND pending_path IS NOT NULL LIMIT 1";
  if (!mysqli_query($conn,$u)) out(['ok'=>0,'error'=>'DB error: '.mysqli_error($conn)], 500);
  if (mysqli_affected_rows($conn) < 1) out(['ok'=>0,'error'=>'Nothing to update'], 409);

  logline("REJECTED by $me for $target");
  out(['ok'=>1,'message'=>'Rejected']);
}

out(['ok'=>0,'error'=>'Unhandled'], 400);
