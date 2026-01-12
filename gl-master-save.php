<?php
// gl-master-save.php
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

$gl_code = strtoupper(trim($_POST['gl_code'] ?? ''));
$gl_name = trim($_POST['gl_name'] ?? '');
$gl_note = trim($_POST['gl_note'] ?? '');
$uid = currentUserId();

if ($gl_code === '' || $gl_name === '') {
  echo alertHtml('danger','GL Code and GL Name are required.');
  exit;
}

$mysqli->begin_transaction();
try {
  // duplicate code
  $st1 = $mysqli->prepare("SELECT gl_id FROM tbl_admin_gl_account WHERE gl_code=? LIMIT 1");
  $st1->bind_param("s", $gl_code);
  $st1->execute();
  if ($st1->get_result()->fetch_assoc()) {
    $mysqli->rollback();
    echo alertHtml('danger', "GL Code <b>{$gl_code}</b> already exists.");
    exit;
  }

  // duplicate name (because table has uk_gl_name)
  $st2 = $mysqli->prepare("SELECT gl_id, gl_code FROM tbl_admin_gl_account WHERE gl_name=? LIMIT 1");
  $st2->bind_param("s", $gl_name);
  $st2->execute();
  if ($r = $st2->get_result()->fetch_assoc()) {
    $mysqli->rollback();
    echo alertHtml('danger', "GL Name already exists (Code: <b>".htmlspecialchars($r['gl_code'])."</b>).");
    exit;
  }

  $ins = $mysqli->prepare("INSERT INTO tbl_admin_gl_account
    (gl_code, gl_name, gl_note, created_user_id, created_at)
    VALUES (?,?,?,?,NOW())");
  $ins->bind_param("sssi", $gl_code, $gl_name, $gl_note, $uid);
  $ins->execute();

  $mysqli->commit();
  echo alertHtml('success', "GL <b>{$gl_code}</b> saved successfully.");
  exit;

} catch (Throwable $e) {
  $mysqli->rollback();
  echo alertHtml('danger','Save failed: '.htmlspecialchars($e->getMessage()));
  exit;
}
