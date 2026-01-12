<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db() {
  global $conn, $con, $mysqli;
  if (isset($conn) && $conn instanceof mysqli) return $conn;
  if (isset($con) && $con instanceof mysqli) return $con;
  if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
  return null;
}
function currentUserId(){
  foreach (['user_id','userid','uid','id','USER_ID','UID'] as $k){
    if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k];
  }
  return 0;
}
function alertHtml($type, $msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.
    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }

$action = strtoupper(trim($_POST['action'] ?? 'DRAFT'));
$gl_code = strtoupper(trim($_POST['gl_code'] ?? ''));
$gl_name = trim($_POST['gl_name'] ?? '');
$parent_gl_id = trim($_POST['parent_gl_id'] ?? '');
$maker_note = trim($_POST['maker_note'] ?? '');

if ($gl_code === '' || $gl_name === '') { echo alertHtml('danger','GL Code and GL Name are required.'); exit; }
if (!in_array($action, ['DRAFT','SUBMIT'], true)) $action = 'DRAFT';

$record_status = ($action === 'SUBMIT') ? 'PENDING' : 'DRAFT';
$maker_user_id = currentUserId();
$parent_id = ($parent_gl_id === '') ? null : (int)$parent_gl_id;

$mysqli->begin_transaction();

try {
  // check exists
  $stmt = $mysqli->prepare("SELECT gl_id, record_status FROM tbl_admin_gl_account WHERE gl_code = ? LIMIT 1");
  $stmt->bind_param("s", $gl_code);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($row = $res->fetch_assoc()) {
    $gl_id = (int)$row['gl_id'];
    $existing_status = $row['record_status'];

    // block if already pending/approved
    if (in_array($existing_status, ['PENDING','APPROVED'], true)) {
      $mysqli->rollback();
      echo alertHtml('danger', "Cannot edit GL <b>{$gl_code}</b>. Current status is <b>{$existing_status}</b>.");
      exit;
    }

    // update
    $stmt2 = $mysqli->prepare("UPDATE tbl_admin_gl_account
      SET gl_name = ?, parent_gl_id = ?, record_status = ?,
          maker_user_id = ?, maker_at = NOW(), maker_note = ?,
          checker_user_id = NULL, checker_at = NULL, checker_note = NULL
      WHERE gl_id = ?");
    $stmt2->bind_param("sisisi", $gl_name, $parent_id, $record_status, $maker_user_id, $maker_note, $gl_id);
    $stmt2->execute();

    $mysqli->commit();
    echo alertHtml('success', "GL <b>{$gl_code}</b> updated. Status: <b>{$record_status}</b>.");
    exit;
  } else {
    // insert
    $stmt3 = $mysqli->prepare("INSERT INTO tbl_admin_gl_account
      (gl_code, gl_name, parent_gl_id, record_status, maker_user_id, maker_at, maker_note)
      VALUES (?,?,?,?,NOW(),?)");
    // fix bind: maker_at is NOW() so parameters should match
    // We'll rewrite correctly:
    $stmt3 = $mysqli->prepare("INSERT INTO tbl_admin_gl_account
      (gl_code, gl_name, parent_gl_id, record_status, maker_user_id, maker_at, maker_note)
      VALUES (?,?,?,?,?,NOW(),?)");
    $stmt3->bind_param("sss sis", $gl_code, $gl_name, $parent_gl_id, $record_status, $maker_user_id, $maker_note);
  }
} catch (Throwable $e) {
  $mysqli->rollback();
  echo alertHtml('danger', 'Save failed: ' . htmlspecialchars($e->getMessage()));
  exit;
}
