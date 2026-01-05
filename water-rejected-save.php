<?php
// water-rejected-save.php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$current_hris = $_SESSION['hris'] ?? '';

$id = $_POST['id'] ?? '';
if (!$id) {
  echo json_encode(['status'=>'error','message'=>'Invalid request.']);
  exit;
}

// Instead of actual delete, mark as deleted
$stmt = $conn->prepare("
  UPDATE tbl_admin_actual_water
  SET approval_status='deleted', rejected_at=NOW()
  WHERE id=? AND entered_hris=?
");
$stmt->bind_param('is', $id, $current_hris);

if ($stmt->execute() && $stmt->affected_rows > 0) {
  echo json_encode(['status'=>'success','message'=>'🗑 Record marked as deleted successfully.']);
} else {
  echo json_encode(['status'=>'error','message'=>'Unable to delete record or unauthorized access.']);
}
