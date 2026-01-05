<?php
require_once 'connections/connection.php';

$q = mysqli_query($conn, "
SELECT DISTINCT vendor_name 
FROM tbl_admin_branch_water 
WHERE vendor_name IS NOT NULL AND vendor_name <> ''
ORDER BY vendor_name ASC
");

$list = [];
while($r = mysqli_fetch_assoc($q)){
    $list[] = $r['vendor_name'];
}

echo json_encode($list);
?>
