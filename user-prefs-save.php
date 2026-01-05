<?php
// user-prefs-save.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
header('Content-Type: application/json');

require_once 'connections/connection.php';

/* fallback alias if $con is set instead of $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (isset($con) && $con instanceof mysqli) { $conn = $con; }
}

$hris_id = $_SESSION['hris'] ?? '';
if ($hris_id === '') {
  echo json_encode(['ok'=>false, 'msg'=>'No HRIS']);
  exit;
}

// Expect raw POST: pinned_json, recents_json, last_page
$pinned_json  = $_POST['pinned_json']  ?? '[]';
$recents_json = $_POST['recents_json'] ?? '[]';
$last_page    = $_POST['last_page']    ?? '';

// Escape inputs
$hris_esc    = mysqli_real_escape_string($conn, $hris_id);
$pinned_esc  = mysqli_real_escape_string($conn, $pinned_json);
$recents_esc = mysqli_real_escape_string($conn, $recents_json);
$last_esc    = mysqli_real_escape_string($conn, $last_page);

// Upsert into prefs table
$sql = "
  INSERT INTO tbl_admin_user_prefs (hris_id, pinned_json, recents_json, last_page)
  VALUES ('$hris_esc', '$pinned_esc', '$recents_esc', '$last_esc')
  ON DUPLICATE KEY UPDATE
    pinned_json=VALUES(pinned_json),
    recents_json=VALUES(recents_json),
    last_page=VALUES(last_page)
";

$ok = mysqli_query($conn, $sql);

if ($ok) {
    echo json_encode(['ok'=>true]);
} else {
    echo json_encode(['ok'=>false, 'msg'=>mysqli_error($conn)]);
}
