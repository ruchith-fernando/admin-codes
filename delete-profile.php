<?php
include 'connections/connection.php';
$profile_key = $_POST['profile_key'] ?? '';
if(!$profile_key){ echo '<div class="alert alert-warning">No profile key provided.</div>'; exit; }
$pk = mysqli_real_escape_string($conn, $profile_key);
mysqli_query($conn, "DELETE FROM tbl_admin_access_profiles WHERE profile_key='$pk'"); // cascades items
echo '<div class="alert alert-success">Profile deleted.</div>';
