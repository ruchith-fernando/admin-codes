<?php
include 'connections/connection.php';
$hris_id = $_GET['hris_id'] ?? '';
$access = [];

if($hris_id){
    $res = mysqli_query($conn, "SELECT menu_key FROM tbl_admin_user_page_access WHERE hris_id='$hris_id' AND is_allowed='yes'");
    while($row = mysqli_fetch_assoc($res)){
        $access[] = $row['menu_key'];
    }
}
echo json_encode($access);
?>
