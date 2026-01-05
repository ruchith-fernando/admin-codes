<?php
// ajax-get-branch.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

$id = intval($_POST['id'] ?? 0);

$q = mysqli_query($conn, "SELECT * FROM tbl_admin_branch_water WHERE id=$id LIMIT 1");
$row = mysqli_fetch_assoc($q);

echo json_encode($row);
?>
