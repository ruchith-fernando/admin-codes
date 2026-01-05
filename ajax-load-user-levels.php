<!-- ajax-load-user-levels.php -->
<?php
include 'connections/connection.php';
$q = $conn->query("SELECT level_key, level_label FROM tbl_admin_user_levels ORDER BY level_label ASC");
$levels = [];
while ($row = $q->fetch_assoc()) {
    $levels[] = $row;
}
echo json_encode($levels);
