<?php
// fetch-profiles.php
include 'connections/connection.php';
$rows = [];
$res = mysqli_query($conn, "SELECT profile_key, profile_label FROM tbl_admin_access_profiles WHERE is_active='yes' ORDER BY profile_label");
while($r = mysqli_fetch_assoc($res)) $rows[] = $r;
echo json_encode($rows);
