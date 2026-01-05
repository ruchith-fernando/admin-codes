<?php
// fetch-profile-items.php
include 'connections/connection.php';

$profile_key = $_GET['profile_key'] ?? '';
$out = [];

if ($profile_key) {
  // check wildcard
  $has_star = false;
  $starChk = mysqli_query($conn, "SELECT 1 FROM tbl_admin_access_profile_items WHERE profile_key='$profile_key' AND menu_key='*' LIMIT 1");
  if ($starChk && mysqli_num_rows($starChk) > 0) $has_star = true;

  if ($has_star) {
    $q = mysqli_query($conn, "SELECT menu_key FROM tbl_admin_menu_keys ORDER BY menu_key");
    while($r = mysqli_fetch_assoc($q)) $out[] = $r['menu_key'];
  } else {
    $q = mysqli_query($conn, "SELECT menu_key FROM tbl_admin_access_profile_items WHERE profile_key='$profile_key' ORDER BY menu_key");
    while($r = mysqli_fetch_assoc($q)) $out[] = $r['menu_key'];
  }
}
echo json_encode($out);
