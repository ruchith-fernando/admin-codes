<?php
include 'connections/connection.php';

$hris = $_POST['original_hris'];
$name = $_POST['name'];
$password = $_POST['password'] ?? '';
$user_level = implode(',', $_POST['user_level']);
$category = $_POST['category_select'];

if ($hris && $name && $user_level && $category) {
    $update_query = "UPDATE tbl_admin_users SET 
                        name='$name',
                        category='$category',
                        user_level='$user_level'";

    if (!empty($password)) {
        $update_query .= ", password='$password'";
    }

    $update_query .= " WHERE hris='$hris'";

    if ($conn->query($update_query)) {
        echo "✅ User updated successfully.";
    } else {
        echo "❌ Update failed. Error: " . $conn->error;
    }
} else {
    echo "❌ Missing required fields.";
}
