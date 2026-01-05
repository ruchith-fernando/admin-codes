<?php
// error-log-mark-done.php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_POST['id'] ?? 0);
$file = trim($_POST['file'] ?? '');
$line = (int)($_POST['line'] ?? 0);
$message = trim($_POST['message'] ?? '');
$user = mysqli_real_escape_string($conn, $_SESSION['admin_name'] ?? 'System');

if (!$id || !$file || !$message) {
  echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
          Invalid parameters.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
  exit;
}

$file_esc = mysqli_real_escape_string($conn, $file);
$message_esc = mysqli_real_escape_string($conn, $message);

$sql = "
  UPDATE tbl_admin_errors
  SET is_resolved = 1,
      resolved_at = NOW(),
      resolved_by = '$user'
  WHERE file = '$file_esc'
    AND line = $line
    AND error_message = '$message_esc'
";

if (mysqli_query($conn, $sql)) {
  echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
          ✅ Error marked as resolved successfully.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
} else {
  echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
          ❌ Database error: ' . htmlspecialchars(mysqli_error($conn)) . '
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
}
?>
