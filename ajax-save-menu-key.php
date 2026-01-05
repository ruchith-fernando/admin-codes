<?php
// ajax-save-menu-key.php
include 'connections/connection.php';

$menu_key = trim($_POST['menu_key']);
$menu_label = trim($_POST['menu_label']);
$menu_group = trim($_POST['menu_group']);
$menu_color = isset($_POST['menu_color']) ? trim($_POST['menu_color']) : null;

if ($menu_key && $menu_label && $menu_group) {
    $check = mysqli_query($conn, "SELECT id FROM tbl_admin_menu_keys WHERE menu_key='$menu_key'");
    if (mysqli_num_rows($check) > 0) {
        echo '<div class="alert alert-danger">Menu Key already exists.</div>';
    } else {
        $insert = mysqli_query($conn, "
          INSERT INTO tbl_admin_menu_keys(menu_key, menu_label, menu_group, color)
          VALUES('$menu_key', '$menu_label', '$menu_group', " . 
          ($menu_color ? "'$menu_color'" : "NULL") . "
        )");
        echo $insert 
          ? '<div class="alert alert-success">Menu Key added successfully.</div>'
          : '<div class="alert alert-danger">Error saving Menu Key.</div>';
    }
}

?>
