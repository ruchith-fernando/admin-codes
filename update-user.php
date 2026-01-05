<?php
include("connections/connection.php");

$id = $_POST['id'];
$company_hierarchy = mysqli_real_escape_string($conn, $_POST['company_hierarchy']);
$designation = mysqli_real_escape_string($conn, $_POST['designation']);
$category = mysqli_real_escape_string($conn, $_POST['category']);
$branch_code = mysqli_real_escape_string($conn, $_POST['branch_code']);

// Sanitize and convert user_level array to comma-separated string
$user_level_array = $_POST['user_level'] ?? [];
$user_level = implode(',', array_map('mysqli_real_escape_string', array_fill(0, count($user_level_array), $conn), $user_level_array));

// Fetch branch name from branch_code
$branch_name = '';
$res = mysqli_query($conn, "SELECT branch_name FROM tbl_admin_branch_information WHERE branch_id = '$branch_code' LIMIT 1");
if ($row = mysqli_fetch_assoc($res)) {
  $branch_name = mysqli_real_escape_string($conn, $row['branch_name']);
}

// Update query
$sql = "UPDATE tbl_admin_users SET 
          company_hierarchy = '$company_hierarchy',
          designation = '$designation',
          category = '$category',
          user_level = '$user_level',
          branch_code = '$branch_code',
          branch_name = '$branch_name'
        WHERE id = '$id'";

// Execute query
if (mysqli_query($conn, $sql)) {
  echo "User updated successfully.";
} else {
  echo "Failed to update: " . mysqli_error($conn);
}
?>
