<?php
// requisition-file.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid <= 0) { http_response_code(401); exit('Session expired.'); }

function deny($code=403, $msg='Forbidden'){
  http_response_code($code);
  exit($msg);
}

function hasAccess(mysqli $conn, int $reqId, int $uid): bool {
  // requester OR any approver in steps can view documents
  $ok = false;

  // requester
  if ($st = $conn->prepare("SELECT req_id FROM tbl_admin_requisitions WHERE req_id=? AND requester_user_id=? LIMIT 1")) {
    $st->bind_param("ii", $reqId, $uid);
    $st->execute();
    $rs = $st->get_result();
    $ok = ($rs && $rs->num_rows > 0);
    $st->close();
  }
  if ($ok) return true;

  // any approver
  if ($st = $conn->prepare("SELECT req_id FROM tbl_admin_requisition_approval_steps WHERE req_id=? AND approver_user_id=? LIMIT 1")) {
    $st->bind_param("ii", $reqId, $uid);
    $st->execute();
    $rs = $st->get_result();
    $ok = ($rs && $rs->num_rows > 0);
    $st->close();
  }

  return $ok;
}

// Inputs
$att_id = (int)($_GET['att_id'] ?? 0);
$req_id = (int)($_GET['req_id'] ?? 0);
$file   = (string)($_GET['file'] ?? '');

$relPath = '';
$realReqId = 0;

// Case 1: attachment table id
if ($att_id > 0) {

  // Ensure attachments table exists
  $hasTbl = false;
  if ($st = $conn->prepare("SHOW TABLES LIKE 'tbl_admin_attachments'")) {
    $st->execute();
    $r = $st->get_result();
    $hasTbl = ($r && $r->num_rows > 0);
    $st->close();
  }
  if (!$hasTbl) deny(404, 'Attachments table not found.');

  if ($st = $conn->prepare("SELECT entity_id, file_path, file_name FROM tbl_admin_attachments WHERE id=? AND entity_type='REQ' LIMIT 1")) {
    $st->bind_param("i", $att_id);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs->fetch_assoc();
    $st->close();
    if (!$row) deny(404, 'File not found.');

    $realReqId = (int)$row['entity_id'];
    $relPath = (string)$row['file_path'];
  } else {
    deny(500,'DB error.');
  }

} else {
  // Case 2: directory fallback
  if ($req_id <= 0) deny(400,'Invalid requisition.');
  if ($file === '') deny(400,'Invalid file.');

  // filename only — no slashes, no traversal
  if (strpos($file, '..') !== false || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
    deny(400,'Invalid file name.');
  }

  $realReqId = $req_id;
  $relPath = 'uploads/requisitions/' . $realReqId . '/' . $file;
}

// Permission
if ($realReqId <= 0) deny(400,'Invalid requisition.');
if (!hasAccess($conn, $realReqId, $uid)) deny(403,'Forbidden');

// Resolve file
$abs = __DIR__ . '/' . ltrim($relPath, '/');
$absReal = realpath($abs);
$base = realpath(__DIR__ . '/uploads/requisitions/' . $realReqId);

if (!$absReal || !file_exists($absReal)) deny(404, 'File not found.');

// Must stay inside requisitions folder for this req_id
if (!$base || strpos($absReal, $base) !== 0) deny(403, 'Forbidden');

// Content type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($absReal) ?: 'application/octet-stream';

header('Content-Type: '.$mime);
header('Content-Length: '.filesize($absReal));
// Inline so PDFs/images show in iframe/img
header('Content-Disposition: inline; filename="'.basename($absReal).'"');
header('X-Content-Type-Options: nosniff');

readfile($absReal);
exit;
