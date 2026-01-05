<?php
// save-user-access.php  (REPLACE with this)
include 'connections/connection.php';

$hris_id     = $_POST['hris_id'] ?? '';
$menu_keys   = $_POST['menu_keys'] ?? [];       // overrides (checkboxes)
$profile_key = $_POST['profile_key'] ?? null;   // selected profile

if (!$hris_id) {
  echo '<div class="alert alert-danger">Missing HRIS ID.</div>';
  exit;
}

// Save profile on user
$profile_sql = is_null($profile_key) || $profile_key === ''
  ? "UPDATE tbl_admin_users SET access_profile_key=NULL WHERE hris='$hris_id'"
  : "UPDATE tbl_admin_users SET access_profile_key='".mysqli_real_escape_string($conn,$profile_key)."' WHERE hris='$hris_id'";
mysqli_query($conn, $profile_sql);

// Save overrides (clear + insert)
mysqli_query($conn, "DELETE FROM tbl_admin_user_page_access WHERE hris_id='$hris_id'");

foreach ($menu_keys as $key){
  $k = mysqli_real_escape_string($conn,$key);
  mysqli_query($conn, "INSERT INTO tbl_admin_user_page_access(hris_id, menu_key, is_allowed) VALUES('$hris_id', '$k', 'yes')");
}

echo '<div class="alert alert-success">Access updated successfully for HRIS: '.htmlspecialchars($hris_id).'</div>';
