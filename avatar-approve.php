<?php
// avatar-approve.php (hardened + whitelist + column autodetect + clean JSON)
session_start();
header('Content-Type: application/json; charset=utf-8');
ob_start();

function reply($arr, $code=200){
  http_response_code($code);
  if (ob_get_length()) { ob_clean(); }
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}
function logline($msg){
  $line = '['.date('c').'] '.$msg."\n";
  @file_put_contents(__DIR__.'/logs/avatar-approve.log', $line, FILE_APPEND);
}

if (empty($_SESSION['hris'])) { logline('401 not logged in'); reply(['ok'=>0,'error'=>'Unauthorized'], 401); }

require_once 'connections/connection.php';
if (!isset($conn) || !($conn instanceof mysqli)) { if (isset($con) && $con instanceof mysqli) { $conn = $con; } }
if (!($conn instanceof mysqli)) { logline('500 no db'); reply(['ok'=>0,'error'=>'DB unavailable'], 500); }
mysqli_set_charset($conn, 'utf8mb4');

/* ---------- permission gate ---------- */
$APPROVER_WHITELIST = ['01006428']; // add more HRIS later
$me_hris = $_SESSION['hris'];
$me_role = strtolower($_SESSION['user_level'] ?? '');

function hasAccess($conn,$hris,$key){
  $h = mysqli_real_escape_string($conn,$hris);
  $k = mysqli_real_escape_string($conn,$key);
  $q = "SELECT 1 FROM tbl_admin_user_page_access
        WHERE hris_id='$h' AND menu_key='$k'
          AND (LOWER(is_allowed)='yes' OR is_allowed='1' OR is_allowed=1 OR LOWER(is_allowed)='true')
        LIMIT 1";
  $r = mysqli_query($conn,$q);
  return ($r && mysqli_num_rows($r)>0);
}

$isApprover = (
  $me_role==='admin' ||
  $me_role==='super-admin' ||
  in_array($me_hris, $APPROVER_WHITELIST, true) ||
  hasAccess($conn,$me_hris,'avatar_approve')
);

if (!$isApprover) { logline("403 actor=$me_hris"); reply(['ok'=>0,'error'=>'Forbidden'], 403); }

/* ---------- inputs ---------- */
$target_hris = trim($_POST['hris_id'] ?? '');
$action      = trim($_POST['action'] ?? '');
$reason      = trim((string)($_POST['reason'] ?? ''));
$remark_id   = (int)($_POST['remark_id'] ?? 0);

if ($target_hris==='' || !in_array($action, ['approve','reject'], true)) {
  logline("400 bad request actor=$me_hris target=$target_hris action=$action");
  reply(['ok'=>0,'error'=>'Bad request']);
}
if ($action==='reject' && $reason==='') {
  logline("400 reason required actor=$me_hris target=$target_hris");
  reply(['ok'=>0,'error'=>'Reason required']);
}

$esc_t = mysqli_real_escape_string($conn,$target_hris);
$esc_m = mysqli_real_escape_string($conn,$me_hris);

/* ---------- ensure pending exists ---------- */
$res = mysqli_query($conn, "SELECT pending_path, pending_mime, submitted_by
                            FROM tbl_admin_user_profile
                            WHERE hris_id='$esc_t' AND pending_path IS NOT NULL
                            LIMIT 1");
if (!$res || mysqli_num_rows($res)===0) {
  logline("404 no pending actor=$me_hris target=$target_hris");
  reply(['ok'=>0,'error'=>'No pending record']);
}
$row = mysqli_fetch_assoc($res);
$pending  = $row['pending_path'] ?? '';
$pmime    = $row['pending_mime'] ?? '';
$uploader = $row['submitted_by'] ?: $target_hris;

/* ---------- detect optional columns ---------- */
$cols = [];
$rc = mysqli_query($conn, "SHOW COLUMNS FROM tbl_admin_user_profile");
if ($rc) { while ($c = mysqli_fetch_assoc($rc)) { $cols[$c['Field']] = true; } }

/* helper for older PHP versions */
function starts_with($haystack, $needle){ return strncmp($haystack, $needle, strlen($needle)) === 0; }

/* ---------- approve ---------- */
if ($action==='approve') {
  $set = [];
  if (!empty($cols['avatar_path']))      $set[] = "avatar_path = pending_path";
  if (!empty($cols['avatar_mime']))      $set[] = "avatar_mime = pending_mime";
  if (!empty($cols['pending_path']))     $set[] = "pending_path = NULL";
  if (!empty($cols['pending_mime']))     $set[] = "pending_mime = NULL";
  if (!empty($cols['status']))           $set[] = "status = 'approved'";
  if (!empty($cols['reviewed_by']))      $set[] = "reviewed_by = '$esc_m'";
  if (!empty($cols['reviewed_at']))      $set[] = "reviewed_at = NOW()";
  if (!empty($cols['rejection_reason'])) $set[] = "rejection_reason = NULL";
  if (!empty($cols['updated_at']))       $set[] = "updated_at = NOW()";

  if (!$set) { logline("500 no columns to set (approve)"); reply(['ok'=>0,'error'=>'Server config error'], 500); }

  $sql = "UPDATE tbl_admin_user_profile SET ".implode(', ', $set)." WHERE hris_id='$esc_t' AND pending_path IS NOT NULL LIMIT 1";
  if (!mysqli_query($conn,$sql)) { logline("500 DB ".mysqli_error($conn)); reply(['ok'=>0,'error'=>'DB error: '.mysqli_error($conn)]); }
  if (mysqli_affected_rows($conn) < 1) { logline("409 nothing to update"); reply(['ok'=>0,'error'=>'Nothing to update']); }

  // notify (remarks)
  $cat = mysqli_real_escape_string($conn,'avatar_result');
  $rec = mysqli_real_escape_string($conn,$target_hris);
  $com = mysqli_real_escape_string($conn,'Your profile photo has been approved.');
  $org = mysqli_real_escape_string($conn,'home-content.php');

  mysqli_query($conn,"INSERT INTO tbl_admin_remarks (category, record_key, sr_number, comment, commented_at, origin_page, sender_hris, hris_id)
                      VALUES ('$cat','$rec',NULL,'$com',NOW(),'$org','$esc_m','$esc_m')");
  $rid = mysqli_insert_id($conn);
  if ($rid) {
    $to = mysqli_real_escape_string($conn,$uploader);
    mysqli_query($conn,"INSERT INTO tbl_admin_remarks_recipients (remark_id,recipient_hris,is_read) VALUES ($rid,'$to','no')");
  }
  if ($remark_id>0){
    $rid = (int)$remark_id;
    mysqli_query($conn,"UPDATE tbl_admin_remarks_recipients SET is_read='yes', read_at=NOW() WHERE remark_id=$rid AND recipient_hris='$esc_m'");
  }

  logline("200 approved actor=$me_hris target=$target_hris");
  reply(['ok'=>1,'message'=>'Approved']);
}

/* ---------- reject ---------- */
if ($action==='reject') {
  // delete pending file safely (only inside uploads/avatars)
  if ($pending) {
    $abs = __DIR__ . '/' . ltrim($pending,'/');
    $real = realpath($abs);
    $allowedDir = realpath(__DIR__ . '/uploads/avatars');
    if ($real && $allowedDir && (starts_with($real, $allowedDir . DIRECTORY_SEPARATOR) || $real=== $allowedDir) && is_file($real)) {
      @unlink($real);
    }
  }

  $esc_reason = mysqli_real_escape_string($conn,$reason);

  $set = [];
  if (!empty($cols['pending_path']))     $set[] = "pending_path = NULL";
  if (!empty($cols['pending_mime']))     $set[] = "pending_mime = NULL";
  if (!empty($cols['status']))           $set[] = "status = 'rejected'";
  if (!empty($cols['reviewed_by']))      $set[] = "reviewed_by = '$esc_m'";
  if (!empty($cols['reviewed_at']))      $set[] = "reviewed_at = NOW()";
  if (!empty($cols['rejection_reason'])) $set[] = "rejection_reason = '$esc_reason'";
  if (!empty($cols['updated_at']))       $set[] = "updated_at = NOW()";

  if (!$set) { logline("500 no columns to set (reject)"); reply(['ok'=>0,'error'=>'Server config error'], 500); }

  $sql = "UPDATE tbl_admin_user_profile SET ".implode(', ', $set)." WHERE hris_id='$esc_t' AND pending_path IS NOT NULL LIMIT 1";
  if (!mysqli_query($conn,$sql)) { logline("500 DB ".mysqli_error($conn)); reply(['ok'=>0,'error'=>'DB error: '.mysqli_error($conn)]); }
  if (mysqli_affected_rows($conn) < 1) { logline("409 nothing to update"); reply(['ok'=>0,'error'=>'Nothing to update']); }

  // notify (remarks)
  $cat = mysqli_real_escape_string($conn,'avatar_result');
  $rec = mysqli_real_escape_string($conn,$target_hris);
  $msg = 'Your profile photo was rejected.' . ($reason ? ' Reason: '.$reason : '');
  $com = mysqli_real_escape_string($conn,$msg);
  $org = mysqli_real_escape_string($conn,'home-content.php');

  mysqli_query($conn,"INSERT INTO tbl_admin_remarks (category, record_key, sr_number, comment, commented_at, origin_page, sender_hris, hris_id)
                      VALUES ('$cat','$rec',NULL,'$com',NOW(),'$org','$esc_m','$esc_m')");
  $rid = mysqli_insert_id($conn);
  if ($rid) {
    $to = mysqli_real_escape_string($conn,$uploader);
    mysqli_query($conn,"INSERT INTO tbl_admin_remarks_recipients (remark_id,recipient_hris,is_read) VALUES ($rid,'$to','no')");
  }
  if ($remark_id>0){
    $rid = (int)$remark_id;
    mysqli_query($conn,"UPDATE tbl_admin_remarks_recipients SET is_read='yes', read_at=NOW() WHERE remark_id=$rid AND recipient_hris='$esc_m'");
  }

  logline("200 rejected actor=$me_hris target=$target_hris");
  reply(['ok'=>1,'message'=>'Rejected']);
}

reply(['ok'=>0,'error'=>'Unhandled'], 400);
