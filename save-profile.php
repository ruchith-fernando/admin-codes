<?php
include 'connections/connection.php';

$profile_key   = $_POST['profile_key']   ?? '';
$profile_label = $_POST['profile_label'] ?? '';
$wildcard_all  = $_POST['wildcard_all']  ?? 'no';
$menu_keys     = $_POST['menu_keys']     ?? [];

if(!$profile_key || !$profile_label){
  echo '<div class="alert alert-danger">Profile Key and Label are required.</div>';
  exit;
}

// Upsert profile
$pk = mysqli_real_escape_string($conn, $profile_key);
$pl = mysqli_real_escape_string($conn, $profile_label);
mysqli_query($conn, "INSERT INTO tbl_admin_access_profiles(profile_key, profile_label, is_active)
                     VALUES('$pk','$pl','yes')
                     ON DUPLICATE KEY UPDATE profile_label=VALUES(profile_label), is_active='yes'");

// Replace items
mysqli_query($conn, "DELETE FROM tbl_admin_access_profile_items WHERE profile_key='$pk'");

if ($wildcard_all === 'yes') {
  // store wildcard as single '*'
  mysqli_query($conn, "INSERT INTO tbl_admin_access_profile_items(profile_key, menu_key) VALUES('$pk','*')");
} else {
  foreach($menu_keys as $key){
    $k = mysqli_real_escape_string($conn, $key);
    mysqli_query($conn, "INSERT INTO tbl_admin_access_profile_items(profile_key, menu_key) VALUES('$pk','$k')");
  }
}

echo '<div class="alert alert-success">Profile saved successfully.</div>';
