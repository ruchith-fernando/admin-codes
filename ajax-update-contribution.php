<?php
include 'connections/connection.php';
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid input'];

if (isset($_POST['hris_no'], $_POST['mobile_no'], $_POST['contribution_amount'], $_POST['effective_from'])) {
    $hris_no = $_POST['hris_no'];
    $mobile_no = $_POST['mobile_no'];
    $new_amount = $_POST['contribution_amount'];
    $effective_from = $_POST['effective_from'];

    if (!empty($hris_no) && !empty($mobile_no) && is_numeric($new_amount) && !empty($effective_from)) {
        $sql = "INSERT INTO tbl_admin_hris_contributions (hris_no, mobile_no, contribution_amount, effective_from)
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssds", $hris_no, $mobile_no, $new_amount, $effective_from);

        if ($stmt->execute()) {
            $response = [
                'status' => 'success',
                'message' => "Contribution updated successfully for mobile number: <strong>$mobile_no</strong>"
            ];
        } else {
            $response['message'] = 'Database error: ' . $conn->error;
        }
    } else {
        $response['message'] = 'Please fill in all fields correctly.';
    }
}

echo json_encode($response);
