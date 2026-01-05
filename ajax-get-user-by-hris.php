<!-- ajax-get-user-by-hris.php -->
<?php
include 'connections/connection.php';

$hris = $_POST['hris'] ?? '';
$response = ['status' => 'error'];

if ($hris) {
    $query = $conn->query("SELECT * FROM tbl_admin_users WHERE hris = '$hris'");
    if ($query && $row = $query->fetch_assoc()) {
        $user_levels = explode(',', $row['user_level']); // comma-separated
        $response = [
            'status' => 'success',
            'hris' => $row['hris'],
            'name' => $row['name'],
            'designation' => $row['designation'],
            'title' => $row['title'],
            'company_hierarchy' => $row['company_hierarchy'],
            'location' => $row['location'],
            'category' => $row['category'],
            'branch_code' => $row['branch_code'],
            'category_select' => $row['category'],
            'user_levels' => $user_levels
        ];
    }
}

echo json_encode($response);
