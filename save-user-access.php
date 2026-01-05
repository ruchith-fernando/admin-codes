<?php
include 'connections/connection.php';
$hris_id = $_POST['hris_id'];
$menu_keys = $_POST['menu_keys'] ?? [];

// Delete old access entries
mysqli_query($conn, "DELETE FROM tbl_admin_user_page_access WHERE hris_id='$hris_id'");

// Insert selected access
foreach($menu_keys as $key){
    mysqli_query($conn, "INSERT INTO tbl_admin_user_page_access(hris_id, menu_key, is_allowed) VALUES('$hris_id', '$key', 'yes')");
}

echo '<div class="alert alert-success">Access updated successfully for HRIS: '.$hris_id.'</div>';
?>
