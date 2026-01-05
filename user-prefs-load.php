<?php
// user-prefs-load.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once 'connections/connection.php';

$hris_id = $_SESSION['hris'] ?? '';
$out = ['pinned'=>[], 'recents'=>[], 'last'=>''];

if ($hris_id === '') {
  echo json_encode($out);
  exit;
}

$hris_esc = mysqli_real_escape_string($conn, $hris_id);
$sql = "SELECT pinned_json, recents_json, last_page FROM tbl_admin_user_prefs WHERE hris_id='$hris_esc' LIMIT 1";
if ($res = mysqli_query($conn, $sql)) {
  if (mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $out['pinned']  = $row['pinned_json']  ? (json_decode($row['pinned_json'], true) ?: []) : [];
    $out['recents'] = $row['recents_json'] ? (json_decode($row['recents_json'], true) ?: []) : [];
    $out['last']    = $row['last_page'] ?? '';
  }
}

echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
