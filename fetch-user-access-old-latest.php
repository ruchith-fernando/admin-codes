<?php
// fetch-user-access.php  (REPLACE with this)
include 'connections/connection.php';

$hris_id = $_GET['hris_id'] ?? '';

$resp = [
  'profile_key'    => null,
  'override_keys'  => [],
  'profile_keys'   => [],
  'effective_keys' => []
];

if ($hris_id) {
  // user’s stored profile
  $u = mysqli_query($conn, "SELECT access_profile_key FROM tbl_admin_users WHERE hris='$hris_id' LIMIT 1");
  if ($u && $row = mysqli_fetch_assoc($u)) {
    $resp['profile_key'] = $row['access_profile_key'];
  }

  // profile keys
  if ($resp['profile_key']) {
    $pk = mysqli_real_escape_string($conn, $resp['profile_key']);

    // wildcard?
    $has_star = false;
    $starChk = mysqli_query($conn, "SELECT 1 FROM tbl_admin_access_profile_items WHERE profile_key='$pk' AND menu_key='*' LIMIT 1");
    if ($starChk && mysqli_num_rows($starChk) > 0) $has_star = true;

    if ($has_star) {
      $q = mysqli_query($conn, "SELECT menu_key FROM tbl_admin_menu_keys ORDER BY menu_key");
      while($r = mysqli_fetch_assoc($q)) $resp['profile_keys'][] = $r['menu_key'];
    } else {
      $q = mysqli_query($conn, "SELECT menu_key FROM tbl_admin_access_profile_items WHERE profile_key='$pk'");
      while($r = mysqli_fetch_assoc($q)) $resp['profile_keys'][] = $r['menu_key'];
    }
  }

  // per-user overrides (your existing table)
  $q2 = mysqli_query($conn, "SELECT menu_key FROM tbl_admin_user_page_access WHERE hris_id='$hris_id' AND is_allowed='yes'");
  while($r2 = mysqli_fetch_assoc($q2)) $resp['override_keys'][] = $r2['menu_key'];

  // effective = union
  $resp['effective_keys'] = array_values(array_unique(array_merge($resp['profile_keys'], $resp['override_keys'])));
}

echo json_encode($resp);
